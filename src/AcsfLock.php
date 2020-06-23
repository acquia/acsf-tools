<?php

namespace Drush\Commands\acsf_tools;

class AcsfLock {

  private $flagsFolder = "/tmp/";

  /**
   * AcsfLock constructor.
   *
   * @param $folder
   */
  public function __construct($folder) {
    $this->flagsFolder = $folder;
  }

  /**
   * @throws \Exception
   */
  public function getLock($id) {
    $lockFile = $id . ".lock";

    $fileManager = new AcsfFileManager();
    $fileManager->createFile($this->flagsFolder . $lockFile, '');
  }

  /**
   * Release the lock removing the lock file.
   *
   * @param $id
   */
  public function releaseLock($id) {
    if ($this->doesLockExist($id)) {
      unlink($this->flagsFolder . $id . ".lock");
    }
  }

  /**
   * Check if the lock for a given $id exists.
   *
   * @param $id
   *   Lock id to check.
   *
   * @return string
   */
  public function doesLockExist($id) {
    return (is_file($this->flagsFolder . $id . ".lock"));
  }
}
