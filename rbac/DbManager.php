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
	public $itemTable = 'auth_item';
	/**
	 * @var string the name of the table storing authorization item hierarchy. Defaults to "auth_item_child".
	 */
	public $itemChildTable = 'auth_item_child';
	/**
	 * @var string the name of the table storing authorization item assignments. Defaults to "auth_assignment".
	 */
	public $assignmentTable = 'auth_assignment';
	/**
	 * @var string the name of the table storing rules. Defaults to "auth_rule".
	 */
	public $ruleTable = 'auth_rule';
	
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