<?php

// Load Composer to get to Symfony components.
$loader = require realpath(__DIR__ . '/../../vendor/autoload.php');
$loader->register();

use Symfony\Component\Yaml\Yaml;

$realpath = realpath(__DIR__ . '/../contrib/acsf-tools');
$yaml = Yaml::parse(file_get_contents($realpath . '/acsf_tools_config.yml'));

// Add a site ID and configure the sites and environment names to
// create an alias file for an Acquia Cloud Site Factory site.

// Acquia Cloud Site Factory id.
$site_id = $yaml['site_id'];

if ($sites_yaml = Yaml::parse(file_get_contents($realpath . '/acsf_sites_list.yml'))) {
  $sites = $sites_yaml['sites'];
}

// Configure the server used for the production environment.
$prod_web = $yaml['prod_web'];

// Configure the server used for the dev and test environments.
$dev_web = $yaml['dev_web'];

// =======================END OF CONFIGURATION==============================.
if ($site_id !== '[PROJECT-NAME]') {
  // Acquia Cloud Site Factory environment settings.
  $envs = array(
    'prod' => array(
      'remote-user' => $site_id . '.01live',
      'root' => '/var/www/html/' . $site_id . '.01live/docroot',
      'remote-host' => $prod_web . '.enterprise-g1.hosting.acquia.com',
    ),
    'test' => array(
      'remote-user' => $site_id . '.01test',
      'root' => '/var/www/html/' . $site_id . '.01test/docroot',
      'remote-host' => $dev_web . '.enterprise-g1.hosting.acquia.com',
    ),
    'dev' => array(
      'remote-user' => $site_id . '.01dev',
      'root' => '/var/www/html/' . $site_id . '.01dev/docroot',
      'remote-host' => $dev_web . '.enterprise-g1.hosting.acquia.com',
    ),
  );
  // These defaults connect to the Acquia Cloud Site Factory.
  $acsf_defaults = array(
    'ssh-options' => '-p 22',
    'path-aliases' => array(
      '%dump-dir' => '/mnt/tmp/'
    )
  );
  // Create the aliases using the defaults and the list of sites.
  foreach ($sites as $site_name => $site_domains) {
    if (!is_array($site_domains)) {
      $site_name = $site_domains;
    }
    foreach ($envs as $env_name => $env_info) {
      $uri = $site_name . '.' . $env_name . '-' . $site_id . '.acsitefactory.com';
      if ($env_name == 'prod') {
        $uri = $site_name . '.' . $site_id . '.acsitefactory.com';
      }
      if (is_array($site_domains) && isset($site_domains[$env_name])) {
        $uri = $site_domains[$env_name];
      }
      $aliases[$site_name . '.' . $env_name] = array_merge(
        $acsf_defaults,
        $env_info,
        array(
          'uri' => $uri,
        )
      );
    }
  }
}
