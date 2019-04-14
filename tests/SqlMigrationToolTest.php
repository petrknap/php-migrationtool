<?php

namespace PetrKnap\Php\MigrationTool\Test;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PetrKnap\Php\MigrationTool\Exception\DatabaseException;
use PetrKnap\Php\MigrationTool\Exception\MigrationException;
use PetrKnap\Php\MigrationTool\Exception\MigrationFileException;
use PetrKnap\Php\MigrationTool\SqlMigrationTool;
use Psr\Log\LoggerInterface;

class SqlMigrationToolTest extends TestCase
{
    const TABLE_NAME = 'migration_table';

    private function getConnection()
    {
        $config = new Configuration();
        $connectionParams = [
            'driver' => 'pdo_sqlite',
            'host' => ':memory:',
        ];
        return DriverManager::getConnection($connectionParams, $config);
    }

    private function getTool(Connection $connection, $dir = null, $table = null)
    {
        if (null === $table) {
            $table = self::TABLE_NAME;
        }
        return new SqlMigrationTool($dir, $connection, $table);
    }

    public function testItAcceptsOnlySqlFiles()
    {
        $tool = $this->getTool($this->getConnection());
        $this->assertEquals('/\.sql$/i', $this->getProperty($tool, 'filePattern'));
    }

    public function testCreateMigrationTableMethodWorks(LoggerInterface $logger = null)
    {
        $pdo = $this->getConnection();
        $tool = $this->getTool($pdo);

        if ($logger) {
            $tool->setLogger($logger);
        }

        $this->invokeMethods($tool, [
            ['createMigrationTable'], // create table
            ['createMigrationTable'], // if not exists
        ]);

        /** @noinspection SqlDialectInspection, SqlNoDataSourceInspection */
        $statement = $pdo->prepare('SELECT name FROM sqlite_master WHERE name = :name');
        $statement->execute(['name' => self::TABLE_NAME]);

        $this->assertEquals(['name' => self::TABLE_NAME], $statement->fetch(\PDO::FETCH_ASSOC));
    }

    public function testCreateMigrationTableMethodLogs()
    {
        $log = [];
        $this->testCreateMigrationTableMethodWorks($this->getLogger($log));

        $this->assertLogEquals([
            'debug' => [
                SqlMigrationTool::MESSAGE__CREATED_MIGRATION_TABLE__TABLE,
            ],
        ], $log);
    }

    public function testCreateMigrationTableMethodThrowsDatabaseExceptionIfCouldNotCreateTable(LoggerInterface $logger = null)
    {
        $pdo = $this->getConnection();
        $tool = new SqlMigrationTool(null, $pdo, 'invalid name');

        if ($logger) {
            $tool->setLogger($logger);
        }

        try {
            $this->invokeMethods($tool, [
                ['createMigrationTable'],
            ]);
            $this->fail();
        } catch (DatabaseException $exception) {
            $this->assertStringMatchesFormat(
                $this->getFormatForMessage(SqlMigrationTool::MESSAGE__COULD_NOT_CREATE_TABLE__TABLE),
                $exception->getMessage()
            );
        }
    }

    public function testCreateMigrationTableMethodLogsDatabaseExceptionIfCouldNotCreateTable()
    {
        $log = [];
        $this->testCreateMigrationTableMethodThrowsDatabaseExceptionIfCouldNotCreateTable($this->getLogger($log));

        $this->assertLogEquals([
            'critical' => [
                SqlMigrationTool::MESSAGE__COULD_NOT_CREATE_TABLE__TABLE,
            ],
        ], $log);
    }

    public function testRegisterMigrationFileMethodWorks(LoggerInterface $logger = null)
    {
        $pdo = $this->getConnection();
        $tool = $this->getTool($pdo);

        if ($logger) {
            $tool->setLogger($logger);
        }

        $this->invokeMethods($tool, [
            ['createMigrationTable'],
            ['registerMigrationFile', [
                __DIR__ . '/SqlMigrationToolTest/RegisterMigrationFileMethodWorks/2017-02-05.1 - First migration.sql',
            ]],
        ]);

        /** @noinspection SqlDialectInspection, SqlNoDataSourceInspection */
        $statement = $pdo->prepare(sprintf('SELECT COUNT(id) AS count FROM %s', self::TABLE_NAME));
        $statement->execute();

        $this->assertEquals(['count' => 1], $statement->fetch(\PDO::FETCH_ASSOC));
    }

