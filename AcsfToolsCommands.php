<?php

/**
 * @file
 */

namespace Drush\Commands\acsf_tools;

use Drush\Drush;

/**
 * A Drush commandfile.
 */
class AcsfToolsCommands extends AcsfToolsUtils {

  /**
   * List the sites of the factory.
   *
   * @command acsf-tools:list
   *
   * @bootstrap site
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
    if ($sites = $this->getSites()) {
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
   * @bootstrap site
   * @usage drush acsf-tools-info
   *   Get more details for all the sites of the factory.
   *
   * @aliases sfi,acsf-tools-info
   */
  public function sitesInfo() {

    // Don't run locally.
    if (!$this->checkAcsfFunction('gardens_site_data_load_file')) {
      return FALSE;
    }

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
      return $this->logger()->error("\nFailed to retrieve the list of sites of the factory.");
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
   * @bootstrap site
   * @params $cmd
   *   The drush command you want to run against all sites in your factory.
   * @params $command_args Optional.
   *   A quoted, space delimited set of arguments to pass to your drush command.
   * @params $command_options Optional.
   *   A quoted space delimited set of options to pass to your drush command.
   * @option profiles
   *   Target sites with specific profiles. Comma list.
   * @option delay
   *   Number of seconds to delay to run command between each site.
   * @option total-time-limit
   *   Total time limit in seconds. If this option is present, the given command will be executed multiple times within the given time limit.
   * @option use-https
   *   Use secure urls for drush commands.
   * @usage drush acsf-tools-ml st
   *   Get output of `drush status` for all the sites.
   * @usage drush acsf-tools-ml cget "'system.site' 'mail'"
   *   Get value of site_mail variable for all the sites.
   * @usage drush acsf-tools-ml upwd "'admin' 'password'"
   *   Update user password.
   * @usage drush acsf-tools-ml cget "'system.site' 'mail'" "'format=json' 'interactive-mode'"
   *   Fetch config value in JSON format.
   * @usage drush acsf-tools-ml cr --delay=10
   *   Run cache clear on all sites with delay of 10 seconds between each site.
   * @usage drush acsf-tools-ml cron --use-https=1
   *   Run cron on all sites using secure url for URI.
   * @aliases sfml,acsf-tools-ml
   */
  public function ml($cmd, $command_args = '', $command_options = '', $options = ['profiles' => '', 'delay' => 0, 'total-time-limit' => 0, 'use-https' => 0]) {

    // drush 9 limits the number of arguments a command can receive. To handle drush commands with dynamic arguments, we try to receive all arguments in a single variable $args & try to split it into individual arguments.
    // Commands with multiple arguments will need to be invoked as drush acsf-tools-ml upwd "'admin' 'password'"
    $command_args = preg_split("/'\s'/", $command_args);

    // Trim off "'" that will stay back after preg split with 1st & the last arg.
    $command_args[0] = ltrim($command_args[0], "'");
    $command_args[count($command_args) -1] = rtrim($command_args[count($command_args) -1], "'");

    // drush 9 has strict validation around keys via which option values can be
    // passed to the command. Its expected to throw exception if an option name
    // not declared by the commands definition is passed to it. Dynamically
    // passing options to all commands will not be directly possible with drush9
    // (as it was the case with drush8). We try to receive all command specific
    // options as an argument & parse it before invoking the sub-command.

    //  Parse list of options to be passed ot the drush sub-command being
    // invoked.
    $command_options = preg_split("/'\s'/", $command_options);
    $command_options[0] = ltrim($command_options[0], "'");
    $command_options[count($command_options) -1] = rtrim($command_options[count($command_options) -1], "'");
    $drush_command_options = [];

    foreach ($command_options as $option_value) {
      list($key, $value) = explode('=', $option_value);
      $drush_command_options[$key] = $value;
    }

    // Command always passes the default option as `yes` irrespective if `--no`
    // option used. Pass confirmation as `no` if use that.
    if ($options['no']) {
      $drush_command_options['no'] = TRUE;
    }

    // Look for list of sites and loop over it.
    if ($sites = $this->getSites()) {
      if (!empty($options['profiles'])) {
        $profiles = explode(',', $options['profiles']);
        unset($options['profiles']);
      }

      $i = 0;
      $delay = $options['delay'];
      $total_time_limit = $options['total-time-limit'];
      $end = time() + $total_time_limit;

      do {
        foreach ($sites as $delta => $details) {
          // Get the first custom domain if any. Otherwise use the first domain
          // which is *.acsitefactory.com. Given this is used as --uri parameter
          // by the drush command, it can have an impact on the drupal process.
          $domain = $details['domains'][1] ?? $details['domains'][0];

          if ($options['use-https']) {
            // Use secure urls in URI to ensure base_url in Drupal uses https.
            $domain = 'https://' . $domain;
          }

          $site_settings_filepath = 'sites/g/files/' . $details['name'] . '/settings.php';
          if (!empty($profiles) && file_exists($site_settings_filepath)) {
            $site_settings = @file_get_contents($site_settings_filepath);
            if (preg_match("/'install_profile'] = '([a-zA-Z_]*)'/", $site_settings, $matches)) {
              if (isset($matches[1]) && !in_array($matches[1], $profiles)) {
                $this->output()->writeln("\n=> Skipping command on $domain");
                continue;
              }
            }
          }

          $drush_command_options['uri'] = $domain;

          $this->output()->writeln("\n=> Running command on $domain");
          drush_invoke_process('@self', $cmd, $command_args, $drush_command_options);

          // Delay in running the command for next site.
          if ($delay > 0 && $i < (count($sites) - 1)) {
            $this->output()
              ->writeln("\n=> Sleeping for $delay seconds before running command on next site.");
            sleep($delay);
          }
        }
      } while ($total_time_limit && time() < $end && !empty($sites));
    }
  }

  /**
   * Make a DB dump for each site of the factory.
   *
   * @command acsf-tools:dump
   *
   * @bootstrap site
   * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   * @option result-folder
   *   The folder in which the dumps will be written. Defaults to ~/drush-backups.
   * @option gzip
   *   Compress the dump into a zip file.
   *
   * @usage drush acsf-tools-dump
   *   Create DB dumps for the sites of the factory. Default result folder will be used.
   * @usage drush acsf-tools-dump --result-folder=/home/project/backup/20160617
   *   Create DB dumps for the sites of the factory and store them in the specified folder. If folder does not exist the command will try to create it.
   * @usage drush acsf-tools-dump --result-folder=/home/project/backup/20160617 --gzip
   *   Same as above but using options of sql-dump command.
   *
   * @aliases sfdu,acsf-tools-dump
   */
  public function dbDump(array $options = ['result-folder' => '~/drush-backups', 'gzip' => FALSE]) {

    // Ask for confirmation before running the command.
    if (!$this->promptConfirm()) {
      return;
    }

    // Identify target folder.
    $result_folder = $options['result-folder'];

    // Look for list of sites and loop over it.
    if ($sites = $this->getSites()) {
      $arguments = drush_get_arguments();
      $command = 'sql-dump';

      $options = drush_get_context('cli');
      unset($options['php']);
      unset($options['php-options']);

      unset($options['result-folder']);

      foreach ($sites as $details) {
        $domain = $details['domains'][0];
        $prefix = explode('.', $domain)[0];

        // Get options passed to this drush command & append it with options
        // needed by the next command to execute.
        $options = Drush::redispatchOptions();
        $options['result-file'] = $result_folder . '/' . $prefix . '.sql';
        $options['uri'] = $domain;

        $this->logger()->info("\n=> Running command on $domain");
        drush_invoke_process('@self', $command, $arguments, $options);
      }
    }
  }

  /**
   * Make a DB dump for each site of the factory.
   *
   * @command acsf-tools:restore
   *
   * @bootstrap site
   * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   * @option source-folder
   *   The folder in which the dumps are stored. Defaults to ~/drush-backups.
   * @option gzip
   *   Restore from a zipped dump.
   *
   * @usage drush acsf-tools-restore
   *   Restore DB dumps for the sites of the factory. Default backup folder will be used.
   * @usage drush acsf-tools-restore --source-folder=/home/project/backup/20160617
   *   Restore DB dumps for factory sites that are stored in the specified folder.
   * @usage drush acsf-tools-restore --source-folder=/home/project/backup/20160617 --gzip
   *   Restore compressed DB dumps for factory sites that are stored in the specified folder.
   *
   * @aliases sfr,acsf-tools-restore
   *
   * @return bool|void
   */
  function dbRestore(array $options = ['source-folder' => '~/drush-backups', 'gzip' => FALSE]) {

    // Ask for confirmation before running the command.
    if (!$this->promptConfirm()) {
      return false;
    }

    // Identify source folder.
    $source_folder = $options['source-folder'];

    if (!is_dir($source_folder)) {
      // Source folder does not exist.
      return $this->logger()->error(dt("Source folder $source_folder does not exist."));
    }

    $gzip = $options['gzip'];

    // Look for list of sites and loop over it.
    if ($sites = $this->getSites()) {
      $arguments = drush_get_arguments();

      $options = drush_get_context('cli');
      unset($options['php']);
      unset($options['php-options']);
      unset($options['source-folder']);
      unset($options['gzip']);

      foreach ($sites as $details) {
        $domain = $details['domains'][0];
        $prefix = explode('.', $domain)[0];

        $source_file = $source_folder . '/' . $prefix . '.sql';

        if ($gzip) {
          $source_file .= '.gz';
        }

        if (!file_exists($source_file)) {
          $this->logger()->error("\n => No source file $source_file for $prefix site.");
          continue;
        }

        // Temporary decompress the dump to be used with drush sql-cli.
        if ($gzip) {
          drush_shell_exec('gunzip -k ' . $source_file);
          $source_file = substr($source_file, 0, -3);
        }

        // Get options passed to this drush command & append it with options
        // needed by the next command to execute.
        $options = Drush::redispatchOptions();
        $options['uri'] = $domain;

        $this->logger()->info("\n=> Dropping and restoring database on $domain");
        $result = drush_invoke_process('@self', 'sql-connect', $arguments, $options, ['output' => FALSE]);
        if (!empty($result['object'])) {
          drush_invoke_process('@self', 'sql-drop', $arguments, $options);
          drush_shell_exec($result['object'] . ' < ' . $source_file);
        }

        // Remove the temporary decompressed dump
        if ($gzip) {
          drush_shell_exec('rm ' . $source_file);
        }
      }
    }
  }
}
