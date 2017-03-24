<?php

namespace Mducharme\PDOSync\Tests;

use PHPUnit\Framework\TestCase;

use PDO;

use Psr\Log\NullLogger;

use Mducharme\PDOSync\Backup;
use Mducharme\PDOSync\Database;

/**
 *
 */
class BackupTest extends TestCase
{
    private $obj;

    public function setUp()
    {
        $db = new Database(new PDO('sqlite::memory:'), new NullLogger());
        $this->obj = new Backup($db, new NullLogger());
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(Backup::class, $this->obj);
    }
}
