#!/bin/sh

# Acquia hosting site / environment names
site="$1"
env="$2"
# database role. (Not expected to be needed in most hook scripts.)
db_role="$3"
# The public domain name of the website.
uri="$4"

# The websites' document root
docroot="/var/www/html/$site.$env/docroot"

# Create and set Drush cache to unique local temporary storage per site.
# This approach isolates drush processes to completely avoid race conditions
# that persist after initial attempts at addressing in BLT: https://github.com/acquia/blt/pull/2922
cacheDir=`/usr/bin/env php /mnt/www/html/$site.$env/vendor/acquia/blt/scripts/blt/drush/cache.php $site $env $uri`
# Print to cloud task log.
echo "Generated temporary drush cache directory: $cacheDir."

DRUSH_CMD="drush9 --root=$docroot --uri=$uri"

# TODO: REMOVE ONCE GOOD FOR PROD. SETTING VARIABLE SO WE ENSURE WE REBUILD.
DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD sset cohesion_rebuild_pending 1
DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD sset cohesion_import_pending 1
# TODO: REMOVE ONCE GOOD FOR PROD. SETTING VARIABLE.

# Fetch if import is pending.
CMD="$(DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD sget cohesion_import_pending  --format=json | grep cohesion_import_pending | grep -o '.$')"

# Ignore if it's not 1, ie when it's 0, empty or has any other value
if [ "$CMD" = "1" ]; then
  DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD dx8:import
fi

# Fetch if rebuild is pending.
CMD="$(DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD sget cohesion_rebuild_pending  --format=json | grep cohesion_rebuild_pending | grep -o '.$')"

# Ignore if it's not 1, ie when it's 0, empty or has any other value
if [ "$CMD" = "1" ]; then
  DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD dx8:rebuild --verbose
fi

# @TODO check if this is sufficient error checking 
if [ $? -eq 0 ]; then
  DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD cset acsf.settings site_owner_maintenance_mode FALSE
  DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD sset system.maintenance_mode FALSE

  echo "Execution of post deployment tasks successful. Disabling maintenance mode."
else
  echo "Execution of post deployment tasks failed. Website will remain in maintenance mode."
  exit 1
fi
