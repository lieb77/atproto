<?php

declare(strict_types=1);

namespace Drupal\atproto_standard_site;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\atproto_client\Client\AtprotoClient;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Service to post blog entries and rides a site.standard.document records
 *
 */
final class AtprotoStandardSite {


  /**
   * Constructs a StandardSite object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelInterface $logger,
    private readonly AtprotoClient $atprotoClient,
  ) {}


 	/**
	 * Publish a blog post as a site.standard.document record
	 *
	 */
	public function postToStandardSite(NodeInterface $node): mixed {

		// Get did from client
		$did = $this->atprotoClient->getDid();

		// Build the tags array
		$tags = [];
		foreach ($node->get('field_tags') as $tagRef) {
			$tid = $tagRef->target_id;
			$tags[] = Term::load($tid)->getName();
		}

		// Define a site.standard.document record
	 	$record = [
			'$type' 		=>  "site.standard.document",
			'site' 			=>  "at://" . $did . "/site.standard.publication/liebs-log",
			'title' 		=>  $node->get('title')->value,
			'path' 			=>  $node->toUrl()->toString(),
			'description' 	=>  mb_substr(MailFormatHelper::htmlToText($node->body->value), 0, 3000), // Should use body->summary
			'publishedAt' 	=>  date('c', (int) $node->getCreatedTime()),
			'tags' 			=>  $tags,
			'textContent' 	=>  MailFormatHelper::htmlToText($node->body->value),
	 	];

	 	// Post the record
	 	try {
			$response = $this->atprotoClient->putRecord( [
				'repo' 		 => $did,
				'collection' => 'site.standard.document',
				'rkey'		 => $node->uuid(),
				'record' 	 => $record,
			]);
			$this->logger->notice("Created standard site record for blog post @title", ["@title" => $node->get('title')->value]);
			return $response;
		}
		catch (\Throwable $e) {
      		$this->logger->error("Post standard site record failed: " . $e->getMessage());
      		return FALSE;
	 	}
	 }


	/**
	 * Publish a ride as a site.standard.document record
	 *
	 */
	public function rideToStandardSite(NodeInterface $node): mixed {

		// Get did from client
		$did = $this->atprotoClient->getDid();

		// Get the bike name
        $bid = $node->field_bike->target_id;
        $bikeName = $bid ? Node::load($bid)->getTitle() : 'Unknown Bike';

		// Calulate date from ridedate
        $rideDateRaw = $node->get('field_ridedate')->value;
        $isoDate = $rideDateRaw ? $rideDateRaw . 'T12:00:00Z' : date('c', $node->getCreatedTime());

		// Build the description
		$textParts = [
            "Route: " . $node->label(),
            "Date: " . $rideDateRaw,
            "Distance: " . $node->get('field_miles')->value . " miles",
            "Bike: " . ($node->get('field_bike')->entity?->label() ?? 'N/A'),
        ];
        $description = implode("\n", $textParts);

		// Inner content is a net.paullieberman.bike.ride
        $content = [
            '$type' 	=> 'net.paullieberman.bike.ride',
            'createdAt' => $isoDate,
            'route' 	=> $node->getTitle(),
            'miles' 	=> (int) $node->get('field_miles')->value,
            'date' 		=> $rideDateRaw,
            'bike' 		=> $bikeName,
            'url' 		=> $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
            'body' 		=> MailFormatHelper::htmlToText($node->body->value),
        ];

		// Outer wreapper is a site.standard.document
		$record = [
			'$type' 		=>  "site.standard.document",
			'site' 			=>  "at://" . $did . "/site.standard.publication/liebs-log",
			'title' 		=> "🚲Lieb's Ride Log🚲",
			'path' 			=>  $node->toUrl()->toString(),
			'description' 	=>  $description,
			'publishedAt' 	=>  date('c', (int) $node->getCreatedTime()),
			'tags' 			=>  ["bicycle", "bikeride", "bikepacking"],
			'coverImage' 	=> [
				'$type' => "blob",
				'ref' => [
					'$link' => "bafkreig5sumxyd76kcuehdb4lhswwav6oqosrqxtwjw7asbk33nfavlkfq"
				],
				'mimeType' => "image/jpeg",
				'size' => 472288
			],
			'content' => $content,
		];

        // Post the record
	 	try {
			$response = $this->atprotoClient->putRecord( [
				'repo' 		 => $did,
				'collection' => 'site.standard.document',
				'rkey'		 => $node->uuid(),
				'record' 	 => $record,
			]);
			$this->logger->notice("Created standard site record for ride  @title", ["@title" => $node->get('title')->value]);
			return $response;
		}
		catch (\Throwable $e) {
      		$this->logger->error("Post standard site record failed: " . $e->getMessage());
      		return FALSE;
	 	}
	 }
}
