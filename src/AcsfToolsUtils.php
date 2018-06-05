<?php

/**
 * @file
 * Contains \Drupal\acsf_tools\AcsfToolsUtils.
 */

namespace Drupal\acsf_tools;

use Symfony\Component\Yaml\Yaml;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

class AcsfToolsUtils extends DrushCommands {
	
  protected $drush;

	/**
	 * Constructor.
	 */
	public function utils($drushCommand) {
	  $this->drush = $drushCommand;
    return $this;
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
      $this->drush->logger()->error("\nFailed to retrieve the list of sites of the factory.");
    }

    return $sites;
  }

  /**
   * Utility function to prompt the user for confirmation they want to run a
   * command against all sites in their Factory.
   * @return bool
   */
  public function promptConfirm() {

    $this->drush->output()->writeln(
      dt('You are about to run a command on all the sites of your factory. 
        Do you confirm you want to do that? If so, type \'yes\''));
    if (!$this->drush->io()->confirm(dt('Do you want to continue?'))) {
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
        $this->drush->output()->writeln($tab . $key . ': ' . $value);
      }
      else {
        $this->drush->output()->writeln($tab . $key . ':');
        $this->recursivePrint($value, $indent + 2);
      }
    }
  }
}
