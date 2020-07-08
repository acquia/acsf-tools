#!/bin/bash

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

function set_maintenance_mode {
  echo "Setting site maintenance_mode to $1."

  DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD sset system.maintenance_mode $1 -y
  maintenance_mode="$(DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD sget system.maintenance_mode)"
  echo "Site maintenance mode: $maintenance_mode"
}

function set_site_owner_maintenance_mode {
  echo "Setting site_owner_maintenance_mode to $1."

  DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD cset acsf.settings site_owner_maintenance_mode $1 -y
  site_owner_maintenance_mode="$(DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD cget acsf.settings site_owner_maintenance_mode -y)"
  echo "Site owner maintenance mode: $site_owner_maintenance_mode"
}

set_site_owner_maintenance_mode 0

# Fetch if import is pending.
CMD="$(DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD sget cohesion_import_pending  --format=json | grep cohesion_import_pending | grep -o '.$')"

# Ignore if it's not 1, ie when it's 0, empty or has any other value
if [ "$CMD" = "1" ]; then
  DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD dx8:import
fi

exitcode=$?
if [ $exitcode -ne 0 ]; then
  echo "Cohesion import failed. Website will remain in maintenance mode."
  # In certain scenarios ACSF can keep a site live even if db-update.sh results in an error.
  set_maintenance_mode 1
  set_site_owner_maintenance_mode 1

  exit $exitcode
else
  echo "Disabling cohesion_import_pending setting."
  DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD sset cohesion_import_pending 0 -y
fi

# Fetch if rebuild is pending.
CMD="$(DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD sget cohesion_rebuild_pending  --format=json | grep cohesion_rebuild_pending | grep -o '.$')"

# Ignore if it's not 1, ie when it's 0, empty or has any other value
if [ "$CMD" = "1" ]; then
  DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD dx8:rebuild --verbose
fi

exitcode=$?
if [ $exitcode -ne 0 ]; then
  echo "Execution of post deployment tasks failed. Website will remain in maintenance mode."
  # In certain scenarios ACSF can keep a site live even if db-update.sh results in an error.
  set_maintenance_mode 1
  set_site_owner_maintenance_mode 1

  exit $exitcode
else
  echo "Execution of post deployment tasks successful."
  set_maintenance_mode 0

  echo "Disabling cohesion_rebuild_pending setting."
  DRUSH_PATHS_CACHE_DIRECTORY=$cacheDir $DRUSH_CMD sset cohesion_rebuild_pending 0 -y
fi
