<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace neo4j\console\controllers;

use Yii;
use yii\console\controllers\BaseMigrateController;
use neo4j\db\Connection;
use yii\db\Query;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;

/**
 * Manages application migrations.
 *
 * A migration means a set of persistent changes to the application environment
 * that is shared among different developers. For example, in an application
 * backed by a database, a migration may refer to a set of changes to
 * the database, such as creating a new table, adding a new table column.
 *
 * This command provides support for tracking the migration history, upgrading
 * or downloading with migrations, and creating new migration skeletons.
 *
 * The migration history is stored in a database table named
 * as [[migrationLabel]]. The table will be automatically created the first time
 * this command is executed, if it does not exist. You may also manually
 * create it as follows:
 *
 * ~~~
 * CREATE TABLE migration (
 *     version varchar(180) PRIMARY KEY,
 *     apply_time integer
 * )
 * ~~~
 *
 * Below are some common usages of this command:
 *
 * ~~~
 * # creates a new migration named 'create_user_table'
 * yii migrate/create create_user_table
 *
 * # applies ALL new migrations
 * yii migrate
 *
 * # reverts the last applied migration
 * yii migrate/down
 * ~~~
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class MigrateController extends BaseMigrateController
{
	/**
	 * @var string the name of the table for keeping applied migration information.
	 */
	public $migrationLabel = 'Migration';
	/**
	 * @inheritdoc
	 */
	public $templateFile = '@yii/views/migration.php';
	/**
	 * @var Connection|array|string the DB connection object or the application component ID of the DB connection to use
	 * when applying migrations. Starting from version 2.0.3, this can also be a configuration array
	 * for creating the object.
	 */
	public $db = 'db';


	/**
	 * @inheritdoc
	 */
	public function options($actionID)
	{
		return array_merge(
			parent::options($actionID),
			['migrationLabel', 'db'] // global for all actions
		);
	}

	/**
	 * This method is invoked right before an action is to be executed (after all possible filters.)
	 * It checks the existence of the [[migrationPath]].
	 * @param \yii\base\Action $action the action to be executed.
	 * @return boolean whether the action should continue to be executed.
	 */
	public function beforeAction($action)
	{
		if (parent::beforeAction($action)) {
			if ($action->id !== 'create') {
				$this->db = Instance::ensure($this->db, Connection::className());
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Creates a new migration instance.
	 * @param string $class the migration class name
	 * @return \yii\db\Migration the migration instance
	 */
	protected function createMigration($class)
	{
        foreach ($this->migrationPath as $migrationPath) {
            $file = $migrationPath . DIRECTORY_SEPARATOR . $class . '.php';
            if (file_exists($file)) {
                require_once($file);
            }
        }

		return new $class(['db' => $this->db]);
	}

	/**
	 * @inheritdoc
	 */
	protected function getMigrationHistory($limit)
	{
		$query = new Query();
		$query->from($this->migrationLabel);
		if ($query->exists($this->db) === false) {
			$this->createMigrationHistoryTable();
		}
		$query = new Query;
		$rows = $query
			->from($this->migrationLabel)
			->orderBy('n.apply_time DESC, n.version DESC')
			->limit($limit)
			->createCommand($this->db)
			->queryAll();

		$history = ArrayHelper::map($rows, 'version', 'apply_time');
		unset($history[self::BASE_MIGRATION]);

		return $history;
	}

	/**
	 * Creates the migration history table.
	 */
	protected function createMigrationHistoryTable()
	{
		$label = $this->migrationLabel;
		$this->stdout("Creating migration history table \"$label\"...", Console::FG_YELLOW);
		$this->db->createCommand()->insert($label, [
			'version' => self::BASE_MIGRATION,
			'apply_time' => time(),
		])->execute();
		$this->stdout("Done.\n", Console::FG_GREEN);
	}

	/**
	 * @inheritdoc
	 */
	protected function addMigrationHistory($version)
	{
		$command = $this->db->createCommand();
		$command->insert($this->migrationLabel, [
			'version' => $version,
			'apply_time' => time(),
		])->execute();
	}

	/**
	 * @inheritdoc
	 */
	protected function removeMigrationHistory($version)
	{
		$command = $this->db->createCommand();
		$command->delete($this->migrationLabel, [
			'version' => $version,
		])->execute();
	}
}
