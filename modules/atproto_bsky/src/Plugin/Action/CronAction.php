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
 * Provides a  Cron Action.
 *
 * @Action(
 * id = "atproto_bsky_cron",
 * label = @Translation("Checks for webmentions via cron"),
 * type = "system"
 * )
 */
final class CronAction extends ActionBase implements ContainerFactoryPluginInterface {

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
    public function execute($object = NULL): void {
        $this->container->get('atproto_bsky.service')->getWebmentions();
    }

    /**
     * {@inheritdoc}
     */
    public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
        return $return_as_object ? AccessResult::allowed() : TRUE;
    }

}