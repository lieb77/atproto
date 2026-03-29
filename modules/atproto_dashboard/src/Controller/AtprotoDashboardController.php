<?php

declare(strict_types=1);

namespace Drupal\pds_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Drupal\pds_sync\PdsRepository;
use Drupal\pds_sync\PdsSyncManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for PDS Sync routes.
 */
final class PdsSyncController extends ControllerBase {

    /**
     * The renderer service.
     *
     * @var \Drupal\Core\Render\RendererInterface
     */
    protected RendererInterface $renderer;

    /**
     * The PDS Sync manager service.
     *
     * @var \Drupal\pds_sync\PdsSyncManager
     */
    protected PdsSyncManager $pdsSyncManager;

    /**
     * The PDS repository service.
     *
     * @var \Drupal\pds_sync\PdsRepository
     */
    protected PdsRepository $pdsRepository;

    /**
     * The date formatter service.
     *
     * @var \Drupal\Core\Datetime\DateFormatterInterface
     */
    protected DateFormatterInterface $dateFormatter;

    /**
     * The entity type manager service.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected EntityTypeManagerInterface $nodeManager;

    /**
     * The controller constructor.
     *
     * @param \Drupal\Core\Render\RendererInterface $renderer
     *   The renderer service.
     * @param \Drupal\pds_sync\PdsSyncManager $pdsSyncManager
     *   The PDS Sync manager service.
     * @param \Drupal\pds_sync\PdsRepository $pdsRepository
     *   The PDS repository service.
     * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
     *   The date formatter service.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $nodeManager
     *   The entity type manager service.
     */
    public function __construct(
        RendererInterface $renderer,
        PdsSyncManager $pdsSyncManager,
        PdsRepository $pdsRepository,
        DateFormatterInterface $dateFormatter,
        EntityTypeManagerInterface $nodeManager
    ) {
        $this->renderer = $renderer;
        $this->pdsSyncManager = $pdsSyncManager;
        $this->pdsRepository = $pdsRepository;
        $this->dateFormatter = $dateFormatter;
        $this->nodeManager = $nodeManager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): self {
        return new self(
            $container->get('renderer'),
            $container->get('pds_sync.manager'),
            $container->get('pds_sync.repository'),
            $container->get('date.formatter'),
            $container->get('entity_type.manager')
        );
    }

    /**
     * PDS Admin Dashboard.
     */
    public function dashboardShell(): array {
        // 1. Keep your existing logic to generate the initial "Local Drupal" list
        $nodes = $this->pdsSyncManager->getRecentRides(25);
    $pds_rides = $this->pdsRepository->getRides();

        $rides = [];
        foreach ($nodes as $node) {
            $rides[] = [
                'route' => $node->label(),
                'date' => $node->get('field_ridedate')->value,
                'miles' => $node->get('field_miles')->value,
                'bike' => $node->get('field_bike')->entity?->label(),
                'rkey' => $node->uuid(),
                'sync_meta' => $this->pdsSyncManager->getReconciledStatus($node, $pds_rides),
            ];
        }

        // 2. Render the initial table as a fragment for the shell
        $initial_table = [
            '#type' => 'component',
            '#component' => 'pds_sync:rides',
            '#props' => ['rides' => $rides],
        ];

        // 3. Return the Tabbed Shell SDC
        return [
            '#type' => 'component',
            '#component' => 'pds_sync:pds-dashboard', // The new wrapper SDC
            '#props' => [
                'initial_view' => $this->renderer->renderInIsolation($initial_table),
            ],
        ];
    }

    /**
     * Drupal View.
     */
    public function drupalView(): Response {
        $nodes = $this->pdsSyncManager->getRecentRides(25);
        $pds_rides = $this->pdsRepository->getRides();

        $rows = [];
        foreach ($nodes as $node) {
            $rows[] = $this->prepareRideData($node, $pds_rides);
        }

        $build = [
            '#type' => 'component',
            '#component' => 'pds_sync:rides',
            '#props' => [
                'rides' => $rows,
                'view_mode' => 'local',
            ],
        ];

        return new Response(trim((string) $this->renderer->renderInIsolation($build)));
    }

    /**
     * PDS View.
     */
    public function pdsView(): Response {
        $pds_rides = $this->pdsRepository->getRides();

        $rows = [];
        foreach ($pds_rides as $pds_ride) {
            // 1. Check if we have this locally
            $local_node = $this->pdsSyncManager->getLocalNodeByRkey($pds_ride['rkey']);

            // 2. Build the full object for the SDC, keeping your existing PDS fields
            $rows[] = [
                'route' => $pds_ride['route'],
                'date' => $pds_ride['date'],
                'miles' => $pds_ride['miles'] ?? '--', // Pass through from PDS
                'bike' => $pds_ride['bike'] ?? 'Unknown', // Pass through from PDS
                'rkey' => $pds_ride['rkey'],
                // Normalize the status so the Twig template gets the right classes
                'sync_meta' => [
                    'status' => $local_node ? 'synced' : 'remote-only',
                    'label' => $local_node ? 'Synced' : 'PDS Only',
                    'class' => $local_node ? 'status-synced' : 'status-remote',
                ],
            ];
        }

        $build = [
            '#type' => 'component',
            '#component' => 'pds_sync:rides',
            '#props' => ['rides' => $rows],
        ];

        return new Response(trim((string) $this->renderer->renderInIsolation($build)));
    }

