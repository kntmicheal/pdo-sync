<?php

namespace Mducharme\PDOSync\Tests;

use PHPUnit\Framework\TestCase;

use PDO;

use Psr\Log\NullLogger;

use Mducharme\PDOSync\Database;

/**
 *
 */
class DatabaseTest extends TestCase
{
    /**
     * @var Database
     */
    private $obj;

    /**
     * Set up test objects
     */
    public function setUp()
    {
        $this->obj = new Database(new PDO('sqlite::memory:'), new NullLogger());
    }

    /**
     *
     */
    public function testTableExistsInvalidTableIsFalse()
    {
        $this->assertFalse($this->obj->tableExists('invalidTable'));
    }

    /**
     *
     */
    public function testTableStructureInvalidTableIsEmpty()
    {
        $this->assertEmpty($this->obj->tableStructure('invalidTable'));
    }

    /**
     *
     */
    public function testTableStructure()
    {
        $this->obj->pdo->query('CREATE TABLE `phpunit` (
            `a` INT(10) NOT NULL, 
            `b` VARCHAR(255) DEFAULT foo
        )');

        $structure = $this->obj->tableStructure('phpunit');

        $this->assertCount(2, $structure);
        $this->assertArrayHasKey('a', $structure);
        $this->assertArrayHasKey('b', $structure);

        $this->assertEquals('INT(10)', $structure['a']['Type']);
        $this->assertEquals('NO', $structure['a']['Null']);
        $this->assertNull($structure['a']['Default']);

        $this->assertEquals('VARCHAR(255)', $structure['b']['Type']);
        $this->assertEquals('YES', $structure['b']['Null']);
        $this->assertEquals('foo', $structure['b']['Default']);
    }
}
