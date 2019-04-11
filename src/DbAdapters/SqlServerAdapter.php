<?php

namespace PetrKnap\Php\MigrationTool\DbAdapters;

/**
 * SqlServerAdapter
 *
 * @author Ondřej Němec <ondrej.nemec@autmes.cz>
 */
class SqlServerAdapter implements DbAdapter {

    public function createTableIfNotExists($tableName) {
        return 'IF NOT EXISTS (SELECT * FROM sysobjects WHERE name=\'' . $tableName . '\' and xtype=\'U\')'.
                       ' CREATE TABLE ' . $tableName . ' ('.
                            'id VARCHAR(16) NOT NULL PRIMARY KEY,' .
                            'applied DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                        ')';
    }

    public function getDbType() {
        return 'sqlsvr';
    }

    public function insertIntoTable($tableName) {
        return 'INSERT INTO ' . $tableName . ' (id) VALUES (:id)';
    }

    public function selectNullFromId($tableName) {
        return 'SELECT null FROM ' . $tableName . ' WHERE id = :id';
    }

    public function selectOneNull($tableName) {
         return 'SELECT TOP 1 null FROM ' . $tableName;
    }

}
