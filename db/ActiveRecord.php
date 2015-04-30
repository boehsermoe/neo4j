<?php
/**
 * Created by PhpStorm.
 * User: bk
 * Date: 29.04.15
 * Time: 16:24
 */

namespace neo4j\db;

use Everyman\Neo4j\Node;
use yii;
use yii\db\BaseActiveRecord;

class ActiveRecord extends BaseActiveRecord
{
	/**
	 * Updates the whole table using the provided attribute values and conditions.
	 * For example, to change the status to be 1 for all customers whose status is 2:
	 *
	 * ~~~
	 * Customer::updateAll(['status' => 1], 'status = 2');
	 * ~~~
	 *
	 * @param array $attributes attribute values (name-value pairs) to be saved into the table
	 * @param string|array $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
	 * Please refer to [[Query::where()]] on how to specify this parameter.
	 * @param array $params the parameters (name => value) to be bound to the query.
	 * @return integer the number of rows updated
	 */
	public static function updateAll($attributes, $condition = '', $params = [])
	{
		$command = static::getDb()->createCommand();
		$command->update(static::labelName(), $attributes, $condition, $params);

		return $command->execute();
	}

	/**
	 * Updates the whole table using the provided counter changes and conditions.
	 * For example, to increment all customers' age by 1,
	 *
	 * ~~~
	 * Customer::updateAllCounters(['age' => 1]);
	 * ~~~
	 *
	 * @param array $counters the counters to be updated (attribute name => increment value).
	 * Use negative values if you want to decrement the counters.
	 * @param string|array $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
	 * Please refer to [[Query::where()]] on how to specify this parameter.
	 * @param array $params the parameters (name => value) to be bound to the query.
	 * Do not name the parameters as `:bp0`, `:bp1`, etc., because they are used internally by this method.
	 * @return integer the number of rows updated
	 */
	public static function updateAllCounters($counters, $condition = '', $params = [])
	{
		$n = 0;
		foreach ($counters as $name => $value) {
			$counters[$name] = new yii\db\Expression("[[$name]]+:bp{$n}", [":bp{$n}" => $value]);
			$n++;
		}
		$command = static::getDb()->createCommand();
		$command->update(static::labelName(), $counters, $condition, $params);

		return $command->execute();
	}

	/**
	 * Deletes rows in the table using the provided conditions.
	 * WARNING: If you do not specify any condition, this method will delete ALL rows in the table.
	 *
	 * For example, to delete all customers whose status is 3:
	 *
	 * ~~~
	 * Customer::deleteAll('status = 3');
	 * ~~~
	 *
	 * @param string|array $condition the conditions that will be put in the WHERE part of the DELETE SQL.
	 * Please refer to [[Query::where()]] on how to specify this parameter.
	 * @param array $params the parameters (name => value) to be bound to the query.
	 * @return integer the number of rows deleted
	 */
	public static function deleteAll($condition = '', $params = [])
	{
		$command = static::getDb()->createCommand();
		$command->delete(static::labelName(), $condition, $params);

		return $command->execute();
	}

	/**
	 * @inheritdoc
	 */
	public static function find()
	{
		return new ActiveQuery(get_called_class());
	}

	/**
	 * Returns the primary key name(s) for this AR class.
	 * The default implementation will return the primary key(s) as declared
	 * in the DB table that is associated with this AR class.
	 *
	 * If the DB table does not declare any primary key, you should override
	 * this method to return the attributes that you want to use as primary keys
	 * for this AR class.
	 *
	 * Note that an array should be returned even for a table with single primary key.
	 *
	 * @return string[] the primary keys of the associated database table.
	 */
	public static function primaryKey()
	{
		return ['id'];
	}

	/**
	 * Returns the list of all attribute names of the model.
	 * The default implementation will return all column names of the table associated with this AR class.
	 * @return array list of attribute names.
	 */
	public function attributes()
	{
		return parent::attributes();
	}

	/**
	 * Returns the named attribute value.
	 * If this record is the result of a query and the attribute is not loaded,
	 * null will be returned.
	 * @param string $name the attribute name
	 * @return mixed the attribute value. Null if the attribute is not set or does not exist.
	 * @see hasAttribute()
	 */
	public function getAttribute($name)
	{
		return $this->$name;
	}