    /**
     * Update.
     */
    public function update(string $rkey): Response {
        $success = $this->pdsRepository->syncByRkey($rkey);

        if ($success) {
            // 1. Load the node to get the label/date for the row
            $nodes = $this->nodeManager->getStorage('node')->loadByProperties(['uuid' => $rkey]);
            $node = reset($nodes);

            // 2. Prepare data, but MANUALLY set the sync_meta to 'Synced'
            $ride_data = $this->prepareRideData($node, []); // Pass empty PDS array
            $ride_data['sync_meta'] = [
                'label' => 'Synced',
                'class' => 'status-synced',
                'can_sync' => FALSE,
                'can_delete' => TRUE,
            ];

            // 3. Return the fresh row
            $build = [
                '#type' => 'component',
                '#component' => 'pds_sync:pds-ride-row',
                '#props' => ['ride' => $ride_data],
            ];

            $markup = $this->renderer->renderInIsolation($build);
            // Cast to string to satisfy trim()
            return new Response(trim((string) $markup));
        }

        return new Response("<div class='status-error'>Sync failed for $rkey</div>", 500);
    }

    /**
     * Deletes a record from the PDS and clears local sync state.
     */
    public function delete(string $rkey): Response {

        $success = $this->pdsRepository->deleteRide($rkey);
        
        if ($success) {
        
            // 1. Reload the node
            $nodes = $this->nodeManager->getStorage('node')->loadByProperties(['uuid' => $rkey]);
        if (! empty($nodes)) {
            $node = reset($nodes);
    
            // 2. Prepare data (it will now naturally be 'untracked' because state is cleared)
            $ride_data = $this->prepareRideData($node, []);
    
            $build = [
            '#type' => 'component',
            '#component' => 'pds_sync:pds-ride-row',
            '#props' => ['ride' => $ride_data],
            ];
    
            // 3. Return the fresh row HTML instead of an empty response
            $html = $this->renderer->renderInIsolation($build);
            return new Response(trim((string) $html));
        }
        // The node doesn't exist in Drupal
        return false;
        }
        return new Response('Delete failed', 500);
    }

    /**
     * Rides.
     *
     * Return a render array.
     */
    public function rides(): array {
        $rides = $this->pdsRepository->getRides();

        return [
            '#type' => 'component',
            '#component' => 'pds_sync:rides',
            '#props' => ['rides' => $rides],
        ];
    }

    /**
     * Logout.
     */
    public function logout(): array {
        $this->service->logout();

        return [
            '#type' => 'item',
            '#markup' => $this->t("Your Bluesky session has been cleared"),
        ];
    }


	/**
	 * Test route to post a blog entry as a site.standard.document
	 * Hard coded values for testing
	 */
	 public function blogToSsd() {
	 	$nid  = 9323;
	 	$node = $this->nodeManager->getStorage('node')->load($nid);
	 	$response = $this->pdsRepository->postToStandardSite($node);
	 	
	  	return [
            '#type' => 'item',
            '#markup' => $this->t("Node posted to Standard Site" ),
        ];
	 
	 }    



    /**
     * Prepares data for the pds-ride-row SDC.
     *
     * @param \Drupal\node\NodeInterface $node
     *   The node object.
     * @param array $pds_rides
     *   The PDS rides array.
     *
     * @return array
     *   The prepared ride data.
     */
    private function prepareRideData(NodeInterface $node, array $pds_rides): array {
        // 1. Extract basic field data
        $date_value = $node->get('field_ridedate')->value; // Assuming ISO string
        $miles = $node->get('field_miles')->value;

        // Get the bike label from the entity reference
        $bike_entity = $node->get('field_bike')->entity;
        $bike_label = $bike_entity ? $bike_entity->label() : 'N/A';

        // 2. Determine Sync Status via the Manager
        // This is where your Yellow/Green/White logic lives
        $sync_meta = $this->pdsSyncManager->getReconciledStatus($node, $pds_rides);

        return [
            'route' => $node->label(),
            'date' => $date_value,
            'miles' => $miles,
            'bike' => $bike_label,
            'rkey' => $node->uuid(),
            'sync_meta' => $sync_meta,
        ];
    }

    // End of class.
}
