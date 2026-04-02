# ATproto suite for Drupal

This suite of modules provides services for integrating Drupal content with the AT Protocol, used by Bluesky and other sites. 
The submodules are:

## ATproto Client atproto_client
- Provides the XRPC calls over http_client.
- Used by all the other modules.

## ATproto Bluesky atproto_bsky
- Posts Drupal content as app.bsky.feed.post records
- These will show up in the Bluesky app

## ATProto Standard Site atproto_standard_site
- Posts Drupal content as sites.standard.document records
- These will show up on leaflet.pub and other sites that support this lexicon

