<?php

declare(strict_types=1);

namespace Drupal\atproto_paullieberman

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\State\StateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\atproto_client\Client\AtprotoClient;

/**
 * Manages the custom Bike Ride lexicon on the PDS.
 */
class AtprotoPaullieberman {

    protected $did;

    public function __construct(
        protected AtprotoClient $atprotoClient,
        protected StateInterface $state,
        protected EntityTypeManagerInterface $entityTypeManager,
        protected LoggerChannelInterface $logger,
        protected TimeInterface $time,
    ) {
        $this->did = $atprotoClient->getDid();
    }

    /**
     * Syncs a ride node to the custom PDS collection.
     */
    public function syncRide(NodeInterface $node): mixed {
    
        $rkey = $node->uuid();
        $bid = $node->field_bike->target_id;
        $bikeName = $bid ? Node::load($bid)->getTitle() : 'Unknown Bike';

        $rideDateRaw = $node->get('field_ridedate')->value;
        $isoDate = $rideDateRaw ? $rideDateRaw . 'T12:00:00Z' : date('c', $node->getCreatedTime());

        $record = [
            '$type' => 'net.paullieberman.bike.ride',
            'createdAt' => $isoDate,
            'route' => $node->getTitle(),
            'miles' => (int) $node->get('field_miles')->value,
            'date' => $rideDateRaw,
            'bike' => $bikeName,
            'url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
            'body' => MailFormatHelper::htmlToText($node->body->value),
        ];

        return $this->atprotoClient->putRecord( [            
			'repo' => $this->did,
			'collection' => 'net.paullieberman.bike.ride',
			'rkey' => $rkey,
			'record' => $record,
        ]);
    }



    /**
     * Deletes a ride from the PDS.
     */
    public function deleteRide(string $rkey): bool {
        try {
            $this->atprotoClient->deleteRecord( [
                'json' => [
                    'repo' => $this->did,
                    'collection' => 'net.paullieberman.bike.ride',
                    'rkey' => $rkey,
                ],
            ]);
            $this->state->delete('pds_sync.sync.' . $rkey);
            return TRUE;
        }
        catch (\Exception $e) {
            $this->logger->error('Failed to delete ride @rkey: @message', ['@rkey' => $rkey, '@message' => $e->getMessage()]);
            return FALSE;
        }
    }

    /**
     * Lists and reconciles rides for the dashboard.
     */
    public function getRides(): array {
        $all_records = [];
        $cursor = NULL; 
		 
		do {
			$query = ['repo' => $this->did, 'collection' => 'net.paullieberman.bike.ride', 'limit' => 100];
			if ($cursor) { 
				$query['cursor'] = $cursor; 
			}
		
			$response = $this->atprotoClient->listRecords($query);
			$all_records = array_merge($all_records, $response->records);
			$cursor = $response->cursor ?? NULL;
		} while ($cursor);


        $rides = array_map(function ($record) {
            $record_array = (array) $record->value;
			$record_array['rkey'] = basename($record->uri);
            $record_array['sync_meta'] = $this->getReconciledStatus($record_array);
            return $record_array;
        }, $all_records);

        usort($rides, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $rides;
    }

	/**
	 * Synchronizes a specific PDS record by its rkey (UUID).
	 */
	public function syncByRkey(string $rkey): bool {
		// 1. Find the local node by UUID
		$nodes = $this->entityTypeManager->getStorage('node')
			->loadByProperties(['uuid' => $rkey]);

		$node = reset($nodes);

		if (!$node) {
			// IT Vet Log: Don't just fail silently; log the Ghost attempt.
			$this->logger->error('Sync failed: No local node found for UUID @uuid', ['@uuid' => $rkey]);
			return false;
		}

		// 2. Reuse your existing sync logic from the DrupalSky extraction
		// This likely calls your atproto client to PUT/POST the record.
		$result = $this->syncRide($node);

		if ($result) {
			// 3. Update the State store to mark it as Synced
			// We use the UUID as the key to keep it portable across environments.
			$this->state->set('pds_sync.sync.' . $node->uuid(), $this->time->getRequestTime());

			$this->logger->info('Manual dashboard sync successful for ride @uuid', ['@uuid' => $rkey]);
			return true;
		}
		return false;
	}


    private function getReconciledStatus(array $pds_record): array {
        $uuid = $pds_record['rkey'];
        $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['uuid' => $uuid]);
        $node = reset($nodes);

        if (!$node) {
            return ['status' => 'ghost', 'label' => 'Ghost (PDS Only)', 'class' => 'status-danger'];
        }

        $last_sync = $this->state->get('pds_sync.sync.' . $node->uuid());
        if (!$last_sync) {
            return ['status' => 'untracked', 'label' => 'Untracked Locally', 'class' => 'status-info', 'node' => $node];
        }

        if ($node->getChangedTime() > $last_sync) {
            return ['status' => 'pending', 'label' => 'Changes Pending', 'class' => 'status-warning', 'node' => $node];
        }

        return ['status' => 'synced', 'label' => 'Synced', 'class' => 'status-success', 'node' => $node];
    }


// end-of-class
}
