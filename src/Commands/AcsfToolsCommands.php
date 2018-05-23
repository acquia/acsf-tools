<?php

namespace Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\acsf_tools\AcsfToolsServiceProvider;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class AcsfToolsCommands extends DrushCommands {

  /**
   * List the sites of the factory.
   *
    * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   * @option fields
   *   The list of fields to display (comma separated list).
   * @usage drush acsf-tools-list
   *   Get all details for all the sites of the factory.
   * @usage drush acsf-tools-list --fields
   *   Get prefix for all the sites of the factory.
   * @usage drush acsf-tools-list --fields=name,domains
   *   Get prefix, name and domains for all the sites of the factory.
   *
   * @command acsf-tools:list
   * @aliases sfl,acsf-tools-list
   */
  public function sitesList(array $options = ['fields' => null]) {

    // Look for list of sites and loop over it.
    if ($sites = $this->getSites()) {
      // Render the info.
      $fields = drush_get_option('fields');
      if (isset($fields)) {
        $expected_attributes = array_flip(explode(',', $fields));
      }

      foreach ($sites as $name => $details) {
        // Get site prefix from main domain.
        $prefix = explode('.', $details['domains'][0])[0];
        $this->output()->writeln($prefix);

        // Filter attributes.
        if (isset($expected_attributes)) {
          $details = array_intersect_key($details, $expected_attributes);
        }

        // Print attributes.
        $this->recursivePrint($details, 2);
      }
    }
  }

  /**
   * List details for each site in the Factory.
   *
   * @usage drush acsf-tools-info
   *   Get more details for all the sites of the factory.
   *
   * @command acsf-tools:info
   * @aliases sfi,acsf-tools-info
   */
  public function sitesInfo() {
    // Look for list of sites and loop over it.
    if (($map = gardens_site_data_load_file()) && isset($map['sites'])) {
      // Acquire sites info.
      $sites = array();
      foreach ($map['sites'] as $domain => $site_details) {
        $conf = $site_details['conf'];

        // Include settings file to get DB name. To save rescources, without bootsrtapping Drupal
        $settings_inc = "/var/www/site-php/{$_ENV['AH_SITE_GROUP']}.{$_ENV['AH_SITE_ENVIRONMENT']}/D7-{$_ENV['AH_SITE_ENVIRONMENT']}-" . $conf['gardens_db_name'] . "-settings.inc";
        $file = file_get_contents($settings_inc);
        $need= "\"database\" => \"";
        $need2= "\",";
        // Find db name
        $dpos = strpos($file, $need);
        $db_name = substr($file, ($dpos + strlen($need)) );
        $upos = strpos($db_name, $need2);
        // Isolate db name
        $db_name = substr($db_name, 0, $upos );

        // Re-structure  site
        $sites[$conf['gardens_site_id']]['domains'][] = $domain;
        $sites[$conf['gardens_site_id']]['conf'] = array('db_name' => $db_name, 'gname' => $conf['gardens_db_name'], );
      }
    }
    else {
      $this->logger()->error("\nFailed to retrieve the list of sites of the factory.");
    }

    $this->output->writeln("\nID\t\tName\t\tDB Name\t\t\t\tDomain\n");

    foreach ($sites as $key => $site) {
      $this->output->writeln("$key\t\t" . $site['conf']['gname'] . "\t\t" . $site['conf']['db_name'] . "\t\t" . $site['domains'][0]);
    }
  }

  /**
   * Runs the passed drush command against all the sites of the factory (ml stands for multiple -l option).
   *
   * @usage drush8 acsf-tools-ml vget site_mail
   *   Get value of site_mail variable for all the sites.
   * @usage drush8 acsf-tools-ml sqlq "select status from system where name='php'"
   *   Check status of php module on all the sites.
   *
   * @command acsf-tools:ml
   * @aliases sfml,acsf-tools-ml
   */
  public function ml() {

    // Look for list of sites and loop over it.
    if ($sites = $this->getSites()) {
      $arguments = drush_get_arguments();
      unset($arguments[0]);
      $command = array_shift($arguments);

      $options = drush_get_context('cli');
      unset($options['php']);
      unset($options['php-options']);

      $processed = array();
      foreach ($sites as $details) {
        $domain = $details['domains'][0];

        $this->logger()->info("\n=> Running command on $domain");
        drush_invoke_process('@self', $command, $arguments, $options + array('l' => $domain));
      }
    }
  }

