<?php

declare(strict_types=1);

namespace Drupal\atproto_client;

/**
 * Returns endpoints for each function.
 */
class EndPoints
{

    /**
     * Returns the endpoint for creating a session.
     *
     * @return string
     *   The endpoint URL.
     */
    public function createSession(): string
    {
        return '/xrpc/com.atproto.server.createSession';
    }

    /**
     * Returns the endpoint for refreshing a session.
     *
     * @return string
     *   The endpoint URL.
     */
    public function refreshSession(): string
    {
        return '/xrpc/com.atproto.server.refreshSession';
    }

    /**
     * Returns the endpoint for reading a specific record by its rkey.
     *
     * @return string
     *   The endpoint URL.
     */
    public function getRecord(): string
    {
        return '/xrpc/com.atproto.repo.getRecord';
    }

    /**
     * Returns the endpoint for listing all records in a collection.
     *
     * @return string
     *   The endpoint URL.
     */
    public function listRecords(): string
    {
        return '/xrpc/com.atproto.repo.listRecords';
    }

    /**
     * Returns the endpoint for creating a new record.
     *
     * @return string
     *   The endpoint URL.
     */
    public function createRecord(): string
    {
        return '/xrpc/com.atproto.repo.createRecord';
    }

    /**
     * Returns the endpoint for updating an existing record or creating it if missing.
     * Best for keeping Drupal edits in sync with the PDS.
     *
     * @return string
     *   The endpoint URL.
     */
    public function putRecord(): string
    {
        return '/xrpc/com.atproto.repo.putRecord';
    }

    /**
     * Returns the endpoint for deleting a record.
     *
     * @return string
     *   The endpoint URL.
     */
    public function deleteRecord(): string
    {
        return '/xrpc/com.atproto.repo.deleteRecord';
    }

    /**
     * Returns the endpoint for getting a post thread.
     *
     * @return string
     *   The endpoint URL.
     */
    public function getPostThread(): string
    {
        return '/xrpc/app.bsky.feed.getPostThread';
    }

    /**
     * Returns the endpoint for getting likes.
     *
     * @return string
     *   The endpoint URL.
     */
    public function getLikes(): string
    {
        return '/xrpc/app.bsky.feed.getLikes';
    }

    /**
     * Returns the endpoint for getting quotes.
     *
     * @return string
     *   The endpoint URL.
     */
    public function getQuotes(): string
    {
        return '/xrpc/app.bsky.feed.getQuotes';
    }

    /**
     * Returns the endpoint for getting reposts.
     *
     * @return string
     *   The endpoint URL.
     */
    public function getRepostedBy(): string
    {
        return '/xrpc/app.bsky.feed.getRepostedBy';
    }
    
    public function getProfile()
    {
        return '/xrpc/app.bsky.actor.getProfile';
    }

    public function getFeed()
    {
        return '/xrpc/app.bsky.feed.getFeed';
    }

    public function getPosts()
    {
        return '/xrpc/app.bsky.feed.getPosts';
    }

    public function getTimeline()
    {
        return '/xrpc/app.bsky.feed.getTimeline';
    }

  
    public function searchPosts()
    {
        return '/xrpc/app.bsky.feed.searchPosts';
    }

   
    public function getFollowers()
    {
        return '/xrpc/app.bsky.graph.getFollowers';
    }

    public function getFollows()
    {
        return '/xrpc/app.bsky.graph.getFollows';
    }

    public function getAuthorFeed()
    {
        return '/xrpc/app.bsky.feed.getAuthorFeed';
    }

}
