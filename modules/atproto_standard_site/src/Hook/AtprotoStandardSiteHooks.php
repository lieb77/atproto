<?php

declare(strict_types=1);

namespace Drupal\atproto_standard_site\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides hook implementations for the PDS Sync module.
 */
class AtprotoStandardSiteHooks {

    
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
        if ($route_name === 'help.page.atproto_standardsite') {
            $output = <<<EOF
                <h2>ATproto Standard Site  Help</h2>              
                <ul>
                  <li>Posts new ride nodes as site.standard.document/li>
                  <li>Posts new blog nodes as site.standard.document</li>
                  <li>Provides action plugins for use with ECA</li>
                </ul>
            EOF;

            return ['#markup' => $output];
        }

        return NULL;
    }
}

