# ACSF Tools

## Summary:

Background tasks is designed to ...

## Install and Configuration:

#### Install

0. Fist you are going to need to request the approval to use this in your own ACSF account, and a piece of code that will allow you to do it. Contact PS or your TAM for that.

In particular you'll need acsf_large_scale_cron.rb


1. Import acsf_tools with background tasks:

```
composer config repositories.alex-moreno vcs https://github.com/alex-moreno/acsf-tools
composer require acquia/acsf-tools:dev-background-tasks-9.x-dev --no-update
composer update acquia/acsf-tools --no-dev
```

or apply the patch

```
https://github.com/acquia/acsf-tools/pull/83.patch
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


Copy files:
```
scripts/acsf_large_scale_cron.rb
scripts/post-deployment.sh
```

TODO: job tasks here

Create jobs pointing to this script:

TODO -> Alex: post-deployment-tasks-cron.sh -> background-tasks-cron.sh
scripts/post-deployment.sh -> background-tasks.sh

```
scripts/post-deployment-tasks-cron.sh
```


Add email:
```
 email_logs_from and email_logs_to 
``` 
 to this file acsf_tools_config.default.yml. And put it in the same folder as your secrets file.