<?php

namespace Drush\Commands\acsf_custom;

use Drush\Commands\DrushCommands;

class AcsfLogs extends DrushCommands {

  const START_LOG_MARKER = "post_deployment_tasks.start.log";
  const FINISH_LOG_MARKER = "post_deployment_tasks.finish.log";

  private $root_logs_folder = NULL;
  private $site_env;
  private $site_group;

  public function __construct() {
    parent::__construct();

    $this->site_group = $_ENV['AH_SITE_GROUP'];
    $this->site_env = $_ENV['AH_SITE_ENVIRONMENT'];

    $this->setRootLogsFolder("/mnt/gfs/$this->site_env.$this->site_group/logs/large_scale_cron_" . date("Ymd", time()));
  }

  /**
   * Initialise root folder.
   *
   * @param $folder
   */
  public function setRootLogsFolder($folder)
  {
    $this->root_logs_folder = $folder;
  }

  /**
   * Initialise root folder.
   *
   * @param $folder
   *
   * @return null
   */
  public function getRootLogsFolder()
  {
    return $this->root_logs_folder;
  }

  /**
   * Get folder where we'll be storing the logs.
   *
   * @param int $iteration
   *
   * @return string
   * @throws \Exception
   */
  public function getLogsFolder($iteration = 0, $createFolder = TRUE) {
    if (!is_integer($iteration)) {
      throw new \Exception("Iteration must be an integer");
    }

    $logsFolder = $this->getRootLogsFolder() . "_$iteration/";

    if (!file_exists($logsFolder) && $createFolder == TRUE) {
      //      $this->writeLog($this->getCurrentTime() . " - Starting deployment. ", $this::START_LOG_MARKER);
      mkdir($logsFolder, 0770, true);

      // Write the start log marker, so we know deployment has started.
      $this->yell("Creating start marker:: " . $logsFolder . $this::START_LOG_MARKER);

      $fileManager = new AcsfFileManager();
      $fileManager->createFile($logsFolder . '/' . $this::START_LOG_MARKER, date("c", time()));
    }
    else {
      if (file_exists($logsFolder . '/' . $this::FINISH_LOG_MARKER)) {
        // FINISH_LOG_MARKER indicate that a previous deployment has finished.
        // If so, then increase iteration variable $iteration.
        $logsFolder = $this->getLogsFolder($iteration + 1, $createFolder);

        // TODO: Send files zipped in the logs folder.
      }
    }

    return $logsFolder;
  }

  /**
   * Finish gracefully the deployment.
   *
   * @throws \Exception
   */
  public function createFinishMarker() {
    $this->yell("Creating finish marker:: " . $this->getLogsFolder() . $this::FINISH_LOG_MARKER);
    $fileManager = new AcsfFileManager();
    $fileManager->createFile($this->getLogsFolder() . $this::FINISH_LOG_MARKER, date("c", time()));
  }

  /**
   * Write message in the log file.
   *
   * @param $message
   * @param $identifier
   * @param string $type
   * @param bool $verbose
   * @param int $timestamp
   *
   * @throws \Exception
   */
  public function writeLog($message, $identifier, $type = 'error', $verbose = false, $timestamp = null)
  {
    if ($timestamp === null) {
      $timestamp = time();
    }
    $date = date("Ymd", $timestamp);
    $AcsfLogs = new AcsfLogs();
    $folder = $AcsfLogs->getLogsFolder(0, FALSE);

    $time = date("H-i-s", $timestamp);

    // Format: dbxxx-20200511.error.log
    $file = $identifier . '-' . $time . '.' . $type . '.' . 'log';

    // Write on a log file.
    file_put_contents($folder . $file, $date . $time . ' - ' .
      $message . " ----------------------- " . PHP_EOL, FILE_APPEND);

    // And if marked as so, output in console as well.
    if ($verbose) {
      $this->yell($message);
    }

    if ($type == 'error' || $type == 'success') {
      // TODO: On success, concatenate instead in a success-log and email later.
      $this->emailLogs($message, " ACSF build results: " . $type);
    }
  }

  /**
   * Email logs whenever there is an error.
   *
   * @param $emailList
   * @return void
   */
  public function emailLogs($message, $subject, array $emailList = null)
  {
    if ($emailList == null) {
      // TODO: Move to secrets.settings
      require_once("../config.php");
    }

    // Get and remove the first email off the list.
    $to = array_shift($email_list);

    $plain_email_list = implode(",", $email_list);
    $headers = "From: acsf-deployment-error@acquia.com" . "\r\n" .
      "CC: $plain_email_list";
    mail($to, $subject, $message, $headers);
  }

}