	/**
	 * Sets the named attribute value.
	 * @param string $name the attribute name
	 * @param mixed $value the attribute value.
	 * @throws yii\base\InvalidParamException if the named attribute does not exist.
	 * @see hasAttribute()
	 */
	public function setAttribute($name, $value)
	{
		if ($this->hasAttribute($name)) {
			$this->$name = $value;
		} else {
			throw new yii\base\InvalidParamException(get_class($this) . ' has no attribute named "' . $name . '".');
		}
	}


	/**
	 * @return string the label name
	 */
	public static function labelName()
	{
		return yii\helpers\StringHelper::basename(get_called_class());
	}

	/**
	 * Returns the database connection used by this AR class.
	 * By default, the "db" application component is used as the database connection.
	 * You may override this method if you want to use a different database connection.
	 * @return Connection the database connection used by this AR class.
	 */
	public static function getDb()
	{
		return Yii::$app->getDb();
	}

	/**
	 * Declares which DB operations should be performed within a transaction in different scenarios.
	 * The supported DB operations are: [[OP_INSERT]], [[OP_UPDATE]] and [[OP_DELETE]],
	 * which correspond to the [[insert()]], [[update()]] and [[delete()]] methods, respectively.
	 * By default, these methods are NOT enclosed in a DB transaction.
	 *
	 * In some scenarios, to ensure data consistency, you may want to enclose some or all of them
	 * in transactions. You can do so by overriding this method and returning the operations
	 * that need to be transactional. For example,
	 *
	 * ~~~
	 * return [
	 *     'admin' => self::OP_INSERT,
	 *     'api' => self::OP_INSERT | self::OP_UPDATE | self::OP_DELETE,
	 *     // the above is equivalent to the following:
	 *     // 'api' => self::OP_ALL,
	 *
	 * ];
	 * ~~~
	 *
	 * The above declaration specifies that in the "admin" scenario, the insert operation ([[insert()]])
	 * should be done in a transaction; and in the "api" scenario, all the operations should be done
	 * in a transaction.
	 *
	 * @return array the declarations of transactional operations. The array keys are scenarios names,
	 * and the array values are the corresponding transaction operations.
	 */
	public function transactions()
	{
		return [];
	}

	/**
	 * @inheritdoc
	 * @param array|Node            $row
	 */
	public static function populateRecord($record, $row)
	{
		$data = [];

		$columns = $record->attributes();
		foreach ($row->getProperties() as $name => $value)
		{
			if (in_array($name, $columns))
			{
				$data[$name] = $record->$name = $value;
			}
		}

		if ($record->hasAttribute('id'))
		{
			$record->id = $row->getId();
		}

		parent::populateRecord($record, $data);
	}

	/**
	 * Inserts a row into the associated database table using the attribute values of this record.
	 *
	 * This method performs the following steps in order:
	 *
	 * 1. call [[beforeValidate()]] when `$runValidation` is true. If validation
	 *    fails, it will skip the rest of the steps;
	 * 2. call [[afterValidate()]] when `$runValidation` is true.
	 * 3. call [[beforeSave()]]. If the method returns false, it will skip the
	 *    rest of the steps;
	 * 4. insert the record into database. If this fails, it will skip the rest of the steps;
	 * 5. call [[afterSave()]];
	 *
	 * In the above step 1, 2, 3 and 5, events [[EVENT_BEFORE_VALIDATE]],
	 * [[EVENT_BEFORE_INSERT]], [[EVENT_AFTER_INSERT]] and [[EVENT_AFTER_VALIDATE]]
	 * will be raised by the corresponding methods.
	 *
	 * Only the [[dirtyAttributes|changed attribute values]] will be inserted into database.
	 *
	 * If the table's primary key is auto-incremental and is null during insertion,
	 * it will be populated with the actual value after insertion.
	 *
	 * For example, to insert a customer record:
	 *
	 * ~~~
	 * $customer = new Customer;
	 * $customer->name = $name;
	 * $customer->email = $email;
	 * $customer->insert();
	 * ~~~
	 *
	 * @param boolean $runValidation whether to perform validation before saving the record.
	 * If the validation fails, the record will not be inserted into the database.
	 * @param array $attributes list of attributes that need to be saved. Defaults to null,
	 * meaning all attributes that are loaded from DB will be saved.
	 * @return boolean whether the attributes are valid and the record is inserted successfully.
	 * @throws \Exception in case insert failed.
	 */
	public function insert($runValidation = true, $attributes = null)
	{
		if ($runValidation && !$this->validate($attributes)) {
			Yii::info('Model not inserted due to validation error.', __METHOD__);
			return false;
		}
		$db = static::getDb();
		if ($this->isTransactional(yii\db\ActiveRecord::OP_INSERT)) {
			$transaction = $db->beginTransaction();
			try {
				$result = $this->insertInternal($attributes);
				if ($result === false) {
					$transaction->rollBack();
				} else {
					$transaction->commit();
				}
			} catch (\Exception $e) {
				$transaction->rollBack();
				throw $e;
			}
		} else {
			$result = $this->insertInternal($attributes);
		}

		return $result;
	}

