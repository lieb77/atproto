<?php

declare(strict_types=1);

namespace Drupal\pds_sync\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;


/**
 * Provides a 'Delete Ride' Action.
 *
 * @Action(
 * id = "pds_sync_delete_ride",
 * label = @Translation("Remove a ride from 'state' if the node is deleted"),
 * type = "node"
 * )
 */
final class DeleteRideAction extends ActionBase implements ContainerFactoryPluginInterface {

    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        private readonly StateInterface $state,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
        return new self(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('state')
        );
    }
    
	/**
	 * {@inheritdoc}
	 */
	public function execute($entity = NULL): void {
		if (!$entity instanceof \Drupal\node\NodeInterface) {
			return;
		}
		
		if ($entity->bundle() === 'ride') {
			// Cleanup the state entry when the node is gone.
			$this->state->delete('pds_sync.sync.' . $entity->uuid());
		}
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
