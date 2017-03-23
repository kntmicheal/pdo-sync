<?php

namespace Mducharme\PDOSync;

use Exception;
use PDO;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Class Synchronizer
 */
class Synchronizer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var PDO
     */
    private $sourceDatabase;

    /**
     * @var PDO
     */
    private $targetDatabase;

    /**
     * @var string
     */
    private $backupIdent;

    /**
     * @param array $data Constructor options.
     */
    public function __construct(array $data)
    {
        $this->setLogger($data['logger']);

        $this->setSourceDatabase($data['source_database']);
        $this->setTargetDatabase($data['target_database']);

        $this->backupIdent = '_syncbackup_'.uniqid();
    }

    /**
     * @param array $tables Optional tables. If null, then all source tables will be synchronized.
     * @return array
     */
    public function __invoke(array $tables=null)
    {
        return $this->run($tables);
    }

    /**
     * @param array $tables Optional tables. If null, then all source tables will be synchronized.
     * @return array
     */
    public function run(array $tables = null)
    {
        if ($tables === null) {
            $tables = $this->allTables($this->sourceDatabase);
        }
        $this->logger->debug('==> Synchronizing databases...');

        $res = [
            'skipped' => [],
            'errored' => [],
            'synced'  => []
        ];

        foreach($tables as $table) {
            $this->logger->debug(sprintf('  ==> Synchronizing table "%s"...', $table));

            // Making sure the tables structures are the same
            if ($this->validateTableStructure($table) === false) {
                $this->logger->error(sprintf('The table "%s" structure is different from target to source. Table skipped.', $table));
                $res['skipped'][] = $table;
                continue;
            }
            if ($this->backupTable($table) === false) {
                $this->logger->error(sprintf('The table "%s" could not be backup. Table skipped.', $table));
                $res['skipped'][] = $table;
                continue;
            }

            // First: empty the target's table.
            $this->emptyTable($table, $this->targetDatabase);

            if ($this->synchronizeTable($table) == false) {
                $this->logger->error(sprintf('The table "%s" could not be synchronized.', $table));
                $this->restoreBackup($table);
                $res['errored'][] = $table;
            } else {
                $this->logger->debug(sprintf('The table "%s" was sucessfully synchronized.', $table));
                $this->deleteBackup($table);
                $res['synced'][] = $table;
            }
        }

        return $res;
    }

    /**
     * Ensures the target's table structure is identical to source's.
     * (Otherwise, synchronization should be skipped)
     *
     * @param string $table
     * @return bool
     */
    private function validateTableStructure($table)
    {
        if ($this->tableExists($table, $this->targetDatabase) === false) {
            $this->createTargetTableFromSource($table);
        }
        $sourceStructure = $this->tableStructure($table, $this->sourceDatabase);
        $targetStructure = $this->tableStructure($table, $this->targetDatabase);

        return ($sourceStructure == $targetStructure);
    }

    /**
     * Creates a table backup copy on target's database.
     * @param string $table
     */
    private function backupTable($table)
    {
        $q = sprintf(
            'CREATE TABLE `%s` LIKE `%s`',
            $table . $this->backupIdent,
            $table
        );
        $this->logger->debug($q);
        $this->targetDatabase->query($q);

        $q = sprintf(
            'INSERT INTO `%s` SELECT * from `%s`',
            $table . $this->backupIdent,
            $table
        );
        $this->logger->debug($q);
        $this->targetDatabase->query($q);


        return true;
    }

    /**
     * @param string $table
     * @return bool
     */
    private function synchronizeTable($table)
    {
        $structure = $this->tableStructure($table, $this->sourceDatabase);
        $columns = [];
        $bindColumns = [];
        foreach($structure as $k=>$v) {
            $columns[] = $k;
            $bindColumns[] = ':'.$k;
        }

        $q = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $bindColumns)
        );

        $sth = $this->targetDatabase->prepare($q);

        $q2 = sprintf('SELECT * FROM `%s`', $table);
        $sth2 = $this->sourceDatabase->query($q2);
        while($row = $sth2->fetch(PDO::FETCH_ASSOC)) {
            foreach($structure as $k=>$v) {
                $sth->bindParam(':'.$k, $row[$k]);
            }
            $sth->execute();
        }

        return true;
    }

    /**
     * @param $table
     */
    private function restoreBackup($table)
    {
        // Oops.
    }

    /**
     * @param string $table
     * @return void
     */
    private function deleteBackup($table)
    {
        $q = sprintf('DROP TABLE `%s`', $table.$this->backupIdent);
        $this->logger->debug($q);
        $this->targetDatabase->query($q);
    }

    /**
     * @param string $table
     * @param PDO $database
     * @return array
     */
    private function tableStructure($table, PDO $database)
    {
        $dbDriver = $database->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($dbDriver === 'sqlite') {
            $q = 'PRAGMA table_info(\'%s\') ';
        } else {
            $q = 'SHOW COLUMNS FROM `%s`';
        }
        $q = sprintf($q, $table);

        $this->logger->debug($q);
        $res = $database->query($q);
        $cols = $res->fetchAll((PDO::FETCH_GROUP|PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC));
        if ($dbDriver === 'sqlite') {
            $ret = [];
            foreach ($cols as $c) {
                // Normalize SQLite's result (PRAGMA) with mysql's (SHOW COLUMNS)
                $ret[$c['name']] = [
                    'Type'      => $c['type'],
                    'Null'      => !!$c['notnull'] ? 'NO' : 'YES',
                    'Default'   => $c['dflt_value'],
                    'Key'       => !!$c['pk'] ? 'PRI' : '',
                    'Extra'     => ''
                ];
            }
            return $ret;
        } else {
            return $cols;
        }
    }

    /**
     * @param $table
     */
    private function createTargetTableFromSource($table)
    {
        $sourceStructure = $this->tableStructure($table, $this->sourceDatabase);

        $sql = [];
        $primary = '';
        foreach($sourceStructure as $field=>$s) {
            $sql[] = sprintf(
                '`%s` %s %s %s',
                $field,
                $s['Type'],
                ($s['Null'] == 'NO' ? 'NOT NULL' : ''),
                $s['Extra']
            );
            if ($s['Key'] == 'PRI') {
                $primary = $field;
            }
        }
        $tableSql = implode(', ', $sql);
        if ($primary) {
            $tableSql .= sprintf(', PRIMARY KEY (`%s`)', $primary);
        }

        $extra = '';
        $dbDriver = $this->sourceDatabase->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($dbDriver === 'mysql') {
            $res = $this->sourceDatabase->query(sprintf('SHOW TABLE STATUS WHERE Name LIKE \'%s\'', $table));
            $status = $res->fetch(PDO::FETCH_ASSOC);
            $extra .= sprintf('ENGINE=%s DEFAULT CHARSET=utf8;', $status['Engine']);
        }

        $q = sprintf('CREATE TABLE `%s` (%s) %s', $table, $tableSql, $extra);

        $ret = $this->targetDatabase->query($q);

    }

    /**
     * Retrieve wether a table exists (true) or not (false) in a database.
     *
     * @param string $table
     * @param PDO $database
     * @return bool
     */
    private function tableExists($table, PDO $database)
    {
        $dbDriver = $database->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($dbDriver === 'sqlite') {
            $q = 'SELECT name FROM sqlite_master WHERE type=\'table\' AND name=\'%s\';';
        } else {
            $q = 'SHOW TABLES LIKE \'%s\'';
        }
        $q = sprintf($q, $table);

        $this->logger->debug($q);
        $res = $database->query($q);
        $tableExists = $res->fetchColumn(0);

        // Return as boolean
        return !!$tableExists;
    }

    private function emptyTable($table, PDO $database)
    {
        $q = sprintf('TRUNCATE TABLE  `%s`', $table);
        $this->logger->debug($q);

        $ret = $database->query($q);

    }

    /**
     * Retrieve all tables from a database.
     *
     * @param PDO $database
     * @return array
     */
    private function allTables(PDO $database)
    {
        $dbDriver = $database->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($dbDriver === 'sqlite') {
            $q = 'SELECT name FROM sqlite_master WHERE type=\'table\';';
        } else {
            $q = 'SHOW TABLES';
        }

        $this->logger->debug($q);
        $res = $database->query($q);
        $tables = $res->fetchAll(PDO::FETCH_COLUMN);

        return $tables;
    }

    /**
     * @param PDO $source
     * @return void
     */
    private function setSourceDatabase(PDO $source)
    {
        $this->sourceDatabase = $source;
    }

    /**
     * @param PDO $source
     * @return void
     */
    private function setTargetDatabase(PDO $target)
    {
        $this->targetDatabase = $target;
    }

    /**
     * @return array
     */
    private function tables()
    {
        if (empty($this->tables)) {
            return $this->allTables($this->sourceDatabase);
        }
        return $this->tables;
    }
}