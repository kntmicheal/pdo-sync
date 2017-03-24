<?php

namespace Mducharme\PDOSync;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class Backup
 */
class Backup implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Database
     */
    private $db;

    /**
     * @var string
     */
    private $suffix = '_pdosyncbackup';

    /**
     * Backup constructor.
     *
     * @param Database        $db     The database to backup.
     * @param LoggerInterface $logger A Psr-3 logger.
     */
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->setLogger($logger);
        $this->db = $db;
    }

    /**
     * @param string $table The table (name) to backup.
     * @return boolean
     */
    public function create($table)
    {
        $backupTable = $table.$this->suffix;
        if ($this->db->tableExists($backupTable) === false) {
            $q = sprintf(
                'CREATE TABLE `%s` LIKE `%s`',
                $backupTable,
                $table
            );
            $this->logger->debug($q);
            $this->db->pdo->query($q);
        } else {
            $this->db->emptyTable($backupTable);
        }

        $q = sprintf(
            'INSERT INTO `%s` SELECT * from `%s`',
            $backupTable,
            $table
        );
        $this->logger->debug($q);
        $this->db->pdo->query($q);


        return true;
    }

    /**
     * @param string $table Table (name) to restore.
     * @return boolean
     */
    public function restore($table)
    {
        $backupTable = $table.$this->suffix;
        if ($this->db->tableExists($backupTable) === false) {
            return false;
        }

        if ($this->db->tableExists($table) === false) {
            $q = sprintf(
                'CREATE TABLE `%s` LIKE `%s`',
                $backupTable
            );
            $this->logger->debug($q);
            $this->db->pdo->query($q);
        } else {
            $this->db->emptyTable($table);
        }

        $q = sprintf(
            'INSERT INTO `%s` SELECT * from `%s`',
            $table,
            $backupTable
        );
        $this->logger->debug($q);
        $this->db->query($q);

        return true;
    }

    /**
     * @param string $table The table (name) to delete the backup from.
     * @return booleam
     */
    public function delete($table)
    {
        $backupTable = $table.$this->suffix;
        if ($this->db->tableExists($backupTable) === false) {
            return false;
        }
        $this->db->deleteTable($backupTable);
        return true;
    }
}