    public function testRegisterMigrationFileMethodLogs()
    {
        $log = [];
        $this->testRegisterMigrationFileMethodWorks($this->getLogger($log));

        $this->assertLogEquals([
            'debug' => [
                SqlMigrationTool::MESSAGE__CREATED_MIGRATION_TABLE__TABLE,
            ],
        ], $log);
    }

    public function testRegisterMigrationFileMethodThrowsDatabaseExceptionIfCouldNotRegisterMigrationId(LoggerInterface $logger = null)
    {
        $pdo = $this->getConnection();
        $tool = $this->getTool($pdo);

        if ($logger) {
            $tool->setLogger($logger);
        }

        try {
            $this->invokeMethods($tool, [
                ['createMigrationTable'],
                ['registerMigrationFile', [
                    '/2017-02-05.1.sql',
                ]],
                ['registerMigrationFile', [
                    '/2017-02-05.1.sql',
                ]],
            ]);
            $this->fail();
        } catch (DatabaseException $exception) {
            $this->assertStringMatchesFormat(
                $this->getFormatForMessage(SqlMigrationTool::MESSAGE__COULD_NOT_REGISTER_MIGRATION__ID),
                $exception->getMessage()
            );
        }
    }

    public function testRegisterMigrationFileMethodLogsDatabaseExceptionIfCouldNotRegisterMigrationId()
    {
        $log = [];
        $this->testRegisterMigrationFileMethodThrowsDatabaseExceptionIfCouldNotRegisterMigrationId($this->getLogger($log));

        $this->assertLogEquals([
            'debug' => [
                SqlMigrationTool::MESSAGE__CREATED_MIGRATION_TABLE__TABLE,
            ],
            'critical' => [
                SqlMigrationTool::MESSAGE__COULD_NOT_REGISTER_MIGRATION__ID,
            ],
        ], $log);
    }

    /**
     * @dataProvider dataIsMigrationAppliedMethodWorks
     * @param string $migrationFile
     * @param bool $expectedResult
     * @param LoggerInterface $logger
     */
    public function testIsMigrationAppliedMethodWorks($migrationFile, $expectedResult, LoggerInterface $logger = null)
    {
        $pdo = $this->getConnection();
        $tool = $this->getTool($pdo);

        if ($logger) {
            $tool->setLogger($logger);
        }

        $this->assertEquals(
            $expectedResult,
            $this->invokeMethods($tool, [
                ['createMigrationTable'],
                ['registerMigrationFile', [
                    '/2017-02-05.1.sql',
                ]],
                ['isMigrationApplied', [$migrationFile]],
            ])
        );
    }

    public function dataIsMigrationAppliedMethodWorks()
    {
        return [
            ['/2017-02-05.1.sql', true],
            ['/2017-02-05.2.sql', false],
        ];
    }

    public function testIsMigrationAppliedMethodLogs()
    {
        $log = [];
        $data = $this->dataIsMigrationAppliedMethodWorks();
        $this->testIsMigrationAppliedMethodWorks(
            $data[0][0],
            $data[0][1],
            $this->getLogger($log)
        );

        $this->assertLogEquals([
            'debug' => [
                SqlMigrationTool::MESSAGE__CREATED_MIGRATION_TABLE__TABLE,
            ],
        ], $log);
    }

    public function testIsMigrationAppliedMethodThrowsDatabaseExceptionIfCouldNotReadFromTable(LoggerInterface $logger = null)
    {
        $pdo = $this->getConnection();
        $tool = $this->getTool($pdo);

        if ($logger) {
            $tool->setLogger($logger);
        }

        try {
            $this->invokeMethods($tool, [
                ['isMigrationApplied', ['/2017-02-05.1.sql']],
            ]);
            $this->fail();
        } catch (DatabaseException $exception) {
            $this->assertStringMatchesFormat(
                $this->getFormatForMessage(SqlMigrationTool::MESSAGE__COULD_NOT_READ_FROM_TABLE__TABLE),
                $exception->getMessage()
            );
        }
    }

    public function testIsMigrationAppliedMethodLogsDatabaseExceptionIfCouldNotReadFromTable()
    {
        $log = [];
        $this->testIsMigrationAppliedMethodThrowsDatabaseExceptionIfCouldNotReadFromTable(
            $this->getLogger($log)
        );

        $this->assertLogEquals([
            'critical' => [
                SqlMigrationTool::MESSAGE__COULD_NOT_READ_FROM_TABLE__TABLE,
            ],
        ], $log);
    }

