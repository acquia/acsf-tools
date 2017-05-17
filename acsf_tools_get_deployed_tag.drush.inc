<?php

/**
 * Implements hook_drush_command().
 */
function acsf_tools_get_deployed_tag_drush_command() {
  $items = array();

  $items['acsf-tools-get-deployed-tag'] = array(
    'description' => dt('Fetches and displays the currently deployed sites tag for a Factory.'),
    'arguments' => array(
      'env' => 'The environment whose tag we\'re requesting. I.e., dev, test, prod',
    ),
    'required-arguments' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'examples' => array(
      'drush @mysite.local acsf-get-deployed-tag dev',
    ),
    'aliases' => ['sft'],
  );

  return $items;
}

/**
 * Drush command to print the currently deployed tag for a given site factory.
 *
 * @param $env
 * @return string
 */
function drush_acsf_tools_get_deployed_tag($env) {

  if (!in_array($env, array('dev','test','prod'))) {
    return drush_set_error('Invalid Factory environment.');
  }

  $config = acsf_tools_get_rest_config();

  $sites_url = acsf_tools_get_factory_url($config, '/api/v1/vcs?type=sites', $env);

  $response = acsf_tools_curl_wrapper($config->username, $config->password, $sites_url);
  drush_print($response->current);
}
