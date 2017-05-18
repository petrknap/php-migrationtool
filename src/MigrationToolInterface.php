<?php

namespace PetrKnap\Php\MigrationTool;

use PetrKnap\Php\MigrationTool\Exception\MigrationException;

/**
 * Migration tool interface
 *
 * @author   Petr Knap <dev@petrknap.cz>
 * @since    2016-06-22
 * @license  https://github.com/petrknap/php-migrationtool/blob/master/LICENSE MIT
 */
interface MigrationToolInterface
{
    /**
     * Runs migration process
     *
     * @throws MigrationException
     * @return void
     */
    public function migrate();
}
