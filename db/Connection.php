<?php
/**
 * Created by PhpStorm.
 * User: bk
 * Date: 24.04.15
 * Time: 16:55
 */

namespace neo4j\db;

use Everyman\Neo4j\Cache;
use Everyman\Neo4j\Client;
use Everyman\Neo4j\Exception;
use Everyman\Neo4j\Node;
use Everyman\Neo4j\PropertyContainer;
use Everyman\Neo4j\Query;
use Everyman\Neo4j\Transaction;
use Everyman\Neo4j\Transport\Curl;
use Everyman\Neo4j\Transport\Stream;
use Everyman\Neo4j\Transport;
use yii\base\Component;
use yii;


/**
 * Connection represents a connection to a database via [PDO](http://www.php.net/manual/en/ref.pdo.php).
 *
 * Connection works together with [[Command]], [[DataReader]] and [[Transaction]]
 * to provide data access to various DBMS in a common set of APIs. They are a thin wrapper
 * of the [[PDO PHP extension]](http://www.php.net/manual/en/ref.pdo.php).
 *
 * To establish a DB connection, set [[dsn]], [[username]] and [[password]], and then
 * call [[open()]] to be true.
 *
 * The following example shows how to create a Connection instance and establish
 * the DB connection:
 *
 * ~~~
 * $connection = new \yii\db\Connection([
 *     'dsn' => $dsn,
 *     'username' => $username,
 *     'password' => $password,
 * ]);
 * $connection->open();
 * ~~~
 *
 * After the DB connection is established, one can execute SQL statements like the following:
 *
 * ~~~
 * $command = $connection->createCommand('SELECT * FROM post');
 * $posts = $command->queryAll();
 * $command = $connection->createCommand('UPDATE post SET status=1');
 * $command->execute();
 * ~~~
 *
 * One can also do prepared SQL execution and bind parameters to the prepared SQL.
 * When the parameters are coming from user input, you should use this approach
 * to prevent SQL injection attacks. The following is an example:
 *
 * ~~~
 * $command = $connection->createCommand('SELECT * FROM post WHERE id=:id');
 * $command->bindValue(':id', $_GET['id']);
 * $post = $command->query();
 * ~~~
 *
 * For more information about how to perform various DB queries, please refer to [[Command]].
 *
 * If the underlying DBMS supports transactions, you can perform transactional SQL queries
 * like the following:
 *
 * ~~~
 * $transaction = $connection->beginTransaction();
 * try {
 *     $connection->createCommand($sql1)->execute();
 *     $connection->createCommand($sql2)->execute();
 *     // ... executing other SQL statements ...
 *     $transaction->commit();
 * } catch (Exception $e) {
 *     $transaction->rollBack();
 * }
 * ~~~
 *
 * Connection is often used as an application component and configured in the application
 * configuration like the following:
 *
 * ~~~
 * [
 *	 'components' => [
 *		 'db' => [
 *			 'class' => '\yii\db\Connection',
 *			 'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
 *			 'username' => 'root',
 *			 'password' => '',
 *			 'charset' => 'utf8',
 *		 ],
 *	 ],
 * ]
 * ~~~
 *
 * @property boolean $isActive Whether the DB connection is established. This property is read-only.
 * @property QueryBuilder $queryBuilder The query builder for the current DB connection. This property is
 * read-only.
 *
 * @property Client $client
 *
 * @author Bennet Klarhölter <boehsermoe@me.com>
 * @since 2.0
 */
class Connection extends Component
{
	/**
	 * @event Event an event that is triggered after a DB connection is established
	 */
	const EVENT_AFTER_OPEN = 'afterOpen';

    public $driverName = 'neo4j';

	public $host = 'localhost';
	public $port = 7474;

	public $username = 'neo4j';
	public $password = 'neo';

	/** @var Client */
	protected $_client = null;

	/**
	 * @var boolean whether to enable query caching.
	 * Note that in order to enable query caching, a valid cache component as specified
	 * by [[queryCache]] must be enabled and [[enableQueryCache]] must be set true.
	 *
	 * Methods [[beginCache()]] and [[endCache()]] can be used as shortcuts to turn on
	 * and off query caching on the fly.
	 * @see queryCacheDuration
	 * @see queryCache
	 * @see queryCacheDependency
	 * @see beginCache()
	 * @see endCache()
	 */
	public $enableQueryCache = false;
	/**
	 * @var integer number of seconds that query results can remain valid in cache.
	 * Defaults to 3600, meaning 3600 seconds, or one hour.
	 * Use 0 to indicate that the cached data will never expire.
	 * @see enableQueryCache
	 */
	public $queryCacheDuration = 3600;
	/**
	 * @var \yii\caching\Dependency the dependency that will be used when saving query results into cache.
	 * Defaults to null, meaning no dependency.
	 * @see enableQueryCache
	 */
	public $queryCacheDependency;
	/**
	 * @var Cache|string the cache object or the ID of the cache application component
	 * that is used for query caching.
	 * @see enableQueryCache
	 */
	public $queryCache = 'cache';

