<?php
namespace Box\Echelon;

/**
 * Expected methods to use the database by the Migration system
 */
interface Db_Wrapper
{
	public function execute($sql, $data = array());
	public function execute_app_mapping($sql, $data = array());
	public function execute_app_shards($sql, $data = array());
	public function execute_log_db($sql, $data = array());
	public function execute_log_db_shards($sql, $data = array());
	public function execute_services_db($sql, $data = array());
	public function execute_updates_db($sql, $data = array());
	public function execute_notes_db($sql, $data = array());


	/**
	 * Returns true if DB_Model can be used in this migration
	 * @param string $reason What is the brief purpose of using DB_Model code?
	 */
	public function enable_db_model($reason);
}
