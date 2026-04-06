<?php
declare(strict_types=1);

namespace Drupal\atproto_dashboard;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\atproto\AtprotoLoggerTrait;
use Drupal\atproto_client\AtprotoClientService;

/**
 * Orchestrates the synchronization between Drupal Nodes and the PDS.
 */
class AtprotoDashboard {

    use AtprotoLoggerTrait;

    protected $did;
    
    public function __construct(
        protected AtprotoClientService $atprotoClient,
        protected LoggerChannelFactoryInterface $loggerFactory
    ) {
    	$this->setLoggerFactory($loggerFactory);
    	$this->did = $atprotoClient->getDid();
    }

	/**
	 * List ride records
	 *
	 */
	public function listRideRecords() {
      	$records = [];
        $cursor = NULL;

		do {
			$query = [
				'repo' 		 => $this->did, 
				'collection' => 'net.paullieberman.bike.ride',
				'limit' 	 => 100
			];
			if ($cursor) {
				$query['cursor'] = $cursor;
			}
			try {
				$response = $this->atprotoClient->listRecords($query);
			}
			catch (\Throwable $e) {
				$this->logger()->error("Call to list records got error: @err",["@err" => $e->getMessage()]);
				return NULL;
			}
			if (FALSE === $response){
				$this->logger()->error("List records returned FALSE");
				return [];
			}

			$records  = array_merge($records, $response->records);
			$cursor   = $response->cursor ?? NULL;
		} while ($cursor);
		
		$rides = array_map(function ($record) {
        	$record_array = (array) $record->value;
            $record_array['rkey'] = basename($record->uri);
            return $record_array;
        }, $records);

        usort($rides, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $rides;

   }
   
   
   /**
	 * List doc records
	 *
	 */
	public function listDocRecords() {
      	$records = [];
        $cursor = NULL;

		do {
			$query = [
				'repo' 		 => $this->did, 
				'collection' => 'site.standard.document', 
				'limit' 	 => 100
			];
			if ($cursor) {
					$query['cursor'] = $cursor;
			}

			$response = $this->atprotoClient->listRecords($query);
			$records  = array_merge($records, $response->records);
			$cursor   = $response->cursor ?? NULL;
		} while ($cursor);
		
		$docs = array_map(function ($record) {
        	$record_array = (array) $record->value;
            $record_array['rkey'] = basename($record->uri);
            return $record_array;
        }, $records);

        usort($docs, fn($a, $b) => strcmp($b['publishedAt'], $a['publishedAt']));
        return $docs;

   }
   
   /**
	 * List Post records
	 *
	 */
	public function listPostRecords() {
      	$records = [];
        $cursor = NULL;

		do {
			$query = [
				'repo' 		 => $this->did, 
				'collection' => 'app.bsky.feed.post', 
				'limit' 	 => 100
			];
			if ($cursor) {
					$query['cursor'] = $cursor;
			}

			$response = $this->atprotoClient->listRecords($query);
			$records  = array_merge($records, $response->records);
			$cursor   = $response->cursor ?? NULL;
		} while ($cursor);
		
		$posts = array_map(function ($record) {
        	$record_array = (array) $record->value;
            $record_array['rkey'] = basename($record->uri);
            return $record_array;
        }, $records);

        usort($posts, fn($a, $b) => strcmp($b['createdAt'], $a['createdAt']));
        return $posts;

   }
   
	/**
	 * Get a single record
	 *
	 */   
	public function getRecord(string $type, string $rkey ){
		switch ($type){
			case 'ride':
				$collection = 'net.paullieberman.bike.ride';
				break;
			case 'post':
				$collection = 'app.bsky.feed.post';
				break;
			case 'doc':
				$collection = 'site.standard.document';
				break;
			default:
				$collection = 'app.bsky.feed.post';
		}
		
		$query = [
			'repo' 		 => $this->did, 
			'collection' => $collection,
   			'rkey'  	 => $rkey,
   		];
   		
   		$record = $this->atprotoClient->getRecord($query);
   		return $record;
   } 
   
    /**
     * Deletes a ride from the PDS.
     */
    public function deleteRide(string $rkey): bool {
        try {
            $this->atprotoClient->deleteRecord( 
            	[
                    'repo' 		 =>  $this->did,
                    'collection' => 'net.paullieberman.bike.ride',
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

   
   
    
//end-of-class
}