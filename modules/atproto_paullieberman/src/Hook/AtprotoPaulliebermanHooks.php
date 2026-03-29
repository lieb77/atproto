<?php

declare(strict_types=1);

namespace Drupal\atproto_paullieberman\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides hook implementations for the PDS Sync module.
 */
class AtprotoPaulliebermanHooks {

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
        if ($route_name === 'help.page.atproto_paullieberman') {
            $output = <<<EOF
                <h2>PDS Sync Help</h2>
                <p>This module provides integration with Bluesky.</p>
                <h3>Setup</h3>
                <ol>
                    <li>Obtain an <a href="https://blueskyfeeds.com/en/faq-app-password">App Password</a> for your BlueSky account. Do not use your login password.</li>
                    <li>Create a new Key at <a href="/admin/config/system/keys">/admin/config/system/keys</a>. This will be an Authentication key and will hold your App Password.</li>
                    <li>Go to the Drupalsky settings at <a href="/admin/config/services/dskysettings">/admin/config/services/dskysettings</a>. Enter your Bluesky handle and select the Key you saved</li>
                    <li>Go to your user profile and you will now see a Bluesky tab</li>
                </ol>
            EOF;

            return ['#markup' => $output];
        }

        return NULL;
    }

}

