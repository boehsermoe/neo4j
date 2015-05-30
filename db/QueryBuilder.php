<?php

namespace neo4j\db;

use yii\base\NotSupportedException;
use yii\db\Exception;
use yii\base\InvalidParamException;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\Inflector;

/**
 * QueryBuilder is the query builder for MySQL databases.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class QueryBuilder extends \yii\db\QueryBuilder
{
	/**
	 * @var Connection the database connection.
	 */
	public $db;

	/**
	 * @var array mapping from abstract column types (keys) to physical column types (values).
	 */
	public $typeMap = [
		/*
		Schema::TYPE_PK => 'int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY',
		Schema::TYPE_BIGPK => 'bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY',
		Schema::TYPE_STRING => 'varchar(255)',
		Schema::TYPE_TEXT => 'text',
		Schema::TYPE_SMALLINT => 'smallint(6)',
		Schema::TYPE_INTEGER => 'int(11)',
		Schema::TYPE_BIGINT => 'bigint(20)',
		Schema::TYPE_FLOAT => 'float',
		Schema::TYPE_DECIMAL => 'decimal(10,0)',
		Schema::TYPE_DATETIME => 'datetime',
		Schema::TYPE_TIMESTAMP => 'timestamp',
		Schema::TYPE_TIME => 'time',
		Schema::TYPE_DATE => 'date',
		Schema::TYPE_BINARY => 'blob',
		Schema::TYPE_BOOLEAN => 'tinyint(1)',
		Schema::TYPE_MONEY => 'decimal(19,4)',
		*/
	];

	public $identifier = 'n';
	public $relationIdentifier = 'r';

	/**
	 * Builds a SQL statement for renaming a column.
	 * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
	 * @param string $oldName the old name of the column. The name will be properly quoted by the method.
	 * @param string $newName the new name of the column. The name will be properly quoted by the method.
	 * @return string the SQL statement for renaming a DB column.
	 * @throws Exception
	 */
	public function renameColumn($table, $oldName, $newName)
	{
		$quotedTable = $this->db->quoteTableName($table);
		$row = $this->db->createCommand('SHOW CREATE TABLE ' . $quotedTable)->queryOne();
		if ($row === false) {
			throw new Exception("Unable to find column '$oldName' in table '$table'.");
		}
		if (isset($row['Create Table'])) {
			$sql = $row['Create Table'];
		} else {
			$row = array_values($row);
			$sql = $row[1];
		}
		if (preg_match_all('/^\s*`(.*?)`\s+(.*?),?$/m', $sql, $matches)) {
			foreach ($matches[1] as $i => $c) {
				if ($c === $oldName) {
					return "ALTER TABLE $quotedTable CHANGE "
					. $this->db->quoteColumnName($oldName) . ' '
					. $this->db->quoteColumnName($newName) . ' '
					. $matches[2][$i];
				}
			}
		}
		// try to give back a SQL anyway
		return "ALTER TABLE $quotedTable CHANGE "
		. $this->db->quoteColumnName($oldName) . ' '
		. $this->db->quoteColumnName($newName);
	}

    /**
     * Builds a SQL statement for adding a foreign key constraint to an existing table.
     * The method will properly quote the table and column names.
     * @param string $name the name of the foreign key constraint.
     * @param string $table the table that the foreign key constraint will be added to.
     * @param string|array $columns the name of the column to that the constraint will be added on.
     * If there are multiple columns, separate them with commas or use an array to represent them.
     * @param string $refTable the table that the foreign key references to.
     * @param string|array $refColumns the name of the column that the foreign key references to.
     * If there are multiple columns, separate them with commas or use an array to represent them.
     * @param string $delete the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @param string $update the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @return string the SQL statement for adding a foreign key constraint to an existing table.
     */
    public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
    {
        $sql = 'ALTER TABLE ' . $this->db->quoteTableName($table)
            . ' ADD CONSTRAINT ' . $this->db->quoteColumnName($name)
            . ' FOREIGN KEY (' . $this->buildColumns($columns) . ')'
            . ' REFERENCES ' . $this->db->quoteTableName($refTable)
            . ' (' . $this->buildColumns($refColumns) . ')';
        if ($delete !== null) {
            $sql .= ' ON DELETE ' . $delete;
        }
        if ($update !== null) {
            $sql .= ' ON UPDATE ' . $update;
        }

        return $sql;
    }

	/**
	 * Builds a SQL statement for dropping a foreign key constraint.
	 * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
	 * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
	 * @return string the SQL statement for dropping a foreign key constraint.
	 */
	public function dropForeignKey($name, $table)
	{
		return 'ALTER TABLE ' . $this->db->quoteTableName($table)
		. ' DROP FOREIGN KEY ' . $this->db->quoteColumnName($name);
	}

    /**
     * Builds a SQL statement for adding a primary key constraint to an existing table.
     * @param string $name the name of the primary key constraint.
     * @param string $table the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     * @return string the SQL statement for adding a primary key constraint to an existing table.
     */
    public function addPrimaryKey($name, $table, $columns)
    {
        if (is_string($columns)) {
            $columns = preg_split('/\s*,\s*/', $columns, -1, PREG_SPLIT_NO_EMPTY);
        }

        foreach ($columns as $i => $col) {
            $columns[$i] = $this->db->quoteColumnName($col);
        }

        return 'ALTER TABLE ' . $this->db->quoteTableName($table) . ' ADD CONSTRAINT '
        . $this->db->quoteColumnName($name) . '  PRIMARY KEY ('
        . implode(', ', $columns). ' )';
    }

	/**
	 * Builds a SQL statement for removing a primary key constraint to an existing table.
	 * @param string $name the name of the primary key constraint to be removed.
	 * @param string $table the table that the primary key constraint will be removed from.
	 * @return string the SQL statement for removing a primary key constraint from an existing table.
	 */
	public function dropPrimaryKey($name, $table)
	{
		return 'ALTER TABLE ' . $this->db->quoteTableName($table) . ' DROP PRIMARY KEY';
	}

	/**
	 * Creates a SQL statement for resetting the sequence value of a table's primary key.
	 * The sequence will be reset such that the primary key of the next new row inserted
	 * will have the specified value or 1.
	 * @param string $tableName the name of the table whose primary key sequence will be reset
	 * @param mixed $value the value for the primary key of the next new row inserted. If this is not set,
	 * the next new row's primary key will have a value 1.
	 * @return string the SQL statement for resetting sequence
	 * @throws InvalidParamException if the table does not exist or there is no sequence associated with the table.
	 */
	public function resetSequence($tableName, $value = null)
	{
		$table = $this->db->getTableSchema($tableName);
		if ($table !== null && $table->sequenceName !== null) {
			$tableName = $this->db->quoteTableName($tableName);
			if ($value === null) {
				$key = reset($table->primaryKey);
				$value = $this->db->createCommand("SELECT MAX(`$key`) FROM $tableName")->queryScalar() + 1;
			} else {
				$value = (int) $value;
			}

			return "ALTER TABLE $tableName AUTO_INCREMENT=$value";
		} elseif ($table === null) {
			throw new InvalidParamException("Table not found: $tableName");
		} else {
			throw new InvalidParamException("There is no sequence associated with table '$tableName'.");
		}
	}

	/**
	 * Builds a SQL statement for enabling or disabling integrity check.
	 * @param boolean $check whether to turn on or off the integrity check.
	 * @param string $table the table name. Meaningless for MySQL.
	 * @param string $schema the schema of the tables. Meaningless for MySQL.
	 * @return string the SQL statement for checking integrity
	 */
	public function checkIntegrity($check = true, $schema = '', $table = '')
	{
		return 'SET FOREIGN_KEY_CHECKS = ' . ($check ? 1 : 0);
	}

	/**
	 * Generates a SELECT SQL statement from a [[Query]] object.
	 * @param Query $query the [[Query]] object from which the SQL statement will be generated.
	 * @param array $params the parameters to be bound to the generated SQL statement. These parameters will
	 * be included in the result with the additional parameters generated during the query building process.
	 * @return array the generated SQL statement (the first array element) and the corresponding
	 * parameters to be bound to the SQL statement (the second array element). The parameters returned
	 * include those provided in `$params`.
	 */
	public function build($query, $params = [])
	{
        $query = $query->prepare($this);

        $params = empty($params) ? $query->params : array_merge($params, $query->params);

		$clauses = [
			$this->buildMatch($query->selectOption),
			'(',
			$this->buildFrom($query->from, $params),
			#$this->buildProperties($query->select, $params),
			')',
            $this->buildSubQuery($query->select),
			$this->buildJoin($query->join, $params),
			$this->buildWhere($query->where, $params),
			$this->buildReturn($query->groupBy ? : $query->select),
			$this->buildOrderBy($query->orderBy),
			$this->buildLimit($query->limit, $query->offset),
		];

		$queryString = implode($this->separator, array_filter($clauses));

		$union = $this->buildUnion($query->union, $params);
		if ($union !== '') {
			$queryString = "($queryString){$this->separator}$union";
		}

		$params = $this->postpareParams($params, $queryString);

		return [$queryString, $params];
	}

	private function postpareParams($params, &$queryString)
	{
		$vars = [];

		foreach ($params as $name => $value)
		{
			$var = substr($name, 1);
			$queryString = str_replace($name, '{'.$var.'}', $queryString);

			$vars[$var] = $value;
		}

        return $vars;
	}

	public function buildHashCondition($condition, &$params)
	{
        $parts = [];
		foreach ($condition as $column => $value)
		{
			if (is_array($value))
			{ // IN condition
				$parts[] = $this->buildInCondition('IN', [$column, $value], $params);
			}
			else
			{
                if ($column === 'id')
                {
                    $column = "id($this->identifier)";
                    $value = intval($value);
                }
				else
                {
                    if (strpos($column, '(') === false)
                    {
                        $column = $this->db->quoteColumnName($column);
                    }

                    if ($this->identifier)
                    {
                        $column = $this->identifier .'.'. $column;
                    }
                }

				if ($value === null)
				{
					$parts[] = "$column IS NULL";
				}
				elseif ($value instanceof Expression)
				{
					$parts[] = "$column=" . $value->expression;
					foreach ($value->params as $n => $v)
					{
						$params[$n] = $v;
					}
				}
				else
				{
					$phName = self::PARAM_PREFIX . count($params);
					$parts[] = "$column=$phName";
					$params[$phName] = $value;
				}
			}
		}

        return count($parts) === 1 ? $parts[0] : '(' . implode(') AND (', $parts) . ')';
	}

    /**
     * Creates an SQL expressions with the `IN` operator.
     * @param string $operator the operator to use (e.g. `IN` or `NOT IN`)
     * @param array $operands the first operand is the column name. If it is an array
     * a composite IN condition will be generated.
     * The second operand is an array of values that column value should be among.
     * If it is an empty array the generated expression will be a `false` value if
     * operator is `IN` and empty if operator is `NOT IN`.
     * @param array $params the binding parameters to be populated
     * @return string the generated SQL expression
     * @throws Exception if wrong number of operands have been given.
     */
    public function buildInCondition($operator, $operands, &$params)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new Exception("Operator '$operator' requires two operands.");
        }
        list($column, $values) = $operands;

        if ($values === [] || $column === []) {
            return $operator === 'IN' ? '0=1' : '';
        }

        if ($values instanceof Query) {
            return $this->buildSubqueryInCondition($operator, $column, $values, $params);
        }

        $values = (array) $values;

        if (count($column) > 1) {
            return $this->buildCompositeInCondition($operator, $column, $values, $params);
        }

        if (is_array($column)) {
            $column = reset($column);
        }
        foreach ($values as $i => $value) {
            if (is_array($value)) {
                $value = isset($value[$column]) ? $value[$column] : null;
            }
            if ($value === null) {
                $values[$i] = 'NULL';
            } elseif ($value instanceof Expression) {
                $values[$i] = $value->expression;
                foreach ($value->params as $n => $v) {
                    $params[$n] = $v;
                }
            } else {
                $phName = self::PARAM_PREFIX . count($params);
                $params[$phName] = $value;
                $values[$i] = $phName;
            }
        }

        if ($column === 'id')
        {
            $column = "id($this->identifier)";
        }
        elseif (strpos($column, '(') === false) {
            $column = $this->db->quoteColumnName($column);
        }

        if (count($values) > 1) {
            return "$column $operator (" . implode(', ', $values) . ')';
        } else {
            $operator = $operator === 'IN' ? '=' : '<>';
            return $column . $operator . reset($values);
        }
    }

    /**
     * Builds SQL for IN condition
     *
     * @param string $operator
     * @param array $columns
     * @param array $values
     * @param array $params
     * @return string SQL
     */
    protected function buildCompositeInCondition($operator, $columns, $values, &$params)
    {
        $vss = [];
        foreach ($values as $value) {
            $vs = [];
            foreach ($columns as $column) {
                if (isset($value[$column])) {
                    $phName = self::PARAM_PREFIX . count($params);
                    $params[$phName] = $value[$column];
                    $vs[] = $phName;
                } else {
                    $vs[] = 'NULL';
                }
            }
            $vss[] = '(' . implode(', ', $vs) . ')';
        }
        foreach ($columns as $i => $column) {
            if ($column === 'id')
            {
                $columns[$i] = "id($this->identifier)";
            }
            elseif (strpos($column, '(') === false) {
                $columns[$i] = $this->db->quoteColumnName($column);
            }
        }

        return '(' . implode(', ', $columns) . ") $operator (" . implode(', ', $vss) . ')';
    }

	public function buildMatch($selectOption = null)
	{
		$match = 'MATCH';
		if ($selectOption !== null) {
			$match .= ' ' . $selectOption;
		}

		return $match;
	}
    public function buildSubQuery(&$select)
    {
        $with = [];

        if ($select instanceof Query)
        {
            $with[] = $this->buildWith($select);
        }
        elseif (is_array($select))
        {
            foreach ($select as $key => $column)
            {
                if ($column instanceof Query) {
                    $with[] = $this->buildWith($column);
                    unset($select[$key]);
                }
            }
        }

        return implode("\n", $with);
    }

    private function buildWith($query)
    {
        list($queryString, $params) = $this->build($query);
        return 'WITH ' . $queryString;
    }

	public function buildFrom($labels, &$params)
	{
		if (empty($labels)) {
			return $this->identifier;
		}

		if (is_array($labels) === false)
		{
			$labels = [$labels];
		}

		return "$this->identifier:".implode(':', $labels);
	}

	/**
	 * @param array $columns
	 * @param array $params the binding parameters to be populated
	 * @return string the Property clause built from [[Query::$select]].
	 */
	public function buildProperties($columns, &$params)
	{
		if (empty($columns))
		{
			return '';
		}

		foreach ($columns as $i => $column)
		{
			if ($column instanceof Expression)
			{
				$columns[$i] = $column->expression;
				$params = array_merge($params, $column->params);
			}
			elseif (preg_match('/^(.*?)(?i:\s+as\s+|\s+)([\w\-_\.]+)$/', $column, $matches))
			{
				$columns[$i] = $matches[1]; // . ' AS ' . $matches[2];

			}
		}

		return json_encode($columns);
	}

	/**
	 * @param array $joins
	 * @param array $params the binding parameters to be populated
	 * @return string the JOIN clause built from [[Query::$join]].
	 * @throws Exception if the $joins parameter is not in proper format
	 */
	public function buildJoin($joins, &$params)
	{
		if (empty($joins)) {
			return '';
		}

		foreach ($joins as $i => $join) {
			if (!is_array($join) || !isset($join[0], $join[1])) {
				throw new Exception('A join clause must be specified as an array of join type, join table, and optionally join condition.');
			}
			list ($foreignLabel, $relationLabels, $direction) = $join;

			$identifier = Inflector::variablize($foreignLabel);
			$foreignNode = "($identifier:$foreignLabel)";

			$relationLabels = (array)$relationLabels;
			$relationLabel = reset($relationLabels);
			$relationIdentifier = Inflector::variablize($relationLabel);
			$relation = "[$relationIdentifier:$relationLabel]";

			if ($direction == ActiveQuery::DIRECTION_OUT)
			{
				$relation = "<-$relation-";
			}
			else
			{
				$relation = "-$relation->";
			}

			$joins[$i] = $relation . $foreignNode;
		}

		return implode($this->separator, $joins);
	}

	/**
	 * @inheritdoc
	 */
	public function buildLimit($limit, $skip)
	{
		$query = [];
		if ($this->hasLimit($limit)) {
			$query[] = 'LIMIT ' . $limit;
		}

		if ($this->hasOffset($skip)) {
			$query[] = 'SKIP ' . $skip;
		}

		return implode(" ", $query);
	}

	public function buildReturn($columns, $distinct = false)
	{
		$return = 'RETURN ';

		if (empty($columns))
		{
			if ($distinct)
			{
				$return .= 'DISTINCT ';
			}

            $return .= $this->identifier;
		}
		else
		{
			$return .= $this->buildColumns($columns);
		}

		return $return;
	}

	/**
	 * Processes columns and properly quote them if necessary.
	 * It will join all columns into a string with comma as separators.
	 * @param string|array $columns the columns to be processed
	 * @return string the processing result
	 */
	public function buildColumns($columns)
	{
		if (!is_array($columns)) {
			if (strpos($columns, '(') !== false) {
				return $columns;
			} else {
				$columns = preg_split('/\s*,\s*/', $columns, -1, PREG_SPLIT_NO_EMPTY);
			}
		}

		foreach ($columns as $i => $column) {
            if ($column === 'id')
            {
                $columns[$i] = "id($this->identifier)";
            }
			elseif ($column instanceof Expression) {
				$columns[$i] = $column->expression;
			}
		}

		return is_array($columns) ? implode(', ', $columns) : $columns;
	}

	public function buildOrderBy($columns)
	{
		if (empty($columns))
		{
			return '';
		}

		$orders = [];
		foreach ($columns as $name => $direction)
		{
			if ($direction instanceof Expression)
			{
				$orders[] = $direction->expression;
			}
			else
			{
				$orders[] = $name . ($direction === SORT_DESC ? ' DESC' : '');
			}
		}

		return 'ORDER BY ' . implode(', ', $orders);
	}

	public function buildHaving($condition, &$params)
	{
		throw new NotSupportedException('Having is not supported by neo4j database');
	}

	/**
	 * Creates a DELETE SQL statement.
	 * For example,
	 *
	 * ~~~
	 * $sql = $queryBuilder->delete('user', 'status = 0');
	 * ~~~
	 *
	 * The method will properly escape the table and column names.
	 *
	 * @param string $label the table where the data will be deleted from.
	 * @param array|string $condition the condition that will be put in the WHERE part. Please
	 * refer to [[Query::where()]] on how to specify condition.
	 * @param array $params the binding parameters that will be modified by this method
	 * so that they can be bound to the DB command later.
	 * @return string the DELETE SQL
	 */
	public function delete($label, $condition, &$params)
	{
		$clauses = [
			$this->buildMatch(),
			'(',
			$this->buildFrom($label, $params),
			//$this->buildProperties($condition, $params),
			')',
			$this->buildUndirectedRelations(),
            $this->buildWhere($condition, $params),
            $this->buildDelete(),
		];

        $queryString = implode($this->separator, array_filter($clauses));

        $params = $this->postpareParams($params, $queryString);

        return [$queryString, $params];
	}

	public function buildDelete()
	{
		$identifiers = [$this->identifier, $this->relationIdentifier];

		return 'DELETE ' . implode(',', $identifiers);
	}

	public function buildUndirectedRelations()
	{
		return "OPTIONAL MATCH ($this->identifier)-[$this->relationIdentifier]-()";
	}
}
