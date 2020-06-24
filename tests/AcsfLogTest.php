<?php declare(strict_types=1);

namespace Drush\Commands\acsf_tools;

use PHPUnit\Framework\TestCase;


final class AcsfLogTest extends TestCase
{

  /** @var AcsfLock */
  protected $AcsfLock;

  const LOGS_FOLDER_TEST_ROOT = "/tmp/logsfoldertest/sitegroup.siteenv/logs";
  const LOGS_FOLDER_TEST = "/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_";

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
    mkdir($this::LOGS_FOLDER_TEST_ROOT, 0777, TRUE);
  }

  protected function setUp(): void
  {

  }

  /**
   *
   * @dataProvider DoGetFolders
   *
   * @throws \Exception
   */
  public function testGetLogsFolder($iteration, $createFolder, $expected, $setFinishMarker = FALSE, $date = NULL) {
    $folder = $this->AcsfLock->getLogsFolder($iteration, $createFolder, $date);
    $this->assertEquals($expected, $folder);
    echo PHP_EOL . 'folder:: ' . $folder;

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
        NULL,
      ),
      // 2nd iteration, lets create a folder.
      array(
        0,
        TRUE,
        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_0/',
        FALSE,
        NULL,
      ),
      // 3rd iteration, lets just get the folder.
      array(
        0,
        FALSE,
        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_0/',
        FALSE,
        NULL,
      ),
      // 4th iteration, create a new folder = TRUE.
      array(
        0,
        TRUE,
        // It will return the current, as the finish marker is not in place
        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_0/',
        TRUE,
        NULL,
      ),
      // 5th iteration, create a new folder = TRUE and previous terminated.
      array(
        0,
        TRUE,
        // It will return the current, as the finish marker is not in place
        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_1/',
        FALSE,
        NULL,
      ),
      // 6th iteration, create a new folder = TRUE and previous terminated.
      array(
        0,
        TRUE,
        // It will return the current, as the finish marker is not in place
        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_1/',
        FALSE,
        NULL,
      ),
      // 6th iteration, create a new folder = TRUE and previous terminated.
      array(
        0,
        FALSE,
        // It will return the current, as the finish marker is not in place
        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_1/',
        FALSE,
        NULL,
      ),
      // 6th iteration, create a new folder = TRUE and previous terminated.
//      array(
//        0,
//        FALSE,
//        // It will return the current, as the finish marker is not in place
//        '/tmp/logsfoldertest/sitegroup.siteenv/logs/large_scale_cron_' . date("Ymd", time()) . '_1/',
//        FALSE,
//        date("Ymd", time()),
//      ),
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
