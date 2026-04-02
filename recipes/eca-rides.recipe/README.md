## Recipe: Ride posts

ID: ride_posts

Do stuff on ride post actions

### Installation

```shell
## Import recipe
composer require drupal/ride_posts

# Apply recipe with Drush (requires version 13 or later):
drush recipe ../recipes/ride_posts

# Apply recipe without Drush:
cd web && php core/scripts/drupal recipe ../recipes/ride_posts

# Rebuilding caches is optional, sometimes required:
drush cr
```