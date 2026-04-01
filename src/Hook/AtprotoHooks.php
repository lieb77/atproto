<?php

declare(strict_types=1);

namespace Drupal\atproto\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides hook implementations for the PDS Sync module.
 */
class AtprotoHooks {

    
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
        if ($route_name === 'help.page.atproto') {
            $output = <<<EOF
                <h2>ATproto Help</h2>
                <p>This module provides a suite of modules for the AT Protocol.</p>
                <h3>Modules</h3>
                <h4>ATproto Client</h4>
                <ul>
                  <li>Handles the client XRPC requests using Drupal's http_client</li>
                  <li>Required by all the other modules</li>
                </ul>
                <h4>ATproto Bluesky</h4>
                <ul>
                  <li>Posts new ride nodes as app.bsky.feed.post</li>
                  <li>Posts new blog nodes as app.bsky.feed.post</li>
                  <li>Provides action plugins for use with ECA</li>
                </ul>
                <h4>ATproto Standard Site</h4>
                <ul>
                  <li>Posts new ride nodes as site.standard.document/li>
                  <li>Posts new blog nodes as site.standard.document</li>
                  <li>Provides action plugins for use with ECA</li>
                </ul>
                <h4>ATproto Paullieberman</h4>
                <ul>
                  <li>Posts new ride nodes as net.paullieberman.bike.ride</li>
                  <li>Posts new blog nodes as net.paullieberman.bike.ride</li>
                  <li>Provides action plugins for use with ECA</li>
                </ul>
                <h4>ATproto Dashboard</h4>
                <ul>
                  <li>Lists records for each lexicon</li>
                  <li>Provides View, Edit, and Delete actions for each record</li>
                </ul>                
                <h3>Setup</h3>
                <ol>
                    <li>Obtain an <a href="https://blueskyfeeds.com/en/faq-app-password">App Password</a> for your BlueSky account. Do not use your login password.</li>
                    <li>Create a new Key at <a href="/admin/config/system/keys">/admin/config/system/keys</a>. This will be an Authentication key and will hold your App Password.</li>
                    <li>Go to the ATproto settings at <a href="/admin/config/services/atproto-settings">/admin/config/services/atprotoclient-settings</a>. Enter your Atproto handle and select the Key you saved</li>
                </ol>
            EOF;

            return ['#markup' => $output];
        }

        return NULL;
    }
}