  /**
   * Make a DB dump for each site of the factory).
   *
   * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   * @option result-folder
   *   The folder in which the dumps will be written. Defaults to ~/drush-backups.
   * @usage drush8 acsf-tools-dump
   *   Create DB dumps for the sites of the factory. Default result folder will be used.
   * @usage drush8 acsf-tools-dump --result-folder=/home/project/backup/20160617
   *   Create DB dumps for the sites of the factory and store them in the specified folder. If folder does not exist the command will try to create it.
   * @usage drush8 acsf-tools-dump --result-folder=/home/project/backup/20160617 --gzip
   *   Same as above but using options of sql-dump command.
   *
   * @command acsf-tools:dump
   * @aliases sfdu,acsf-tools-dump
   */
  public function dbDump(array $options = ['result-folder' => null]) {

    // Ask for confirmation before running the command.
    if (!$this->promptConfirm()) {
      return;
    }

    // Identify target folder.
    $result_folder = drush_get_option('result-folder');
    if (!isset($result_folder)) {
      $result_folder = '~/drush-backups';
    }

    if (!is_dir($result_folder) || !is_writable($result_folder)) {
      // Target folder does not exist. Try to create it.
      if (!mkdir($result_folder, 0777, TRUE)) {
       $this->logger()->error("\nImpossible to write to $result_folder folder.");
        return;
      }
    }

    // Look for list of sites and loop over it.
    if ($sites = _drush_acsf_tools_get_sites()) {
      $arguments = drush_get_arguments();
      $command = 'sql-dump';

      $options = drush_get_context('cli');
      unset($options['php']);
      unset($options['php-options']);

      unset($options['result-folder']);

      $processed = array();
      foreach ($sites as $details) {
        $domain = $details['domains'][0];
        $prefix = explode('.', $domain)[0];

        $options['result-file'] = $result_folder . '/' . $prefix . '.sql';

        $this->logger()->info("\n=> Running command on $domain");
        drush_invoke_process('@self', $command, $arguments, $options + array('l' => $domain));
      }
    }
  }

  /**
   * Utility function to retrieve the list of sites in a given Factory.
   *
   * @return array|bool
   */
  private function getSites() {
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

  /**
   * Utility function to recursively pretty print arrays for drush.
   *
   * @param $variable
   * @param $indent
   */
  private function recursivePrint($variable, $indent) {

    foreach ($variable as $key => $value) {
      if (!is_array($value)) {
        $this->output()->writeln($key . ': ' . $value, $indent);
      }
      else {
        $this->output()->writeln($key . ':', $indent);
        recursivePrint($value, $indent + 2);
      }
    }
  }

  /**
   * Utility function to prompt the user for confirmation they want to run a
   * command against all sites in their Factory.
   * @return bool
   */
  private function promptConfirm() {
    // Ask for confirmation before running the command.
    // Special care for -y option to avoid drush_prompt default behaviour.
    $yes = drush_get_context('DRUSH_AFFIRMATIVE');
    if ($yes) {
      drush_set_context('DRUSH_AFFIRMATIVE', FALSE);
    }

    $input = drush_prompt(
      dt('You are about to run a command on all the sites of your factory.
Do you confirm you want to do that? If yes, type \'ok\'')
    );
    if ($input != 'ok') {
      return FALSE;
    }

    if ($yes) {
      drush_set_context('DRUSH_AFFIRMATIVE', TRUE);
    }

    return TRUE;
  }
}
