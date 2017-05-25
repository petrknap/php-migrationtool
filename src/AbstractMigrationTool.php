<?php

namespace PetrKnap\Php\MigrationTool;

use PetrKnap\Php\MigrationTool\Exception\MismatchException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Abstract migration tool
 *
 * @author   Petr Knap <dev@petrknap.cz>
 * @since    2016-06-22
 * @license  https://github.com/petrknap/php-migrationtool/blob/master/LICENSE MIT
 */
abstract class AbstractMigrationTool implements MigrationToolInterface, LoggerAwareInterface
{
    const MESSAGE__WRONG_CONFIGURATION__OBJECT_ATTRIBUTE_EXPECTED = 'The {object}\'s attribute {attribute} was expected to be set to {expected}';
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
        return $this->logger;
    }

    /**
     * @inheritdoc
     */
    public function migrate()
    {
        $migrationFiles = $this->getMigrationFiles();
        $migrationFilesToMigrate = [];
        foreach ($migrationFiles as $migrationFile) {
            if ($this->isMigrationApplied($migrationFile)) {
                if (!empty($migrationFilesToMigrate)) {
                    $context = [
                        'id' => $this->getMigrationId($migrationFile),
                    ];

                    if ($this->logger) {
                        $this->logger->critical(
                            self::MESSAGE__DETECTED_GAPE_BEFORE_MIGRATION__ID,
                            $context
                        );
                    }

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

        if (empty($migrationFilesToMigrate)) {
            $context = [
                'path' => $this->directory,
                'pattern' => $this->filePattern,
            ];

            if ($this->logger) {
                $this->logger->notice(
                    self::MESSAGE__THERE_IS_NOTHING_TO_MIGRATE__PATH_PATTERN,
                    $context
                );
            } else {
                user_error(
                    $this->interpolate(
                        self::MESSAGE__THERE_IS_NOTHING_TO_MIGRATE__PATH_PATTERN,
                        $context
                    ),
                    E_USER_NOTICE
                );
            }
        } else {
            foreach ($migrationFilesToMigrate as $migrationFile) {
                $this->applyMigrationFile($migrationFile);

                if ($this->logger) {
                    $this->logger->info(
                        self::MESSAGE__MIGRATION_FILE_APPLIED__PATH,
                        [
                            'path' => $migrationFile,
                        ]
                    );
                }
            }
        }

        if ($this->logger) {
            $this->logger->info(
                self::MESSAGE__DONE
            );
        }
    }

    /**
     * Returns list of paths to migration files
     *
     * @return string[]
     */
    protected function getMigrationFiles()
    {
        $directoryIterator = new \DirectoryIterator($this->directory);
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

                    if ($this->logger) {
                        $this->logger->notice(
                            self::MESSAGE__FOUND_UNSUPPORTED_FILE__PATH,
                            $context
                        );
                    } else {
                        user_error(
                            $this->interpolate(
                                self::MESSAGE__FOUND_UNSUPPORTED_FILE__PATH,
                                $context
                            ),
                            E_USER_NOTICE
                        );
                    }
                }
            }
        }
        sort($migrationFiles);

        if (empty($migrationFiles)) {
            $context = [
                'path' => $this->directory,
                'pattern' => $this->filePattern,
            ];

            if ($this->logger) {
                $this->logger->warning(
                    self::MESSAGE__THERE_IS_NOTHING_MATCHING_PATTERN__PATH_PATTERN,
                    $context
                );
            } else {
                user_error(
                    $this->interpolate(
                        self::MESSAGE__THERE_IS_NOTHING_MATCHING_PATTERN__PATH_PATTERN,
                        $context
                    ),
                    E_USER_WARNING
                );
            }
        }

        if ($this->logger) {
            $this->logger->info(
                self::MESSAGE__FOUND_MIGRATION_FILES__COUNT_PATH_PATTERN,
                [
                    'count' => count($migrationFiles),
                    'path' => $this->directory,
                    'pattern' => $this->filePattern,
                ]
            );
        }

        return $migrationFiles;
    }

    /**
     * @param string $pathToMigrationFile
     * @return string
     */
    protected function getMigrationId($pathToMigrationFile)
    {
        $fileInfo = new \SplFileInfo($pathToMigrationFile);
        $basenameParts = explode(' ', $fileInfo->getBasename('.' . $fileInfo->getExtension()));
        return $basenameParts[0];
    }

    /**
     * @param string $pathToMigrationFile
     * @return bool
     */
    abstract protected function isMigrationApplied($pathToMigrationFile);

    /**
     * @param $pathToMigrationFile
     * @return void
     */
    abstract protected function applyMigrationFile($pathToMigrationFile);
}
