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
     * Utility function to check if the current environment is an ACSF platform.
     *
     * @return bool
     */
    public function isAcsfPlatform() {
        // Check if the current environment is an ACSF platform.
        return function_exists('gardens_site_data_load_file');
    }

	/**
   * Utility function to retrieve the list of sites in a given Factory.
   *
   * @return array|bool
	 */
  public function getSites() {
    // If ACSF platform.
    if ($this->isAcsfPlatform()) {
      return $this->getAcsfSites();
    }

    return $this->getMultiSiteSites();
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
   * Utility function to check if a function should be run
   * locally, or remotely in ACSF.
   */
  public function checkAcsfFunction($function_name = '') {

    if (!function_exists($function_name)) {
      $error = "This command cannot be run locally, please run with a valid ACSF alias.";
      $this->logger()->error(dt($error));
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

  /**
   * Utility function to retrieve a list of sites in an ACSF Factory.
   *
   * @return array|bool
   */
  public function getAcsfSites() {
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

              // Path domains need a trailing slash to be recognized as a drush alias.
              if (FALSE !== strpos($domain, '/')) {
                  $domain = rtrim($domain, '/') . '/';
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
     * Utility function to retrieve a list of sites in a multi-site Drupal installation.
     *
     * @return array
     * @throws \RuntimeException
     */
    public function getMultiSiteSites(): array {
        // Read information from the sites.php file inside `/sites` directory.
        // @todo: Change this to proper file where data is available.
        $multisite_directory_path = DRUPAL_ROOT . '/sites';
        $multisite_file_path = $multisite_directory_path.'/sites.php';

        if (!is_file($multisite_file_path)) {
            throw new \RuntimeException("Cannot find $multisite_file_path");
        }

        require $multisite_file_path;

        if (!isset($sites) || !is_array($sites)) {
            throw new \RuntimeException("Multi-site not defined in $multisite_file_path");
        }

        $site_details = [];
        foreach ($sites as $site_name => $site_path) {
            $site_details[$site_path] = [
                'name' => $site_path,
                'domains' => [
                    $site_name,
                ],
                'flags' => [],
                'conf' => [
                    // These below array maintained same as ACSF sites.
                    'db_name' => $site_path, // Default to site name as DB name.
                    'site_id' => $site_path, // Default to site name as site ID.
                    'gardens_site_id' => $site_path, // Default to site name as gardens site ID.
                    'gardens_db_name' => $site_path, // Default to site name as gardens DB name.
                ],
                'machine_name' => $site_path, // Default to site name as machine name.
            ];
        }

        return $site_details;
    }

}
