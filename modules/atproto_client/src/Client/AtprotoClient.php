<?php

declare(strict_types=1);

namespace Drupal\atproto_client\Client;

use Drupal\atproto_core\AtprotoLoggerTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;
use Drupal\atproto_client\Endpoints;


/**
 * The AtprotoClient class.
 */
class AtprotoClient {

	use AtprotoLoggerTrait;
	
    protected ImmutableConfig $settings;
    protected ?string $did;
    protected ?string $handle;
    protected ?object $session = NULL;
    protected $tempstore;
    protected string $pdsUrl;

    /**
     * Constructs a new AtprotoClient object.
     *
     */
    public function __construct(
        protected LoggerChannelFactoryInterface $loggerFactory,
        protected ConfigFactoryInterface $configFactory,
        protected KeyRepositoryInterface $keyRepository,
        protected ClientInterface $httpClient,
        PrivateTempStoreFactory $tempStore,
        protected EndPoints $endpoints
    ) {
        $this->tempstore = $tempStore->get('atproto_client');
		$this->setLoggerFactory($loggerFactory);
		
        // Use the Factory to get our immutable settings.
        $this->settings = $this->configFactory->get('atproto.settings');

		$this->pdsUrl = "https://" . $this->settings->get('pds');
        $this->handle = $this->settings->get('handle');
        $this->did    = $this->settings->get('did');

        // Lazy-resolve DID if missing.
        if (empty($this->did) && !empty($this->handle)) {
            $this->did = $this->getDidForHandle($this->handle);
            if ($this->did) {
                $this->saveDid($this->did);
            }
        }
    }

    /**
     * Gets the session.
     *
     * @return object|false
     *   The session object or FALSE on failure.
     */
    protected function getSession(): object|false {
        if ($this->session) {
            return $this->session;
        }

        $appKey = $this->settings->get('app_key');
        if (empty($this->handle) || empty($appKey)) {
            return FALSE;
        }

        $session = $this->tempstore->get('session');

        if ($session) {
            $this->session = $this->refreshSession($session);
        }

        if (!$this->session) {
            $key = $this->keyRepository->getKey($appKey)->getKeyValue();
            $this->session = $this->createSession($this->handle, $key);
        }

        $this->tempstore->set('session', $this->session);
        return $this->session;
    }

    /**
     * Make an authenticated call to the PDS.
     *
     * @param string $method
     *   The HTTP method.
     * @param string $endpoint
     *   The API endpoint.
     * @param array $options
     *   (optional) An array of request options.
     *
     * @return object|false
     *   The decoded JSON response or FALSE on failure.
     */
    public function request(string $method, string $endpoint, array $options = []): object|false {
        $session = $this->getSession();

        if (!$session) {
            $this->logger()->error("Could not establish a session for $endpoint");
            return FALSE;
        }

        $default_options = [
            'timeout' => 5, // Total request time.
            'connect_timeout' => 2, // Time to establish connection.
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Bearer " . $session->accessJwt,
            ],
        ];

        $options = array_merge_recursive($default_options, $options);

