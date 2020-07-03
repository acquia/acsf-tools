# ACSF Tools

## Summary:

Background tasks is designed to ...

## Install and Configuration:

#### Install

Fist you are going to need to request the approval to use this in your account, and a piece of code that will allow you to do it. Contact PS or your TAM for that.

In particular you'll need acsf_large_scale_cron.rb


Import acsf_tools with background tasks:

```
composer config repositories.alex-moreno vcs https://github.com/alex-moreno/acsf-tools
composer require acquia/acsf-tools:dev-background-tasks-9.x-dev --no-update
composer update acquia/acsf-tools --no-dev
```



Add following to db-update.sh:

```
# ############# POST DEPLOYMENT TASKS RELATED ############################
docroot="/var/www/html/$sitegroup.$env/docroot"
DRUSH_CMD="drush9 --root=$docroot --uri=https://$domain"

# 1. Tell acsf to not remove maintenance mode
DRUSH_PATHS_CACHE_DIRECTORY="$cache_dir" $DRUSH_CMD cset acsf.settings site_owner_maintenance_mode TRUE

# 2. Create flag file
DRUSH_PATHS_CACHE_DIRECTORY="$cache_dir" $DRUSH_CMD acsf-tools:set-background-tasks-pending
# ############# END POST DEPLOYMENT TASKS RELATED #########################
```


Copy files (first 3 should be moved to acsf-tools):
```
scripts/acsf_large_scale_cron.rb
scripts/post-deployment-error-tasks-cron.sh
scripts/post-deployment-tasks-cron.sh
scripts/post-deployment.sh
```

