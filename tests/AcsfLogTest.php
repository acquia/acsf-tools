<?php declare(strict_types=1);

namespace Drush\Commands\acsf_tools;

use PHPUnit\Framework\TestCase;


final class AcsfLogTest extends TestCase
{

  /** @var AcsfLock */
  protected $AcsfLock;

  const LOGS_FOLDER_TEST_ROOT = "/tmp/logsfoldertest/sitegroup.siteenv";
  const LOGS_FOLDER_TEST = "/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_";
  const LOGS_FOLDER_TEST_ROOT_NO_DATE = "/tmp/logsfoldertest/sitegroup.siteenv/recursive_logs";

  public function __construct(?string $name = NULL, array $data = [], $dataName = '') {
    parent::__construct($name, $data, $dataName);

    // Set up environment variables.
    $_ENV['AH_SITE_GROUP'] = 'sitedomain';
    $this->site_group = $_ENV['AH_SITE_GROUP'];

    $_ENV['AH_SITE_ENVIRONMENT'] = 'siteenvironment';
    $this->site_env = $_ENV['AH_SITE_ENVIRONMENT'];

    $this->AcsfLock = new AcsfLogs();
    $this->AcsfLock->setRootLogsFolder($this::LOGS_FOLDER_TEST . date("Ymd", time()));

    // Wipe out contents with previous tests
    if (is_dir($this::LOGS_FOLDER_TEST_ROOT)) {
      $this->rrmdir($this::LOGS_FOLDER_TEST_ROOT);
    }
    // And prepare a fresh new one for a new batch of tests.
    mkdir($this::LOGS_FOLDER_TEST_ROOT_NO_DATE, 0777, TRUE);

    $this->AcsfLock->setRootNoDateLogsFolder($this::LOGS_FOLDER_TEST_ROOT_NO_DATE . "/large_scale_cron_");

  }

  protected function setUp(): void
  {

  }

  /**
   *
   * @dataProvider DoGetFoldersRecursive
   *
   * @throws \Exception
   */
  public function testGetLastLogsFolderRecursive($date, $iteration, $expectedFolder, $createFolderForTest, $recursion) {

    if ($createFolderForTest == TRUE && !is_dir($expectedFolder) && $expectedFolder != "") {
      mkdir($expectedFolder, 0777, TRUE);
    }

    $folder = $this->AcsfLock->getLastLogsFolderRecursive($date, $iteration, $recursion);
    $this->assertEquals($expectedFolder, $folder);

    echo PHP_EOL . 'logs folder:: ' . $folder . " | ";
    echo PHP_EOL . " --------- " . PHP_EOL . PHP_EOL;

  }

  /**
   * @return array
   */
  public function DoGetFoldersRecursive() {
    return array(
      // First iteration, we expect to return an empty folder.
      array(
        NULL,
        NULL,
        '',
        TRUE,
        TRUE,
      ),
      array(
        '20200624',
        NULL,
        $this::LOGS_FOLDER_TEST_ROOT . '/recursive_logs/large_scale_cron_20200624_0/',
        TRUE,
        TRUE,
      ),
      array(
        '20200624',
        NULL,
        $this::LOGS_FOLDER_TEST_ROOT . '/recursive_logs/large_scale_cron_20200624_1/',
        TRUE,
        TRUE,
      ),
      // This test should fail, as there is another folder created after 0 (large_scale_cron_20200624_1).
      array(
        '20200624',
        NULL,
        $this::LOGS_FOLDER_TEST_ROOT . '/recursive_logs/large_scale_cron_20200624_1/',
        FALSE,
        TRUE,
      ),
      // Making sure consecutive calls work as well
      array(
        '20200624',
        NULL,
        $this::LOGS_FOLDER_TEST_ROOT . '/recursive_logs/large_scale_cron_20200624_1/',
        FALSE,
        TRUE,
      ),
      // Making sure consecutive calls work as well
      array(
        '20200624',
        NULL,
        $this::LOGS_FOLDER_TEST_ROOT . '/recursive_logs/large_scale_cron_20200624_2/',
        TRUE,
        TRUE,
      ),
      array(
        '20200624',
        NULL,
        $this::LOGS_FOLDER_TEST_ROOT . '/recursive_logs/large_scale_cron_20200624_2/',
        FALSE,
        TRUE,
      ),
      // Specify which folder to return.
      array(
        '20200624',
        1,
        $this::LOGS_FOLDER_TEST_ROOT . '/recursive_logs/large_scale_cron_20200624_1/',
        FALSE,
        FALSE,
      ),
      // Specify which folder to return.
      array(
        '20200624',
        0,
        $this::LOGS_FOLDER_TEST_ROOT . '/recursive_logs/large_scale_cron_20200624_0/',
        FALSE,
        FALSE,
      ),
      // Specify which folder to return.
      array(
        '20200624',
        2,
        $this::LOGS_FOLDER_TEST_ROOT . '/recursive_logs/large_scale_cron_20200624_2/',
        FALSE,
        FALSE,
      ),
      // Specify which folder to return.
      array(
        NULL,
        2,
        $this::LOGS_FOLDER_TEST_ROOT . '/recursive_logs/large_scale_cron_20200624_2/',
        FALSE,
        FALSE,
      ),
      // Specify which folder to return.
      array(
        '20200624',
        NULL,
        $this::LOGS_FOLDER_TEST_ROOT . '/recursive_logs/large_scale_cron_20200624_2/',
        FALSE,
        TRUE,
      ),
    );

  }

