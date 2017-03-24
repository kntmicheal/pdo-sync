<?php

namespace Mducharme\PDOSync;

use PDO;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Class Database
 */
class Database implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    /**
     * @var PDO
     */
    public $pdo;

    /**
     * @var string
     */
    private $dbDriver;

    /**
     * TableInfo constructor.
     * @param PDO             $db     The database where to run backups.
     * @param LoggerInterface $logger A PSR-3 logger.
     */
    public function __construct(PDO $db, LoggerInterface $logger)
    {
        $this->pdo = $db;
        $this->pdoDriver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->setLogger($logger);
    }

    /**
     * Retrieves the list of tables available on a database.
     *
     * @return array
     */
    public function tables()
    {
        if ($this->pdoDriver === 'sqlite') {
            $q = 'SELECT name FROM sqlite_master WHERE type = \'table\';';
        } else {
            $q = 'SHOW TABLES';
        }

        $this->logger->debug($q);
        $res = $this->pdo->query($q);
        $tables = $res->fetchAll(PDO::FETCH_COLUMN);

        return $tables;
    }

    /**
     * @param string $table The table (name) to retrieve the structure of.
     * @return array
     */
    public function tableStructure($table)
    {
        if ($this->pdoDriver === 'sqlite') {
            $q = 'PRAGMA table_info(\'%s\') ';
        } else {
            $q = 'SHOW COLUMNS FROM `%s`';
        }
        $q = sprintf($q, $table);

        $this->logger->debug($q);
        $res = $this->pdo->query($q);
        $cols = $res->fetchAll((PDO::FETCH_GROUP|PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC));

        if ($this->pdoDriver === 'sqlite') {
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
     * @param string $table The table (name) to check the existence of.
     * @return boolean
     */
    public function tableExists($table)
    {
        if ($this->dbDriver === 'sqlite') {
            $q = 'SELECT name FROM sqlite_master WHERE type=\'table\' AND name=\'%s\';';
        } else {
            $q = 'SHOW TABLES LIKE \'%s\'';
        }
        $q = sprintf($q, $table);

        $this->logger->debug($q);
        $res = $this->pdo->query($q);
        if ($res === false) {
            return false;
        }
        $tableExists = $res->fetchColumn(0);

        // Return as boolean
        return !!$tableExists;
    }

    /**
     * Create a database table with an identical structure as a source database.
     *
     * @param string   $table       The table (name) to create.
     * @param Database $source      The source to create the table structure from.
     * @param string   $sourceTable The source table to use for structure, if different from `$table`.
     * @return boolean
     */
    public function createTableFromSource($table, Database $source, $sourceTable = '')
    {
        if (!$sourceTable) {
            $sourceTable = $table;
        }

        $sourceStructure = $source->tableStructure($sourceTable);

        $sql = [];
        $primary = '';
        foreach ($sourceStructure as $field => $fieldInfos) {
            $sql[] = sprintf(
                '`%s` %s %s %s',
                $field,
                $fieldInfos['Type'],
                ($fieldInfos['Null'] == 'NO' ? 'NOT NULL' : ''),
                $fieldInfos['Extra']
            );
            if ($fieldInfos['Key'] == 'PRI') {
                $primary = $field;
            }
        }
        $tableSql = implode(', ', $sql);
        if ($primary) {
            $tableSql .= sprintf(', PRIMARY KEY (`%s`)', $primary);
        }

        $extra = '';
        $dbDriver = $source->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($dbDriver === 'mysql') {
            $res = $source->pdo->query(sprintf(
                'SHOW TABLE STATUS WHERE `Name` LIKE \'%s\'',
                $sourceTable
            ));
            $status = $res->fetch(PDO::FETCH_ASSOC);
            $extra .= sprintf(
                'ENGINE=%s COLLATE %s;',
                $status['Engine'],
                $status['Collation']
            );
        }

        $q = sprintf(
            'CREATE TABLE `%s` (%s) %s',
            $table,
            $tableSql,
            $extra
        );

        $ret = $this->pdo->query($q);
        return !!$ret;
    }

    /**
     * @param string $table The table (name) to empty.
     * @return void
     */
    public function emptyTable($table)
    {
        $q = sprintf(
            'TRUNCATE TABLE  `%s`',
            $table
        );
        $this->logger->debug($q);

        $ret = $this->pdo->query($q);
    }

    /**
     * @param string $table The table (name) to empty.
     * @return void
     */
    public function deleteTable($table)
    {
        $q = sprintf(
            'DROP TABLE `%s`',
            $table
        );
        $this->logger->debug($q);

        $ret = $this->pdo->query($q);
    }
}
