<?php
namespace Box\Echelon;

/**
 * Expected methods to use the database by the Migration system
 */
interface Db_Facade
{
	/**
	 * @param string $database_name Database name in the context of your configuration
	 * @param string $sql SQL to execute against database
	 * @return mixed
	 */
	public function execute($database_name, $sql);

	/**
	 * Since the facade goes only so far as to wrap access funneled through it, access to
	 * the database through other means, such as ActiveRecords, can be gated behind
	 * conditional blocks against this method
	 */
	public function active_record_is_enabled();
}
