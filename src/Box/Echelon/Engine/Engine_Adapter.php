<?php
namespace Box\Echelon\Engine;

/**
 * Database engine adapter makes it possible for multiple database engine drivers
 * to share a single interface consumed by migrations against various types
 * of database engine providers.
 * @package Box\Echelon\Engine
 */
interface Engine_Adapter {
	/**
	 * Execute structured query against database
	 * @return mixed Result of query
	 */
	public function query($sql);

	/**
	 * @param string $query
	 * @param array $data
	 * @return mixed Result of running query as prepared statement with data
	 */
	public function query_prepared($query, array $data = array());
}