<?php
namespace Box\Echelon\Engine;
use Bart\Log4PHP;
use Box\Echelon\Engine\Mysql\Prepared_Statement;

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
	 * @param string $query
	 * @param array $data
	 * @return mixed Result of running query as prepared statement with data
	 */
	public function query_prepared($query, array $data = array())
	{
		$stmt = new Prepared_Statement($this->mysqli, $query, $data);

		// Need to commit this code to Bart still...
		return \Bart\Loan\Loan::using($stmt, function(Prepared_Statement $stmt) {
			return $stmt->prepare_and_fetch_as_array();
		});
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