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

2. Add following to db-update.sh:

```
# ############# POST DEPLOYMENT TASKS RELATED ############################
docroot="/var/www/html/$sitegroup.$env/docroot"
DRUSH_CMD="drush9 --root=$docroot --uri=https://$domain"

# 1. Tell acsf to not remove maintenance mode
DRUSH_PATHS_CACHE_DIRECTORY="$cache_dir" $DRUSH_CMD cset acsf.settings site_owner_maintenance_mode TRUE

# 2. Create flag file
DRUSH_PATHS_CACHE_DIRECTORY="$cache_dir" $DRUSH_CMD acsf-tools:set-background-tasks-pending

# RUN THIS ON EVERYTHING BUT PROD AND ACQINT (as they are the only ones having cron server for now)
if [ "$env" != "01live" ] && [ "$env" != "01acqint" ]; then
  $docroot/../scripts/post-deployment.sh $sitegroup $env $db_role $uri
fi
# ############# END POST DEPLOYMENT TASKS RELATED #########################
```

3. Copy files (if not already on place):

```
scripts/acsf_large_scale_cron.rb
scripts/background-tasks.sh
```

(As mentioned you'll need to get acsf_large_scale_cron.rb from your representative at Acquia).

Create jobs pointing to the postdeployment script (post-deployment-tasks-cron.sh). For example, 6 jobs would look like this:

```
# Job 1
flock -xn /tmp/bayerwsf_acqint_hsc_1.lck -c "/var/www/html/bayerwsf.01acqint/scripts/post-deployment-tasks-cron.sh 0 3"
# Job 2
flock -xn /tmp/bayerwsf_acqint_hsc_2.lck -c "/var/www/html/bayerwsf.01acqint/scripts/post-deployment-tasks-cron.sh 9 3"
# Job 3
flock -xn /tmp/bayerwsf_acqint_hsc_3.lck -c "/var/www/html/bayerwsf.01acqint/scripts/post-deployment-tasks-cron.sh 18 3"
# Job 4
flock -xn /tmp/bayerwsf_acqint_hsc_4.lck -c "/var/www/html/bayerwsf.01acqint/scripts/post-deployment-tasks-cron.sh 27 3"
# Job 5
flock -xn /tmp/bayerwsf_acqint_hsc_5.lck -c "/var/www/html/bayerwsf.01acqint/scripts/post-deployment-tasks-cron.sh 36 3"
# Job 6
flock -xn /tmp/bayerwsf_acqint_hsc_6.lck -c "/var/www/html/bayerwsf.01acqint/scripts/post-deployment-tasks-cron.sh 45 3"

```

Optional. Add an error queue jobs:
 
 ```
flock -xn /tmp/bayerwsf_acqint_hsc_6.lck -c "/var/www/html/bayerwsf.01acqint/scripts/post-deployment-tasks-cron.sh 45 3 errors"
```

Add email to this file acsf_tools_config.default.yml. And put it in the same folder as your secrets file.

``` email_logs_from  ``` 

and

 ```email_logs_to```
 
 

