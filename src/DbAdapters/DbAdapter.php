<?php

namespace PetrKnap\Php\MigrationTool\DbAdapters;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * @author Ondřej Němec <ondrej.nemec@autmes.cz>
 */
interface DbAdapter {
    
     public function getDbType();
     
     public function selectOneNull($tableName);
     
     public function createTableIfNotExists($tableName);
     
     public function insertIntoTable($tableName);
     
     public function selectNullFromId($tableName);
     
     
}
