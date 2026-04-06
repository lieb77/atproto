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
     * Doc View.
     */
    public function postView(): Response {

		$posts = $this->atprotoDashboard->listPostRecords();
        $build = [
            '#type' => 'component',
            '#component' => 'atproto_dashboard:posts',
            '#props' => ['posts' => $posts],
        ];

        return new Response(trim((string) $this->renderer->renderInIsolation($build)));
    }
    
   
	/**
	 * View record
	 *
	 */
	public function viewRecord(string $type, string $rkey): Response {
	
		$record = $this->atprotoDashboard->getRecord($type, $rkey);

	    $build = [
            '#type' => 'component',
            '#component' => 'atproto_dashboard:json',
            '#props' => ['json' => $record],
        ];

        return new Response(trim((string) $this->renderer->renderInIsolation($build)));

	
	}   
   
    public function update(string $rkey): Response {
		return new Response("", 200);
	}

    /**
     * Deletes a record from the PDS
     */
    public function delete(string $rkey): Response {

        $success = $this->atprotoDashboard->deleteRide($rkey);
        
        if ($success) {
            return new Response("", 200);
        }
        return new Response('Delete failed', 500);
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

    // End of class.
}
