<?php declare(strict_types=1);

namespace Drush\Commands\acsf_tools;

use PHPUnit\Framework\TestCase;

final class AcsfLockTest extends TestCase
{

  /** @var AcsfLock */
  protected $AcsfLock;

  const TMP_FOLDER = "/tmp/locks/";

  protected function setUp(): void
  {
    $this->AcsfLock = new AcsfLock($this::TMP_FOLDER);
  }

  /**
   * @dataProvider DoGetLocks
   */
  public function testGetFlagsFolder($folder, $id, $lock, $expected): void
  {
    $lockFile = $folder . "$id.lock";
    if (!file_exists($lockFile)) {
      file_put_contents($lockFile, "1");
    }

    $exists = $this->AcsfLock->doesLockExist($id);
    if ($expected) {
      $this->assertTrue($exists);
    }
    else {
      $this->assertFalse($exists);
    }

  }

  /**
   * @dataProvider DoGetLocks
   *
   * @throws \Exception
   */
//  public function testGetLock($folder, $id, $lock, $expected) {
//    $this->AcsfLock->getLock($id);
//  }

  /**
   * @dataProvider DoGetLocks
   *
   * @throws \Exception
   */
  public function testReleaseLock($folder, $id, $lock, $expected) {

    // Create the lock.
    $this->AcsfLock->getLock($id);
    $this->assertTrue($this->AcsfLock->doesLockExist($id));

    // Release lock.
    $this->AcsfLock->releaseLock($id);
    $this->assertFalse($this->AcsfLock->doesLockExist($id));

    // Re test again to ensure no errors or warnings are thrown.
    $this->AcsfLock->releaseLock($id);
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
        $this::TMP_FOLDER,
        'dev2jash7',
        '/tmp/locks/dev2jash7.lock',
        TRUE
      ),
      array(
        $this::TMP_FOLDER,
        '2398765',
        '/tmp/locks/2398765.lock',
        TRUE
      ),
      array(
        $this::TMP_FOLDER,
        '2398765.lock',
        '/tmp/locks/2398765.lock.lock',
        TRUE
      ),
      array(
        $this::TMP_FOLDER,
        NULL,
        '/tmp/locks/2398765.lock',
        TRUE
      ),
      array(
        $this::TMP_FOLDER,
        "",
        '/tmp/locks/2398765.lock',
        TRUE
      ),
      array(
        $this::TMP_FOLDER,
        "+_)&&^(",
        '/tmp/locks/2398765.lock',
        TRUE
      ),
    );
  }

}
