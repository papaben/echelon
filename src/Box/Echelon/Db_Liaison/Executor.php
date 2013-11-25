<?php
namespace Box\Echelon\Db_Liaison;
use Bart\Log4PHP;
use Box\Echelon\Databases;
use Box\Echelon\Db_Liaison;

/**
 * Executes all SQL passed
 */
class Executor implements Db_Liaison
{
	/** @var \Logger */
	private $logger;

	public function __construct()
	{
		$this->logger = Log4PHP::getLogger(__CLASS__);
	}

	/**
	 * @param string $database_name Database name in the context of your configuration
	 * @param string $sql SQL to execute against database
	 * @param array $data For parameterized queries
	 * @return mixed
	 */
	public function execute($database_name, $sql, array $data)
	{
		$engine = Databases::get($database_name);

		return $engine->query_prepared($sql, $data);
	}

	/**
	 * Migrations can wrap access to ActiveRecord objects behind checks
	 * against this method. The underlying database connection via your app
	 * can't be controlled by the Echelon system.
	 * @return boolean
	 */
	public function is_active_record_enabled()
	{
		$this->logger->info('ActiveRecord check performed');
		return true;
	}
}