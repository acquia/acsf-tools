<?php

/**
 * Implements hook_drush_command().
 */
function acsf_tools_content_staging_deploy_drush_command() {
  $items = array();

  // TODO: Add Groups argument support.

  $items['acsf-tools-content-staging-deploy'] = array(
    'description' => dt('A command line utility for starting a Factory content staging deploy.'),
    'arguments' => array(
      'env' => 'The target environment you are staging content to.',
      'sites' => 'A comma-delimited list of site aliases you wish to stage. Pass \'all\' to stage all sites.'
    ),
    'required-arguments' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'examples' => array(
      'Single site' => 'drush @mysite.local acsf-content-staging-deploy dev sitename',
      'Multiple sites' => 'drush @mysite.local acsf-content-staging-deploy dev sitename1,sitename2',
      'All sites' => 'drush @mysite.local acsf-content-staging-deploy dev all'
    ),
    'aliases' => ['sfdp'],
  );

  return $items;
}

function drush_acsf_tools_content_staging_deploy($env, $sites) {

  // Bail if an invalid staging environment.
  if (!in_array($env, array('dev','test'))) {
    return drush_set_error(dt('Invalid staging environment.'));
  }

  // Ask/warn user about staging all sites.
  $stage_all_sites = FALSE;
  if ($sites == 'all') {
    $warning = 'Are you sure you want to stage ALL sites? **WARNING: Staging all sites in a factory can take a very long time.';
    if (!drush_confirm(dt($warning))) {
      return drush_set_error(dt('Ok, exiting.'));
    }
    $stage_all_sites = TRUE;
  }

  $config = acsf_tools_get_rest_config();

  // Get a list of sites in the prod factory.
  $prod_sites = get_factory_sites($config, 'prod');

  // Walk the prod list looking for the alias(es) the user specified.
  $to_stage = array();
  $user_sites = explode(',', $sites);
  foreach ($prod_sites as $prod_site) {
    if ($stage_all_sites) {
      $to_stage[] = $prod_site->id;
    }
    else {
      // Search list of prod sites with the list of site names the user
      // provided.
      if (in_array($prod_site->site, $user_sites)) {
        $to_stage[] = $prod_site->id;
      }

      // TODO: Add group support.
    }
  }

  if (empty($to_stage)) {
    drush_set_error(dt('No sites found. Exiting.'));
  }

  // TODO:  add support for electing not to stage the Factory.

  // Kick off a staging process with our list of site node IDs.
  $post_data = array(
    'to_env' => $env,
    'sites' => $to_stage,
  );

  $staging_endpoint = acsf_tools_get_factory_url($config, '/api/v1/stage');

  $result = acsf_tools_curl_wrapper($config->username, $config->password, $staging_endpoint, $post_data);
  drush_print($result->message);
}