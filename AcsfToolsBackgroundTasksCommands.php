<?php

namespace Drush\Commands\acsf_tools;

use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\Sql\SqlBase;
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

  /**
   * Initisalise variables that are going to be needed.
   *
   * We are not using a constructor instead for performance reasons. We can
   * also call this only when strictly necessary.
   */
  public function initialise()
  {
    if ($this->site_group == NULL) {
      $this->site_group = $_ENV['AH_SITE_GROUP'];
    }

    if ($this->site_env == NULL) {
      $this->site_env = $_ENV['AH_SITE_ENVIRONMENT'];
    }
  }

  /**
   * Mark a site with post deployment tasks pending.
   *
   * @command acsf-tools:set-background-tasks-pending
   *
   * @option retry-count
   *   How many times to retry if post deployment tasks exit with an error.
   *
   * @bootstrap configuration
   * @throws \Exception
   */
  public function setBackgroundTasksPending($options = ['retry-count' => 3, 'rootfolder' => null,])
  {
    $this->initialise();
    $fileManager = new AcsfFileManager();

    $AcsfFlags = new AcsfFlags($this->site_group, $this->site_env, '/mnt/gfs/');
    $flagsFolder = $AcsfFlags->getFlagsFolder();

    if (!file_exists($flagsFolder)) {
      $fileManager->createFolder($flagsFolder);
    }

    if ($this->getSiteID()) {
      // Does a lock exist?
      $AcsfLock = new AcsfLock($AcsfFlags->getFlagsFolder());
      if ($AcsfLock->doesLockExist($this->getSiteID())) {
        $fileManager->createFile($AcsfFlags->getFlagfileName($this->getSiteID()), $options['retry-count']);
      }

    }
  }

  /**
   * Runs post deployment tasks.
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

    // TODO: DEPLOYMENT PENDING && NO SITES WITH ERRORS
    // todo: if queue X, then check errored sites, if not just the normal check.
    // Check the flag file exists / Value is 0<X<3
    // Check lock file DOES NOT exist
    if ($options['queue'] == 'default') {
      $process = $this->checkDeploymentPending();
    }
    else {
      $process = $this->checkDeploymentPendingAfterError($AcsfLock->doesLockExist($this->getSiteID()));
    }

    if ($process != TRUE) {
      $this->say('No post deployment tasks pending.');
    } else {
      $AcsfLogs = new AcsfLogs();
      // Bootstrap Drupal.
      try {
        $this->say("\n" . $this->getCurrentTime() . " - Starting post deployment task for " . 'no_id');
        if (!Drush::bootstrapManager()
          ->doBootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
          $this->say("Unable to bootstrap Drupal.");
          throw new \Exception(dt($this->getCurrentTime() . ' - Unable to bootstrap Drupal.'));
        }
        // POTENTIALLY TODO: code after this
      } catch (Exception $exception) {
        $this->say("Exception during Drupal bootstrap.");
        $message = $this->getCurrentTime() . " - Exception during Drupal bootstrap: " . $exception;
        // TODO: which site is failing here, any id possible would be good.
        $AcsfLogs->writeLog($message, 'no_id', 'error');

        // If Drupal can not be boostrapped, stop trying to run post deployment tasks.
        $AcsfFlags->removeFlagFile($this->getSiteID());
        $AcsfLock->releaseLock($this->getSiteID());
        throw $exception;
      }

      $lock = \Drupal::lock();
      if ($lock->acquire('post_deployment_tasks', $lockTimeout)) {
        // Get some configs from the bootstrapped Drupal.
        $self = $this->siteAliasManager()->getSelf();
        $selfConfig = $self->exportConfig()->export();

        $uri = $selfConfig['options']['uri'];
        $root = $selfConfig['options']['root'];

        $this->initialise();
        $site_group = $this->site_group;
        $site_env = $this->site_env;
        $db_name = $this->getSiteID();

        // TODO: Add lock file here.
        $AcsfLock = new AcsfLock($AcsfFlags->getFlagsFolder());
        $AcsfLock->getLock($db_name);

        // Decrease the flag counter.
        $AcsfFlags->decreaseFlagCounter($this->getSiteID());
        $counter = $AcsfFlags->getFlagCounter($this->getSiteID());

        $this->say($this->getCurrentTime() . ' - Starting post deployment tasks.');
        $this->say('Retries left (excluding this run): ' . $counter);

        try {
          if ($options['script'] === null) {
            $options['script'] = "$root/../scripts/post-deployment.sh";
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

            // @TODO move logging of $process output to a separate method.
            $AcsfLogs->writeLog("Post deployment script finished successfully:\n"
              . "Exit code: " . $exitCode
              . "Script output: " . $data
              . "Script error output:\n$errorOutput", $db_name, 'success');

            $AcsfFlags->removeFlagFile($this->getSiteID());
            $AcsfLock->releaseLock($db_name);
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
            $AcsfFlags->removeFlagFile($this->getSiteID());
            $AcsfLock->releaseLock($db_name);
          }
        } catch (Exception $exception) {
          $AcsfLogs->writeLog($this->getCurrentTime() . " - Exception happened: " . $exception, $db_name);
        }

        $this->say($this->getCurrentTime() . " - Releasing lock for " . $db_name);
        $lock->release('post_deployment_tasks');
        $AcsfLock->releaseLock($db_name);
        // Remove lock file here.
      } else {
        $this->say($this->getCurrentTime() . ' - Deployment tasks pending, but this process could not
        acquire a lock. Another process is already running post deployment commands.');
      }
    }
  }

  /**
   * Fetch current status.
   *
   * @command acsf-tools:post-deployment-tasks-status
   *
   * @usage acsf-tools:post-deployment-tasks-status
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
   * @throws \Exception
   */
  public function fetchDeploymentStatus($options = ['format' => 'table', 'date' => NULL, 'iteration' => NULL]) {
    if ($options['date'] === NULL) {
      $options['date'] = date("Ymd", time());
    }

    // 0 passed in this option becomes FALSE. We want 0;
    if ($options['iteration'] === FALSE) {
      $options['iteration'] = 0;
    }

    $results = array();

    // We need to initialise folders.
    $this->initialise();

    $acsfLogs = new AcsfLogs();
    $logsFolder = $acsfLogs->getLogsFolder($options['date'], $options['iteration']);

    if ($logsFolder === NULL) {
      $this->say('Logs folder not found.');
    }
    else {
      $f = $acsfLogs::START_LOG_MARKER;
      $v = $this->AcsfExecute("cat ${logsFolder}${f} 2>/dev/null;", "");
      $results[] = [
        'name' => 'Deployment started',
        'value' => trim($v),
        'description' => '',
      ];

      $v = $this->AcsfExecute("cd $logsFolder; ls ./*.success.log 2>/dev/null | wc -l;", "");
      $results[] = [
        'name' => 'Successes',
        'value' => trim($v),
        'description' => 'How many sites have been successfully processed. Counts success log files.',
      ];

      $v = $this->AcsfExecute("cd $logsFolder;ls ./*.error.log 2>/dev/null | wc -l;", "");
      $results[] = [
        'name' => 'Errors',
        'value' => trim($v),
        'description' => 'How many tasks executions have resulted in errors. Counts error log files.',
      ];
    }

    $acsfFlags = new AcsfFlags($this->site_group, $this->site_env,'/mnt/gfs/');
    $gfsFlagsFolder = $acsfFlags->getFlagsFolder();

    $v = $this->AcsfExecute("cd $gfsFlagsFolder;ls ./*.lock 2>/dev/null | wc -l;", "");
    $results[] = [
      'name' => 'Sites being processed',
      'value' => trim($v),
      'description' => 'Lock file count.',
    ];

    $v = $this->AcsfExecute("ls $gfsFlagsFolder | grep -v lock | wc -l;", "");
    $results[] = [
      'name' => 'Sites with pending tasks',
      'value' => trim($v),
      'description' => 'Flag file count.',
    ];

    $v = $this->AcsfExecute("grep 3 $gfsFlagsFolder/* 2>/dev/null | wc -l;", "");
    $results[] = [
      'name' => 'Pending',
      'value' => trim($v),
      'description' => 'Sites with tasks pending and processing not started yet.',
    ];

    $v = $this->AcsfExecute("grep 2 $gfsFlagsFolder/* 2>/dev/null | wc -l;", "");
    $results[] = [
      'name' => 'Processing now or failed once',
      'value' => trim($v),
      'description' => 'Sites with tasks being executed first time or have failed once.',
    ];

    $v = $this->AcsfExecute("grep 1 $gfsFlagsFolder/* 2>/dev/null | wc -l;", "");
    $results[] = [
      'name' => 'Failed two times',
      'value' => trim($v),
      'description' => 'Sites with tasks processing failed two times.',
    ];

    $v = $this->AcsfExecute("grep 0 $gfsFlagsFolder/* 2>/dev/null | wc -l;", "");
    $results[] = [
      'name' => 'Failed two times and processing',
      'value' => trim($v),
      'description' => 'Sites with tasks processing failed two times and processing is happening now.',
    ];

    $v = $this->AcsfExecute("cat $gfsFlagsFolder/* 2>/dev/null;", "");
    $results[] = [
      'name' => 'Content of flag files',
      'value' => trim($v),
      'description' => "0 - third processing started after 2 errors" . PHP_EOL .
                       "1 - second processing started after 1 error" . PHP_EOL .
                       "2 - first processing started or first processing finished with an error (second processing has not been started)" . PHP_EOL .
                       "3 - processing has not started",
    ];

    if ($logsFolder !== NULL) {
      $f = $acsfLogs::FINISH_LOG_MARKER;
      $v = $this->AcsfExecute("cat ${logsFolder}${f} 2>/dev/null;", "");
      $results[] = [
        'name' => 'Deployment finished',
        'value' => trim($v),
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
   * @command acsf-tools:post-deployment-sites-status
   *
   * @usage acsf-tools:post-deployment-sites-status
   *
   * @field-labels
   *   name: Name
   *   success: Success log count
   *   error: Error log count
   *   flag: Flag file content
   *   lock: Lock file exists
   *   value: Other value
   *
   * @default-fields name,success,error,flag,lock,value
   *
   * @filter-default-field name
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *
   * @throws \Exception
   */
  public function fetchDeploymentSitesStatus($options = ['format' => 'table', 'date' => NULL, 'iteration' => NULL]) {
    if ($options['date'] === NULL) {
      $options['date'] = date("Ymd", time());
    }

    // 0 passed in this option becomes FALSE. We want 0;
    if ($options['iteration'] === FALSE) {
      $options['iteration'] = 0;
    }

    $results = array();

    // We need to initialise folders.
    $this->initialise();

    $acsfFlags = new AcsfFlags($this->site_group, $this->site_env, '/mnt/gfs/');
    $gfsFlagsFolder = $acsfFlags->getFlagsFolder();

    $acsfLogs = new AcsfLogs();
    $logsFolder = $acsfLogs->getLastLogsFolder($options['date'], $options['iteration']);

    echo 'flagsfolder:' . $gfsFlagsFolder;


    if ($logsFolder === NULL) {
      $this->say('Logs folder not found.');
    }
    else {
      // $acsfUtils = new AcsfToolsUtils();
      $sites = array();

      $successLogSuffix = '.success.log';
      $errorLogSuffix = '.error.log';
      $logs = scandir($logsFolder);

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

          if (file_exists($gfsFlagsFolder . $siteDb)) {
            $sites[$siteDb]['flag'] = file_get_contents($gfsFlagsFolder . $siteDb);
          }

          if (file_exists($gfsFlagsFolder . $siteDb . '.lock')) {
            $sites[$siteDb]['lock'] = 1;
          }
        }
      }

      $totals = array(
        'success' => 0,
        'error' => 0,
        'success_error' => 0,
        'flag' => 0,
        'lock' => 0,
      );

      foreach ($sites as $site => $v) {
        $results[] = [
          'name' => $site,
          'success' => $v['success'],
          'error' => $v['error'],
          'flag' => $v['flag'],
          'lock' => $v['lock'],
          'value' => '',
        ];

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

      $results[] = [
        'name' => 'Total sites with success log',
        'value' => $totals['success'],
      ];

      $results[] = [
        'name' => 'Total sites with error log',
        'value' => $totals['error'],
      ];

      $results[] = [
        'name' => 'Total sites with success and error log',
        'value' => $totals['success_error'],
      ];

      $results[] = [
        'name' => 'Total sites with tasks pending',
        'value' => $totals['flag'],
      ];

      $results[] = [
        'name' => 'Total sites with tasks being executed',
        'value' => $totals['lock'],
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
  public function checkDeploymentPending()
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
  public function checkDeploymentPendingAfterError($existsLock) {
    $pending = FALSE;

    $AcsfFlags = new AcsfFlags($this->site_group, $this->site_env,'/mnt/gfs/');
    if (file_exists($AcsfFlags->getFlagfileName($this->getSiteID())) && $existsLock) {
      $retries = intval(file_get_contents($AcsfFlags->getFlagfileName($this->getSiteID())));

      if (is_int($retries) && (0 < $retries && $retries < 3)) {
        $pending = true;
      }
    }

    return $pending;
  }

  /**
   * Get the site ID.
   *
   * @return NULL|String
   * @throws \Exception
   */
  // TODO: siteID can clash with ACSF
  public function getSiteID()
  {
    // This could benefit of caching.
    $dbname = null;
    if ($sql = SqlBase::create([])) {
      $db_spec = $sql->getDbSpec();
      $dbname = isset($db_spec['database']) ? $db_spec['database'] : null;
    }

    return $dbname;
  }

}
