<?php

namespace PetrKnap\Php\MigrationTool;

use PetrKnap\Php\MigrationTool\Exception\MismatchException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract migration tool
 *
 * @author   Petr Knap <dev@petrknap.cz>
 * @since    2016-06-22
 * @license  https://github.com/petrknap/php-migrationtool/blob/master/LICENSE MIT
 */
abstract class AbstractMigrationTool implements MigrationToolInterface, LoggerAwareInterface
{
    const MESSAGE__FOUND_UNSUPPORTED_FILE__PATH = 'Found unsupported file {path}';
    const MESSAGE__FOUND_MIGRATION_FILES__COUNT_PATH_PATTERN = 'Found {count} migration files in {path} matching {pattern}';
    const MESSAGE__MIGRATION_FILE_APPLIED__PATH = 'Migration file {path} applied';
    const MESSAGE__THERE_IS_NOTHING_MATCHING_PATTERN__PATH_PATTERN = 'In {path} is nothing matching {pattern}';
    const MESSAGE__THERE_IS_NOTHING_TO_MIGRATE__PATH_PATTERN = 'In {path} is nothing matching {pattern} to migrate';
    const MESSAGE__DETECTED_GAPE_BEFORE_MIGRATION__ID = 'Detected gape before migration {id}';
    const MESSAGE__DONE = 'Database is now up-to-date';

    /**
     * @var string
     */
    private $directory;

    /**
     * @var string
     */
    private $filePattern;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param string $directory
     * @param string $filePattern
     */
    public function __construct($directory, $filePattern = '/^.*$/i')
    {
        $this->directory = $directory;
        $this->filePattern = $filePattern;
    }

    /**
     * Interpolates context values into the message placeholders for exceptions
     *
     * @param string $message
     * @param array $context
     * @return string
     */
    protected function interpolate($message, array $context = [])
    {
        $replace = [];
        foreach ($context as $key => $val) {
            // check that the value can be casted to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * @inheritdoc
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        if (null === $this->logger) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * @inheritdoc
     */
    public function migrate()
    {
        $migrationModules = $this->getMigrationFiles();
        $migrationFilesToMigrate = [];
        foreach ($migrationModules as $migrationModuleId => $migrationFiles) {
            $migrationFilesToMigrate[$migrationModuleId] = $this->migrateModule($migrationModuleId, $migrationFiles);
        }
        
        foreach ($migrationFilesToMigrate as $moduleId => $migrationFiles) {
            if (empty($migrationFiles)) {
                $context = [
                    'path' => $migrationFiles,
                    'pattern' => $this->filePattern,
                ];

                $this->getLogger()->notice(
                    self::MESSAGE__THERE_IS_NOTHING_TO_MIGRATE__PATH_PATTERN,
                    $context
                );
            } else {
                 foreach ($migrationFiles as $migrationFile) {
                    $this->applyMigrationFile($moduleId, $migrationFile);
                    $this->getLogger()->info(
                        self::MESSAGE__MIGRATION_FILE_APPLIED__PATH,
                        [
                            'path' => $migrationFile,
                        ]
                    );
                }
            }
        }

        $this->getLogger()->info(
            self::MESSAGE__DONE
        );
    }
    
    private function migrateModule($moduleId, array $migrationFiles) {
        $migrationFilesToMigrate = [];
        foreach ($migrationFiles as $migrationFile) {
            if ($this->isMigrationApplied($moduleId, $migrationFile)) {
                if (!empty($migrationFilesToMigrate)) {
                    $context = [
                        'id' => $this->getMigrationId($moduleId, $migrationFile),
                    ];

                    $this->getLogger()->critical(
                        self::MESSAGE__DETECTED_GAPE_BEFORE_MIGRATION__ID,
                        $context
                    );

                    throw new MismatchException(
                        sprintf(
                            "%s\nFiles to migrate:\n\t%s",
                            $this->interpolate(
                                self::MESSAGE__DETECTED_GAPE_BEFORE_MIGRATION__ID,
                                $context
                            ),
                            implode("\n\t", $migrationFilesToMigrate)
                        )
                    );
                }
            } else {
                $migrationFilesToMigrate[] = $migrationFile;
            }   
        }
        return $migrationFilesToMigrate;
        
    }

    /**
     * Returns list of paths to migration files
     *
     * @return string[]
     */
    protected function getMigrationFiles()
    {
        $migrationFiles = [];
        if (is_array($this->directory)) {
            foreach ($this->directory as $module => $dir) {
                $migrations = $this->getMigrationFilesFromModule(new \DirectoryIterator($dir));
                sort($migrations);
                $migrationFiles[$module . '_'] = $migrations;
            }
        } else {
            $migrationFiles[''] = $this->getMigrationFilesFromModule(new \DirectoryIterator($this->directory));
        }

        foreach ($migrationFiles as $list) {
            if (empty($list)) {
                $context = [
                    'path' => $this->directory,
                    'pattern' => $this->filePattern,
                ];

                $this->getLogger()->warning(
                    self::MESSAGE__THERE_IS_NOTHING_MATCHING_PATTERN__PATH_PATTERN,
                    $context
                );
            }
        }

        $this->getLogger()->info(
            self::MESSAGE__FOUND_MIGRATION_FILES__COUNT_PATH_PATTERN,
            [
                'count' => count($migrationFiles),
                'path' => $this->directory,
                'pattern' => $this->filePattern,
            ]
        );

        return $migrationFiles;
    }
    
    private function getMigrationFilesFromModule($directoryIterator) {
        $migrationFiles = [];
        foreach ($directoryIterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isFile()) {
                if (preg_match($this->filePattern, $fileInfo->getRealPath())) {
                    $migrationFiles[] = $fileInfo->getRealPath();
                } else {
                    $context = [
                        'path' => $fileInfo->getRealPath(),
                    ];

                    $this->getLogger()->notice(
                        self::MESSAGE__FOUND_UNSUPPORTED_FILE__PATH,
                        $context
                    );
                }
            }
        }
        return $migrationFiles;
    }

    /**
     * @param string $pathToMigrationFile
     * @return string
     */
    protected function getMigrationId($moduleId, $pathToMigrationFile)
    {
        $fileInfo = new \SplFileInfo($pathToMigrationFile);
        $basenameParts = explode(' ', $fileInfo->getBasename('.' . $fileInfo->getExtension()));
        return $moduleId . $basenameParts[0];
    }

    /**
     * @param string $pathToMigrationFile
     * @return bool
     */
    abstract protected function isMigrationApplied($moduleId, $pathToMigrationFile);

    /**
     * @param $pathToMigrationFile
     * @return void
     */
    abstract protected function applyMigrationFile($moduleId, $pathToMigrationFile);
}
