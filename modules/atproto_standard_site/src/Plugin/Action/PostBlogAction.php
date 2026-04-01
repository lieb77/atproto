<?php

declare(strict_types=1);

namespace Drupal\atproto_standard_site\Plugin\Action;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\atproto_standard_site\AtprotoStandardSite;

/**
 * Provides a Blog Insert Action action.
 *
 */
#[Action(
    id: 'atproto_standard_site_post_blog_action',
    label: new TranslatableMarkup('Post Blog to Standard Site Action'),
    category: new TranslatableMarkup('Custom'),
    type: 'node',
)]
final class PostBlogAction extends ActionBase implements ContainerFactoryPluginInterface {

    /**
     * {@inheritdoc}
     */
    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
         private readonly AtprotoStandardSite $atprotoStandardSite,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
        return new self(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('atproto_standard_site.service'),
        );
    }

     /**
     * {@inheritdoc}
     */
    public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
        $result = AccessResult::allowed();
        return $return_as_object ? $result : $result->isAllowed();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?ContentEntityInterface $entity = NULL): void {
        if (!$entity instanceof \Drupal\node\NodeInterface) {
            return;
        } 
        $this->atprotoStandardSite->postToStandardSite($entity);
    }

}
