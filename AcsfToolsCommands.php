<?php

/**
 * @file
 */

namespace Drush\Commands\acsf_tools;

use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;

/**
 * A Drush commandfile.
 */
class AcsfToolsCommands extends AcsfToolsUtils implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

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
   * @option domain-pattern
   *   Pattern / keyword to check for choosing the domain for uri parameter.
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
   * @usage drush acsf-tools-ml cron --domain-pattern=collection
   *   Run cron on all sites using domain that contains the pattern "collection" for URI.
   *   By default it uses first custom domain. If no domain available it uses acsitefactory.com domain.
   *   From abc.collection.xyz.com and abc.xyz.acsitefactory.com it will choose abc.collection.xyz.com domain.
   * @aliases sfml,acsf-tools-ml
   */
  public function ml($cmd, $command_args = '', $command_options = '', $options = ['domain-pattern' => '', 'delay' => 0, 'total-time-limit' => 0, 'use-https' => 0]) {
    $command_args = $this->getCommandArgs($command_args);

    $drush_command_options = $this->getCommandOptions($command_options);

    // Command always passes the default option as `yes` irrespective if `--no`
    // option used. Pass confirmation as `no` if use that.
    if ($options['no']) {
      $drush_command_options['no'] = TRUE;
    }

    // Look for list of sites and loop over it.
    if ($sites = $this->getSites()) {
      $i = 0;
      $delay = $options['delay'];
      $total_time_limit = $options['total-time-limit'];
      $end = time() + $total_time_limit;

      do {
        foreach ($sites as $delta => $details) {
          $domain = $this->getDomain($details, $options);

          $process = $this->prepareCommand($domain, $details, $cmd, $command_args, $drush_command_options);
          if (empty($process)) {
            continue;
          }

          $this->output()->writeln("\n=> Running command on $domain");

          $is_command_success = TRUE;
          try {
            $process->mustRun();
          }
          catch (\Exception $e) {
            $is_command_success = FALSE;
          }

          if (!$is_command_success || !$process->isSuccessful()) {
            $this->output()->writeln("\n=> The command failed to execute for the site $domain.");
            $this->output()->writeln($process->getErrorOutput());
            continue;
          }

          // Print the output. Some commands (such as updb) are logging
          // "normal" messages in the error output so we are displaying these
          // as well.
          if (!empty($process->getOutput())) {
            $this->output()->writeln($process->getOutput());
          }
          if (!empty($process->getErrorOutput())) {
            $this->output()->writeln($process->getErrorOutput());
          }

          // Delay in running the command for next site.
          if ($delay > 0 && $i < (count($sites) - 1)) {
            $this->output()->writeln("\n=> Sleeping for $delay seconds before running command on next site.");
            sleep($delay);
          }
        }
      } while ($total_time_limit && time() < $end && !empty($sites));
    }
  }

  /**
   * Runs the passed drush command against all the sites of the factory (mlc stands for ml + concurrent).
   *
   * @command acsf-tools:mlc
   *
   * @bootstrap site
   * @params $cmd
   *   The drush command you want to run against all sites in your factory.
   * @params $command_args Optional.
   *   A quoted, space delimited set of arguments to pass to your drush command.
   * @params $command_options Optional.
   *   A quoted space delimited set of options to pass to your drush command.
   * @option domain-pattern
   *   Pattern / keyword to check for choosing the domain for uri parameter.
   * @option use-https
   *   Use secure urls for drush commands.
   * @option concurrency-limit
   *   The maximum number of commands to run in parallel. 0 for no limit.
   * @usage drush acsf-tools-mlc st
   *   Get output of `drush status` for all the sites.
   * @usage drush acsf-tools-mlc cget "'system.site' 'mail'"
   *   Get value of site_mail variable for all the sites.
   * @usage drush acsf-tools-mlc upwd "'admin' 'password'"
   *   Update user password.
   * @usage drush acsf-tools-mlc cget "'system.site' 'mail'" "'format=json' 'interactive-mode'"
   *   Fetch config value in JSON format.
   * @usage drush acsf-tools-mlc cron --use-https=1
   *   Run cron on all sites using secure url for URI.
   * @usage drush acsf-tools-mlc cron --domain-pattern=collection
   *   Run cron on all sites using domain that contains the pattern "collection" for URI.
   *   By default it uses first custom domain. If no domain available it uses acsitefactory.com domain.
   *   From abc.collection.xyz.com and abc.xyz.acsitefactory.com it will choose abc.collection.xyz.com domain.
   * @usage drush acsf-tools-mlc cr --concurrency-limit=5
   *   Run cache clear on all the sites with a limit of 5 concurrent commands.
   * @aliases sfmlc,acsf-tools-mlc
   */
  public function mlc($cmd, $command_args = '', $command_options = '', $options = ['domain-pattern' => '', 'use-https' => 0, 'concurrency-limit' => 0]) {
    // Look for list of sites and loop over it.
    $sites = $this->getSites();
    if (empty($sites)) {
      return;
    }

    $command_args = $this->getCommandArgs($command_args);

    // Parse list of options to be passed to the drush sub-command being invoked.
    $drush_command_options = $this->getCommandOptions($command_options);

    // Command always passes the default option as `yes` irrespective if `--no`
    // option used. Pass confirmation as `no` if use that.
    if ($options['no']) {
      $drush_command_options['no'] = TRUE;
    }

    $processes = [];

    foreach ($sites as $details) {
      $domain = $this->getDomain($details, $options);
      $process = $this->prepareCommand($domain, $details, $cmd, $command_args, $drush_command_options);
      if (empty($process)) {
        continue;
      }

      $processes[$domain] = $process;
    }

    $processes_chunks = intval($options['concurrency-limit']) ? array_chunk($processes, intval($options['concurrency-limit']), TRUE) : [$processes];

    foreach ($processes_chunks as $processes_chunk) {
      foreach ($processes_chunk as $domain => $process) {
        $this->output()->writeln("\n=> Executing command on $domain");
        $process->start();
      }

      // Wait while commands are finished and log output.
      while (!empty($processes_chunk)) {
        foreach ($processes_chunk as $domain => $process) {
          if (!$process->isTerminated()) {
            continue;
          }

          // Remove from array now.
          unset($processes_chunk[$domain]);

          if ($process->isSuccessful()) {
            $this->output()->writeln("\n=> The command executed successfully for the site $domain.");
            $this->output()->writeln($process->getOutput());
            $this->output()->writeln($process->getErrorOutput());
          }
          else {
            $this->output()->writeln("\n=> The command failed to execute for the site $domain.");
            $this->output()->writeln($process->getErrorOutput());
          }
        }
      }
    }
  }

  /**
   * Wrapper function to parse command arguments.
   *
   * @param string $command_args
   *   Command arguments.
   *
   * @return array
   *   Arguments as array.
   */
  protected function getCommandArgs($command_args) {
    // Drush 9 limits the number of arguments a command can receive. To handle drush commands with dynamic arguments, we try to receive all arguments in a single variable $args & try to split it into individual arguments.
    // Commands with multiple arguments will need to be invoked as drush acsf-tools-ml upwd "'admin' 'password'"
    $command_args = preg_split("/'\s'/", $command_args);

    // Trim off "'" that will stay back after preg split with 1st & the last arg.
    $command_args[0] = ltrim($command_args[0], "'");
    $command_args[count($command_args) -1] = rtrim($command_args[count($command_args) -1], "'");

    return $command_args;
  }

  /**
   * Wrapper function to parse command options.
   *
   * @param string $command_options
   *   Command options.
   *
   * @return array
   *   Options as array.
   */
  protected function getCommandOptions($command_options) {
    // Drush 9 has strict validation around keys via which option values can be
    // passed to the command. Its expected to throw exception if an option name
    // not declared by the commands definition is passed to it. Dynamically
    // passing options to all commands will not be directly possible with drush9
    // (as it was the case with drush8). We try to receive all command specific
    // options as an argument & parse it before invoking the sub-command.
    if (empty($command_options)) {
      return [];
    }

    $command_options = preg_split("/'\s'/", $command_options);
    $command_options[0] = ltrim($command_options[0], "'");
    $command_options[count($command_options) -1] = rtrim($command_options[count($command_options) -1], "'");

    foreach ($command_options as $option_value) {
      [$key, $value] = explode('=', $option_value);
      $drush_command_options[$key] = $value;
    }

    return $drush_command_options;
  }

  /**
   * Get domain for particular site.
   * @param array $site
   *   ACSF Site data.
   * @param array $options
   *   Command options.
   *
   * @return string
   *   Domain.
   */
  protected function getDomain(array $site, $options) {
    // Get the first custom domain if any. Otherwise use the first domain
    // which is *.acsitefactory.com. Given this is used as --uri parameter
    // by the drush command, it can have an impact on the drupal process.
    $domain = $site['domains'][1] ?? $site['domains'][0];

    // Find a domain containing the pattern from specified in command options.
    if (array_key_exists('domain-pattern', $options) && !empty($options['domain-pattern'])) {
      foreach ($site['domains'] as $possible_domain) {
        if (strpos($possible_domain, $options['domain-pattern']) !== FALSE) {
          $domain = $possible_domain;
          break;
        }
      }
    }

    if (array_key_exists('use-https', $options) && $options['use-https']) {
      // Use secure urls in URI to ensure base_url in Drupal uses https.
      $domain = 'https://' . $domain;
    }

    return $domain;
  }

  /**
   * Wrapper function to prepare process if site available for processing.
   *
   * @param string $domain
   *   Domain.
   * @param array $details
   *   ACSF Site details.
   * @param string $cmd
   *   Drush command.
   * @param array $command_args
   *   Drush command arguments.
   * @param array $drush_command_options
   *   Drush command options.
   *
   * @return \Symfony\Component\Process\Process|null
   *   Process object if site available for processing.
   */
  protected function prepareCommand(string $domain, array $details, string $cmd, array $command_args, array $drush_command_options) {
    if (!$this->isSiteAvailable($details)) {
      $this->output()->writeln("\n=> Skipping command on $domain as site is not ready yet");
      return NULL;
    };

    $drush_command_options['uri'] = $domain;

    $self = $this->siteAliasManager()->getSelf();
    return Drush::drush($self, $cmd, $command_args, $drush_command_options);

  }

  /**
   * Create a database backup for each site of the factory.
   *
   * @command acsf-tools:dump
   *
   * @bootstrap site
   * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   * @option result-folder
   *   The folder in which the backups will be written. Defaults to ~/drush-backups/[YYYYmmdd-hhmm].
   * @option gzip
   *   Compress the backups into a zip file.
   *
   * @usage drush acsf-tools-dump
   *   Create database backups for the sites of the factory. Default result folder (~/drush-backups/[YYYYmmdd-hhmm])  will be used.
   * @usage drush acsf-tools-dump --result-folder=/home/project/backup/1.0.9
   *   Create database backups for the sites of the factory and store them in the specified folder. If folder does not exist the command will try to create it.
   * @usage drush acsf-tools-dump --result-folder=/home/project/backup/20160617 --gzip
   *   Same as above but using options of sql-dump command.
   *
   * @aliases sfdu,acsf-tools-dump
   */
  public function dbDump(array $options = ['result-folder' => NULL, 'gzip' => FALSE]) {

    // Use the default result folder if not provided.
    if (empty($options['result-folder'])) {
      $options['result-folder'] = $_SERVER['HOME'] . '/drush-backups/' . date('Ymd-Hi');
    }

    // Ask for confirmation before running the command.
    if (!$this->io()->confirm("Are you sure you want to take database dumps of all the sites of the factory in {$options['result-folder']}.")) {
      return;
    }

    // Identify target folder.
    $backup_result_folder = $options['result-folder'];

    // If dump directory does not exist.
    if(!file_exists($backup_result_folder)){
      if (!$this->io()->confirm("Target directory ($backup_result_folder) does not exist. Do you want to create this directory?")) {
        throw new UserAbortException();
      }
      $this->output()->writeln("=> Creating $backup_result_folder directory ...");
      // Create dump directory.
      if (!mkdir($backup_result_folder, 0755, TRUE) || !file_exists($backup_result_folder)) {
        $this->io()->error("Unable to create the dump directory $backup_result_folder.");
        return;
      }
      else{
        $this->io()->success("Folder $backup_result_folder has been created.");
      }
    }
    // @TODO: Handle the case the destination folder is not empty.

    // Look for list of sites and loop over it.
    if ($sites = $this->getSites()) {
      $arguments = [];
      $command = 'sql-dump';

      $options = Drush::input()->getOptions();
      unset($options['php']);
      unset($options['php-options']);

      unset($options['result-folder']);

      foreach ($sites as $details) {
        $domain = $details['domains'][0];

        // Get options passed to this drush command & append it with options
        // needed by the next command to execute.
        $options = Drush::redispatchOptions();
        unset($options['php']);
        unset($options['php-options']);
        unset($options['result-folder']);

        $options['result-file'] = $backup_result_folder . '/' . $details['machine_name'] . '.sql';
        $options['uri'] = $domain;

        $this->output()->writeln("=> Taking database dump of {$details['machine_name']} ...");
        $self = $this->siteAliasManager()->getSelf();
        // Remove empty values from array.
        $options = array_filter($options);
        $process = Drush::drush($self, $command, $arguments, $options);
        $exit_code = $process->run();

        if ($exit_code !== 0) {
          // Throw an exception with details about the failed process.
          $this->io()->error("The command failed to execute for the site {$details['machine_name']}.");
          continue;
        }

        // Log success message.
        if (isset($options['gzip']) && $options['gzip']) {
          $options['result-file'] .= '.gz';
        }
        $this->io()->success("Database dump of {$details['machine_name']} site successfully saved in {$options['result-file']}.");
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
      $arguments = [];

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
          $shell_execution = Drush::shell('gunzip -k ' . $source_file);
          $exit_code = $shell_execution->run();

          if ($exit_code !== 0) {
            // Throw an exception with details about the failed process.
            $this->output()
              ->writeln("\n=> The command gunzip failed to execute for the site $domain.");
            continue;
          }

          $source_file = substr($source_file, 0, -3);
        }

        // Get options passed to this drush command & append it with options
        // needed by the next command to execute.
        $options = Drush::redispatchOptions();
        $options['uri'] = $domain;
        unset($options['php']);
        unset($options['php-options']);
        unset($options['source-folder']);
        unset($options['gzip']);
        // Command Started.
        $this->output()
          ->writeln("\n=> Restoring the Database on the Domain $domain.");

        $self = $this->siteAliasManager()->getSelf();

        // Remove empty values from array.
        $options = array_filter($options);
        $sql_connect_process = Drush::drush($self, 'sql-connect', $arguments, $options, ['output' => FALSE]);
        $exit_code_sql_connect = $sql_connect_process->run();

        if ($exit_code_sql_connect !== 0) {
          // $exit_code_sql_connect an exception with details about the failed process.
          $this->output()
            ->writeln("\n=> The sql-connect command failed to execute for the site $domain.");
          continue;
        }

        $result = $sql_connect_process->getOutput();

        if (!empty($result)) {
          $sql_drop_process = Drush::drush($self, 'sql-drop', $arguments, $options);
          $sql_drop_process_exit_code = $sql_drop_process->run();

          if ($sql_drop_process_exit_code !== 0) {
            // Throw an exception with details about the failed process.
            $this->output()
              ->writeln("\n=> The sql-drop command failed to execute for the site $domain.");
            continue;
          }

          // Ensure that we do not have any carriage returns in our output.
          $result = str_replace(array("\r", "\n"), '', $result);
          $shell_execution_process = Drush::shell('cat ' . $source_file . ' | ' . $result);
          $exit_code_shell = $shell_execution_process->run();

          if ($exit_code_shell !== 0) {
            // Throw an exception with details about the failed process.
            $this->output()
              ->writeln("\n=> The command failed to execute for the site $domain.");
            continue;
          }
        }
        else {
          $this->logger()->error("Skipping restore because result of sql-connect was empty.");
        }

        // Remove the temporary decompressed dump
        if ($gzip) {
          $shell_execution_rm = Drush::shell('rm ' . $source_file);
          $exit_code_rm = $shell_execution_rm->run();

          if ($exit_code_rm !== 0) {
            // Throw an exception with details about the failed process.
            $this->output()
              ->writeln("\n=> The Shell rm command failed to execute for the site $domain.");
            continue;
          }
        }

        // Command Completed.
        $this->output()
          ->writeln("\n=> Dropping and restoring database on $domain Completed.");
      }
    }
  }
}