    /**
     * @dataProvider dataApplyMigrationFileMethodWorks
     * @param string $pathToMigrationFile
     * @param int $expectedCount
     * @param LoggerInterface $logger
     */
    public function testApplyMigrationFileMethodWorks($pathToMigrationFile, $expectedCount, LoggerInterface $logger = null)
    {
        $pdo = $this->getConnection();
        $tool = $this->getTool($pdo);

        if ($logger) {
            $tool->setLogger($logger);
        }

        $this->invokeMethods($tool, [
            ['createMigrationTable'],
            ['applyMigrationFile', [
                __DIR__ . '/SqlMigrationToolTest/ApplyMigrationFileMethodWorks/create_table.sql',
            ]],
        ]);

        $this->invokeMethods($tool, [
            ['applyMigrationFile', [
                $pathToMigrationFile,
            ]],
        ]);

        /** @noinspection SqlNoDataSourceInspection, SqlDialectInspection */
        $this->assertEquals(
            ['count' => $expectedCount],
            $pdo->query('SELECT COUNT(*) AS count FROM t')->fetch(\PDO::FETCH_ASSOC)
        );
    }

    public function dataApplyMigrationFileMethodWorks()
    {
        return [
            [
                __DIR__ . '/SqlMigrationToolTest/ApplyMigrationFileMethodWorks/single_query.sql',
                2,
            ],
            [
                __DIR__ . '/SqlMigrationToolTest/ApplyMigrationFileMethodWorks/multi_query.sql',
                1,
            ],
        ];
    }

    public function testApplyMigrationFileMethodLogs()
    {
        $log = [];
        $data = $this->dataApplyMigrationFileMethodWorks();
        $this->testApplyMigrationFileMethodWorks(
            $data[0][0],
            $data[0][1],
            $this->getLogger($log)
        );

        $this->assertLogEquals([
            'debug' => [
                SqlMigrationTool::MESSAGE__CREATED_MIGRATION_TABLE__TABLE,
            ],
        ], $log);
    }

    /**
     * @dataProvider dataApplyMigrationFileMethodThrowsMigrationFileExceptionIfThereIsBrokenMigrationFile
     * @param string $pathToMigrationFile
     * @param string $expectedMessage
     * @param LoggerInterface $logger
     */
    public function testApplyMigrationFileMethodThrowsMigrationFileExceptionIfThereIsBrokenMigrationFile($pathToMigrationFile, $expectedMessage, LoggerInterface $logger = null)
    {
        $pdo = $this->getConnection();
        $tool = $this->getTool($pdo);

        if ($logger) {
            $tool->setLogger($logger);
        }

        $this->invokeMethods($tool, [
            ['createMigrationTable'],
            ['applyMigrationFile', [
                __DIR__ . '/SqlMigrationToolTest/ApplyMigrationFileMethodThrowsMigrationFileExceptionIfThereIsBrokenMigrationFile/create_table.sql',
            ]],
        ]);

        try {
            $this->invokeMethods($tool, [
                ['applyMigrationFile', [
                    $pathToMigrationFile,
                ]],
            ]);
            $this->fail('Expected exception');
        } catch (MigrationFileException $exception) {
            $this->assertStringMatchesFormat(
                $this->getFormatForMessage($expectedMessage),
                $exception->getMessage()
            );
        }
    }

    public function dataApplyMigrationFileMethodThrowsMigrationFileExceptionIfThereIsBrokenMigrationFile()
    {
        return [
            [
                __DIR__ . '/SqlMigrationToolTest/ApplyMigrationFileMethodThrowsMigrationFileExceptionIfThereIsBrokenMigrationFile/single_query_with_error.sql',
                SqlMigrationTool::MESSAGE__YOU_HAVE_AN_ERROR_IN_YOUR_SQL_SYNTAX__PATH,
            ],
            [
                __DIR__ . '/SqlMigrationToolTest/ApplyMigrationFileMethodThrowsMigrationFileExceptionIfThereIsBrokenMigrationFile/multi_query_with_error.sql',
                SqlMigrationTool::MESSAGE__YOU_HAVE_AN_ERROR_IN_YOUR_SQL_SYNTAX__PATH,
            ],
            [
                __DIR__ . '/SqlMigrationToolTest/ApplyMigrationFileMethodThrowsMigrationFileExceptionIfThereIsBrokenMigrationFile/file_not_found.sql',
                SqlMigrationTool::MESSAGE__COULD_NOT_READ_MIGRATION_FILE__PATH
            ],
        ];
    }

