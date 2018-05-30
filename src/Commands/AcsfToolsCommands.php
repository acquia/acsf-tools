<?php

namespace Drupal\acsf_tools\Commands;

use Drush\Commands\DrushCommands;

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
   * @command acsf:tools-list
   * @aliases sfl,acsf-tools-list
   */
  public function toolsList(array $options = ['fields' => null]) {
    // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
    // legacy command.
  }

  /**
   * List details for each site in the Factory.
   *
   * @usage drush acsf-tools-info
   *   Get more details for all the sites of the factory.
   *
   * @command acsf:tools-info
   * @aliases sfi,acsf-tools-info
   */
  public function toolsInfo() {
    // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
    // legacy command.
  }

  /**
   * Runs the passed drush command against all the sites of the factory (ml stands for multiple -l option).
   *
   * @usage drush8 acsf-tools-ml vget site_mail
   *   Get value of site_mail variable for all the sites.
   * @usage drush8 acsf-tools-ml sqlq "select status from system where name='php'"
   *   Check status of php module on all the sites.
   *
   * @command acsf:tools-ml
   * @aliases sfml,acsf-tools-ml
   */
  public function toolsMl() {
    // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
    // legacy command.
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
   * @command acsf:tools-dump
   * @aliases sfdu,acsf-tools-dump
   */
  public function toolsDump(array $options = ['result-folder' => null]) {
    // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
    // legacy command.
  }

}
