<?php

namespace Drush\Commands\acsf_tools;

class AcsfLock {

  private $flagsFolder = "/tmp/";

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
   * @param $id
   */
  public function releaseLock($id) {
      unlink($this->flagsFolder . $id . ".lock");
  }

  public function doesLockExist($id) {
    return $this->flagsFolder . $id;
  }
}
