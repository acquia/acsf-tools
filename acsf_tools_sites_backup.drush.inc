<?php

/**
 * Implements hook_drush_command().
 */
function acsf_tools_sites_backup_drush_command() {
  $items = array();

  // TODO: Should prompt user for a single site, list of sites, or all sites.

  $items['acsf-tools-sites-backup'] = array(
    'description' => dt('\'A command line utility for backup up sites within a Factory.'),
    'arguments' => array(
      'env' => 'The target environment you wish to run the backup in.',
      'sites' => 'A comma-delimited list of site aliases you wish to backup. Pass \'all\' to backup all sites.'
    ),
    'required-arguments' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'examples' => array(
      'Single site' => 'drush @mysite.local acsf-sites-backup dev sitename',
      'Multiple sites' => 'drush @mysite.local acsf-sites-backup dev sitename1,sitename2',
      'All sites' => 'drush @mysite.local acsf-sites-backup dev all'
    ),
   'aliases' => ['sfb'],
  );

  return $items;
}

/**
 * A helper function to backup sites within in a site factory.
 *
 * @param $env
 * @return bool
 */
function drush_acsf_tools_sites_backup($env, $sites) {

  if (!in_array($env, array('dev','test','prod'))) {
    return drush_set_error('Invalid Factory environment.');
  }

  $config = acsf_tools_get_rest_config();

  // Ask/warn user about backing up all sites.
  $backup_all_sites = FALSE;
  if ($sites == 'all') {
    $warning = 'Are you sure you want to backup ALL sites?';
    if (!drush_confirm(dt($warning))) {
      return drush_set_error(dt('Ok, exiting.'));
    }
    $backup_all_sites = TRUE;
  }

  // Get a list of sites in the prod factory.
  $factory_sites = get_factory_sites($config, $env);

  // Walk the prod list looking for the alias(es) the user specified.
  $to_backup = array();
  $user_sites = explode(',', $sites);
  foreach ($factory_sites as $site) {
    if ($backup_all_sites) {
      post_site_backup($config, $site, $env);
    }
    else {
      // Search list of prod sites with the list of site names the user
      // provided.
      if (in_array($site->site, $user_sites)) {
        post_site_backup($config, $site, $env);
      }

      // TODO: Add group support.
    }
  }
}

function post_site_backup($config, $site, $env) {

  $backup_endpoint = acsf_tools_get_factory_url($config, "/api/v1/sites/$site->id/backup", $env);

  $post_data = array(
    'label' => $site->site . ' ' . date('m-d-Y g:i')
  );

  $result = acsf_tools_curl_wrapper($config->username, $config->password, $backup_endpoint, $post_data);
  drush_print(dt("Backup started for site $site->site."));
}