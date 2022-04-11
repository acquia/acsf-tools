<?php

/**
 * @file
 */

namespace Drush\Commands\acsf_tools;

use Drush\Drush;
use Symfony\Component\Yaml\Yaml;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

class AcsfToolsUtils extends DrushCommands {

  /**
   * Utility function to compute the path to the local copy of the sites.json.
   *
   * @return false|string
   */
  public function getLocalSitesJsonFilepath() {
    $filepath = FALSE;

    if ($this->aliasRecord && !$this->aliasRecord->isLocal()) {
      $alias_name = str_replace('@', '', $this->aliasRecord->name());
      $home = $this->getConfig()->home();

      $filepath = $home . '/.drush/' . $alias_name . '.sites.json';
    }

    return $filepath;
  }

	/**
   * Utility function to retrieve the list of sites in a given Factory.
   *
   * @return array|bool
	 */
  public function getSites() {
    $sites = FALSE;

    // Exit early if the command is executed outside an ACSF server and no
    // alias is provided.
    if ($this->aliasRecord && $this->aliasRecord->isLocal() && !$this->checkAcsfFunction('gardens_site_data_load_file')) {
      return $sites;
    }

    $map = FALSE;
    if ($this->checkAcsfFunction('gardens_site_data_load_file')) {
      $map = gardens_site_data_load_file();
    }
    elseif ($this->aliasRecord && !$this->aliasRecord->isLocal() && $sites_json_filepath = $this->getLocalSitesJsonFilepath()) {
      $alias_name = str_replace('@', '', $this->aliasRecord->name());

      // Download the remote ACSF sites.json so we can process it locally.
      if (!file_exists($sites_json_filepath)) {
        $self = $this->siteAliasManager()->getSelf();
        $process = Drush::drush($self, 'rsync', [$this->aliasRecord->name() . ':/mnt/files/' . $alias_name . '/files-private/sites.json',  $sites_json_filepath, '-y']);

        try {
          $process->mustRun();
        }
        catch (\Exception $e) {
          return FALSE;
        }
      }

      $json = @file_get_contents($sites_json_filepath);
      $map = $json ? json_decode($json, TRUE) : FALSE;
    }

    // Look for list of sites and loop over it.
    if ($map && isset($map['sites'])) {
      // Acquire sites info.
      $sites = array();
      foreach ($map['sites'] as $domain => $site_details) {
        if (!isset($sites[$site_details['name']])) {
          $sites[$site_details['name']] = $site_details;
        }

        // Path domains need a trailing slash to be recognized as a drush alias.
        if (FALSE !== strpos($domain, '/')) {
          $domain = rtrim($domain, '/') . '/';
        }

        $sites[$site_details['name']]['domains'][] = $domain;

        // Identify the site machine name from the acsitefactory.com domain.
        $machine_name = [];
        if (preg_match('/(.*)\..*\.acsitefactory\.com/', $domain, $machine_name)) {
          $sites[$site_details['name']]['machine_name'] = $machine_name[1];
        }
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
    $sites_url = $this->getFactoryUrl($config, '/api/v1/sites?limit=100', $env);
    return $this->curlWrapper($config->username, $config->password, $sites_url)->sites;
  }

  /**
   * Utility function to prompt the user for confirmation they want to run a
   * command against all sites in their Factory.
   *
   * @return bool
   * @throws UserAbortException
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
  public function getRestConfig($path = NULL) {

    if ($path == NULL) {
      $path = realpath(dirname(__FILE__));
    }

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
    $config->email_logs_from = $yaml['email_logs_from'];
    $config->email_logs_to = $yaml['email_logs_to'];

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
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Determines whether or not a site is available based on its flags array.
   *
   * Restricted sites usually mean that an installation or another process is
   * working on the site, so need to skip those.
   *
   * @param array $data
   *   The ACSF site data array.
   *
   * @return bool
   *   True if site is available.
   */
  public function isSiteAvailable(array $data): bool {
    // Initialize variables.
    $site_available = TRUE;

    // Not available if access is restricted or site is under operation.
    if ($this->isAccessRestricted($data) || $this->isOperationBlocked($data)) {
      $site_available = FALSE;
    }
    return $site_available;
  }

  /**
   * Determines whether or not a site is restricted.
   *
   * Restricted sites usually mean that an installation or another process is
   * working on the site, so need to skip those.
   *
   * @param array $data
   *   The ACSF site data array.
   *
   * @return bool
   *   True if access restriction is enabled.
   */
  public function isAccessRestricted(array $data): bool {
    if (array_key_exists('access_restricted', $data['flags'])) {
      if (isset($data['flags']['access_restricted']['enabled']) && $data['flags']['access_restricted']['enabled'] == 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Determines whether or not another process is using a site's data.
   *
   * The 'operation' key means that the site is in a usable state but a
   * process is using the site's data.
   *
   * @param array $data
   *   The ACSF site data array.
   *
   * @return bool
   *   True if the site's data is being used by another process.
   */
  public function isOperationBlocked(array $data): bool {
    if (isset($data['flags']['operation']) && $data['flags']['operation'] == 'move') {
      return TRUE;
    }
    return FALSE;
  }
}
