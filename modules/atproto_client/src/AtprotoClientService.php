<?php

declare(strict_types=1);

namespace Drupal\atproto_client;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

use Drupal\atproto_client\Client\AtprotoClient;
use Drupal\atproto_client\Endpoints;
use Drupal\atproto\AtprotoLoggerTrait;


/**
 * The AtprotoClient class.
 */
class AtprotoClientService {

	use AtprotoLoggerTrait;
	
	public function __construct(
        protected LoggerChannelFactoryInterface $loggerFactory,
        protected EndPoints $endpoints,
        protected AtprotoClient $atprotoClient,
    ) {
    	$this->setLoggerFactory($loggerFactory);
	}
	
	
	/**
	 * Pass the DID through from the client
	 *
	 */
	public function getDid() {
		return $this->atprotoClient->getDid();
	}
	    
 	/**
     * shorthand for com.atproto.repo.listRecords (GET)
     */
    public function listRecords(array $params): mixed {
		try { 	
       		$response = $this->atprotoClient->request('GET', $this->endpoints->listRecords(), [
            	'query' => $params,
        	]);
        	return $response;
        }
        catch(\Throwable $e) {
        	$this->logger()->error("List records got @err", ["@err" => $e->getMessage()]);
        	return FALSE;
        }
        
    }

	/**
     * shorthand for com.atproto.repo.getRecord (GET)
     */
    public function getRecord(array $params): mixed {
		try { 	
       		$response = $this->atprotoClient->request('GET', $this->endpoints->getRecord(), [
            	'query' => $params,
        	]);
        	return $response;
        }
        catch(\Throwable $e) {
           	$this->logger()->error("Get record  got @err", ["@err" => $e->getMessage()]);
        	return FALSE;
        }
        
    }


	/**
     * shorthand for com.atproto.repo.putRecord (Usually POST)
     */
    public function putRecord(array $params): mixed {

    	try{
	        return $this->atprotoClient->request('POST', $this->endpoints->putRecord(), [
    	        'json' => $params,
        	]);
        }catch (\Throwable $e) { 
			$this->logger()->error("PutRecord failed with @err", ["@err" => $e->getMessage()]);
			return FALSE;
        }
    }


    /**
     * shorthand for com.atproto.repo.createRecord (POST)
     */
    public function createRecord(array $params): mixed {
    	try {
			return $this->atprotoClient->request('POST', $this->endpoints->createRecord(), [
				'json' => $params,
			]);
		}
		catch (\Throwable $e) { 
			$this->logger()->error("Create Record failed with @err", ["@err" => $e->getMessage()]);
			return FALSE;
        }
    }

   
    /**
     * shorthand for com.atproto.repo.deleteRecord (POST)
     */
    public function deleteRecord(array $params): bool {
        try {
            $this->atprotoClient->request('POST', $this->endpoints->deleteRecord(), [
                'json' => $params,
            ]);
            return TRUE;
        } catch (\Throwable $e) {
			$this->logger()->error("Delete Record failed with @err", ["@err" => $e->getMessage()]);
            return FALSE;
        }
    }

    /**
     * shorthand for app.bsky.feed.getPostThread (GET)
     */
    public function getPostThread(string $uri, int $depth = 1): mixed {
    
    	try {
			return $this->atprotoClient->request('GET', $this->endpoints->getPostThread(), [
				'query' => ['uri' => $uri, 'depth' => $depth],
			]);
		}
		catch (\Throwable $e) {
			$this->logger()->error("Get Post Thread failed with @err", ["@err" => $e->getMessage()]);
			return FALSE;
		}
    }

    /**
     * shorthand for app.bsky.feed.getLikes (GET)
     */
    public function getLikes(string $uri, int $limit = 50): mixed {
    	try {
			return $this->atprotoClient->request('GET', $this->endpoints->getLikes(), [
				'query' => ['uri' => $uri, 'limit' => $limit],
			]);
		}
		catch (\Throwable $e) {
			$this->logger()->error("Get Likes failed with @err", ["@err" => $e->getMessage()]);
			return FALSE;
		}
			
    }
    
    
    public function getProfile(array $params): mixed {
    	try {
			return $this->atprotoClient->request('GET', $this->endpoints->getProfile(), [
				'query' => $params,
			]);
		}
		catch (\Throwable $e) {
			$this->logger()->error("Get Profile failed with @err", ["@err" => $e->getMessage()]);
			return FALSE;
		}

    }
    
    public function getFollowers(array $params): mixed {
    	try {
			return $this->atprotoClient->request('GET', $this->endpoints->getfollowers(), [
				'query' => $params,
			]);
		}
		catch (\Throwable $e) {
			$this->logger()->error("Get Followers failed with @err", ["@err" => $e->getMessage()]);
			return FALSE;
		}
    
    }
    
	public function getFollows(array $params): mixed {
		try {
			return $this->atprotoClient->request('GET', $this->endpoints->getfollows(), [
				'query' => $params,
			]);
		}
		catch (\Throwable $e) {
			$this->logger()->error("Get Follows failed with @err", ["@err" => $e->getMessage()]);
			return FALSE;
		}
    
    }
    
    public function getTimeline(array $params): mixed {
    	try {
			return $this->atprotoClient->request('GET', $this->endpoints->getTimeline(), [
				'query' => $params,
			]);
		}
		catch (\Throwable $e) {
			$this->logger()->error("Get Timeline failed with @err", ["@err" => $e->getMessage()]);
			return FALSE;
		}
    
    }
    
    
     public function getAuthorFeed(array $params): mixed {
     	try {
			return $this->atprotoClient->request('GET', $this->endpoints->getAuthorFeed(), [
				'query' => $params,
			]);
		}
		catch (\Throwable $e) {
			$this->logger()->error("Get Author Feed failed with @err", ["@err" => $e->getMessage()]);
			return FALSE;
		}
    
    }
    
    public function searchPosts(array $params): mixed {
    	try {
			return $this->atprotoClient->request('GET', $this->endpoints->searchPosts(), [
				'query' => $params,
			]);
		}
		catch (\Throwable $e) {
			$this->logger()->error("Seqarch Posts failed with @err", ["@err" => $e->getMessage()]);
			return FALSE;
		}
    
    }

// end-of-class
} 