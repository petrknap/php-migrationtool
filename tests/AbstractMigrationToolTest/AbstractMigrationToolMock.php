<?php

namespace PetrKnap\Php\MigrationTool\Test\AbstractMigrationToolTest;

use PetrKnap\Php\MigrationTool\AbstractMigrationTool;

class AbstractMigrationToolMock extends AbstractMigrationTool
{
    /**
     * @var array
     */
    private $appliedMigrations;

    public function __construct(array $appliedMigrations, $pathToDirectoryWithMigrationFiles = null)
    {
        parent::__construct($pathToDirectoryWithMigrationFiles, '/\.ext/i');
        $this->appliedMigrations = $appliedMigrations;
    }

    /**
     * @return array
     */
    public function getAppliedMigrations()
    {
        return $this->appliedMigrations;
    }

    /**
     * @inheritdoc
     */
    protected function isMigrationApplied($pathToMigrationFile)
    {
        return in_array($this->getMigrationId($pathToMigrationFile), $this->appliedMigrations);
    }

    /**
     * @inheritdoc
     */
    protected function applyMigrationFile($pathToMigrationFile)
    {
        $this->appliedMigrations[] = $this->getMigrationId($pathToMigrationFile);
    }
}
