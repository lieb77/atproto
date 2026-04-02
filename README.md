# ATproto suite for Drupal

This suite of modules provides services for integrating Drupal content with the AT Protocol, used by Bluesky and other sites. 
The submodules are:

## ATproto Client atproto_client
- Provides the XRPC calls over http_client.
- Used by all the other modules.

## ATproto Bluesky atproto_bsky
- Posts Drupal content as app.bsky.feed.post records
- These will show up in the Bluesky app

## ATproto Standard Site atproto_standard_site
- Posts Drupal content as sites.standard.document records
- These will show up on leaflet.pub and other sites that support this lexicon

## ATproto Paullieberman atproto_paullieberman
- Posts Drupal content as my custom net.paullieberman.bike.ride records
- Provided as an example of using a custom lexicon

## ATproto Dashboard atproto_dashboard
- View and manage the atproto records in your repo

# Dependencies
- ECA - The modules provide Action Plugins for use with ECA. The module includes recipes for creating the ECA models.
- Indieweb Webmention indieweb_webmention . This module provides the syndication and webmention entities used by these modules.
- Drupal core ^11.3 although I'm sure most of this will work on earlier versions.

# NOTE: this is Alpha code. Use at your own risk.

