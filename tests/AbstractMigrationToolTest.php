<?php

namespace PetrKnap\Php\MigrationTool\Test;

use PetrKnap\Php\MigrationTool\AbstractMigrationTool;
use PetrKnap\Php\MigrationTool\Exception\MismatchException;
use PetrKnap\Php\MigrationTool\Test\AbstractMigrationToolTest\AbstractMigrationToolMock;
use PHPUnit_Framework_Error_Notice;
use PHPUnit_Framework_Error_Warning;
use Psr\Log\LoggerInterface;

class AbstractMigrationToolTest extends TestCase
{
    /**
     * @dataProvider dataMigrateMethodWorks
     * @param array $appliedMigrations
     * @param array $expectedAppliedMigrations
     * @param LoggerInterface $logger
     */
    public function testMigrateMethodWorks(array $appliedMigrations, array $expectedAppliedMigrations, LoggerInterface $logger = null)
    {
        $tool = new AbstractMigrationToolMock(
            $appliedMigrations,
            __DIR__ . '/AbstractMigrationToolTest/MigrateMethodWorks'
        );

        if ($logger) {
            $tool->setLogger($logger);
        }

        try {
            $tool->migrate();
        } catch (PHPUnit_Framework_Error_Notice $ignored) {
            // It throws notice if all migrations are applied
        }

        $this->assertEquals($expectedAppliedMigrations, $tool->getAppliedMigrations());
    }

    public function dataMigrateMethodWorks()
    {
        $expectedAppliedMigrations = ['2017-02-05.1', '2017-02-05.2', '2017-02-05.3'];
        return [
            [[], $expectedAppliedMigrations],
            [['2017-02-05.1'], $expectedAppliedMigrations],
            [['2017-02-05.1', '2017-02-05.2'], $expectedAppliedMigrations],
            [['2017-02-05.1', '2017-02-05.2', '2017-02-05.3'], $expectedAppliedMigrations],
        ];
    }

    public function testMigrateMethodLogs()
    {
        $log = [];
        $data = $this->dataMigrateMethodWorks();
        $this->testMigrateMethodWorks(
            $data[0][0],
            $data[0][1],
            $this->getLogger($log)
        );
        $this->assertLogEquals([
            'info' => [
                AbstractMigrationTool::MESSAGE__FOUND_MIGRATION_FILES__COUNT_PATH_PATTERN,
                AbstractMigrationTool::MESSAGE__MIGRATION_FILE_APPLIED__PATH,
                AbstractMigrationTool::MESSAGE__MIGRATION_FILE_APPLIED__PATH,
                AbstractMigrationTool::MESSAGE__MIGRATION_FILE_APPLIED__PATH,
                AbstractMigrationTool::MESSAGE__DONE,
            ],
        ], $log);
    }

    /**
     * @dataProvider dataThrowsMismatchExceptionIfThereIsGapeInMigrations
     * @param array $appliedMigrations
     * @param LoggerInterface $logger
     */
    public function testThrowsMismatchExceptionIfThereIsGapeInMigrations(array $appliedMigrations, LoggerInterface $logger = null)
    {
        $tool = new AbstractMigrationToolMock(
            $appliedMigrations,
            __DIR__ . '/AbstractMigrationToolTest/ThrowsMismatchExceptionIfThereIsGapeInMigrations'
        );

        if ($logger) {
            $tool->setLogger($logger);
        }

        try {
            $tool->migrate();
        } catch (MismatchException $exception) {
            $this->assertStringMatchesFormat(
                $this->getFormatForMessage(AbstractMigrationTool::MESSAGE__DETECTED_GAPE_BEFORE_MIGRATION__ID),
                $exception->getMessage()
            );
        }
    }

    public function dataThrowsMismatchExceptionIfThereIsGapeInMigrations()
    {
        return [
            [['2017-02-05.2']],
            [['2017-02-05.3']],
            [['2017-02-05.1', '2017-02-05.3']],
            [['2017-02-05.2', '2017-02-05.3']],
        ];
    }

    public function testLogsMismatchExceptionIfThereIsGapeInMigrations()
    {
        $log = [];
        $data = $this->dataThrowsMismatchExceptionIfThereIsGapeInMigrations();
        $this->testThrowsMismatchExceptionIfThereIsGapeInMigrations(
            $data[0][0],
            $this->getLogger($log)
        );
        $this->assertLogEquals([
            'info' => [
                AbstractMigrationTool::MESSAGE__FOUND_MIGRATION_FILES__COUNT_PATH_PATTERN,
            ],
            'critical' => [
                AbstractMigrationTool::MESSAGE__DETECTED_GAPE_BEFORE_MIGRATION__ID,
            ],
        ], $log);
    }

    public function testThrowsWarningIfMigrationFolderIsEmpty(LoggerInterface $logger = null)
    {
        $dir = __DIR__ . '/AbstractMigrationToolTest/ThrowsWarningIfMigrationFolderIsEmpty';
        @mkdir($dir);
        $tool = new AbstractMigrationToolMock([], $dir);

        if ($logger) {
            $tool->setLogger($logger);
        }

        try {
            $tool->migrate();
            $this->assertNotNull($logger); // if there is a logger
        } catch (PHPUnit_Framework_Error_Warning $warning) {
            $this->assertNull($logger); // if there is not a logger
            $this->assertStringMatchesFormat(
                $this->getFormatForMessage(AbstractMigrationTool::MESSAGE__THERE_IS_NOTHING_MATCHING_PATTERN__PATH_PATTERN),
                $warning->getMessage()
            );
        }
    }

