<?php

namespace Drush\Commands\acsf_tools;

use PHPMailer\PHPMailer\PHPMailer;

class AcsfLogs extends AcsfToolsUtils {

  const START_LOG_MARKER = "background_tasks.start.log";
  const FINISH_LOG_MARKER = "background_tasks.finish.log";

  private $root_logs_folder = NULL;
  private $site_env;
  private $site_group;
  private $root_folder_no_date;

  public function __construct() {
    parent::__construct();

    $this->site_group = $_ENV['AH_SITE_GROUP'];
    $this->site_env = $_ENV['AH_SITE_ENVIRONMENT'];

    $this->setRootLogsFolder("/mnt/gfs/$this->site_group.$this->site_env/logs/large_scale_cron_" . date("Ymd", time()));
    $this->root_folder_no_date = "/mnt/gfs/$this->site_group.$this->site_env/logs/large_scale_cron_";
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
   */
  public function setRootNoDateLogsFolder($folder)
  {
    $this->root_folder_no_date = $folder;
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
      mkdir($logsFolder, 0770, true);

      // Write the start log marker, so we know background tasks activity have started.
      $this->yell("Creating start marker:: " . $logsFolder . $this::START_LOG_MARKER);

      $fileManager = new AcsfFileManager();
      $fileManager->createFile($logsFolder . '/' . $this::START_LOG_MARKER, date("c", time()));
    }
    elseif (!file_exists($logsFolder)) {
      $logsFolder = '';
    }
    elseif (file_exists($logsFolder . '/' . $this::FINISH_LOG_MARKER)) {
      // FINISH_LOG_MARKER indicate that a previous background tasks activity has finished.
      // If so, then increase iteration variable $iteration.
      $logsFolder = $this->getLogsFolder($iteration + 1, $createFolder);
    }

    return $logsFolder;
  }

  /**
   * Return last date or specify a given date.
   *
   * @param null $date
   * @param null $iteration
   * @param bool $searchRecursively
   *
   * @return string
   */
  public function getLastLogsFolderRecursive($date = NULL, $iteration = NULL, $searchRecursively = TRUE) {
    if ($iteration == NULL) {
      $iteration = 0;
    }

    if ($date == NULL) {
      $date = date("Ymd", time());
    }
    $logsFolder = $this->root_folder_no_date . $date . "_$iteration/";

    if ($searchRecursively == TRUE) {
      if (!file_exists($logsFolder) && $iteration == 0) {
        $logsFolder = '';
      }
      elseif (!file_exists($logsFolder) && $iteration > 0) {
        $iteration--;
        $logsFolder = $this->root_folder_no_date . $date . "_$iteration/";
      }
      elseif (file_exists($logsFolder)){
        $logsFolder = $this->getLastLogsFolderRecursive($date, $iteration + 1);
      }
    }

    return $logsFolder;
  }

    /**
   * Get folder where last logs are stored.
   *
   * @param $site_group
   * @param $site_env
   * @param $date
   * @param int $iteration
   *
   * @return string
   */
  public function getLastLogsFolder($date = NULL, $iteration = NULL) {
    $site_env = $this->site_env;
    $site_group = $this->site_group;

    if ($date == NULL) {
      $date = date("Ymd", time());
    }

    $logsFolder = NULL;
    $prefix = "/mnt/gfs/$site_group.$site_env/logs/large_scale_cron_" . $date . "_";

    if ($iteration !== NULL) {
      $logsFolder = $prefix . $iteration . "/";
    }
    else {
      for ($i=0; $i<99999; $i++) {
        $currentLogsFolder = $prefix . $i . "/";
        $nextLogsFolder = $prefix . ($i + 1) . "/";

        if (file_exists($currentLogsFolder) && !file_exists($nextLogsFolder)) {
          $logsFolder = $currentLogsFolder;
          break;
        }
        elseif (file_exists($nextLogsFolder)) {
          continue;
        }
        else {
          break;
        }
      }
    }

    return $logsFolder;
  }

  /**
   * Finish gracefully the background task.
   *
   * @throws \Exception
   */
  public function createFinishMarker() {
    $this->yell("Creating finish marker:: " . $this->getLogsFolder(0, FALSE) . $this::FINISH_LOG_MARKER);

    $fileManager = new AcsfFileManager();
    $fileManager->createFile($this->getLogsFolder(0, FALSE) . $this::FINISH_LOG_MARKER, date("c", time()));
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
      $secretsFolder = '/mnt/files/' . $this->site_group . '.' . $this->site_env . '/';
      $config = $this->getRestConfig($secretsFolder);

      $email_list = $config->email_logs_to;
    }

    $headers = "From: no-reply-acsf-backgroundtasks-logs@example.com" . "\r\n" .
      "CC: ";
    mail($email_list, $subject, $message, $headers);
  }

  /**
   * @param $folder
   * @param $destination
   *
   * @throws \PHPMailer\PHPMailer\Exception
   */
  public function emailCompressedLogs($folder, $destination = NULL, $emailList = NULL, $smtp = NULL) {
    if ($destination == NULL) {
      $destination = $folder;
    }

    $dirbase = dirname($destination);
    $file = basename($destination);

    $compressedFile = "$dirbase/$file.tar.gz";

    exec("tar -cjf $compressedFile -C $destination .");

    $mail = new PHPMailer();

    if ($smtp == NULL) {
      $mail->isSendmail();
    }

    if ($emailList == NULL) {
      $secretsFolder = '/mnt/files/' . $this->site_group . '.' . $this->site_env . '/';
      $config = $this->getRestConfig($secretsFolder);
      $emailList = $config->email_logs_to;
      $from = $config->email_logs_from;
    }

    $emailList = explode(',', $emailList);
    echo PHP_EOL . 'email to:: ' . $to = array_shift($emailList);

    $mail->addReplyTo($to);
    $mail->addAddress($to);
    $mail->setFrom($from);
    foreach ($emailList as $emailcc) {
      $mail->addAddress($emailcc);
    }

    $mail->isHTML(true);
    $mail->Subject = "Your log is attached and/or ready for download";
    $mail->Body = 'Your log is ready for download from:' . $compressedFile;
    $mail->AltBody = 'Your log is ready for download';
    $mail->AddAttachment( $compressedFile, $compressedFile, 'base64', 'application/octet-stream' );

    if(!$mail->send()){
      $this->yell( PHP_EOL . 'Message could not be sent. ' . ' ::::-> Mailer Error: ' . $mail->ErrorInfo);
      echo PHP_EOL . 'Message could not be sent.';
      echo'Mailer Error: ' . $mail->ErrorInfo;
    }else {
      $this->yell('Message has been sent');
      echo  PHP_EOL . 'Message has been sent';
    }
  }

}
