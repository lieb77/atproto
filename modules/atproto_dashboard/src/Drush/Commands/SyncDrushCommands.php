<?php

namespace Drupal\pds_sync\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pds_sync\PdsRepository;

/**
* Drush commands for pds_sync PDS syncing.
*/
class SyncDrushCommands extends DrushCommands {

	/**
	* The node storage (aliased for consistency).
	*/
	protected $nodeManager;
	
	/**
	* The PDS repository service.
	*/
	protected $pdsRepository;
	
	public function __construct(
		EntityTypeManagerInterface $entity_type_manager,
		PdsRepository $pds_repository
		) {
		parent::__construct();
		$this->nodeManager = $entity_type_manager;
		$this->pdsRepository = $pds_repository;
	}
	
	/**
	* Syncs the most recent rides to the PDS.
	*
	* @command pds_sync:sync-recent
	* @param int $limit The number of recent rides to sync.
	* @usage drush pds_sync:sync-recent 15
	* @category pds_sync
	*/
	public function syncRecent(int $limit = 10): void {
		// Query for the most recent rides based on your custom date field
		$nids = $this->nodeManager->getStorage('node')->getQuery()
			->condition('type', 'ride')
			->sort('field_ridedate', 'DESC') // Most recent first
			->range(0, $limit)
			->accessCheck(FALSE)
			->execute();
		
		if (empty($nids)) {
			$this->logger()->warning(dt('No ride nodes found to sync.'));
			return;
		}
		
		$count = count($nids);
		$this->output()->writeln(dt('Found @count recent rides. Starting sync...', [
			'@count' => $count,
		]));
		
		foreach ($nids as $nid) {
			$node = $this->nodeManager->getStorage('node')->load($nid);
			$this->output()->writeln(dt('Syncing: @title (@date)', [
				'@title' => $node->label(),
				'@date' => $node->get('field_ridedate')->value,
			]));
			
			try {
				// Your hardened repository handles the connection check
				$success = $this->pdsRepository->syncRide($node);
				$success = $this->pdsRepository->postRideToTimeline($node);
				
				if (!$success) {
					$this->logger()->error(dt('PDS returned a failure for node @id.', ['@id' => $nid]));
					}
			} catch (\Exception $e) {
				$this->logger()->error(dt('Critical error syncing node @id: @msg', [
					'@id' => $nid,
					'@msg' => $e->getMessage(),
					]));
				}
			}
			
			$this->logger()->success(dt('Finished processing @count rides.', ['@count' => $count]));
		}
		
	
	/**
	* Publishes the Feed Generator record to the PDS.
	*
	* @command pds_sync:create-feed
	* @aliases pds-cf
	*/
	public function feedGen(): void {
		$this->output()->writeln(dt('Starting creation of feed record...')); 
	
		try {
			$response = $this->pdsRepository->createFeedRecord();
			
			if ($response) {
				$this->logger()->success(dt('Successfully created/updated the "Ride Log" feed record.'));
			} else {
				$this->logger()->error(dt('Feed record creation failed without an exception.'));
			}
		} catch (\Exception $e) {
			$this->logger()->error(dt('Critical error creating feed record: @msg', [
			'@msg' => $e->getMessage(),
			]));
		}
		
		$this->output()->writeln(dt('Process complete.'));
	}
	
	/**
	* Publishes the Standard Site Publication record to the PDS.
	*
	* @command pds_sync:ss-pub
	* @aliases pds-cf
	*/
	public function ssPubGen(): void {
		$this->output()->writeln(dt('Starting creation of SSP record...')); 
		try {
			$response = $this->pdsRepository->createStandardSitePublicationRecord();
			
			if ($response) {
				$this->logger()->success(dt('Successfully created/updated the SSP record.'));
			} else {
				$this->logger()->error(dt('SSP record creation failed without an exception.'));
			}
		} catch (\Exception $e) {
			$this->logger()->error(dt('Critical error creating SSP record: @msg', [
			'@msg' => $e->getMessage(),
			]));
		}
		$this->output()->writeln(dt('Process complete.'));
	}
	
	
}