	/**
	 * Inserts an ActiveRecord into DB without considering transaction.
	 * @param array $attributes list of attributes that need to be saved. Defaults to null,
	 * meaning all attributes that are loaded from DB will be saved.
	 * @return boolean whether the record is inserted successfully.
	 */
	protected function insertInternal($attributes = null)
	{
		if (!$this->beforeSave(true)) {
			return false;
		}
		#$values = $this->getDirtyAttributes($attributes);
		$values = $this->getAttributes($attributes);
		if (empty($values)) {
			foreach ($this->getPrimaryKey(true) as $key => $value) {
				$values[$key] = $value;
			}
		}

		$db = static::getDb();
		$command = $db->createCommand()->insert($this->labelName(), $values);
		if (!$command->execute()) {
			return false;
		}

		foreach ($this->getPrimaryKey(true) as $name => $value) {
			$id = $name == 'id' ? $command->container->getId() : $command->container->getProperty($name);
			$this->setAttribute($name, $id);

			$values[$name] = $id;
		}

		$this->afterSave(true);
		$this->setOldAttributes($values);

		return true;
	}

	/**
	 * Saves the changes to this active record into the associated database table.
	 *
	 * This method performs the following steps in order:
	 *
	 * 1. call [[beforeValidate()]] when `$runValidation` is true. If validation
	 *    fails, it will skip the rest of the steps;
	 * 2. call [[afterValidate()]] when `$runValidation` is true.
	 * 3. call [[beforeSave()]]. If the method returns false, it will skip the
	 *    rest of the steps;
	 * 4. save the record into database. If this fails, it will skip the rest of the steps;
	 * 5. call [[afterSave()]];
	 *
	 * In the above step 1, 2, 3 and 5, events [[EVENT_BEFORE_VALIDATE]],
	 * [[EVENT_BEFORE_UPDATE]], [[EVENT_AFTER_UPDATE]] and [[EVENT_AFTER_VALIDATE]]
	 * will be raised by the corresponding methods.
	 *
	 * Only the [[dirtyAttributes|changed attribute values]] will be saved into database.
	 *
	 * For example, to update a customer record:
	 *
	 * ~~~
	 * $customer = Customer::findOne($id);
	 * $customer->name = $name;
	 * $customer->email = $email;
	 * $customer->update();
	 * ~~~
	 *
	 * Note that it is possible the update does not affect any row in the table.
	 * In this case, this method will return 0. For this reason, you should use the following
	 * code to check if update() is successful or not:
	 *
	 * ~~~
	 * if ($this->update() !== false) {
	 *     // update successful
	 * } else {
	 *     // update failed
	 * }
	 * ~~~
	 *
	 * @param boolean $runValidation whether to perform validation before saving the record.
	 * If the validation fails, the record will not be inserted into the database.
	 * @param array $attributeNames list of attributes that need to be saved. Defaults to null,
	 * meaning all attributes that are loaded from DB will be saved.
	 * @return integer|boolean the number of rows affected, or false if validation fails
	 * or [[beforeSave()]] stops the updating process.
	 * @throws StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
	 * being updated is outdated.
	 * @throws \Exception in case update failed.
	 */
	public function update($runValidation = true, $attributeNames = null)
	{
		if ($runValidation && !$this->validate($attributeNames)) {
			Yii::info('Model not updated due to validation error.', __METHOD__);
			return false;
		}
		$db = static::getDb();
		if ($this->isTransactional(yii\db\ActiveRecord::OP_UPDATE)) {
			$transaction = $db->beginTransaction();
			try {
				$result = $this->updateInternal($attributeNames);
				if ($result === false) {
					$transaction->rollBack();
				} else {
					$transaction->commit();
				}
			} catch (\Exception $e) {
				$transaction->rollBack();
				throw $e;
			}
		} else {
			$result = $this->updateInternal($attributeNames);
		}

		return $result;
	}