  /**
   *
   * @dataProvider DoGetFolders
   *
   * @throws \Exception
   */
  public function testGetLogsFolder($iteration, $createFolder, $expected, $setFinishMarker = FALSE) {
    $folder = $this->AcsfLock->getLogsFolder($iteration, $createFolder);
    $this->assertEquals($expected, $folder);

    if ($setFinishMarker == TRUE) {
      $marker = $expected . '/' . $this->AcsfLock::FINISH_LOG_MARKER;
      $writeFile = file_put_contents($marker, "terminated");
    }

  }

  /**
   * @return array
   */
  public function DoGetFolders() {
    return array(
      // First iteration, we expect to return an empty folder.
      array(
        0,
        FALSE,
        '',
        FALSE,
      ),
      // 2nd iteration, lets create a folder.
      array(
        0,
        TRUE,
        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_0/',
        FALSE,
      ),
      // 3rd iteration, lets just get the folder.
      array(
        0,
        FALSE,
        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_0/',
        FALSE,
      ),
      // 4th iteration, create a new folder = TRUE.
      array(
        0,
        TRUE,
        // It will return the current, as the finish marker is not in place
        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_0/',
        TRUE,
      ),
      // 5th iteration, create a new folder = TRUE and previous terminated.
      array(
        0,
        TRUE,
        // It will return the current, as the finish marker is not in place
        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_1/',
        FALSE,
      ),
      // 6th iteration, create a new folder = TRUE and previous terminated.
      array(
        0,
        TRUE,
        // It will return the current, as the finish marker is not in place
        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_1/',
        FALSE,
      ),
      // 6th iteration, create a new folder = TRUE and previous terminated.
      array(
        0,
        FALSE,
        // It will return the current, as the finish marker is not in place
        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_1/',
        FALSE,
      ),
    );
  }

  /**
   * @dataProvider DoGetLocks
   */
  public function testEmailLogs($message, $subject) {
    //$this->AcsfLock->emailLogs($message, $subject);

    $this->assertTrue(TRUE);
  }

  /**
   * Data provider method.
   *
   * @return array
   */
  public function DoGetLocks()
  {
    return array(
      array(
        'Random message',
        'Subject message'
      ),
      array(
        'Random message',
        'Subject message',

      ),
    );
  }

  /**
   * Prepare the folders for testing.
   * @param $dir
   */
  private function rrmdir($dir) {
    if (is_dir($dir)) {
      $objects = scandir($dir);
      foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
          if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
            $this->rrmdir($dir. DIRECTORY_SEPARATOR .$object);
          else
            unlink($dir. DIRECTORY_SEPARATOR .$object);
        }
      }
      rmdir($dir);
    }
  }

}