	/**
	 * Returns a value indicating whether the DB connection is established.
	 * @return boolean whether the DB connection is established
	 */
	public function getIsActive()
	{
		return $this->_client !== null;
	}

	/**
	 * @return \Everyman\Neo4j\Client
	 */
	public function getClient()
	{
		return $this->_client;
	}

	/**
	 * Establishes a DB connection.
	 * It does nothing if a DB connection has already been established.
	 * @throws Exception if connection fails
	 */
	public function open()
	{
		if ($this->_client === null) {
			if (empty($this->host)) {
				throw new yii\base\InvalidConfigException('Connection::host cannot be empty.');
			}
			$token = 'Opening DB connection: ' . $this->host;
			try {
				Yii::trace($token, __METHOD__);
				Yii::beginProfile($token, __METHOD__);
				$this->initConnection();
				Yii::endProfile($token, __METHOD__);
			} catch (\PDOException $e) {
				Yii::endProfile($token, __METHOD__);
				throw new yii\db\Exception($e->getMessage(), $e->errorInfo, (int) $e->getCode(), $e);
			}
		}
	}

	/**
	 * Closes the currently active DB connection.
	 * It does nothing if the connection is already closed.
	 */
	public function close()
	{
		if ($this->_client !== null) {
			Yii::trace('Closing DB connection: ' . $this->host, __METHOD__);
			$this->_client = null;
		}
	}

	/**
	 * Creates the PDO instance.
	 * This method is called by [[open]] to establish a DB connection.
	 * The default implementation will create a PHP PDO instance.
	 * You may override this method if the default PDO needs to be adapted for certain DBMS.
	 * @return Transport the pdo instance
	 */
	protected function createClientInstance()
	{
		$client = new Client($this->host, $this->port);
		$client->getTransport()
			#->useHttps()
			->setAuth($this->username, $this->password);

		return $client;
	}

	/**
	 * Initializes the DB connection.
	 * This method is invoked right after the DB connection is established.
	 * The default implementation turns on `PDO::ATTR_EMULATE_PREPARES`
	 * if [[emulatePrepare]] is true, and sets the database [[charset]] if it is not empty.
	 * It then triggers an [[EVENT_AFTER_OPEN]] event.
	 */
	protected function initConnection()
	{
		$this->_client = $this->createClientInstance();

		$this->trigger(self::EVENT_AFTER_OPEN);
	}

    /**
     * Creates a command for execution.
     * @param string|Query $query the Query statement to be executed
     * @param array $params the parameters to be bound to the SQL statement
     * @return Command the DB command
     */
	public function createCommand($query = null, $params = [])
	{
        $container = null;

        if ($query instanceof PropertyContainer)
        {
            $container = $query;
            $query = null;
        }

		$this->open();
		$command = new Command([
			'db' => $this,
			'query' => $query,
			'container' => $container,
		]);

		return $command->bindValues($params);
	}

	/**
	 * Returns the query builder for the current DB connection.
	 * @return QueryBuilder the query builder for the current DB connection.
	 */
	public function getQueryBuilder()
	{
		if ($this->_builder === null) {
			$this->_builder = $this->createQueryBuilder();
		}

		return $this->_builder;
	}

	private $_builder;

	/**
	 * Creates a query builder for the database.
	 * This method may be overridden by child classes to create a DBMS-specific query builder.
	 * @return QueryBuilder query builder instance
	 */
	public function createQueryBuilder()
	{
		return new QueryBuilder($this);
	}

	/** @var $_transaction Transaction */
	private $_transaction;

	/**
	 * Returns the currently active transaction.
	 * @return Transaction the currently active transaction. Null if no active transaction.
	 */
	public function getTransaction()
	{
		return ($this->_transaction && !$this->_transaction->isClosed()) ? $this->_transaction : null;
	}

	/**
	 * Starts a transaction.
	 * @return Transaction the transaction initiated
	 */
	public function beginTransaction()
	{
		$this->open();

		if ($this->_transaction === null) {
			$this->_transaction = $this->client->beginTransaction();
		}

		return $this->_transaction;
	}

	/**
	 * Quotes a string value for use in a query.
	 * Note that if the parameter is not a string, it will be returned without change.
	 * @param string $str string to be quoted
	 * @return string the properly quoted string
	 * @see http://www.php.net/manual/en/function.PDO-quote.php
	 */
	public function quoteValue($str)
	{
		return $str;
	}

	/**
	 * Quotes a table name for use in a query.
	 * If the table name contains schema prefix, the prefix will also be properly quoted.
	 * If the table name is already quoted or contains special characters including '(', '[[' and '{{',
	 * then this method will do nothing.
	 * @param string $name table name
	 * @return string the properly quoted table name
	 */
	public function quoteTableName($name)
	{
		return $name;
	}

	/**
	 * Quotes a column name for use in a query.
	 * If the column name contains prefix, the prefix will also be properly quoted.
	 * If the column name is already quoted or contains special characters including '(', '[[' and '{{',
	 * then this method will do nothing.
	 * @param string $name column name
	 * @return string the properly quoted column name
	 */
	public function quoteColumnName($name)
	{
		return $name;
	}
}