<?php
/**
 * Created by PhpStorm.
 * User: bk
 * Date: 31.08.15
 * Time: 07:50
 */

namespace neo4j\rbac;

use neo4j\db\Connection;
use yii\caching\Cache;
use yii\di\Instance;

class DbManager extends \yii\rbac\DbManager
{
    /**
     * Initializes the application component.
     * This method overrides the parent implementation by establishing the database connection.
     */
    public function init()
    {
        $this->db = Instance::ensure($this->db, Connection::className());
        if ($this->cache !== null) {
            $this->cache = Instance::ensure($this->cache, Cache::className());
        }
    }
} 