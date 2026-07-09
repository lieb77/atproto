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
use Drupal\atproto_client\AtprotoClientService;

/**
 * Manages the custom Bike Ride lexicon on the PDS.
 */
class AtprotoPaullieberman {
    use AtprotoLoggerTrait;
    
    protected $lexicon = 'net.paullieberman.bike.ride';

    public function __construct(
        protected AtprotoClientService $atprotoClient,
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
    
        $rkey 	  =  $his->generateTid();
        $bid 	  = $node->field_bike->target_id;
        $bikeName = $bid ? Node::load($bid)->getTitle() : 'Unknown Bike';

        $rideDateRaw = $node->get('field_ridedate')->value;
        $isoDate     = $rideDateRaw ? $rideDateRaw . 'T12:00:00Z' : date('c', $node->getCreatedTime());

        $record = [
            '$type' 	=> $this->lexicon,
            'createdAt' => $isoDate,
            'route' 	=> $node->getTitle(),
            'miles' 	=> (int) $node->get('field_miles')->value,
            'date' 		=> $rideDateRaw,
            'bike' 		=> $bikeName,
            'url' 		=> $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
            'body' 		=> MailFormatHelper::htmlToText($node->body->value),
        ];

        $this->logger()->notice("In PostRide, about to call putRecord");
        return $this->atprotoClient->putRecord( [            
			'repo' 		 => $this->atprotoClient->getDid(),
			'collection' => $this->lexicon,
			'rkey' 		 => $rkey,
			'record' 	 => $record,
        ]);
    }


    /**
     * Deletes a ride from the PDS.
     */
    public function deleteRide(NodeInterface $node): bool {
        $rkey  =  $his->generateTid();
    	
        try {
            $this->atprotoClient->deleteRecord( 
            	[
                    'repo' 		 =>  $this->atprotoClient->getDid(),
                    'collection' => $this->lexicon,
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

    /**
         * Generate the TID for the Rkey
         */
    private function generateTid(?int $customMicroTime = null, int $customClockId = null): string {
    // 1. Determine microtime
    $microTime = $customMicroTime ?? (int)(microtime(true) * 1000000);

    // 2. Generate or use provided random clock ID (10 bits = 0 to 1023)
    $clockId = $customClockId ?? mt_rand(0, 1023);

    // 3. Pack bits (Top bit 0 + 53 bits time + 10 bits clockId)
    // 64-bit PHP is required for bitwise shift safety
    $packed = ($microTime << 10) | ($clockId & 0x3FF);

    // 4. Base32 alphabet for ATProtocol: 2-7, a-z (excluding 0, 1, 8, 9 to prevent confusion)
    $alphabet = '234567abcdefghijklmnopqrstuvwxyz';
    $base32 = '';

    // 5. Convert to Base32
    $temp = $packed;
    while ($temp > 0) {
        $base32 = $alphabet[$temp & 0x1F] . $base32;
        $temp >>= 5;
    }

    // 6. Pad to exactly 13 characters
    return str_pad($base32, 13, '2', STR_PAD_LEFT);
}
 

// end-of-class
}
