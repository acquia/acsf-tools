<?php

/**
 * @file
 * Contains \Drupal\acsf_tools\AcsfToolsUtils.
 */

namespace Drupal\acsf_tools;

use Symfony\Component\Yaml\Yaml;

class AcsfToolsUtils {
	
	/**
	 * Constructor.
	 */
	public function __construct() {

	}

	/**
   * Utility function to retrieve the list of sites in a given Factory.
   *
   * @return array|bool
	 */
  public function getSites() {
    $sites = FALSE;

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
}
