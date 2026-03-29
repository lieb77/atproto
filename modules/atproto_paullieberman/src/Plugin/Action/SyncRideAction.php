<?php

declare(strict_types=1);

namespace Drupal\pds_sync\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\pds_sync\PdsRepository;

/**
 * Provides a 'Sync Ride' Action.
 *
 * @Action(
 * id = "pds_sync_sync_ride",
 * label = @Translation("Syncs a Drupal ride entity to the PDS"),
 * type = "node"
 * )
 */
final class SyncRideAction extends ActionBase implements ContainerFactoryPluginInterface {

    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        protected PdsRepository $pdsRepository,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
        return new self(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('pds_sync.repository')
        );
    }
    
    /**
     * {@inheritdoc}
     */
	public function execute($entity = NULL): void {
		if (!$entity instanceof \Drupal\node\NodeInterface) {
			return;
		}	
		$this->pdsRepository->syncRide($entity);
	}

    /**
     * {@inheritdoc}
     */
    public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
        // In Drupal 11, we should return an AccessResult object.
        $result = AccessResult::allowed();

        // If the caller explicitly asked for a boolean (the default), 
        // we return the result of isAllowed(). Otherwise, return the object.
        return $return_as_object ? $result : $result->isAllowed();
    }
    
}