    public function testApplyMigrationFileMethodLogsMigrationFileExceptionIfThereIsBrokenMigrationFile()
    {
        $log = [];
        $data = $this->dataApplyMigrationFileMethodThrowsMigrationFileExceptionIfThereIsBrokenMigrationFile();
        $this->testApplyMigrationFileMethodThrowsMigrationFileExceptionIfThereIsBrokenMigrationFile(
            $data[0][0],
            $data[0][1],
            $this->getLogger($log)
        );
        $this->testApplyMigrationFileMethodThrowsMigrationFileExceptionIfThereIsBrokenMigrationFile(
            $data[2][0],
            $data[2][1],
            $this->getLogger($log)
        );

        $this->assertLogEquals([
            'debug' => [
                SqlMigrationTool::MESSAGE__CREATED_MIGRATION_TABLE__TABLE,
                SqlMigrationTool::MESSAGE__CREATED_MIGRATION_TABLE__TABLE,
            ],
            'critical' => [
                SqlMigrationTool::MESSAGE__YOU_HAVE_AN_ERROR_IN_YOUR_SQL_SYNTAX__PATH,
                SqlMigrationTool::MESSAGE__COULD_NOT_READ_MIGRATION_FILE__PATH,
            ],
        ], $log);
    }

    /**
     * @dataProvider dataApplyMigrationFileMethodRollbacksTransactionIfThereIsBrokenMigrationFile
     * @param string $pathToMigrationFile
     */
    public function testApplyMigrationFileMethodRollbacksTransactionIfThereIsBrokenMigrationFile($pathToMigrationFile)
    {
        $pdo = $this->getConnection();
        $tool = $this->getTool($pdo);

        $this->invokeMethods($tool, [
            ['createMigrationTable'],
            ['applyMigrationFile', [
                __DIR__ . '/SqlMigrationToolTest/ApplyMigrationFileMethodRollbacksTransactionIfThereIsBrokenMigrationFile/create_table.sql',
            ]],
        ]);

        try {
            $this->invokeMethods($tool, [
                ['applyMigrationFile', [
                    $pathToMigrationFile,
                ]],
            ]);
            $this->fail('Expected exception');
        } catch (MigrationFileException $ignored) {
            /** @noinspection SqlDialectInspection, SqlNoDataSourceInspection */
            $this->assertEquals(
                ['count' => 2],
                $pdo->query('SELECT COUNT(*) AS count FROM t')->fetch(\PDO::FETCH_ASSOC)
            );
        }
    }

    public function dataApplyMigrationFileMethodRollbacksTransactionIfThereIsBrokenMigrationFile()
    {
        return [
            [
                __DIR__ . '/SqlMigrationToolTest/ApplyMigrationFileMethodRollbacksTransactionIfThereIsBrokenMigrationFile/single_query_with_error.sql',
            ],
            [
                __DIR__ . '/SqlMigrationToolTest/ApplyMigrationFileMethodRollbacksTransactionIfThereIsBrokenMigrationFile/multi_query_with_error.sql',
            ],
        ];
    }

    public function testMigrationProcessStopsAtFirstException()
    {
        $pdo = $this->getConnection();
        $tool = $this->getTool($pdo, __DIR__ . '/SqlMigrationToolTest/MigrationProcessStopsAtFirstException');

        try {
            $tool->migrate();
            $this->fail();
        } catch (MigrationException $ignored) {
            // Ignored exception
        }

        /** @noinspection SqlDialectInspection, SqlNoDataSourceInspection */
        $rows = $pdo->query('SELECT v FROM t');
        foreach ($rows as $row) {
            $this->assertContains($row['v'], [2, 3, 4, 5, 6]);
        }

        /** @noinspection SqlDialectInspection, SqlNoDataSourceInspection */
        $rows = $pdo->query(sprintf('SELECT id FROM %s', self::TABLE_NAME));
        foreach ($rows as $row) {
            $this->assertContains($row['id'], ['2017-02-05.1', '2017-02-05.2']);
        }
    }
}