        try {
            $response = $this->httpClient->request($method, $this->pdsUrl . $endpoint, $options);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents());
            }
        }
        catch (\Exception $e) {
            $this->logger()->error("atproto request to $endpoint failed: " . $e->getMessage());
        }

        return FALSE;
    }

    /**
     * Gets the handle.
     *
     * @return string|null
     *   The handle.
     */
    public function getHandle(): ?string {
        return $this->handle;
    }

    /**
     * Gets the DID.
     *
     * @return string|null
     *   The DID.
     */
    public function getDid(): ?string {
        return $this->did;
    }

    /**
     * Logout.
     *
     * Delete the saved session.
     */
    public function logout(): void {
        $this->tempstore->delete('session');
        $this->logger()->info("Session closed");
    }

    /*************** Private functions *******************/

    /**
     * Saves the DID to configuration.
     *
     * @param string $did
     *   The DID to save.
     */
    private function saveDid(string $did): void {
        $this->configFactory->getEditable('atproto_client.settings')
            ->set('did', $did)
            ->save();
    }

    /**
     * Create authenicated session.
     *
     * Returns session data array.
     *   did => string
     *   didDoc => array
     *   handle => string
     *   email => string
     *   emailConfirmed
     *   emailAuthFactor
     *   accessJwt => string
     *   refreshJwt => string
     *   active => boolean true
     *
     * @param string $user
     *   The username.
     * @param string $pass
     *   The password.
     *
     * @return object|false
     *   The session object or FALSE on failure.
     */
    private function createSession(string $user, string $pass): object|false {
        $request = $this->httpClient->post(
            $this->pdsUrl . $this->endpoints->createSession(),
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => json_encode(['identifier' => $user, 'password' => $pass]),
            ]
        );

        if ($request->getStatusCode() == 200) {
            $this->logger()->info("Session opened");
            return json_decode($request->getBody()->getContents());
        }
        $this->logger()->error("Create session got " . $request->getStatusCode());
        return FALSE;
    }

    /**
     * Refreshes the session.
     *
     * @param object $session
     *   The session object.
     *
     * @return object|false
     *   The refreshed session object or FALSE on failure.
     */
    private function refreshSession(object $session): object|false {
        $request = $this->httpClient->post(
            $this->pdsUrl . $this->endpoints->refreshSession(),
            [
                'headers' => [
                    'Authorization' => "Bearer " . $session->refreshJwt,
                ],
            ]
        );

        if ($request->getStatusCode() == 200) {
            $this->logger()->info("Session refreshed");
            return json_decode($request->getBody()->getContents());
        }
        $this->logger()->error("Refresh session got " . $request->getStatusCode());
        return FALSE;
    }

    /**
     * Gets DID for Handle.
     *
     * @param string $handle
     *   The handle.
     *
     * @return string|false
     *   The DID or FALSE on failure.
     */
    public function getDidForHandle(string $handle): string|false {
        if (empty($handle)) {
            return FALSE;
        }
        // This is public, so we can do it before have a session.  
        $request = $this->httpClient->request(
            'GET',
             $this->pdsUrl . "/xrpc/com.atproto.identity.resolveHandle",
             [ 
                'query' => [
                    'handle' => $handle,
                ],
            ]
        );

        if ($request->getStatusCode() == 200) {
            $data = json_decode($request->getBody()->getContents());
            return $data->did;
        }
        return FALSE;
    }

    /**
     * Gets PDS for DID.
     *
     * Uses plc.directory.
     *
     * @param string $did
     *   The DID.
     *
     * @return string|false
     *   The PDS URL or FALSE on failure.
     */
    private function getPds(string $did): string|false {
        $request = $this->httpClient->request('GET', "https://plc.directory/" . $did);
        if ($request->getStatusCode() == 200) {
            $results = json_decode($request->getBody()->getContents());
            return $results->service[0]->serviceEndpoint;
        }
        return FALSE;
    }



    /** ***** Wrapper functions for requests *******  **/

 	/**
     * shorthand for com.atproto.repo.listRecords (GET)
     */
    public function listRecords(array $params): mixed {
		try { 	
       		$response = $this->request('GET', $this->endpoints->listRecords(), [
            	'query' => $params,
        	]);
        	return $response;
        }
        catch(\Exception $e) {
        	return FALSE;
        }
        
    }

	/**
     * shorthand for com.atproto.repo.getRecord (GET)
     */
    public function getRecord(array $params): mixed {
		try { 	
       		$response = $this->request('GET', $this->endpoints->getRecord(), [
            	'query' => $params,
        	]);
        	return $response;
        }
        catch(\Exception $e) {
        	return FALSE;
        }
        
    }




	/**
     * shorthand for com.atproto.repo.putRecord (Usually POST)
     */
    public function putRecord(array $params): mixed {

    	try{
	        return $this->request('POST', $this->endpoints->putRecord(), [
    	        'json' => $params,
        	]);
        }catch (\Exception $e) { 
			$this->logger()->error("PutRecord failed with @err", ["@err" => $e]);
			return FALSE;
        }
    }

    /**
     * shorthand for com.atproto.repo.createRecord (POST)
     */
    public function createRecord(array $params): mixed {
        return $this->request('POST', $this->endpoints->createRecord(), [
            'json' => $params,
        ]);
    }

   
    /**
     * shorthand for com.atproto.repo.deleteRecord (POST)
     */
    public function deleteRecord(array $params): bool {
        try {
            $this->request('POST', $this->endpoints->deleteRecord(), [
                'json' => $params,
            ]);
            return TRUE;
        } catch (\Exception $e) {
            return FALSE;
        }
    }

    /**
     * shorthand for app.bsky.feed.getPostThread (GET)
     */
    public function getPostThread(string $uri, int $depth = 1): mixed {
        return $this->request('GET', $this->endpoints->getPostThread(), [
            'query' => ['uri' => $uri, 'depth' => $depth],
        ]);
    }

    /**
     * shorthand for app.bsky.feed.getLikes (GET)
     */
    public function getLikes(string $uri, int $limit = 50): mixed {
        return $this->request('GET', $this->endpoints->getLikes(), [
            'query' => ['uri' => $uri, 'limit' => $limit],
        ]);
    }
    
    
    public function getProfile(array $params): mixed {
	    return $this->request('GET', $this->endpoints->getProfile(), [
            'query' => $params,
    	]);
    }
    
    public function getFollowers(array $params): mixed {
    	return $this->request('GET', $this->endpoints->getfollowers(), [
            'query' => $params,
    	]);
    
    }
    
	public function getFollows(array $params): mixed {
    	return $this->request('GET', $this->endpoints->getfollows(), [
            'query' => $params,
    	]);
    
    }
    
    public function getTimeline(array $params): mixed {
    	return $this->request('GET', $this->endpoints->getTimeline(), [
            'query' => $params,
    	]);
    
    }
    
    
     public function getAuthorFeed(array $params): mixed {
    	return $this->request('GET', $this->endpoints->getAuthorFeed(), [
            'query' => $params,
    	]);
    
    }
    
    public function searchPosts(array $params): mixed {
    	return $this->request('GET', $this->endpoints->searchPosts(), [
            'query' => $params,
    	]);
    
    }
    
    
    
    // End of class
}

