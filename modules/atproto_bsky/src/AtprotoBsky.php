<?php

declare(strict_types=1);

namespace Drupal\atproto_bsky;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\node\NodeInterface;
use Drupal\atproto_core\AtprotoLoggerTrait;
use Drupal\atproto_client\Client\AtprotoClient;

/**
 * Manages Bluesky timeline posts, syndication entities, and webmention backfeed.
 *
 * syndication and webmention entities are provided by the indieweb_webmention module.
 * We have extended the syndicaion entity to include an additional field.
 *  see src/Hook/AtprotoBskyHooks -> #[Hook('entity_base_field_info')]
 *
 */
class AtprotoBsky {
    use AtprotoLoggerTrait;

    public function __construct(
        protected AtprotoClient $atprotoClient,
        protected EntityTypeManagerInterface $entityTypeManager,
        protected LoggerChannelFactoryInterface $loggerFactory
    ) {
    	$this->setLoggerFactory($loggerFactory);
    }
    
    /**
     * Posts a ride to the public Bluesky timeline.
     *
     * lexicon app.bsky.feed.post
     *
     * This is executed by our PostRideAction via ECA
     *
     */
    public function postRideToTimeline(NodeInterface $node): mixed {
        $this->logger->info("Starting postRideToTimeline");
        $rideDateRaw = $node->get('field_ridedate')->value;
        $textParts = [
            "🚲Lieb's Ride Log🚲",
            "Route: "    . $node->label(),
            "Date: "     . $rideDateRaw,
            "Distance: " . $node->get('field_miles')->value . " miles",
            "Bike: "     . ($node->get('field_bike')->entity?->label() ?? 'N/A'),
            "",
            "#bicycle #bikeride #bikepacking",
        ];

        $text = implode("\n", $textParts);
        $facets = $this->createTagFacets($text, ['bicycle', 'bikeride', 'bikepacking']);
        $uri = $node->toUrl()->setAbsolute()->toString();

        $postRecord = [
            'repo' 		 => $this->atprotoClient->getDid(),
            'collection' => 'app.bsky.feed.post',
            'record' 	 => [
                '$type' 	=> 'app.bsky.feed.post',
                'text' 		=> $text,
                'facets' 	=> $facets,
                'createdAt' => date('c', strtotime($rideDateRaw)),
                'tags' 		=> ['lieb-ride-log'],
                'embed' 	=> [
                    '$type' => 'app.bsky.embed.external',
                    'external' 		  => [
                        'uri'   	  => $uri,
                        'title' 	  => "Ride: " . $node->label(),
                        'description' => MailFormatHelper::htmlToText($node->body->value),
                    ],
                ],
            ],
        ];

        $response = $this->atprotoClient->createRecord($postRecord);
        
        // If the post was successful we create a syndication entity
        if (isset($response->uri)) {
        	$this->logger->info("Ride posted to @uri",["@uri" => $response->uri]);
            $this->createSyndicationEntity((int) $node->id(), $response->uri);
        }
        return $response;
    }


   /**
     * Posts a blog entry to the public Bluesky timeline.
     *
     * lexicon app.bsky.feed.post
     *
     * This is executed by our PostBlogAction via ECA
     *
     */
    public function postBlogToTimeline(NodeInterface $node): mixed {
		// Not yet implemented
		// Currently using Bridgy and Bridgy Fed for this
		
		return FALSE;
	}


	/**
	 * Get webmentions
	 *
	 * Depends on the syndication and webmention entities
	 * provided by the indieweb_webmention module
	 *
	 * Iteerates the syndications and checks for likes, replies, and reposts
	 *
	 * This is executed by our CronAction via ECA
	 *
	 */
	public function getWebmentions(){

		 $syndications = $this->getSyndications();

		foreach ($syndications as $syndication) {
			if (!empty($syndication['at_uri'])) {
				$this->logger->info("Checking syndication of node @nid for webmentions.", [
					'@nid' => $syndication['nid']
				]);
				$this->checkForWebmentions($syndication);
			}
		}
	}

	/**
	 * See above
	 *
	 */
    public function checkForWebmentions(array $syndication): void {
        $atUri = $syndication['at_uri'];
        $node = $this->entityTypeManager->getStorage('node')->load($syndication['nid']);
        $target_path = $node->toUrl()->toString();

        $wmValues = [
            'source' => "https://bsky.app/profile/paullieberman.net/post/" . basename($atUri),
            'target' => $target_path,
            'type' => "entry",
        ];

        $response = $this->atprotoClient->getPostThread($atUri);

        if (isset($response->thread->post)) {
            $post = $response->thread->post;
            if ($post->replyCount > 0) { $this->processReplies($response->thread->replies, $wmValues); }
            if ($post->likeCount > 0) { $this->processLikes($atUri, $wmValues); }
           // if ($post->repostCount > 0) { $this->processReposts($atUri, $wmValues); }
        }
    }


