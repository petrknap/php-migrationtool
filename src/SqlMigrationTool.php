<?php

namespace PetrKnap\Php\MigrationTool;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Schema\Table;
use PetrKnap\Php\MigrationTool\Exception\DatabaseException;
use PetrKnap\Php\MigrationTool\Exception\MigrationFileException;

/**
 * SQL migration tool
 *
 * @author   Petr Knap <dev@petrknap.cz>
 * @since    2016-06-22
 * @license  https://github.com/petrknap/php-migrationtool/blob/master/LICENSE MIT
 */
class SqlMigrationTool extends AbstractMigrationTool
{
    const SQLSTATE__BASE_TABLE_OR_VIEW_ALREADY_EXISTS = '42S01';

    const MESSAGE__COULD_NOT_CREATE_TABLE__TABLE = 'Could not create migration table {table}';
    const MESSAGE__CREATED_MIGRATION_TABLE__TABLE = 'Created migration table {table}';
    const MESSAGE__COULD_NOT_REGISTER_MIGRATION__ID = 'Could not register migration {id}';
    const MESSAGE__COULD_NOT_READ_MIGRATION_FILE__PATH = 'Could not read migration file {path}';
    const MESSAGE__COULD_NOT_READ_FROM_TABLE__TABLE = 'Could not read from table {table}';
    const MESSAGE__YOU_HAVE_AN_ERROR_IN_YOUR_SQL_SYNTAX__PATH = 'You have an error in your SQL syntax in {path}';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $migrationTableName;

    /**
     * @param string $directory
     * @param Connection $connection
     * @param string $migrationTableName
     * @param string $filePattern
     */
    public function __construct($directory, Connection $connection, $migrationTableName = 'migrations', $filePattern = '/\.sql$/i')
    {
        parent::__construct($directory, $filePattern);
        $this->connection = $connection;
        $this->migrationTableName = $migrationTableName;
    }

    /**
     * @inheritdoc
     */
    public function migrate()
    {
        $this->createMigrationTable();

        parent::migrate();
    }

    /**
     * @throws DatabaseException
     */
    protected function createMigrationTable()
    {
        try {
            $table = new Table($this->migrationTableName);
            $table->addColumn('id', 'string', ['length' => 16]);
            $table->addColumn('applied', 'datetime');
            $table->setPrimaryKey(['id']);

            $this->connection->getSchemaManager()->createTable($table);

            $this->getLogger()->debug(
                self::MESSAGE__CREATED_MIGRATION_TABLE__TABLE,
                [
                    'table' => $this->migrationTableName,
                ]
            );
        } catch (TableExistsException $ignored) {
            // Do nothing
        } catch (DBALException $exception) {
            if (static::SQLSTATE__BASE_TABLE_OR_VIEW_ALREADY_EXISTS === $this->connection->errorCode()) {
                return /* DBAL sometimes does not throw correct TableExistsException */;
            }

            $context = [
                'table' => $this->migrationTableName,
            ];

            $this->getLogger()->critical(
                self::MESSAGE__COULD_NOT_CREATE_TABLE__TABLE,
                $context
            );

            throw new DatabaseException(
                $this->interpolate(
                    self::MESSAGE__COULD_NOT_CREATE_TABLE__TABLE,
                    $context
                ),
                0,
                $exception
            );
        }
    }

    /**
     * @param string $pathToMigrationFile
     * @throws DatabaseException
     */
    protected function registerMigrationFile($pathToMigrationFile)
    {
        $migrationId = $this->getMigrationId($pathToMigrationFile);
        try {
            $this->connection->insert(
                $this->migrationTableName,
                [
                    'id' => $migrationId,
                    'applied' => (new \DateTime())->format(str_replace('O', '', \DateTime::ISO8601)),
                ]
            );
        } catch (DBALException $exception) {
            $context = [
                'id' => $migrationId,
            ];

            $this->getLogger()->critical(
                self::MESSAGE__COULD_NOT_REGISTER_MIGRATION__ID,
                $context
            );

            throw new DatabaseException(
                $this->interpolate(
                    self::MESSAGE__COULD_NOT_REGISTER_MIGRATION__ID,
                    $context
                ),
                0,
                $exception
            );
        }
    }

    /**
     * @inheritdoc
     */
    protected function isMigrationApplied($pathToMigrationFile)
    {
        $migrationId = $this->getMigrationId($pathToMigrationFile);
        try {
            $statement = $this->connection->prepare(
                "SELECT id FROM {$this->connection->quoteIdentifier($this->migrationTableName)} WHERE id = :id"
            );
            $statement->execute(['id' => $migrationId]);

            return false !== $statement->fetch();
        } catch (DBALException $exception) {
            $context = [
                'table' => $this->migrationTableName,
            ];

            $this->getLogger()->critical(
                self::MESSAGE__COULD_NOT_READ_FROM_TABLE__TABLE,
                $context
            );

            throw new DatabaseException(
                $this->interpolate(
                    self::MESSAGE__COULD_NOT_READ_FROM_TABLE__TABLE,
                    $context
                ),
                0,
                $exception
            );
        }
    }

    /**
     * @param string $pathToMigrationFile
     * @return string
     * @throws MigrationFileException
     */
    protected function loadMigrationData($pathToMigrationFile)
    {
        $migrationData = @file_get_contents($pathToMigrationFile);

        if ($migrationData === false) {
            $context = [
                'path' => $pathToMigrationFile,
            ];

            $this->getLogger()->critical(
                self::MESSAGE__COULD_NOT_READ_MIGRATION_FILE__PATH,
                $context
            );

            throw new MigrationFileException(
                $this->interpolate(
                    self::MESSAGE__COULD_NOT_READ_MIGRATION_FILE__PATH,
                    $context
                )
            );
        }

        return $migrationData;
    }

    /**
     * @inheritdoc
     */
    protected function applyMigrationFile($pathToMigrationFile)
    {
        $migrationData = $this->loadMigrationData($pathToMigrationFile);

        $this->connection->beginTransaction();

        try {
            $this->connection->exec($migrationData);
            $this->registerMigrationFile($pathToMigrationFile);
            $this->connection->commit();
        } catch (DBALException $exception) {
            $this->connection->rollBack();

            $context = [
                'path' => $pathToMigrationFile,
            ];

            $this->getLogger()->critical(
                self::MESSAGE__YOU_HAVE_AN_ERROR_IN_YOUR_SQL_SYNTAX__PATH,
                $context
            );

            throw new MigrationFileException(
                $this->interpolate(
                    self::MESSAGE__YOU_HAVE_AN_ERROR_IN_YOUR_SQL_SYNTAX__PATH,
                    $context
                ),
                0,
                $exception
            );
        }
    }
}
