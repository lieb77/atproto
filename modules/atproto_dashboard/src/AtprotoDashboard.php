<?php
declare(strict_types=1);

namespace Drupal\pds_sync;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\node\NodeInterface;
use Drupal\pds_sync\PdsRepository;

/**
 * Orchestrates the synchronization between Drupal Nodes and the PDS.
 */
class PdsSyncManager {

    /**
     * Constructs a new PdsSyncManager object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     * @param \Drupal\Core\State\StateInterface $state
     *   The state service.
     * @param \Drupal\pds_sync\PdsRepository $pdsRepository
     *   The PDS repository.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected StateInterface $state,
        protected PdsRepository $pdsRepository,
        protected TimeInterface $time,
    ) {}

    /**
     * Fetches the most recent rides from Drupal.
     *
     * @param int $limit
     *   The number of rides to fetch.
     *
     * @return \Drupal\node\NodeInterface[]
     *   An array of ride nodes.
     */
    public function getRecentRides(int $limit = 10): array {
        $storage = $this->entityTypeManager->getStorage('node');
        $query = $storage->getQuery()
            ->condition('type', 'ride')
            ->condition('status', 1)
            ->sort('field_ridedate', 'DESC')
            ->range(0, $limit)
            ->accessCheck(TRUE);

        $nids = $query->execute();
        return $storage->loadMultiple($nids);
    }

    /**
     * Finds a local ride node by its PDS rkey (UUID).
     *
     * @param string $rkey
     *   The PDS rkey (UUID).
     *
     * @return \Drupal\node\NodeInterface|null
     *   The ride node, or NULL if not found.
     */
    public function getLocalNodeByRkey(string $rkey): ?NodeInterface {
        $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
            'uuid' => $rkey,
            'type' => 'ride', // Ensuring we only look at ride nodes
        ]);

        return $nodes ? reset($nodes) : NULL;
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

