<?php

/**
 * @file
 */

namespace Drush\Commands\acsf_tools;

use Symfony\Component\Yaml\Yaml;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

class AcsfToolsUtils extends DrushCommands {

	/**
   * Utility function to retrieve the list of sites in a given Factory.
   *
   * @return array|bool
	 */
  public function getSites() {
    $sites = FALSE;

    // Don't run locally.
    if (!$this->checkAcsfFunction('gardens_site_data_load_file')) {
      return FALSE;
    }

    // Look for list of sites and loop over it.
    if (($map = gardens_site_data_load_file()) && isset($map['sites'])) {
      // Acquire sites info.
      $sites = array();
      foreach ($map['sites'] as $domain => $site_details) {
        if (!isset($sites[$site_details['name']])) {
          $sites[$site_details['name']] = $site_details;
        }
        $sites[$site_details['name']]['domains'][] = $domain;
      }
    }
    else {
      $this->logger()->error("\nFailed to retrieve the list of sites of the factory.");
    }

    return $sites;
  }

  /**
   * Utility function to retrieve a list of sites remotely, via the API.
   *
   * @return array|bool
   */
  function getRemoteSites($config, $env = 'prod') {

    // TODO: What happens when more than 100 sites? Implement paging.
    $sites_url = $this->getFactoryUrl($config, '/api/v1/sites?limit=100', $env);
    return $this->curlWrapper($config->username, $config->password, $sites_url)->sites;
  }

  /**
   * Utility function to prompt the user for confirmation they want to run a
   * command against all sites in their Factory.
   * @return bool
   */
  public function promptConfirm() {

    $this->output()->writeln(
      dt('You are about to run a command on all the sites of your factory. 
        Do you confirm you want to do that? If so, type \'yes\''));
    if (!$this->io()->confirm(dt('Do you want to continue?'))) {
      throw new UserAbortException();
    }

    return TRUE;
  }

  /**
   * Utility function to recursively pretty print arrays for drush.
   *
   * @param $variable
   * @param $indent
   */
  public function recursivePrint($variable, $indent) {

    $tab = str_repeat(' ', $indent);

    foreach ($variable as $key => $value) {
      if (!is_array($value)) {
        $this->output()->writeln($tab . $key . ': ' . $value);
      }
      else {
        $this->output()->writeln($tab . $key . ':');
        $this->recursivePrint($value, $indent + 2);
      }
    }
  }

  /**
   * Utility function to retrieve locally stored REST API connection info.
   *
   * @return mixed
   */
  public function getRestConfig() {

    $path = realpath(dirname(__FILE__));
    $yaml = Yaml::parse(file_get_contents($path . '/acsf_tools_config.yml'));
    if ($yaml === FALSE) {
      $error  = 'acsf_tools_config.yml not found. Make sure to copy/rename ';
      $error .= 'acsf_tools_config.default.yml and set the appropriate ';
      $error .= 'connection info.';
      $this->logger()->error(dt($error));
    }

    $config = new \stdClass();
    $config->site_id = $yaml['site_id'];
    $config->username = $yaml['rest_api_user'];
    $config->password = $yaml['rest_api_key'];
    $config->prod_uri = $yaml['rest_factories']['prod'];
    $config->test_uri = $yaml['rest_factories']['test'];
    $config->dev_uri = $yaml['rest_factories']['dev'];
    $config->root_domain = $yaml['root_domain'];
    $config->subdomain_pattern = $yaml['subdomain_pattern'];
    $config->prod_web = $yaml['prod_web'];
    $config->dev_web = $yaml['dev_web'];

    return $config;
  }

  /**
   * Utility function to retrieve the correct factory URI given an environment and desired path.
   *
   * @param $config
   * @param string $path
   * @param string $env
   * @return string
   */
  public function getFactoryUrl($config, $path = '', $env = 'prod') {

    switch ($env) {
      case 'dev':
        $factory_url = $config->dev_uri . $path;
        break;
      case 'test':
        $factory_url = $config->test_uri . $path;
        break;
      default:
        $factory_url = $config->prod_uri . $path;
        break;
    }

    return $factory_url;
  }

  /**
   * Helper script to abstract curl requests into a single function. Handles both
   * GET and POST, depending on whether $data is defined or not.
   *
   * @param $username
   * @param $password
   * @param $url
   * @param array $data
   * @return mixed
   */
  public function curlWrapper($username, $password, $url, $data = array()) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    if (!empty($data)) {
      curl_setopt($ch, CURLOPT_POST, count($data));
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $result = json_decode(curl_exec($ch));
    curl_close($ch);
    return $result;
  }

  /**
   * Utility function to check if a function should be run
   * locally, or remotely in ACSF.
   */
  public function checkAcsfFunction($function_name = '') {
    if (!function_exists($function_name)) {
      $error = "This command cannot be run locally, please run with a valid ACSF alias.";
      $this->logger()->error(dt($error));
      return FALSE;
    }
  }
}
