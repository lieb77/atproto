<?php

declare(strict_types=1);

namespace Drupal\atproto_bsky\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\atproto_bsky\AtprotoBsky;


/**
 * Provides a Post Ride Action.
 *
 * @Action(
 * id = "atproto_bsky_post_ride",
 * label = @Translation("Post ride to Bluesky"),
 * type = "node"
 * )
 */
final class PostRideAction extends ActionBase implements ContainerFactoryPluginInterface {

    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        protected ContainerInterface $container,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
        return new self(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container
        );
    }
    
    /**
     * {@inheritdoc}
     */
	public function execute($entity = NULL): void {
		if (!$entity instanceof \Drupal\node\NodeInterface || $entity->bundle() !== 'ride') {
			return;
		}
		$this->container->get('atproto_bsky.service')->postRideToTimeline($entity);
	}

    /**
     * {@inheritdoc}
     */
    public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
        $result = AccessResult::allowed();
        return $return_as_object ? $result : $result->isAllowed();
    }
    
}
