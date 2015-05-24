<?php
/**
 * Created by PhpStorm.
 * User: bk
 * Date: 29.04.15
 * Time: 16:24
 */

namespace neo4j\db;

use Everyman\Neo4j\Exception;
use Everyman\Neo4j\Node;
use Everyman\Neo4j\PropertyContainer;
use Everyman\Neo4j\Query\ResultSet;
use Everyman\Neo4j\Query\Row;
use yii;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecordInterface;
use yii\db\BaseActiveRecord;

class ActiveRecord extends BaseActiveRecord
{
    /**
     * The insert operation. This is mainly used when overriding [[transactions()]] to specify which operations are transactional.
     */
    const OP_INSERT = 0x01;
    /**
     * The update operation. This is mainly used when overriding [[transactions()]] to specify which operations are transactional.
     */
    const OP_UPDATE = 0x02;
    /**
     * The delete operation. This is mainly used when overriding [[transactions()]] to specify which operations are transactional.
     */
    const OP_DELETE = 0x04;
    /**
     * All three operations: insert, update, delete.
     * This is a shortcut of the expression: OP_INSERT | OP_UPDATE | OP_DELETE.
     */
    const OP_ALL = 0x07;

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
     *
     * @throws \Everyman\Neo4j\Exception
     */
    public static function deleteAll($condition = '', $params = [])
    {
        $command = static::getDb()->createCommand();
        $command->delete(static::labelName(), $condition, $params);

        try
        {
            $command->execute();
            return true;
        }
        catch (\Everyman\Neo4j\Exception $ex)
        {
            throw $ex;
        }
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
     * @inheritdoc
     *
     * @return ActiveQuery
     */
    public function hasMany($class, $link, $direction)
    {
        /** @var ActiveQuery $query */
        $query = parent::hasMany($class, $link);
        $query->direction = $direction;

        return $query;
    }

    public function hasOne($class, $link, $direction)
    {
        /** @var ActiveQuery $query */
        $query = parent::hasOne($class, $link);
        $query->direction = $direction;

        return $query;
    }

    /**
     * Returns the primary key value(s).
     * @param boolean $asArray whether to return the primary key value as an array. If true,
     * the return value will be an array with column names as keys and column values as values.
     * Note that for composite primary keys, an array will always be returned regardless of this parameter value.
     * @property mixed The primary key value. An array (column name => column value) is returned if
     * the primary key is composite. A string is returned otherwise (null will be returned if
     * the key value is null).
     * @return mixed the primary key value. An array (column name => column value) is returned if the primary key
     * is composite or `$asArray` is true. A string is returned otherwise (null will be returned if
     * the key value is null).
     */
    public function getPrimaryKey($asArray = false)
    {
        $keys = $this->primaryKey();
        if (count($keys) === 1 && !$asArray) {
            return $this->{$keys[0]};
        } else {
            $values = [];
            foreach ($keys as $name) {
                $values[$name] = $this->{$name};
            }

            return $values;
        }
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
     * @param array            $row
     */
    public static function populateRecord($record, $row)
    {
        $data = [];

        $columns = $record->attributes();

        foreach ($row as $name => $value)
        {
            if (in_array($name, $columns))
            {
                $data[$name] = $record->$name = $value;
            }
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
        if ($this->isTransactional(self::OP_INSERT)) {
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
        $values = $this->getDirtyAttributes($attributes);
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

        $changedAttributes = array_fill_keys(array_keys($values), null);
        $this->setOldAttributes($values);
        $this->afterSave(true, $changedAttributes);

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
     * @throws yii\db\StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
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
        if ($this->isTransactional(self::OP_UPDATE)) {
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

        $values = $this->getDirtyAttributes($attributes);
        if (empty($values)) {
            $this->afterSave(false, $values);
            return 0;
        }

        $command = static::getDb()->createCommand();
        $command->update(static::labelName(), $values, $this->id);

        if (!$command->execute()) {
            return false;
        }

        $changedAttributes = [];
        foreach ($values as $name => $value) {
            $changedAttributes[$name] = isset($this->oldAttributes[$name]) ? $this->oldAttributes[$name] : null;
            $this->oldAttributes[$name] = $value;
        }
        $this->afterSave(false, $changedAttributes);

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
     * @throws yii\db\StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
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
                throw new yii\db\StaleObjectException('The object being deleted is outdated.');
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

        return $this->labelName() === $record->labelName() && $this->getPrimaryKey() === $record->getPrimaryKey();
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

    public function loadNode()
    {
        return $this->getDb()->client->getNode($this->getPrimaryKey());
    }

    const LINK_LEFT_RIGHT = 0;
    const LINK_RIGHT_LEFT = 1;
    const LINK_TWO_WAY = 2;

    /**
     * Establishes the relationship between two models.
     *
     * @param string $name the case sensitive name of the relationship
     * @param ActiveRecord $model the model to be linked with the current one.
     * @param array $extraColumns additional column values to be saved into the pivot table.
     * This parameter is only meaningful for a relationship involving a pivot table
     * (i.e., a relation set with [[ActiveRelationTrait::via()]] or `[[ActiveQuery::viaTable()]]`.)
     * @throws yii\base\InvalidCallException if the method is unable to link two models.
     */
    public function link($name, $model, $extraColumns = [])
    {
        /** @var ActiveQuery $relation */
        $relation = $this->getRelation($name);

        if (!$relation->direction)
        {
            throw new yii\base\InvalidCallException('Unable to link models: only directed relationships are supported.');
        }
        elseif ($this->getIsNewRecord() || $model->getIsNewRecord()) {
            throw new yii\base\InvalidCallException('Unable to link models: one or both models are newly created.');
        }

        if ($relation->via !== null) {
            if (is_array($relation->via)) {
                /** @var ActiveQuery $viaRelation */
                list($viaName, $viaRelation) = $relation->via;
                $viaClass = $viaRelation->modelClass;
                // unset $viaName so that it can be reloaded to reflect the change
                unset($this[$viaName]);
            } else {
                $viaRelation = $relation->via;
                $viaTable = reset($relation->via->from);
            }

            if (!$viaRelation->direction)
            {
                throw new yii\base\InvalidCallException('Unable to link models: only directed relationships are supported.');
            }

            if (is_array($relation->via)) {
                /** @var $viaClass ActiveRecord */
                /** @var $record ActiveRecord */
                $record = new $viaClass();
                foreach ($extraColumns as $column => $value) {
                    $record->$column = $value;
                }
                $record->insert(false);

                $viaNode = $record->loadNode();
            } else {
                /** @var $viaTable string */
                $viaNode = static::getDb()->createCommand()->insert($viaTable, $extraColumns)->execute();
            }

            $node = $this->loadNode();
            if ($viaRelation->direction == ActiveQuery::DIRECTION_IN)
            {
                $node->relateTo($viaNode, $viaRelation->link);
            }
            elseif ($viaRelation->direction == ActiveQuery::DIRECTION_OUT)
            {
                $node->relateTo($viaNode, $viaRelation->link);
            }
            else
            {
                throw new yii\base\InvalidCallException('Unable to link models: the link does not involve any direction.');
            }
        }
        else {
            if ($relation->direction == ActiveQuery::DIRECTION_IN)
            {
                $this->bindModels($relation->link, $this, $model);
            }
            elseif ($relation->direction == ActiveQuery::DIRECTION_OUT)
            {
                $this->bindModels($relation->link, $model, $this);
            }
            else
            {
                throw new yii\base\InvalidCallException('Unable to link models: the link does not involve any direction.');
            }
        }

        // update lazily loaded related objects
        if (!$relation->multiple) {
            $related = $model;
        }
        elseif ($this->isRelationPopulated($name)) {
            if ($relation->indexBy !== null) {
                $indexBy = $relation->indexBy;
                $related[$model->$indexBy] = $model;
            } else {
                $related[] = $model;
            }
        }

        if (isset($related))
        {
            $this->populateRelation($name, $related);
        }
    }

    /**
     * @param array $link
     * @param ActiveRecord $foreignModel
     * @param ActiveRecord $primaryModel
     * @throws yii\base\InvalidCallException
     */
    private function bindModels($link, $foreignModel, $primaryModel)
    {
        if (is_array($link))
        {
            $link = implode(':', $link);
        }

        $primaryModel = $primaryModel->loadNode();
        $foreignModel = $foreignModel->loadNode();

        if ($primaryModel === null)
        {
            throw new yii\base\InvalidCallException('Unable to link models: the node of ' . get_class($primaryModel) . ' was not found.');
        }
        elseif ($foreignModel === null)
        {
            throw new yii\base\InvalidCallException('Unable to link models: the node of ' . get_class($foreignModel) . ' was not found.');
        }

        $primaryModel->relateTo($foreignModel, $link)->save();
    }
}