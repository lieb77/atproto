<?php

declare(strict_types=1);

namespace Drupal\atproto_dashboard\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides hook implementations for the PDS Sync module.
 */
class AtprotoDashboardHooks {

    
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
        if ($route_name === 'help.page.atproto_dashboard') {
            $output = <<<EOF
                <h2>ATproto Dashboard Help</h2>
                <p>This module provides a dashboard for working wih  AT Protocol records.</p>
                <ul>
                  <li>Lists records for each lexicon</li>
                  <li>Provides View, Edit, and Delete actions for each record</li>
                </ul>                
            EOF;

            return ['#markup' => $output];
        }

        return NULL;
    }
}

