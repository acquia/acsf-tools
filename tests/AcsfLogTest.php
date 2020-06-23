<?php declare(strict_types=1);

namespace Drush\Commands\acsf_tools;

use PHPUnit\Framework\TestCase;

final class AcsfLogTest extends TestCase
{

  /** @var AcsfLock */
  protected $AcsfLock;

  const TMP_FOLDER = "/tmp/locks/";

  protected function setUp(): void
  {
    $this->AcsfLock = new AcsfLogs();
  }

  /**
   * @dataProvider DoGetLocks
   */
  public function testEmailLogs($message, $subject) {
    $this->AcsfLock->emailLogs($message, $subject);

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

}
