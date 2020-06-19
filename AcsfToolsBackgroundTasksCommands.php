<?php

namespace Drush\Commands\acsf_custom;

use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
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

    $AcsfFlags = new AcsfFlags($this->site_group, $this->site_env, $this->getSiteID(), '/tmp/gfs/');
    $flagsFolder = $AcsfFlags->getFlagsFolder();

    if (!file_exists($flagsFolder)) {
      $fileManager->createFolder($flagsFolder);
    }

    if ($this->getSiteID()) {
      // Does a lock exist?
      $AcsfLock = new AcsfLock($AcsfFlags->getFlagsFolder());
      if ($AcsfLock->doesLockExist($this->getSiteID())) {
        $fileManager->createFile($AcsfFlags->getFlagfileName(), $options['retry-count']);
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
  // TODO: rename to runBbackgroundTasks
  public function runBackgroundTasks($options = [
    'timeout' => 1800,
    'script' => null,
    'rootfolder' => null,
    'queue' => 'default'
  ])
  {
    // Defaults to 30 mintues.
    $lockTimeout = $options['timeout'];

    $AcsfFlags = new AcsfFlags($this->site_group, $this->site_env, $this->getSiteID(), '/tmp/gfs/');
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
        $AcsfFlags->removeFlagFile();
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
        $AcsfFlags->decreaseFlagCounter();
        $counter = $AcsfFlags->getFlagCounter();

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

            $AcsfFlags->removeFlagFile();
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
            $AcsfFlags->removeFlagFile();
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
   * Fetch current status deployment.
   *
   * @command acsf-tools:background-tasks-status
   * @usage acsf-tools:background-tasks-status
   *   Testing.
   * @throws \Exception
   */
  public function fetchDeploymentStatus() {
    // We need to initialise folders.
    $this->initialise();

    $AcsfLogs = new AcsfLogs();
    $logsFolder = $AcsfLogs->getLogsFolder(0,FALSE);

    $AcsfFlags = new AcsfFlags($this->site_group, $this->site_env, $this->getSiteID(),'/tmp/gfs/');
    $gfsFlagsFolder = $AcsfFlags->getFlagsFolder();

    // TODO: REMOVE ECHO WITH YELL.
    echo PHP_EOL . "| ====== ONGOING DEPLOYMENT STATUS ======= " . PHP_EOL;
    echo $this->AcsfExecute("cd $logsFolder; ls ./*.success.log 2>/dev/null | wc -l;", "| Success log file count: ");
    echo $this->AcsfExecute("cd $logsFolder;ls ./*.error.log 2>/dev/null | wc -l;", "| Error log file count: ");
    echo $this->AcsfExecute("cd $gfsFlagsFolder;ls ./*.lock 2>/dev/null | wc -l;", "| Lock file count: ");

    // TODO: is the site still in maintenance mode?

    echo $this->AcsfExecute("ls $gfsFlagsFolder | wc -l; ", "| Flag file count: " );

    echo $this->AcsfExecute("cat $gfsFlagsFolder/* 2>/dev/null;", "| Content of flag files: ");
    echo PHP_EOL;
    echo "| ---- NOTE ON FLAG FILES: ----" . PHP_EOL;
    echo "| 1 - second processing started after an error" . PHP_EOL;
    echo "| 2 - first processing started or first processing finished with an error (second processing has not been started)" . PHP_EOL;
    echo "| 3 - means processing has not started" . PHP_EOL;
    echo " ---------------------------------------- " . PHP_EOL;

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

    $AcsfFlags = new AcsfFlags($this->site_group, $this->site_env,$this->getSiteID(),'/tmp/gfs/');
    if (file_exists($AcsfFlags->getFlagfileName())) {
      $retries = intval(file_get_contents($AcsfFlags->getFlagfileName()));

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

    $AcsfFlags = new AcsfFlags($this->site_group, $this->site_env,$this->getSiteID(),'/tmp/gfs/');
    if (file_exists($AcsfFlags->getFlagfileName()) && $existsLock) {
      $retries = intval(file_get_contents($AcsfFlags->getFlagfileName()));

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
