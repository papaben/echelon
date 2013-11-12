<?php
namespace Box\Echelon\Engine;

/**
 * Custom adapters for your own applications should implement Driver_Adapter
 * Generally, you'll have one adapter for each database engine type (e.g. mysql, oracle, mongo)
 */
class Your_Apps_Adapter implements Engine_Adapter
{
	/**
	 * Execute structured query against database
	 * @return mixed Result of query
	 */
	public function query($sql)
	{
		// TODO: Implement query() method.
	}

	/**
	 * @param string $query
	 * @param array $data
	 * @return mixed Result of running query as prepared statement with data
	 */
	public function query_prepared($query, array $data = array())
	{
		// TODO: Implement query_prepared() method.
	}
}