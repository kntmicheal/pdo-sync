<?php

namespace Mducharme\PDOSync\Tests;

use PHPUnit\Framework\TestCase;

use PDO;

use Psr\Log\NullLogger;

use Mducharme\PDOSync\Synchronizer;
use Mducharme\PDOSync\Database;

/**
 *
 */
class SynchronizerTest extends TestCase
{
    /**
     * @var Synchronizer
     */
    private $obj;
    private $source;
    private $target;

    /**
     * Set up object under test
     */
    public function setUp()
    {
        $this->source = new PDO('sqlite::memory:');
        $this->target = new PDO('sqlite::memory:');
        $this->obj = new Synchronizer($this->source, $this->target, new NullLogger());
    }

    /**
     *
     */
    public function testRunWithoutTables()
    {
        $results = $this->obj->run([]);
        $this->assertArrayHasKey('skipped', $results);
        $this->assertArrayHasKey('synced', $results);
        $this->assertArrayHasKey('errored', $results);
        $this->assertEmpty(($results['skipped']));
        $this->assertEmpty(($results['synced']));
        $this->assertEmpty(($results['errored']));
    }

    /**
     *
     */
    public function testInvokable()
    {
        $obj = $this->obj;
        $results = $obj([]);
        $this->assertEquals($results, $obj->run([]));
    }

    /**
     *
     */
    public function testRunWithInvalidTableIsSkipped()
    {
        $results = $this->obj->run(['invalid_table']);
        $this->assertContains('invalid_table', $results['skipped']);
    }

    public function testRun()
    {
        $target = new Database($this->target, new NullLogger());
        $source = new Database($this->source, new NullLogger());

        $this->assertFalse($source->tableExists('phpunit'));
        $this->source->exec('CREATE TABLE `phpunit` (a int(10) NOT NULL, b VARCHAR(255) DEFAULT foo)');
        $this->source->query('INSERT INTO `phpunit` (a, b) VALUES (1, \'bar\')');
        $this->source->query('INSERT INTO `phpunit` (a, b) VALUES (2, \'foobar\')');

        $this->obj->run(['phpunit']);

        // Todo...
        $this->assertTrue(true);
    }
}
