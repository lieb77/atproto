## Recipe: Cron

ID: cron

Do stuff on cron events

### Installation

```shell
## Import recipe
composer require drupal/cron

# Apply recipe with Drush (requires version 13 or later):
drush recipe ../recipes/cron

# Apply recipe without Drush:
cd web && php core/scripts/drupal recipe ../recipes/cron

# Rebuilding caches is optional, sometimes required:
drush cr
```