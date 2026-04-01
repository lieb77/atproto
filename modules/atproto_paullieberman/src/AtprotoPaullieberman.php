<?php

declare(strict_types=1);

namespace Drupal\atproto_paullieberman;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\State\StateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\atproto\AtprotoLoggerTrait;
use Drupal\atproto_client\Client\AtprotoClient;

/**
 * Manages the custom Bike Ride lexicon on the PDS.
 */
class AtprotoPaullieberman {
    use AtprotoLoggerTrait;
    
    protected $lexicon = 'net.paullieberman.bike.ride';

    public function __construct(
        protected AtprotoClient $atprotoClient,
        protected StateInterface $state,
        protected EntityTypeManagerInterface $entityTypeManager,
        protected LoggerChannelFactoryInterface $loggerFactory,
        protected TimeInterface $time,
    ) {
    	$this->setLoggerFactory($loggerFactory);
    }


    /**
     * Post a ride node to the custom PDS collection.
     *
     * lexicon: net.paullieberman.bike.ride
     */
    public function PostRide(NodeInterface $node): mixed {
    
        $rkey 	  = $node->uuid();
        $bid 	  = $node->field_bike->target_id;
        $bikeName = $bid ? Node::load($bid)->getTitle() : 'Unknown Bike';

        $rideDateRaw = $node->get('field_ridedate')->value;
        $isoDate     = $rideDateRaw ? $rideDateRaw . 'T12:00:00Z' : date('c', $node->getCreatedTime());

        $record = [
            '$type' 	=> $lexicon,
            'createdAt' => $isoDate,
            'route' 	=> $node->getTitle(),
            'miles' 	=> (int) $node->get('field_miles')->value,
            'date' 		=> $rideDateRaw,
            'bike' 		=> $bikeName,
            'url' 		=> $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
            'body' 		=> MailFormatHelper::htmlToText($node->body->value),
        ];

        return $this->atprotoClient->putRecord( [            
			'repo' 		 =>  $this->$atprotoClient->getDid(),
			'collection' => $lexicon,
			'rkey' 		 => $rkey,
			'record' 	 => $record,
        ]);
    }


    /**
     * Deletes a ride from the PDS.
     */
    public function deleteRide(NodeInterface $node): bool {
    	$rkey = $node->uuid();
    	
        try {
            $this->atprotoClient->deleteRecord( 
            	[
                    'repo' 		 =>  $this->$atprotoClient->getDid(),
                    'collection' => $lexicon,
                    'rkey' 		 => $rkey,
                ],
            );
            return TRUE;
        }
        catch (\Exception $e) {
            $this->logger()->error('Failed to delete ride @rkey: @message', ['@rkey' => $rkey, '@message' => $e->getMessage()]);
            return FALSE;
        }
    }

 

// end-of-class
}
