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

   
   
    /**
     * Checks the local sync state for a given node.
     *
     * @param \Drupal\node\NodeInterface $node
     *   The node to check.
     *
     * @return int|null
     *   The timestamp of the last sync, or NULL if not synced.
     */
    public function getLocalSyncStatus(NodeInterface $node): ?int {
        return $this->state->get('pds_sync.sync.' . $node->uuid());
    }

    /**
     * Marks a node as synced locally.
     *
     * @param \Drupal\node\NodeInterface $node
     *   The node to mark as synced.
     */
    public function setLocalSyncStatus(NodeInterface $node): void {
        $this->state->set('pds_sync.sync.' . $node->uuid(), $this->time->getRequestTime());
    }

    /**
     * The "Rolling Window" Pruning:
     * Keeps the PDS clean by ensuring only the latest X records exist.
     *
     * @param int $keep
     *   The number of records to keep.
     *
     * @return int
     *   The number of records deleted.
     */
    public function prunePdsFeed(int $keep = 15): int {
        $all_pds_rides = $this->pdsRepository->getRides();
        if (count($all_pds_rides) <= $keep) {
            return 0;
        }

        // Sort PDS records by date descending
        usort($all_pds_rides, fn($a, $b) => strcmp($b['date'], $a['date']));

        $to_delete = array_slice($all_pds_rides, $keep);
        $count = 0;
        foreach ($to_delete as $ride) {
            if ($this->pdsRepository->deleteRide($ride['rkey'])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Gets the reconciled status of a node.
     *
     * @param \Drupal\node\NodeInterface $node
     *   The node to check.
     * @param array $pds_rides
     *   An array of PDS rides.
     *
     * @return array
     *   An array containing the label, class, and sync/delete permissions.
     */
    public function getReconciledStatus(NodeInterface $node, array $pds_rides): array {
        $uuid = $node->uuid();
        $is_on_pds = false;

        // Search the PDS results for this UUID
        foreach ($pds_rides as $pds_ride) {
            if ($pds_ride['rkey'] === $uuid) {
                $is_on_pds = true;
                break;
            }
        }

        $local_sync = $this->getLocalSyncStatus($node);

        if ($is_on_pds) {
            return [
                'label' => 'Synced',
                'class' => 'status-synced',
                'can_sync' => FALSE,
                'can_delete' => TRUE,
            ];
        }

        if ($local_sync) {
            return [
                'label' => 'Archived',
                'class' => 'status-archived',
                'can_sync' => TRUE,
                'can_delete' => FALSE,
            ];
        }

        return [
            'label' => 'Untracked',
            'class' => 'status-untracked',
            'can_sync' => TRUE,
            'can_delete' => FALSE,
        ];
    }
}

