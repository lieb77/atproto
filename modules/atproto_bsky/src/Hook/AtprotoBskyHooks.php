<?php

declare(strict_types=1);

namespace Drupal\atproto_bsky\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides hook implementations for the PDS Sync module.
 */
class AtprotoBskyHooks {

    /**
     * Implements hook_help().
     *
     * @param string $route_name
     *   The name of the route being accessed.
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The corresponding route match object.
     *
     * @return array|null
     *   An array of help text to display, or NULL if no help is available.
     */
    #[Hook('help')]
    public function help(string $route_name, RouteMatchInterface $route_match): ?array
    {
        if ($route_name === 'help.page.atproto_bsky') {
            $output = <<<EOF
                <h2>ATproto Bluesky Help</h2>
                <ul>
                  <li>Posts Drupal content as app.bsky.feed.post records</li>
                  <li>Tracks the posts and creates webmentions for likes, replies, and reposts.</li>
                </ul>
            EOF;

            return ['#markup' => $output];
        }

        return NULL;
    }

  
   
    /**
     * Implements hook_entity_base_field_info().
     *
     * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
     *   The entity type definition.
     *
     * @return array|null
     *   An array of base field definitions, or NULL if not applicable.
     */
    #[Hook('entity_base_field_info')]
    public function entityBaseFieldInfo(EntityTypeInterface $entity_type): ?array
    {
        if ($entity_type->id() === 'indieweb_syndication') {
            $fields = [];
            $fields['at_uri'] = BaseFieldDefinition::create('string')
                ->setLabel(t('AT Protocol URI'))
                ->setDescription(t('The full at:// URI for the Bluesky post.'))
                ->setSettings(['max_length' => 255])
                ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => -5])
                ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -5]);

            return $fields;
        }

        return NULL;
    }

}  

