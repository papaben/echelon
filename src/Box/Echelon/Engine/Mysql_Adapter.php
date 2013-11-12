<?php
namespace Box\Echelon\Engine;
use Bart\Log4PHP;

/**
 * Example adapter for MySQL databases
 */
class Mysql_Adapter implements Engine_Adapter
{
	/** @var \mysqli */
	private $mysqli;
	/** @var \Logger */
	private $logger;

	public function __construct($host, $user, $passwd, $dbname = '', $port = 3306)
	{
		$this->mysqli = new \mysqli($host, $user, $passwd, $dbname, $port);
		$this->logger = Log4PHP::getLogger(__CLASS__);
	}

	/**
	 * Execute structured query against database
	 * @return mixed Result of query
	 */
	public function query($sql)
	{
		return $this->mysqli->query($sql);
	}

	/**
	 * @NOTE Not using prepared statements here atm because there isn't a glaring need
	 * for them in this context and the way they work in PHP out of the box would make
	 * this code overly complex for the task at hand (DDL, not data, manipulation)
	 * @param string $query
	 * @param array $data
	 * @return mixed Result of running query as prepared statement with data
	 */
	public function query_prepared($query, array $data = array())
	{
		// TODO: Implement query_prepared() method.
	}

	public function close()
	{
		try
		{
			$this->mysqli->close();
		}
		catch (\Exception $e)
		{
			$this->logger->warn('Could not close resource to mysql');
		}
	}
}