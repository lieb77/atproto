<?php
declare(strict_types=1);

namespace Drupal\atproto_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Drupal\atproto_dashboard\AtprotoDashboard;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for PDS Sync routes.
 */
final class AtprotoDashboardController extends ControllerBase {

 
    /**
     * The controller constructor.
 	 *
     */
    public function __construct(
        private readonly RendererInterface $renderer,       
        private readonly DateFormatterInterface $dateFormatter,
        private readonly AtprotoDashboard $atprotoDashboard,
    ) { }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): self {
        return new self(
            $container->get('renderer'),
            $container->get('date.formatter'),
            $container->get('atproto_dashboard.dashboard')
        );
    }

    /**
     * PDS Admin Dashboard.
     */
    public function dashboardShell(): array {
  		
  		$rides = $this->atprotoDashboard->listRideRecords();

        // Render the initial table as a fragment for the shell
        $initial_table = [
            '#type' 	 => 'component',
            '#component' => 'atproto_dashboard:rides',
            '#props' 	 => ['rides' => $rides],
        ];

		
        // Return the Tabbed Shell SDC
        return [
            '#type' => 'component',
            '#component' => 'atproto_dashboard:pds-dashboard',
            '#props' => [
                'initial_view' => $this->renderer->renderInIsolation($initial_table),
            ],
        ];
    }

    /**
     * Ride  View.
     */
    public function rideView(): Response {

		$rides = $this->atprotoDashboard->listRideRecords();
        $build = [
            '#type' 	 => 'component',
            '#component' => 'atproto_dashboard:rides',
            '#props' 	 => ['rides' => $rides],
        ];

        return new Response(trim((string) $this->renderer->renderInIsolation($build)));
    }

    /**
     * Doc View.
     */
    public function docView(): Response {

		$docs = $this->atprotoDashboard->listDocRecords();
        $build = [
            '#type' => 'component',
            '#component' => 'atproto_dashboard:docs',
            '#props' => ['docs' => $docs],
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
