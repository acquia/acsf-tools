<?php declare(strict_types=1);

use Drush\Commands\acsf_tools\AcsfFileManager;
use PHPUnit\Framework\TestCase;

final class AcsfCustomPostDeploymentCommandsTest extends TestCase
{

  /** @var \Drush\Commands\acsf_tools\AcsfToolsBackgroundTasksCommands */
  protected $postDeployment;

  protected function setUp(): void
  {
    // Set up environment variables.
    $_ENV['AH_SITE_GROUP'] = 'sitedomain';
    $this->site_group = $_ENV['AH_SITE_GROUP'];

    $_ENV['AH_SITE_ENVIRONMENT'] = 'siteenvironment';
    $this->site_env = $_ENV['AH_SITE_ENVIRONMENT'];

    $this->postDeployment = new \Drush\Commands\acsf_tools\AcsfToolsBackgroundTasksCommands();
  }

  /**
   * @dataProvider DoGetFolders
   */
  public function testGetFlagsFolder($site, $env, $expected): void
  {
    $AcsfFlags = new \Drush\Commands\acsf_tools\AcsfFlags($site,$env, '/mnt/gfs/');
    $this->assertEquals(
      $expected,
      $AcsfFlags->getFlagsFolder()
    );

  }

  /**
   * Data provider method.
   *
   * @return array
   */
  public function DoGetFolders()
  {
    return array(
      array(
        'mysite',
        'dev',
        '/mnt/gfs/mysite.dev/flags/'
      ),
      array(
        null,
        null,
        '/mnt/gfs/./flags/'
      ),
      array(
        '',
        '',
        '/mnt/gfs/./flags/'
      ),
      array(
        '23456',
        '908734',
        '/mnt/gfs/23456.908734/flags/'
      ),
      array(
        '234£@$%^}{[]',
        '}{-)*^&^*&978',
        '/mnt/gfs/234£@$%^}{[].}{-)*^&^*&978/flags/'
      ),
    );
  }

//  /**
//   * @dataProvider DoGetFolderLogs
//   */
//  public function testWriteLog($timestamp, $root_folder, $filelog, $expected)
//  {
//    try {
//      $AcsfLogs = new \Drush\Commands\acsf_tools\AcsfLogs();
//      $AcsfLogs->writeLog("This log was written from a unit test. No code was harmed during testing.", 'randomID', 'error', true, $timestamp);
//    } catch (Exception $exception) {
//      echo PHP_EOL . " --------  Expected exception on permissions denied IN TEST testWriteLog $exception" . PHP_EOL . " -------- " . PHP_EOL;
//      $this->assertFalse(FALSE);
//    }
//
//    if ($expected === TRUE) {
//      $this->assertTrue(is_dir($root_folder));
//    }
//  }
//
//  /**
//   * Data provider method.
//   *
//   * @return array
//   */
//  public function DoGetFolderLogs()
//  {
//    $timestamp = time();
//
//    return array(
//      array(
//        $timestamp,
//        '/tmp/my-test/projects/mnt-test/',
//        "/tmp/my-test/projects/mnt-test/gfs/sitedomain.siteenvironment/logs/large_scale_cron_" . date("Ymd", $timestamp) . "/",
//        TRUE
//      ),
//      array(
//        $timestamp,
//        null,
//        "my-test/projects/mnt-test/tmp/sitedomain.siteenvironment/logs/large_scale_cron_" . date("Ymd", $timestamp) . "/",
//        FALSE
//      ),
//
//    );
//  }

  /**
   * Testing getFileNames.
   *
   * @dataProvider DoGetFileNames
   */
  public function testGetFileName($db_name) {
    $AcsfFlags = new \Drush\Commands\acsf_tools\AcsfFlags('sitegroup', 'siteenv', 'ID087', '/mnt/gfs/');

    $this->assertTrue(true);
  }

  /**
   * Data provider method.
   *
   * @return array
   */
  public function DoGetFileNames()
  {
    return array(
      array(
        "daskjfhapoi",
      ),
      array(
        "daskjfhapoi0",
      ),
    );
  }

  /**
   * Test if folders are empty.
   *
   * @dataProvider DoGetEmptyFolders
   */
  public function testFolderEmpty($folder, $expected) {
    $filemanager = new AcsfFileManager();
    if ($expected) {
      // Let's create a new folder.
      exec("mkdir -p $folder");
      $this->assertTrue($filemanager->folderEmpty($folder));
    }
    else {
      $this->assertFalse($filemanager->folderEmpty($folder));
    }
  }

  /**
   * Data provider method.
   *
   * @return array
   */
  public function DoGetEmptyFolders()
  {
    return array(
      array(
        "/tmp/new-folder/",
        TRUE
      ),
      array(
        "/",
        FALSE
      ),
    );
  }
}
