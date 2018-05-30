<?php

namespace Drush\Commands;

use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Drupal\acsf_tools\AcsfToolsServiceProvider;
use Drupal\acsf_tools\AcsfToolsUtils;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A Drush commandfile.
 */
class AcsfToolsCommands extends DrushCommands {


  protected $utils;

  /**
   * @hook pre-command *
   */
  public function setupAcsfToolsUtils()
  {
    //$this->addServicesToContainer();
    $this->utils = \Drupal::service('acsf_tools.utils')->utils();
  }

  /**
   * This is necessary to define our own services.
   */
  protected function addServicesToContainer() {
    \Drupal::service('kernel')->addServiceModifier(new AcsfToolsServiceProvider());
    \Drupal::service('kernel')->rebuildContainer();
  }

  /**
   * List the sites of the factory.
   *
   * @command acsf-tools:list
   *
   * @bootstrap full
   * @param array $options An associative array of options whose values come
   *   from cli, aliases, config, etc.
   * @option fields
   *   The list of fields to display (comma separated list).
   * @usage drush acsf-tools-list
   *   Get all details for all the sites of the factory.
   * @usage drush acsf-tools-list --fields
   *   Get prefix for all the sites of the factory.
   * @usage drush acsf-tools-list --fields=name,domains
   *   Get prefix, name and domains for all the sites of the factory.
   *
   * @aliases sfl,acsf-tools-list
   */
  public function sitesList(array $options = ['fields' => null]) {

    // Look for list of sites and loop over it.
    if ($sites = $this->utils) {
      // Render the info.
      $fields = $options['fields'];
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
   * @command acsf-tools:info
   *
   * @bootstrap full
   * @usage drush acsf-tools-info
   *   Get more details for all the sites of the factory.
   *
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
   * @command acsf-tools:ml
   *
   * @bootstrap full
   * @params $cmd
   *   The drush command you want to run against all sites in your factory.
   * @params $args Optional.
   *   A quoted, space delimited set of arguments to pass to your drush command.
   * @usage drush acsf-tools-ml st
   *   Get output of `drush status` for all the sites.
   * @usage drush acsf-tools-ml cget "system.site mail"
   *   Get value of site_mail variable for all the sites.
   * @aliases sfml,acsf-tools-ml
   */
  public function ml($cmd, $args = '') {

    // TODO: Find a better way to handle multiple args, e.g. `drush sqlq "SELECT .."`.
    $args = explode(" ", $args);

    // Look for list of sites and loop over it.
    if ($sites = $this->getSites()) {

      $processed = array();
      foreach ($sites as $details) {
        $domain = $details['domains'][0];

        $this->output()->writeln("=> Running command on $domain");
        drush_invoke_process('@self', $cmd, $args, array('uri' => $domain));
      }
    }
  }

  /**
   * Make a DB dump for each site of the factory).
   *
   * @command acsf-tools:dump
   *
   * @bootstrap full
   * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   * @option result-folder
   *   The folder in which the dumps will be written. Defaults to ~/drush-backups.
   * @usage drush acsf-tools-dump
   *   Create DB dumps for the sites of the factory. Default result folder will be used.
   * @usage drush acsf-tools-dump --result-folder=/home/project/backup/20160617
   *   Create DB dumps for the sites of the factory and store them in the specified folder. If folder does not exist the command will try to create it.
   * @usage drush acsf-tools-dump --result-folder=/home/project/backup/20160617 --gzip
   *   Same as above but using options of sql-dump command.
   *
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

    $utils = new AcsfToolsUtils();

    // Look for list of sites and loop over it.
    if ($sites = $utils->getSites()) {
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
   * Utility function to recursively pretty print arrays for drush.
   *
   * @param $variable
   * @param $indent
   */
  private function recursivePrint($variable, $indent) {

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
   * Utility function to prompt the user for confirmation they want to run a
   * command against all sites in their Factory.
   * @return bool
   */
  private function promptConfirm() {

    $this->output()->writeln(
      dt('You are about to run a command on all the sites of your factory. 
        Do you confirm you want to do that? If so, type \'yes\''));
    if (!$this->io()->confirm(dt('Do you want to continue?'))) {
      throw new UserAbortException();
    }

    return TRUE;
  }
}
