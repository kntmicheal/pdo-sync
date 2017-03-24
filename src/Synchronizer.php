<?php

namespace Mducharme\PDOSync;

use Exception;
use PDO;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

use Mducharme\PDOSync\Backup;
use Mducharme\PDOSync\Database;

/**
 * Class Synchronizer
 */
class Synchronizer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Database
     */
    private $sourceDatabase;

    /**
     * @var Database
     */
    private $targetDatabase;

    /**
     * @var string
     */
    private $backupIdent = '_pdosync';

    /**
     * Synchronizer constructor.
     * @param PDO             $source The source database. (Where to copy from).
     * @param PDO             $target The target database. (Where to copy to).
     * @param LoggerInterface $logger A PSR-3 logger.
     */
    public function __construct(PDO $source, PDO $target, LoggerInterface $logger)
    {
        $this->setLogger($logger);

        $this->sourceDatabase = new Database($source, $logger);
        $this->targetDatabase = new Database($target, $logger);
    }

    /**
     * This class can be executed.
     *
     * @param array $tables Optional tables. If null, then all source tables will be synchronized.
     * @return array
     */
    public function __invoke(array $tables = null)
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
            $tables = $this->sourceDatabase->tables();
        }
        $this->logger->debug('==> Synchronizing databases...');

        $backup = new Backup($this->targetDatabase, $this->logger);

        $res = [
            'skipped' => [],
            'errored' => [],
            'synced'  => []
        ];

        foreach ($tables as $table) {
            $this->logger->debug(sprintf('  ==> Synchronizing table "%s"...', $table));

            if ($this->sourceDatabase->tableExists($table) === false) {
                $this->logger->error(sprintf(
                    'Table "%s" does not exist in source database.',
                    $table
                ));
                $res['skipped'][] = $table;
                continue;
            }

            // Making sure the tables structures are the same
            if ($this->validateTableStructure($table) === false) {
                $this->logger->error(sprintf(
                    'The table "%s" structure is different from target to source. Table skipped.',
                    $table
                ));
                $res['skipped'][] = $table;
                continue;
            }

            if ($backup->create($table) === false) {
                $this->logger->error(sprintf(
                    'The table "%s" could not be backup. Table skipped.',
                    $table
                ));
                $res['skipped'][] = $table;
                continue;
            }

            // First: empty the target's table.
            $this->targetDatabase->emptyTable($table);

            if ($this->synchronizeTable($table) == false) {
                $this->logger->error(sprintf(
                    'The table "%s" could not be synchronized.',
                    $table
                ));
                $backup->restore($table);
                $res['errored'][] = $table;
            } else {
                $this->logger->debug(sprintf(
                    'The table "%s" was sucessfully synchronized.',
                    $table
                ));
                $backup->delete($table);
                $res['synced'][] = $table;
            }
        }

        return $res;
    }

    /**
     * Ensures the target's table structure is identical to source's.
     * (Otherwise, synchronization should be skipped)
     *
     * @param string $table The table (name) to validate structure for.
     * @return boolean
     */
    private function validateTableStructure($table)
    {
        if ($this->targetDatabase->tableExists($table) === false) {
            $this->targetDatabase->createTableFromSource($table, $this->sourceDatabase);
        }

        $sourceStructure = $this->sourceDatabase->tableStructure($table);
        $targetStructure = $this->targetDatabase->tableStructure($table);

        return ($sourceStructure == $targetStructure);
    }

    /**
     * @param string $table The table (name) to synchronize (from source to target).
     * @return boolean
     */
    private function synchronizeTable($table)
    {
        $structure = $this->sourceDatabase->tableStructure($table);
        $columns = [];
        $bindColumns = [];
        foreach ($structure as $k => $v) {
            $columns[] = $k;
            $bindColumns[] = ':'.$k;
        }

        $q = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $bindColumns)
        );

        $sth = $this->targetDatabase->pdo->prepare($q);

        $q2 = sprintf(
            'SELECT * FROM `%s`',
            $table
        );
        $sth2 = $this->sourceDatabase->pdo->query($q2);
        while ($row = $sth2->fetch(PDO::FETCH_ASSOC)) {
            foreach ($structure as $k => $v) {
                $sth->bindParam(':'.$k, $row[$k]);
            }
            $sth->execute();
        }

        return true;
    }
}
