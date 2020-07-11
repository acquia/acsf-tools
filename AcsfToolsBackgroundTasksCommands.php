<?php

namespace Drush\Commands\acsf_tools;

use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Exception;

/**
 * Edit this file to reflect your organization's needs.
 */
class AcsfToolsBackgroundTasksCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
  use SiteAliasManagerAwareTrait;

  private $site_group = NULL;
  private $site_env = NULL;
  private $mnt_folder = NULL;

  public function __construct() {
    parent::__construct();

    if ($this->site_group == NULL) {
      $this->site_group = $_ENV['AH_SITE_GROUP'];
    }

    if ($this->site_env == NULL) {
      $this->site_env = $_ENV['AH_SITE_ENVIRONMENT'];
    }
  }

  /**
   * Mark a site with post background tasks pending.
   *
   * @command acsf-tools:set-background-tasks-pending
   *
   * @option retry-count
   *   How many times to retry if post background tasks exit with an error.
   *
   * @bootstrap configuration
   * @throws \Exception
   */
  public function setBackgroundTasksPending($options = ['retry-count' => 3, 'rootfolder' => null,])
  {
    $fileManager = new AcsfFileManager();

    $AcsfFlags = new AcsfFlags($this->site_group, $this->site_env, '/mnt/gfs/');
    $flagsFolder = $AcsfFlags->getFlagsFolder();

    if (!file_exists($flagsFolder)) {
      $fileManager->createFolder($flagsFolder);
    }

    if ($this->getSiteID()) {
      $fileManager->createFile($AcsfFlags->getFlagfileName($this->getSiteID()), $options['retry-count']);
      $AcsfLogs = new AcsfLogs();
      // Force the creation of the logs folder during the first iteration.
      $AcsfLogs->getLogsFolder();
    }
  }

  /**
   * Runs background tasks.
   *
   * @command acsf-tools:run-background-tasks
   *
   * @bootstrap configuration
   *
   * @param array $options
   *
   * @throws \Exception
   */
  public function runBackgroundTasks($options = [
    'timeout' => 1800,
    'script' => null,
    'rootfolder' => null,
    'queue' => 'default'
  ])
  {
    // Defaults to 30 mintues.
    $lockTimeout = $options['timeout'];

    $AcsfFlags = new AcsfFlags($this->site_group, $this->site_env, '/mnt/gfs/');
    $AcsfLock = new AcsfLock($AcsfFlags->getFlagsFolder());

    // Check the flag file exists / Value is 0<X<3
    // Check lock file DOES NOT exist
    if ($options['queue'] == 'default') {
      $process = $this->checkBackgroundTasksPending();
    }
    else {
      $process = $this->checkBackgroundTasksPendingAfterError($AcsfLock->doesLockExist($this->getSiteID()));
    }

    if ($process != TRUE) {
      $this->say('No background tasks pending.');
    } else {
      $AcsfLogs = new AcsfLogs();
      // Bootstrap Drupal.
      try {
        $this->say("\n" . $this->getCurrentTime() . " - Starting background task for " . 'no_id');
        if (!Drush::bootstrapManager()
          ->doBootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
          $this->say("Unable to bootstrap Drupal.");
          throw new \Exception(dt($this->getCurrentTime() . ' - Unable to bootstrap Drupal.'));
        }
      } catch (Exception $exception) {
        $this->say("Exception during Drupal bootstrap.");
        $message = $this->getCurrentTime() . " - Exception during Drupal bootstrap: " . $exception;
        $AcsfLogs->writeLog($message, 'no_id', 'error');

        // If Drupal can not be boostrapped, stop trying to run background tasks.
        $AcsfLock->releaseLock($this->getSiteID());
        $AcsfFlags->removeFlagFile($this->getSiteID());
        throw $exception;
      }

      $lock = \Drupal::lock();
      if ($lock->acquire('background_tasks', $lockTimeout)) {
        // Get some configs from the bootstrapped Drupal.
        $self = $this->siteAliasManager()->getSelf();
        $selfConfig = $self->exportConfig()->export();

        $uri = $selfConfig['options']['uri'];
        $root = $selfConfig['options']['root'];

        $site_group = $this->site_group;
        $site_env = $this->site_env;
        $db_name = $this->getSiteID();

        $AcsfLock = new AcsfLock($AcsfFlags->getFlagsFolder());
        $AcsfLock->getLock($db_name);

        // Decrease the flag counter.
        $AcsfFlags->decreaseFlagCounter($this->getSiteID());
        $counter = $AcsfFlags->getFlagCounter($this->getSiteID());

        $this->say($this->getCurrentTime() . ' - Starting background tasks.');
        $this->say('Retries left (excluding this run): ' . $counter);

        try {
          if ($options['script'] === null) {
            $options['script'] = "$root/../scripts/background-tasks.sh";
          }

          // Prepare the command to be run.
          $fullCommand = $options['script'] . " $site_group $site_env $db_name $uri";
          $this->say($this->getCurrentTime() . ' - Running ' . $fullCommand);
          $process = $this->processManager()->shell($fullCommand);
          $process->setVerbose(true);

          // And run it.
          $result = $process->run();
          $data = $process->getOutput();
          $exitCode = $process->getExitCode();

          if ($process->isSuccessful()) {
            $this->say($this->getCurrentTime() . ' - Script finished successfully.');
            $this->say("Script output:\n$data");

            // Capture error output. In case there were some warnings.
            $errorOutput = $process->getErrorOutput();

            $AcsfLogs->writeLog("Background tasks script finished successfully:\n"
              . "Exit code: " . $exitCode
              . "Script output: " . $data
              . "Script error output:\n$errorOutput", $db_name, 'success');

            $AcsfLock->releaseLock($db_name);
            $AcsfFlags->removeFlagFile($this->getSiteID());
          } else {
            $errorOutput = $process->getErrorOutput();
            $AcsfLogs->writeLog('Script finished with error code: '
              . "Exit code: " . $exitCode
              . "Retries left (excluding this run): " . $counter
              . $result . ' ' . $process->getExitCodeText()
              . "Script output: \n" . $data
              . "Script error output:\n$errorOutput", $db_name, 'error', true);
          }

          // Remove flag file if there are no more retries left.
          if ($counter === 0) {
            $AcsfLock->releaseLock($db_name);
            $AcsfFlags->removeFlagFile($this->getSiteID());
          }
        } catch (Exception $exception) {
          $AcsfLogs->writeLog($this->getCurrentTime() . " - Exception happened: " . $exception, $db_name);
        }

        $this->say($this->getCurrentTime() . " - Releasing lock for " . $db_name);
        $lock->release('background_tasks');
        $AcsfLock->releaseLock($db_name);
        // Remove lock file here.
      } else {
        $this->say($this->getCurrentTime() . ' - Background tasks pending, but this process could not
        acquire a lock. Another process is already running background commands.');
      }
    }
  }

  /**
   * Fetch current status.
   *
   * @command acsf-tools:background-tasks-status
   *
   * @usage acsf-tools:background-tasks-status
   *
   * @field-labels
   *   name: Name
   *   value: Value
   *   description: Description
   *
   * @default-fields name,value,description
   *
   * @filter-default-field name
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *
   * @bootstrap site
   * @throws \Exception
   */
  public function fetchBackgroundTasksStatus($options = ['format' => 'table', 'date' => NULL, 'iteration' => NULL]) {
    if ($options['date'] === NULL) {
      $options['date'] = date("Ymd", time());
    }

    // 0 passed in this option becomes FALSE. We want 0;
    if ($options['iteration'] === FALSE) {
      $options['iteration'] = 0;
    }

    $statuses = $this->getBackgroundTasksSitesStatus($options['date'], $options['iteration']);

    $results = array();

    $acsfLogs = new AcsfLogs();
    $logsFolder = $acsfLogs->getLastLogsFolder($options['date'], $options['iteration']);

    if ($logsFolder === NULL) {
      $this->say('Logs folder not found.');
    }
    else {
      $f = $acsfLogs::START_LOG_MARKER;
      $v = $this->AcsfExecute("cat ${logsFolder}${f} 2>/dev/null;", "");
      $results[] = [
        'name' => 'Background task started',
        'value' => trim($v),
        'description' => '',
      ];

      $results[] = [
        'name' => 'Sites',
        'value' => $statuses['totals']['sites'],
        'description' => 'How many sites are in total. Includes sites currently in ACSF and sites for which a log file exists. Analyzing old log files can skew this total, because some sites might not exist in ACSF anymore.',
      ];

      $results[] = [
        'name' => 'Successes',
        'value' => $statuses['totals']['success'],
        'description' => 'How many sites have been successfully processed without errors.',
      ];

      $results[] = [
        'name' => 'Successes after errors',
        'value' => $statuses['totals']['success_error'],
        'description' => 'How many sites have been successfully processed after errors in previous iterations.',
      ];

      $results[] = [
        'name' => 'Errors',
        'value' => $statuses['totals']['error'],
        'description' => 'How many sites have resulted in errors.',
      ];
    }

    $acsfFlags = new AcsfFlags($this->site_group, $this->site_env,'/mnt/gfs/');
    $gfsFlagsFolder = $acsfFlags->getFlagsFolder();

    $results[] = [
      'name' => 'Sites being processed',
      'value' => $statuses['totals']['lock'],
      'description' => 'Lock file count.',
    ];

    $results[] = [
      'name' => 'Sites with pending tasks',
      'value' => $statuses['totals']['flag'],
      'description' => 'Flag file count.',
    ];

    $results[] = [
      'name' => 'Pending',
      'value' => $statuses['totals']['pending'],
      'description' => 'Sites with tasks pending and processing not started yet.',
    ];

    $results[] = [
      'name' => 'Processing',
      'value' => $statuses['totals']['processing'],
      'description' => 'Sites with tasks processing first time.',
    ];

    $results[] = [
      'name' => 'Failed 1 time and Pending',
      'value' => $statuses['totals']['error_1_pending'],
      'description' => '',
    ];

    $results[] = [
      'name' => 'Failed 1 time and Processing',
      'value' => $statuses['totals']['error_1_processing'],
      'description' => '',
    ];

    $results[] = [
      'name' => 'Failed 2 times and Pending',
      'value' => $statuses['totals']['error_2_pending'],
      'description' => '',
    ];

    $results[] = [
      'name' => 'Failed 2 times and Processing',
      'value' => $statuses['totals']['error_2_processing'],
      'description' => '',
    ];

    $results[] = [
      'name' => 'Failed 3 times',
      'value' => $statuses['totals']['error_3'],
      'description' => '',
    ];

    if ($logsFolder !== NULL) {
      $f = $acsfLogs::FINISH_LOG_MARKER;
      $v = trim($this->AcsfExecute("cat ${logsFolder}${f} 2>/dev/null;", ""));
      $results[] = [
        'name' => 'Background task finished',
        'value' => empty($v) ? 'Not finished yet' : $v,
        'description' => '',
      ];
    }

    $results[] = [
      'name' => 'Flag files location',
      'value' => $gfsFlagsFolder,
      'description' => '',
    ];

    $results[] = [
      'name' => 'Log files location',
      'value' => $logsFolder,
      'description' => '',
    ];

    return new RowsOfFields($results);
  }

  /**
   * Fetch current status for each site.
   *
   * @command acsf-tools:background-tasks-sites-status
   *
   * @usage acsf-tools:background-tasks-sites-status
   *
   * @field-labels
   *   name: Name (DB role)
   *   domain: Domain
   *   success: Success log count
   *   error: Error log count
   *   flag: Flag file content
   *   lock: Lock file exists
   *   value: Other value
   *
   * @default-fields name,domain,success,error,flag,lock,value
   *
   * @filter-default-field name
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *
   * @bootstrap site
   * @throws \Exception
   */
  public function fetchBackgroundTasksSitesStatus($options = ['format' => 'table', 'date' => NULL, 'iteration' => NULL]) {
    if ($options['date'] === NULL) {
      $options['date'] = date("Ymd", time());
    }

    // 0 passed in this option becomes FALSE. We want 0;
    if ($options['iteration'] === FALSE) {
      $options['iteration'] = 0;
    }

    $statuses = $this->getBackgroundTasksSitesStatus($options['date'], $options['iteration']);

    $results = array();

    foreach ($statuses['sites'] as $i => $v) {
      $results[] = [
        'name' => $v['name'],
        'domain' => $v['domain'],
        'success' => $v['success'],
        'success_error' => $v['success_error'],
        'error' => $v['error'],
        'flag' => $v['flag'],
        'lock' => $v['lock'],
      ];
    }

    return new RowsOfFields($results);
  }

  /**
   * Execute a bash command in the ACSF filesystem.
   *
   * @param $command
   *   Commando to execute.
   * @param $msg
   *   Message to show when showing the results of the command.
   *
   * @return string
   */
  public function AcsfExecute($command, $msg) {
    $output = null;
    exec($command, $output, $return);

    return $msg . implode($output). PHP_EOL;
  }

  /**
   * A small wrapper to help managing current dates instead of hardcoding them.
   *
   * @return false|string
   */
  public function getCurrentTime()
  {
    return date("d-m-Y H:i:s");
  }

  /**
   * Check if we have tasks pending.
   *
   * @return bool
   * @throws \Exception
   */
  public function checkBackgroundTasksPending()
  {
    $pending = false;
    $AcsfFlags = new AcsfFlags($this->site_group, $this->site_env,'/mnt/gfs/');

    if (file_exists($AcsfFlags->getFlagfileName($this->getSiteID()))) {
      $retries = intval(file_get_contents($AcsfFlags->getFlagfileName($this->getSiteID())));

      if (is_int($retries) && $retries > 0) {
        $pending = true;
      }
    }

    return $pending;
  }

  /**
   * Check for sites with errors.
   *
   * @return bool
   * @throws \Exception
   */
  public function checkBackgroundTasksPendingAfterError($existsLock) {
    $pending = FALSE;

    $AcsfFlags = new AcsfFlags($this->site_group, $this->site_env,'/mnt/gfs/');
    if (file_exists($AcsfFlags->getFlagfileName($this->getSiteID())) && !$existsLock) {
      $retries = intval(file_get_contents($AcsfFlags->getFlagfileName($this->getSiteID())));

      if (is_int($retries) && (0 < $retries && $retries < 3)) {
        $pending = true;
      }
    }

    return $pending;
  }

  /**
   * Fetch current status for each site.
   *
   * @return array Array of information about each site and computed totals.
   */
  public function getBackgroundTasksSitesStatus($date = NULL, $iteration = NULL) {
    if ($date === NULL) {
      $date = date("Ymd", time());
    }

    $results = array();

    $acsfFlags = new AcsfFlags($this->site_group, $this->site_env, '/mnt/gfs/');
    $gfsFlagsFolder = $acsfFlags->getFlagsFolder();

    $acsfLogs = new AcsfLogs();
    $logsFolder = $acsfLogs->getLastLogsFolder($date, $iteration);

    if ($logsFolder === NULL) {
      $this->say('Logs folder not found.');
    }

    $acsfUtils = new AcsfToolsUtils();
    $acsfSites = $acsfUtils->getSites();

    $sites = array();

    foreach ($acsfSites as $db => $conf) {
      if (isset($conf['flags']['preferred_domain']) && $conf['flags']['preferred_domain'] === TRUE) {
        $sites[$db] = array(
          'success' => 0,
          'error' => 0,
          'domain' => reset($conf['domains']),
        );

        if (file_exists($gfsFlagsFolder . 'background_tasks_pending_' . $db)) {
          $sites[$db]['flag'] = file_get_contents($gfsFlagsFolder . 'background_tasks_pending_' . $db);
        }

        if (file_exists($gfsFlagsFolder . $db . '.lock')) {
          $sites[$db]['lock'] = 1;
        }
        else {
          $sites[$db]['lock'] = 0;
        }
      }
    }

    $successLogSuffix = '.success.log';
    $errorLogSuffix = '.error.log';

    $logs = $logsFolder !== NULL ? scandir($logsFolder) : array();

    foreach ($logs as $log) {
      // Check if it is a success log.
      if (substr_compare($log, $successLogSuffix, strlen($log)-strlen($successLogSuffix), strlen($successLogSuffix)) === 0) {
        $type = 'success';
      }
      // Check if it's an error log.
      elseif (substr_compare($log, $errorLogSuffix, strlen($log)-strlen($errorLogSuffix), strlen($errorLogSuffix)) === 0) {
        $type = 'error';
      }
      else {
        $type = 'other';
      }

      if ($type == 'success' || $type == 'error') {
        $split = explode('-', $log);
        $siteDb = $split[0];

        // We can have old log files for sites which are not on ACSF anymore.
        if (!isset($sites[$siteDb])) {
          $sites[$siteDb] = array(
            'success' => 0,
            'error' => 0,
          );
        }

        if ($type == 'success') {
          $sites[$siteDb]['success'] += 1;
        }
        elseif ($type == 'error') {
          $sites[$siteDb]['error'] += 1;
        }

      }
    }

    $totals = array(
      'success' => 0,
      'error' => 0,
      'success_error' => 0,
      'flag' => 0,
      'lock' => 0,
      'error_1_pending' => 0,
      'error_2_pending' => 0,
      'error_3' => 0,
      'error_1_processing' => 0,
      'error_2_processing' => 0,
      'pending' => 0,
    );

    foreach ($sites as $site => $v) {

      if (file_exists($gfsFlagsFolder . 'background_tasks_pending_' . $site)) {
        $v['flag'] = file_get_contents($gfsFlagsFolder . 'background_tasks_pending_' . $site);
      }

      if (file_exists($gfsFlagsFolder . $site . '.lock')) {
        $v['lock'] = 1;
      }
      else {
        $v['lock'] = 0;
      }

      $results['sites'][] = [
        'name' => $site,
        'domain' => $v['domain'],
        'success' => $v['success'],
        'error' => $v['error'],
        'flag' => $v['flag'],
        'lock' => $v['lock'],
      ];

      if ($v['lock'] == 0 && $v['flag'] == '3') {
        $totals['pending']++;
      }

      if ($v['lock'] == 0 && $v['flag'] == '2') {
        $totals['error_1_pending']++;
      }

      if ($v['lock'] == 0 && $v['flag'] == '1') {
        $totals['error_2_pending']++;
      }

      if ($v['lock'] == 1 && $v['flag'] == '2') {
        $totals['processing']++;
      }

      if ($v['lock'] == 1 && $v['flag'] == '1') {
        $totals['error_1_processing']++;
      }

      if ($v['lock'] == 1 && $v['flag'] == '0') {
        $totals['error_2_processing']++;
      }

      if ($v['error'] == 3) {
        $totals['error_3']++;
      }

      if ($v['success'] > 0 && $v['error'] == 0) {
        $totals['success']++;
      }

      if ($v['error'] > 0 && $v['success'] == 0) {
        $totals['error']++;
      }

      if ($v['error'] > 0 && $v['success'] > 0) {
        $totals['success_error']++;
      }

      if ($v['flag'] > 0) {
        $totals['flag']++;
      }

      if ($v['lock'] > 0) {
        $totals['lock']++;
      }
    }

    $results['totals'] = $totals;
    $results['totals']['sites'] = count($sites);

    return $results;
  }

  /**
   * Get the site ID.
   *
   * @return NULL|String
   * @throws \Exception
   */
  public function getSiteID()
  {
    return $GLOBALS['gardens_site_settings']['conf']['acsf_db_name'];
  }

}