	/**
	 * @see update()
	 * @throws yii\db\StaleObjectException
	 */
	protected function updateInternal($attributes = null)
	{
		if (!$this->beforeSave(false)) {
			return false;
		}
		$values = $this->getAttributes($attributes);
		if (empty($values)) {
			$this->afterSave(false);
			return 0;
		}

		$command = static::getDb()->createCommand();
		$command->update(static::labelName(), $values, $this->id);

		if (!$command->execute()) {
			return false;
		}

		$this->afterSave(false);
		$this->setOldAttributes($values);

		return true;
	}

	/**
	 * Deletes the table row corresponding to this active record.
	 *
	 * This method performs the following steps in order:
	 *
	 * 1. call [[beforeDelete()]]. If the method returns false, it will skip the
	 *    rest of the steps;
	 * 2. delete the record from the database;
	 * 3. call [[afterDelete()]].
	 *
	 * In the above step 1 and 3, events named [[EVENT_BEFORE_DELETE]] and [[EVENT_AFTER_DELETE]]
	 * will be raised by the corresponding methods.
	 *
	 * @return integer|boolean the number of rows deleted, or false if the deletion is unsuccessful for some reason.
	 * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
	 * @throws StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
	 * being deleted is outdated.
	 * @throws \Exception in case delete failed.
	 */
	public function delete()
	{
		$db = static::getDb();
		if ($this->isTransactional(self::OP_DELETE)) {
			$transaction = $db->beginTransaction();
			try {
				$result = $this->deleteInternal();
				if ($result === false) {
					$transaction->rollBack();
				} else {
					$transaction->commit();
				}
			} catch (\Exception $e) {
				$transaction->rollBack();
				throw $e;
			}
		} else {
			$result = $this->deleteInternal();
		}

		return $result;
	}

	/**
	 * Deletes an ActiveRecord without considering transaction.
	 * @return integer|boolean the number of rows deleted, or false if the deletion is unsuccessful for some reason.
	 * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
	 * @throws StaleObjectException
	 */
	protected function deleteInternal()
	{
		$result = false;
		if ($this->beforeDelete()) {
			// we do not check the return value of deleteAll() because it's possible
			// the record is already deleted in the database and thus the method will return 0
			$condition = $this->getOldPrimaryKey(true);
			$lock = $this->optimisticLock();
			if ($lock !== null) {
				$condition[$lock] = $this->$lock;
			}
			$result = $this->deleteAll($condition);
			if ($lock !== null && !$result) {
				throw new StaleObjectException('The object being deleted is outdated.');
			}
			$this->setOldAttributes(null);
			$this->afterDelete();
		}

		return $result;
	}

	/**
	 * Returns a value indicating whether the given active record is the same as the current one.
	 * The comparison is made by comparing the table names and the primary key values of the two active records.
	 * If one of the records [[isNewRecord|is new]] they are also considered not equal.
	 * @param ActiveRecord $record record to compare to
	 * @return boolean whether the two active records refer to the same row in the same database table.
	 */
	public function equals($record)
	{
		if ($this->isNewRecord || $record->isNewRecord) {
			return false;
		}

		return $this->tableName() === $record->tableName() && $this->getPrimaryKey() === $record->getPrimaryKey();
	}

	/**
	 * Returns a value indicating whether the specified operation is transactional in the current [[scenario]].
	 * @param integer $operation the operation to check. Possible values are [[OP_INSERT]], [[OP_UPDATE]] and [[OP_DELETE]].
	 * @return boolean whether the specified operation is transactional in the current [[scenario]].
	 */
	public function isTransactional($operation)
	{
		$scenario = $this->getScenario();
		$transactions = $this->transactions();

		return isset($transactions[$scenario]) && ($transactions[$scenario] & $operation);
	}
}