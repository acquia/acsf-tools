# ACSF Tools

## Summary:

Background tasks are designed to execution long running drush commands in the
background by using multiple concurrent cron jobs. One use case is to offload
long running "light" tasks from deployment to post deployment to achieve higher
concurrency.

## Installation and Configuration:

#### Install

0. Fist you are going to need to request the approval to use this in your own ACSF account, and a piece of code that will allow you to do it. Contact PS or your TAM for that.

In particular you'll need acsf_large_scale_cron.rb


1. Import acsf_tools with background tasks:

```
composer require acquia/acsf-tools:dev-background-tasks-10.x-dev --no-update
composer update acquia/acsf-tools --no-dev
```

or apply the patch

```
https://github.com/acquia/acsf-tools/pull/83.patch
```

2. Add following to db-update.sh after database is updated (eg, after drush updb):

```
# ############# POST DEPLOYMENT TASKS RELATED ############################
# Custom argument. Currently used for triggering usage of post deployment tasks.
# To trigger post deployment tasks execution using cron jobs, pass "usecronbackgroundtasks"
custom_argument="$5"

docroot="/var/www/html/$sitegroup.$env/docroot"
DRUSH_CMD="drush9 --root=$docroot --uri=https://$domain"

# Fetch if post deployment tasks are pending.
CMD1="$(DRUSH_PATHS_CACHE_DIRECTORY="$cache_dir" $DRUSH_CMD sget cohesion_import_pending  --format=json | grep cohesion_import_pending | grep -o '.$')"
CMD2="$(DRUSH_PATHS_CACHE_DIRECTORY="$cache_dir" $DRUSH_CMD sget cohesion_rebuild_pending  --format=json | grep cohesion_rebuild_pending | grep -o '.$')"

# Ignore if it's not 1, ie when it's 0, empty or has any other value
# If any post deployment tasks are pending
if [ "$CMD1" = "1" ] || [ "$CMD2" = "1" ]; then
  echo "Post deployment tasks pending."

  # If we are on an environment with no cron server, run the tasks immediately
  if [ "$env" != "01live" ] && [ "$env" != "01acqint" ] && [ "$custom_argument" != "usecronbackgroundtasks" ]; then
    echo "Executing post deployment tasks."
    $docroot/../scripts/background-tasks.sh $sitegroup $env $db_role $uri

    exitcode=$?
    if [ $exitcode -ne 0 ]; then
        exit $exitcode
    fi
  else
    # If the environment has dedicated cron servers, mark the site with tasks cohesion_import_pending
    # Tasks will be processed by cron running scripts/post-deployment-tasks-cron.sh

    # 1. Tell acsf to not remove maintenance mode
    echo "Enabling site_owner_maintenance_mode to keep the site in maintenance mode"
    DRUSH_PATHS_CACHE_DIRECTORY="$cache_dir" $DRUSH_CMD cset acsf.settings site_owner_maintenance_mode TRUE -y
    # 2. Create flag file
    echo "Marking site with pending background tasks."
    DRUSH_PATHS_CACHE_DIRECTORY="$cache_dir" $DRUSH_CMD acsf-tools:set-background-tasks-pending
  fi
else
  echo "No post deployment tasks pending."
fi

# ############# END POST DEPLOYMENT TASKS RELATED #########################
```

3. Copy files (if not already on place):

```
scripts/acsf_large_scale_cron.rb
scripts/background-tasks.sh
scripts/post-deployment-tasks-cron.sh
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

Optional. Add an error queue job. It will only process jobs which have failed once:

 ```
flock -xn /tmp/bayerwsf_acqint_hsc_error_1.lck -c "/var/www/html/bayerwsf.01acqint/scripts/post-deployment-tasks-cron.sh 0 1 error"
```

Add email to this file acsf_tools_config.default.yml. And put it in the same folder as your secrets file. Change file name to acsf_tools_config.yml

``` email_logs_from  ```

and

 ```email_logs_to```
