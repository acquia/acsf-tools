<?php
/**
 * @file
 * Utility command to fetch ACSF drush aliases.
 */

use Symfony\Component\Yaml\Yaml;

/**
 * Implements hook_drush_command().
 */
function acsf_tools_generate_aliases_drush_command() {
  $items = array();

  $items['acsf-tools-generate-aliases'] = array(
    'description' => dt('Fetch the drush aliases for all the sites in your factory.'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'examples' => array(
      'drush acsf-tools-fetch-aliases' => 'Fetch drush aliases for all the sites in your factory.',
    ),
    'aliases' => ['sfa'],
  );

  return $items;
}

/**
 * Drush callback to fetch all drush aliases and store them locally.
 *
 * @return bool
 */
function drush_acsf_tools_generate_aliases() {

  $config = acsf_tools_get_rest_config();

  // Query REST API for a list of all site names.
  $sites = get_factory_sites($config, 'prod');

  // Delete the existing sites file, if present.
  $realpath = realpath(__DIR__);
  $sites_file = $realpath . '/acsf_sites_list.yml';
  if (file_exists($sites_file)) {
    unlink($sites_file);
  }

  // Repopulate the sites file.
  $site_names = array();
  foreach ($sites as $site) {
    $site_names['sites'][] = $site->site;
  }
  $yaml = Yaml::dump($site_names);
  file_put_contents($sites_file, $yaml);

  // Put our aliases file in ../site-aliases.
  $aliases_template = $realpath  . '/acsf_tools.aliases.drushrc.php';
  $new_alias_filename = $config->site_id . '.aliases.drushrc.php';
  $new_file = $realpath . '/../site-aliases/' . $new_alias_filename;
  if (!file_exists($new_file) ) {
    copy($aliases_template, $new_file);
  }

  // Clear drush cache to load the new aliases.
  drush_cache_clear_drush();
  drush_print(dt("ACSF aliases generated for $config->site_id."));
}
