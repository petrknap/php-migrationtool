<?php

namespace PetrKnap\Php\MigrationTool\DbAdapters;

/**
 * MySqlAdapter
 *
 * @author Ondřej Němec <ondrej.nemec@autmes.cz>
 */
class MySqlAdapter implements DbAdapter {
    
    public function getDbType() {
        return  "mysql";
    }

    public function createTableIfNotExists($tableName) {
        return 'CREATE TABLE IF NOT EXISTS ' . $tableName .
                    '(' .
                    'id VARCHAR(16) NOT NULL,' .
                    'applied DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,' .
                    'PRIMARY KEY (id)' .
                    ')';
    }

    public function insertIntoTable($tableName) {
        return 'INSERT INTO ' . $tableName . ' (id) VALUES (:id)';
    }

    public function selectOneNull($tableName) {
        return 'SELECT null FROM ' . $tableName . ' LIMIT 1';
    }

    public function selectNullFromId($tableName) {
        return 'SELECT null FROM ' . $tableName . ' WHERE id = :id';
    }

}
