<?php
/**
 * Created by PhpStorm.
 * User: bk
 * Date: 24.04.15
 * Time: 16:55
 */

namespace neo4j\db;

use Everyman\Neo4j\Exception;
use Everyman\Neo4j\Transport\Curl;
use Everyman\Neo4j\Transport\Stream;
use Everyman\Neo4j\Transport;
use yii\base\Component;
use yii;

class Connection extends Component
{
	/**
	 * @event Event an event that is triggered after a DB connection is established
	 */
	const EVENT_AFTER_OPEN = 'afterOpen';

	public $host;

	public $port;

	/** @var Transport */
	protected $transport;

	/**
	 * Establishes a DB connection.
	 * It does nothing if a DB connection has already been established.
	 * @throws Exception if connection fails
	 */
	public function open()
	{
		if ($this->transport === null) {
			if (empty($this->host)) {
				throw new yii\base\InvalidConfigException('Connection::dsn cannot be empty.');
			}
			$token = 'Opening DB connection: ' . $this->host;
			try {
				Yii::trace($token, __METHOD__);
				Yii::beginProfile($token, __METHOD__);
				$this->initConnection();
				Yii::endProfile($token, __METHOD__);
			} catch (\PDOException $e) {
				Yii::endProfile($token, __METHOD__);
				throw new Exception($e->getMessage(), $e->errorInfo, (int) $e->getCode(), $e);
			}
		}
	}

	/**
	 * Closes the currently active DB connection.
	 * It does nothing if the connection is already closed.
	 */
	public function close()
	{
		if ($this->transport !== null) {
			Yii::trace('Closing DB connection: ' . $this->host, __METHOD__);
			$this->transport = null;
		}
	}

	/**
	 * Creates the PDO instance.
	 * This method is called by [[open]] to establish a DB connection.
	 * The default implementation will create a PHP PDO instance.
	 * You may override this method if the default PDO needs to be adapted for certain DBMS.
	 * @return Transport the pdo instance
	 */
	protected function createTransportInstance()
	{
		$transport = $this->host;
		$port = $this->port;

		try {
			if ($transport === null) {
				$transport = new Curl();
			} else if (is_string($transport)) {
				$transport = new Curl($transport, $port);
			}
		} catch (Exception $e) {
			if ($transport === null) {
				$transport = new Stream();
			} else if (is_string($transport)) {
				$transport = new Stream($transport, $port);
			}
		}

		return $transport;
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
		$this->transport = $this->createTransportInstance();

		$this->trigger(self::EVENT_AFTER_OPEN);
	}
} 