	/**
	 * Get Syndications
	 *
	 * Depends on the syndication entity  provided by the indieweb_webmentions module,
	 * which we have extended to add tha at_uri field.
	 *
	 */
 	public function getSyndications(): array {
        $syndications = [];
        $storage = $this->entityTypeManager->getStorage('indieweb_syndication');
        $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
        foreach ($storage->loadMultiple($ids) as $synd) {
            $syndications[] = [
                'url' 	 => $synd->get('url')->value,
                'nid' 	 => $synd->get('entity_id')->value,
                'at_uri' => $synd->get('at_uri')->value,
            ];
        }
        return $syndications;
    }

	/**
	 * Process replies
	 *
	 * Saves a webmention entity for each reply
	 *
	 */
    private function processReplies(array $replies, array $wmValues): void {
        foreach ($replies as $reply) {
            $post = $reply->post;
            $author = $post->author;
            $source = "https://bsky.app/profile/{$author->handle}/post/" . basename($post->uri);

            $this->saveIfNew(array_merge($wmValues, [
                'source' 		=> $source,
                'property' 		=> 'in-reply-to',
                'author_name' 	=> $author->displayName ?: $author->handle,
                'author_url' 	=> "https://bsky.app/profile/{$author->handle}",
                'author_photo' 	=> $author->avatar ?? '',
                'content_text' 	=> $post->record->text,
                'status' => 1,
            ]));
        }
    }

	/**
	 * Process likes
	 *
	 * Saves a webmention entity for each like
	 *
	 */
    private function processLikes(string $atUri, array $wmValues): void {
        $response = $this->atprotoClient->getLikes($atUri);
        foreach ($response->likes as $like) {
            $source = "https://bsky.app/profile/{$like->actor->handle}";
            $this->saveIfNew(array_merge($wmValues, [
                'source' => $source,
                'property' => 'like-of',
                'author_name' => $like->actor->displayName ?: $like->actor->handle,
                'author_url' => $source,
                'author_photo' => $like->actor->avatar ?? '',
                'status' => 1,
            ]));
        }
    }

	/**
	 * Process reposts
	 *
	 * Saves a webmention entity for each repost
	 *
	 */	 
    private function processReposts(string $atUri, array $wmValues): void {
        $response = $this->atprotoClient->request('GET', "/xrpc/app.bsky.feed.getRepostedBy", ['query' => ['uri' => $atUri]]);
        foreach ($response->repostedBy as $actor) {
            $source = "https://bsky.app/profile/{$actor->handle}";
            $this->saveIfNew(array_merge($wmValues, [
                'source' => $source,
                'property' => 'repost-of',
                'author_name' => $actor->displayName ?: $actor->handle,
                'author_url' => $source,
                'author_photo' => $actor->avatar ?? '',
                'status' => 1,
            ]));
        }
    }

	/**
	 * Save if new
	 *
	 * Saves a webmention entity
	 *
	 */
    private function saveIfNew(array $values): void {
        $storage = $this->entityTypeManager->getStorage('indieweb_webmention');
        $existing = $storage->loadByProperties(['source' => $values['source'], 'target' => $values['target']]);
        if (empty($existing)) {
            $storage->create($values)->save();
        }
    }

	/**
	 * Create facets for tags
	 *
	 */
    private function createTagFacets(string $text, array $tags): array {
        $facets = [];
        foreach ($tags as $tag) {
            $search = '#' . $tag;
            $pos = strpos($text, $search);
            if ($pos !== FALSE) {
                $facets[] = [
                    'index' => ['byteStart' => $pos, 'byteEnd' => $pos + strlen($search)],
                    'features' => [['$type' => 'app.bsky.richtext.facet#tag', 'tag' => $tag]],
                ];
            }
        }
        return $facets;
    }

	/**
	 * Create the syndication entity
	 *
	 * Note: We have extended the syndication entity to include the at_uri field
	 */
    private function createSyndicationEntity(int $nid, string $atUri): void {
        $rkey = end(explode('/', $atUri));
        $url = "https://bsky.app/profile/paullieberman.net/post/{$rkey}";
        $this->entityTypeManager->getStorage('indieweb_syndication')->create([
            'entity_id' 	 => $nid,
            'entity_type_id' => 'node',
            'url' 			 => $url,
            'at_uri' 		 => $atUri,
        ])->save();
        $this->logger->info("Syndication saved for node @nid",["@nid" => $nid]);
    }

// end-of-class
}