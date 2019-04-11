<?php

namespace PetrKnap\Php\MigrationTool;

use PetrKnap\Php\MigrationTool\Exception\DatabaseException;
use PetrKnap\Php\MigrationTool\Exception\MigrationFileException;
use PetrKnap\Php\MigrationTool\DbAdapters\DbAdapter;
use PetrKnap\Php\MigrationTool\DbAdapters\MySqlAdapter;

/**
 * SQL migration tool
 *
 * @author   Petr Knap <dev@petrknap.cz>
 * @since    2016-06-22
 * @license  https://github.com/petrknap/php-migrationtool/blob/master/LICENSE MIT
 */
class SqlMigrationTool extends AbstractMigrationTool
{
    const MESSAGE__COULD_NOT_CREATE_TABLE__TABLE = 'Could not create migration table {table}';
    const MESSAGE__CREATED_MIGRATION_TABLE__TABLE = 'Created migration table {table}';
    const MESSAGE__COULD_NOT_REGISTER_MIGRATION__ID = 'Could not register migration {id}';
    const MESSAGE__COULD_NOT_READ_MIGRATION_FILE__PATH = 'Could not read migration file {path}';
    const MESSAGE__COULD_NOT_READ_FROM_TABLE__TABLE = 'Could not read from table {table}';
    const MESSAGE__YOU_HAVE_AN_ERROR_IN_YOUR_SQL_SYNTAX__PATH = 'You have an error in your SQL syntax in {path}';

    /**
     * @var \PDO
     */
    private $pdo;

    /**
     * @var string
     */
    private $migrationTableName;

    /**
     * @var bool
     */
    private $supportsMultiStatements;
    
    /**
     *
     * @var DbAdapter
     */
    private $adapter;

    /**
     * @param string $directory
     * @param \PDO $pdo
     * @param string $migrationTableName
     * @param string $filePattern
     */
    public function __construct($directory, \PDO $pdo, $migrationTableName = 'migrations', $filePattern = '/\.sql$/i', DbAdapter $adapter = null)
    {
        parent::__construct($directory, $filePattern);
        $this->pdo = $pdo;
        $this->migrationTableName = $migrationTableName;
        $this->adapter = ($adapter) ? $adapter : new MySqlAdapter();

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->supportsMultiStatements = in_array($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME), [
           $this->adapter->getDbType()
        ]);
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
        /** @noinspection SqlNoDataSourceInspection,SqlDialectInspection */
            $this->pdo->prepare(
                    $this->adapter->selectOneNull($this->migrationTableName)
            )->execute();
        } catch (\PDOException $ignored) {
            try {
                /** @noinspection SqlNoDataSourceInspection,SqlDialectInspection */
                $this->pdo->exec(
                    $this->adapter->createTableIfNotExists($this->migrationTableName)
                );

                if ($this->getLogger()) {
                    $this->getLogger()->debug(
                        self::MESSAGE__CREATED_MIGRATION_TABLE__TABLE,
                        [
                            'table' => $this->migrationTableName,
                        ]
                    );
                }
            } catch (\PDOException $exception) {
                $context = [
                    'table' => $this->migrationTableName,
                ];

                if ($this->getLogger()) {
                    $this->getLogger()->critical(
                        self::MESSAGE__COULD_NOT_CREATE_TABLE__TABLE,
                        $context
                    );
                }

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
    }

    /**
     * @param string $pathToMigrationFile
     * @throws DatabaseException
     */
    protected function registerMigrationFile($pathToMigrationFile)
    {
        $migrationId = $this->getMigrationId($pathToMigrationFile);
        try {
            /** @noinspection SqlNoDataSourceInspection,SqlDialectInspection */
            $this->pdo->prepare(
                    $this->adapter->insertIntoTable($this->migrationTableName)
            )->execute(['id' => $migrationId]);
        } catch (\PDOException $exception) {
            $context = [
                'id' => $migrationId,
            ];

            if (null != $this->getLogger()) {
                $this->getLogger()->critical(
                    self::MESSAGE__COULD_NOT_REGISTER_MIGRATION__ID,
                    $context
                );
            }

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
            /** @noinspection SqlNoDataSourceInspection,SqlDialectInspection */
            $statement = $this->pdo->prepare(
                    $this->adapter->selectNullFromId($this->migrationTableName)
            );
            $statement->execute(['id' => $migrationId]);

            return false !== $statement->fetch();
        } catch (\PDOException $exception) {
            $context = [
                'table' => $this->migrationTableName,
            ];

            if ($this->getLogger()) {
                $this->getLogger()->critical(
                    self::MESSAGE__COULD_NOT_READ_FROM_TABLE__TABLE,
                    $context
                );
            }

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

            if ($this->getLogger()) {
                $this->getLogger()->critical(
                    self::MESSAGE__COULD_NOT_READ_MIGRATION_FILE__PATH,
                    $context
                );
            }

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

        $this->pdo->beginTransaction();

        try {
            if ($this->supportsMultiStatements) {
                $statement = $this->pdo->prepare($migrationData);
                $statement->execute();
                while ($statement->nextRowset());
                $statement->closeCursor();
            } else {
                $this->pdo->exec($migrationData);
            }
            $this->registerMigrationFile($pathToMigrationFile);
            $this->pdo->commit();
        } catch (\PDOException $exception) {
            $this->pdo->rollBack();

            $context = [
                'path' => $pathToMigrationFile,
            ];

            if ($this->getLogger()) {
                $this->getLogger()->critical(
                    self::MESSAGE__YOU_HAVE_AN_ERROR_IN_YOUR_SQL_SYNTAX__PATH,
                    $context
                );
            }

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
