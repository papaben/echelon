<?php
namespace Box\Echelon;

/**
 * Liason between migrations themselves and database schemas known to your app
 */
interface Db_Liaison
{
	/**
	 * @param string $database_name Database name in the context of your configuration
	 * @param string $sql SQL to execute against database
	 * @param array $data For parameterized queries
	 * @return mixed
	 */
	public function execute($database_name, $sql, array $data);

	/**
	 * Migrations can wrap access to ActiveRecord objects behind checks
	 * against this method. The underlying database connection via your app
	 * can't be controlled by the Echelon system.
	 * @return boolean
	 */
	public function is_active_record_enabled();
}


