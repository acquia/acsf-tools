<?php

/**
 * Implements hook_drush_command().
 */
function acsf_sites_backup_drush_command() {
  $items = array();

  // TODO: Should prompt user for a single site, list of sites, or all sites.

  $items['acsf-sites-backup'] = array(
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
function drush_acsf_sites_backup($env, $sites) {

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

  // TODO: Build site list

  // TODO: Send request to backup sites with defined list and return output.
}