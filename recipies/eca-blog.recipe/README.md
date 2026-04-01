## Recipe: Blog Posts

ID: blog_posts

Do stuff on blog post events

### Installation

```shell
## Import recipe
composer require drupal/blog_posts

# Apply recipe with Drush (requires version 13 or later):
drush recipe ../recipes/blog_posts

# Apply recipe without Drush:
cd web && php core/scripts/drupal recipe ../recipes/blog_posts

# Rebuilding caches is optional, sometimes required:
drush cr
```