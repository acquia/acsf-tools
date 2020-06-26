<?php

namespace Drush\Commands\acsf_tools;

class AcsfFlags {

  protected $flagsFolder;
  protected $site_env;
  protected $site_group;

  /**
   * AcsfFlags constructor.
   *
   * @param $site_group
   * @param $site_env
   * @param string $rootFolder
   *   Root folder including trailing slash where to store the flags.
   */
  public function __construct($site_group, $site_env, $rootFolder = '/tmp/') {
    $this->site_env = $site_env;
    $this->site_group = $site_group;
    $this->flagsFolder = $rootFolder . $site_group . '.' . $site_env . '/flags/';

    return $rootFolder . $site_group . '.' . $site_env . '/flags/';
  }

  /**
   * Get flag counter.
   *
   * @return int
   * @throws \Exception
   */
  public function getFlagCounter($id)
  {
    $retries = false;

    if (file_exists($this->getFlagFileName($id))) {
      $retries = intval(file_get_contents($this->getFlagFileName($id)));
    }

    return $retries;
  }

  /**
   * Decrease flag counter.
   *
   * @return bool
   * @throws \Exception
   */
  public function decreaseFlagCounter($id)
  {
    $status = false;
    if (file_exists($this->getFlagFileName($id))) {
      // Decrease the counter.
      $retries = intval(file_get_contents($this->getFlagFileName($id)));
      $retries = $retries - 1;
      file_put_contents($this->getFlagFileName($id), $retries);

      $status = true;
    }

    return $status;
  }

  /**
   * Remove settings file.
   *
   * @throws \Exception
   */
  public function removeFlagFile($id)
  {
    // Remove the file.
    unlink($this->getFlagFileName($id));

    $fileManager = new AcsfFileManager();
    $flagsFolder = $this->getFlagsFolder();

    // 1. Check if flags folder is empty.
    if ($fileManager->folderEmpty($flagsFolder)) {
      $AcsfLogs = new AcsfLogs();

      // 2. If so, write the FINISH marker in the logs folder.
      $AcsfLogs->createFinishMarker();

      // 3. Compress and send the logs in $flagsFolder.
      $AcsfLogs->emailCompressedLogs($flagsFolder);
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
  public function getFlagFileName($id)
  {
    return $this->getFlagsFolder() . 'background_tasks_pending_' . $id;
  }

  /**
   * Return the flags folder.
   *
   * @return string
   *   Flags folder.
   */
  public function getFlagsFolder()
  {
    return $this->flagsFolder;
  }
}
