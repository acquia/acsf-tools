<?php

namespace Drush\Commands\acsf_custom;

use Exception;
use Drush\Commands\DrushCommands;

class AcsfFileManager extends DrushCommands {

  /**
   * Create file.
   *
   * @param $filePath
   *   Path of the file to be created.
   * @param $content
   *   Content to be written in the file.
   *
   * @throws \Exception
   */
  public function createFile($filePath, $content, $retries = 0)
  {
    $writeFile = file_put_contents($filePath, strval($content));

    if ($writeFile === false || !is_readable($filePath)) {
      $this->yell("Failed to create a file $filePath");
      // Let's retry up to 3 times before we throw an error.
      if ($retries <= 3) {
        sleep(1);
        $this->createFile($filePath, $content, $retries + 1);
      } else {
        // Give up and throw expection.
        $this->yell("Exception: Failed to create a file $filePath");
        throw new Exception("Could not create a file $filePath");
      }
    } else {
      $this->say("File created: $filePath");
    }
  }

  /**
   * Create a folder.
   *
   * @param $folder
   *   Folder to be created.
   *
   * @throws \Exception
   */
  public function createFolder($folder)
  {
    $this->say("Folder $folder does not exist. Creating it.");

    if (mkdir($folder)) {
      $this->say("Folder created.");
    } else {
      throw new Exception(date("d-m-Y H:i:s") . " - Could not create folder $folder");
    }
  }

  /**
   * Return if the folder is empty.
   *
   * @param $folder
   *   Folder to check.
   *
   * @return bool
   *   Return if is empty or not.
   */
  public function folderEmpty($folder)
  {
    // Check here only logs files.
    if ($files = glob($folder . "/*")) {
      $empty = FALSE;
    } else {
      $empty = TRUE;
    }
    return $empty;
  }
}
