<?php

namespace Drush\Commands\acsf_custom;

class AcsfFlags {

  protected $flagsFolder;
  protected $site_env;
  protected $site_group;
  protected $id;

  /**
   * AcsfFlags constructor.
   *
   * @param $site_group
   * @param $site_env
   * @param $identifier
   * @param string $rootFolder
   *   Root folder including trailing slash where to store the flags.
   */
  public function __construct($site_group, $site_env, $id, $rootFolder = '/tmp/') {
    $this->site_env = $site_env;
    $this->site_group = $site_group;
    $this->flagsFolder = $rootFolder . $site_group . '.' . $site_env . '/flags/';
    $this->id = $id;

    return $rootFolder . $site_group . '.' . $site_env . '/flags/';
  }

  /**
   * Get flag counter.
   *
   * @return int
   * @throws \Exception
   */
  public function getFlagCounter()
  {
    $retries = false;

    if (file_exists($this->getFlagFileName())) {
      $retries = intval(file_get_contents($this->getFlagFileName()));
    }

    return $retries;
  }

  /**
   * Decrease flag counter.
   *
   * @return bool
   * @throws \Exception
   */
  public function decreaseFlagCounter()
  {
    $status = false;
    if (file_exists($this->getFlagFileName())) {
      // Decrease the counter.
      $retries = intval(file_get_contents($this->getFlagFileName()));
      $retries = $retries - 1;
      file_put_contents($this->getFlagFileName(), $retries);

      $status = true;
    }

    return $status;
  }

  /**
   * Remove settings file.
   *
   * @throws \Exception
   */
  public function removeFlagFile()
  {
    // Remove the file.
    unlink($this->getFlagFileName());

    $fileManager = new AcsfFileManager();
    $flagsFolder = $this->getFlagsFolder();

    // 1. Check if flags folder is empty.
    if ($fileManager->folderEmpty($flagsFolder)) {
      $AcsfLogs = new AcsfLogs();

      // 2. If so, write the FINISH marker in the logs folder.
      $AcsfLogs->createFinishMarker();
      // 3. TODO (MAYBE): cleanup method to find any lock files.
      // 4. TODO (MAYBE): move locks to a safe place and log it.
    }
  }

  /**
   * Get log file name.
   *
   * @param $dbname
   *
   * @return string
   * @throws \Exception
   */
  public function getFlagFileName()
  {
    return $this->getFlagsFolder() . 'post_deployment_tasks_pending_' . $this->id;
  }

  /**
   * Return the flags folder.
   *
   * @param $site_group
   *   Group site, normally AH_SITE_GROUP
   * @param $site_env
   *   Environment, normally AH_SITE_ENVIRONMENT
   *
   * @return string
   *   Flags folder.
   */
  public function getFlagsFolder()
  {
    return $this->flagsFolder;
  }
}
