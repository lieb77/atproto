<?php
/*
 * @to-do:
 *
 * Add image
 *  Here is some JS that creates the blob and dies this. Need to find the php
 *      
import fs from "fs";
import { AtpAgent } from "@atproto/api";
const agent = new AtpAgent({ service: "<https://bsky.social>" });

async function uploadImageAndPublishDocument() {
  await agent.login({
    identifier: "your-handle.bsky.social",
    password: "your-app-password",
  });

  const did = agent.session.did;

  // 1. Read the local image file into a buffer
  const imageBuffer = fs.readFileSync("./path/to/your/cover.jpg");

  // 2. Upload the blob to your repository
  const { data: blobResponse } = await agent.com.atproto.repo.uploadBlob(
    imageBuffer,
    { encoding: "image/jpeg" }
  );

  console.log("Blob successfully uploaded!");

  // 3. Define the Document Record, attaching the returned blob reference
  const documentRecord = {
    $type: "site.standard.document",
    site: `at://your-did/site.standard.publication/your-pub-rkey`,
    title: "My New Post with a Cover Image",
    publishedAt: "2026-06-18T12:00:00.000Z",
    cover: blobResponse.blob, // This links the blob to your document
  };

  // 4. Write the document record to your repository
  try {
    const response = await agent.com.atproto.repo.createRecord({
      repo: did,
      collection: "site.standard.document",
      record: documentRecord,
    });

    console.log("Document record with cover image published:");
    console.log(response.data.uri);
  } catch (error) {
    console.error("Failed to publish document:", error);
  }
}

uploadImageAndPublishDocument();

 */


declare(strict_types=1);

namespace Drupal\atproto_standard_site;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\atproto\AtprotoLoggerTrait;
use Drupal\atproto_client\AtprotoClientService;


/**
 * Service to post blog entries and rides as site.standard.document records
 *
 */
final class AtprotoStandardSite {
   use AtprotoLoggerTrait;

  /**
   * Constructs a StandardSite object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
    private readonly AtprotoClientService $atprotoClient,
    ) {
        $this->setLoggerFactory($loggerFactory);
    }
 

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
				'rkey'		 => $this->generateTid();,
				'record' 	 => $record,
			]);
			$this->logger()->notice("Created standard site record for blog post @title", ["@title" => $node->get('title')->value]);
		}
		catch (\Throwable $e) {
      		$this->logger()->error("Post standard site record failed: " . $e->getMessage());
      		return FALSE;
	 	}
	 	
	 	$this->createSyndicationEntity($node->id(), $response->uri);
	 	return TRUE;
	 }


	/**
	 * Publish a ride as a site.standard.document record
	 *
	 */
	public function rideToStandardSite(NodeInterface $node): mixed {

        $this->logger()->notice("Entered rideToStandardSite");
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
				'rkey'		 => $this->generateTid(); 
				'record' 	 => $record,
			]);
			$this->logger()->notice("Created standard site record for ride  @title", ["@title" => $node->get('title')->value]);
			return $response;
		}
		catch (\Throwable $e) {
      		$this->logger()->error("Post standard site record failed: " . $e->getMessage());
      		return FALSE;
	 	}
	 }
	 
	 /**
	 * Create the syndication entity
	 *
	 * Note: We have extended the syndication entity to include the at_uri field
	 */
    private function createSyndicationEntity(string $nid, string $atUri): void {
        $this->entityTypeManager->getStorage('indieweb_syndication')->create([
            'entity_id' 	 => $nid,
            'entity_type_id' => 'node',
            'url' 			 => "https://leaflet.pub/p/paullieberman.net",
            'at_uri' 		 => $atUri,
        ])->save();
        $this->logger()->info("Syndication saved for node @nid",["@nid" => $nid]);
    }

     /**
	 * Generate the TID for the Rkey
	 */
    private function generateTid(?int $customMicroTime = null, int $customClockId = null): string {
    // 1. Determine microtime
    $microTime = $customMicroTime ?? (int)(microtime(true) * 1000000);

    // 2. Generate or use provided random clock ID (10 bits = 0 to 1023)
    $clockId = $customClockId ?? mt_rand(0, 1023);

    // 3. Pack bits (Top bit 0 + 53 bits time + 10 bits clockId)
    // 64-bit PHP is required for bitwise shift safety
    $packed = ($microTime << 10) | ($clockId & 0x3FF);

    // 4. Base32 alphabet for ATProtocol: 2-7, a-z (excluding 0, 1, 8, 9 to prevent confusion)
    $alphabet = '234567abcdefghijklmnopqrstuvwxyz';
    $base32 = '';

    // 5. Convert to Base32
    $temp = $packed;
    while ($temp > 0) {
        $base32 = $alphabet[$temp & 0x1F] . $base32;
        $temp >>= 5;
    }

    // 6. Pad to exactly 13 characters
    return str_pad($base32, 13, '2', STR_PAD_LEFT);
}

// end-of-class    
}	
