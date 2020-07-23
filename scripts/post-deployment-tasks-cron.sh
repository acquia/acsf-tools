#!/bin/bash

# First parameter should specify number of seconds to sleep before starting
# Timeout allows to desynchronize multiple cron jobs
ARG1=$1
# Default to no timeout
TIMEOUT=${ARG1:=0}

# Second parameter should specify how many sites to process concurrently
ARG2=$2
# Defaults to 10
CONCURRENCY=${ARG2:=10}

# Thir parameter should specify how many seconds a single site processing should teak
ARG3=$3
# Defaults to 3600 seconds = 60 minutes
SINGLE_SITE_TTL=${ARG3:=3600}

# Reprocess
ARG4=$4
# Defaults to default queue
QUEUE_TYPE=${ARG4:=default}

# Temporary folder where flags are stored for ACSF post deployment tasks.
FLAGSFOLDER="/mnt/gfs/${AH_SITE_GROUP}.${AH_SITE_ENVIRONMENT}/flags"

SITES_JSON="/var/www/site-php/${AH_SITE_GROUP}.${AH_SITE_ENVIRONMENT}/multisite-config.json"

SITE_COUNT=$(grep -oP 'acsf_site_id\":\d+' ${SITES_JSON} | grep -oP '\d+' | sort | uniq | wc -l)

ALL_SITES_TTL=$((SITE_COUNT * SINGLE_SITE_TTL / CONCURRENCY))
COMMAND="acsf-tools:run-background-tasks --timeout ${SINGLE_SITE_TTL} --queue ${QUEUE_TYPE}"
DRUSH_COMMAND="drush9"

sleep $TIMEOUT

# If flags folder is empty, ignore
if [ "$(ls -A $FLAGSFOLDER)" ]; then

  START_TIME=$(date +%s)

  ruby /var/www/html/${AH_SITE_NAME}/scripts/acsf_large_scale_cron.rb \
    --concurrency=${CONCURRENCY} \
    --site-cron-ttl=${SINGLE_SITE_TTL} \
    --max-runtime=${ALL_SITES_TTL} \
    --sitegroup=${AH_SITE_GROUP} \
    --environment=${AH_SITE_ENVIRONMENT} \
    --drush=${DRUSH_COMMAND} \
    --domain-filter="preferred" \
    --cron-command="${COMMAND}"

  END_TIME=$(date +%s)
  TOTAL_TIME=$((END_TIME - START_TIME))

  echo "Execution of \"${DRUSH_COMMAND} ${COMMAND}\" on ${SITE_COUNT} sites took: ${TOTAL_TIME}" seconds

else
  echo "$FLAGSFOLDER is empty, no need of any actions"
fi
