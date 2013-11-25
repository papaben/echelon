<?php
namespace Box\Echelon\Db_Liaison;
use Bart\Log4PHP;
use Box\Echelon\Db_Liaison;

/**
 * Echoes all commands; will not execute anything
 */
class Echo_Only implements Db_Liaison
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
	 * @return mixed
	 */
	public function execute($database_name, $sql, array $data)
	{
		echo "/* [Echoer] {$database_name}: */\n{$sql}\n";
	}

	public function is_active_record_enabled()
	{
		// Migration probably contains some ActiveRecord manipulation
		$this->logger->info('Checked for active record.');
		return false;
	}
}