    public function testLogsWarningIfMigrationFolderIsEmpty()
    {
        $log = [];
        $this->testThrowsWarningIfMigrationFolderIsEmpty(
            $this->getLogger($log)
        );
        $this->assertLogEquals([
            'warning' => [
                AbstractMigrationTool::MESSAGE__THERE_IS_NOTHING_MATCHING_PATTERN__PATH_PATTERN,
            ],
            'info' => [
                AbstractMigrationTool::MESSAGE__FOUND_MIGRATION_FILES__COUNT_PATH_PATTERN,
                AbstractMigrationTool::MESSAGE__DONE,
            ],
            'notice' => [
                AbstractMigrationTool::MESSAGE__THERE_IS_NOTHING_TO_MIGRATE__PATH_PATTERN,
            ],
        ], $log);
    }

    public function testThrowsNoticeIfThereIsNothingToMigrate(LoggerInterface $logger = null)
    {
        $tool = new AbstractMigrationToolMock(
            ['2017-02-05.1', '2017-02-05.2', '2017-02-05.3'],
            __DIR__ . '/AbstractMigrationToolTest/ThrowsNoticeIfThereIsNothingToMigrate'
        );

        if ($logger) {
            $tool->setLogger($logger);
        }

        try {
            $tool->migrate();
            $this->assertNotNull($logger); // if there is a logger
        } catch (PHPUnit_Framework_Error_Notice $notice) {
            $this->assertNull($logger); // if there is not a logger
            $this->assertStringMatchesFormat(
                $this->getFormatForMessage(AbstractMigrationTool::MESSAGE__THERE_IS_NOTHING_MATCHING_PATTERN__PATH_PATTERN),
                $notice->getMessage()
            );
        }
    }

    public function testLogsNoticeIfThereIsNothingToMigrate()
    {
        $log = [];
        $this->testThrowsNoticeIfThereIsNothingToMigrate(
            $this->getLogger($log)
        );
        $this->assertLogEquals([
            'info' => [
                AbstractMigrationTool::MESSAGE__FOUND_MIGRATION_FILES__COUNT_PATH_PATTERN,
                AbstractMigrationTool::MESSAGE__DONE,
            ],
            'notice' => [
                AbstractMigrationTool::MESSAGE__THERE_IS_NOTHING_TO_MIGRATE__PATH_PATTERN,
            ],
        ], $log);
    }

    public function testThrowsNoticeIfThereIsUnsupportedFile(LoggerInterface $logger = null)
    {
        $tool = new AbstractMigrationToolMock(
            [],
            __DIR__ . '/AbstractMigrationToolTest/ThrowsNoticeIfThereIsUnsupportedFile'
        );

        if ($logger) {
            $tool->setLogger($logger);
        }

        try {
            $tool->migrate();
            $this->assertNotNull($logger); // if there is a logger
        } catch (PHPUnit_Framework_Error_Notice $notice) {
            $this->assertNull($logger); // if there is not a logger
            $this->assertStringMatchesFormat(
                $this->getFormatForMessage(AbstractMigrationTool::MESSAGE__FOUND_UNSUPPORTED_FILE__PATH),
                $notice->getMessage()
            );
        }
    }

    public function testLogsNoticeIfThereIsUnsupportedFile()
    {
        $log = [];
        $this->testThrowsNoticeIfThereIsUnsupportedFile(
            $this->getLogger($log)
        );
        $this->assertLogEquals([
            'notice' => [
                AbstractMigrationTool::MESSAGE__FOUND_UNSUPPORTED_FILE__PATH,
                AbstractMigrationTool::MESSAGE__THERE_IS_NOTHING_TO_MIGRATE__PATH_PATTERN,
            ],
            'warning' => [
                AbstractMigrationTool::MESSAGE__THERE_IS_NOTHING_MATCHING_PATTERN__PATH_PATTERN,
            ],
            'info' => [
                AbstractMigrationTool::MESSAGE__FOUND_MIGRATION_FILES__COUNT_PATH_PATTERN,
                AbstractMigrationTool::MESSAGE__DONE,
            ],
        ], $log);
    }

    public function testGetMigrationFilesMethodWorks()
    {
        $tool = new AbstractMigrationToolMock(
            [],
            __DIR__ . '/AbstractMigrationToolTest/GetMigrationFilesWorks'
        );

        $this->assertEquals(
            [
                __DIR__ . '/AbstractMigrationToolTest/GetMigrationFilesWorks/2016-06-22.1 - First migration.ext',
                __DIR__ . '/AbstractMigrationToolTest/GetMigrationFilesWorks/2016-06-22.2 - Second migration.ext',
                __DIR__ . '/AbstractMigrationToolTest/GetMigrationFilesWorks/2016-06-22.3 - Third migration.ext',
            ],
            @$this->invokeMethods($tool, [['getMigrationFiles']]) // @ because it throws notice if there is unsupported file
        );
    }

    /**
     * @dataProvider dataGetMigrationIdMethodWorks
     * @param string $pathToMigrationFile
     * @param string $expectedMigrationId
     */
    public function testGetMigrationIdMethodWorks($pathToMigrationFile, $expectedMigrationId)
    {
        $tool = new AbstractMigrationToolMock([]);

        $this->assertEquals(
            $expectedMigrationId,
            $this->invokeMethods($tool, [['getMigrationId', [$pathToMigrationFile]]])
        );
    }

    public function dataGetMigrationIdMethodWorks()
    {
        return [
            ['/migration_file.ext', 'migration_file'],
            ['/migration file.ext', 'migration'],
            ['/migration_file', 'migration_file'],
            ['/migration file', 'migration'],
        ];
    }
}
