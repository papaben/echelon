<?php
namespace Box\Echelon\Engine\Mysql;
use Bart\Log4PHP;
use Box\Echelon\Echelon_Exception;

/**
 * Small facade for mysql prepared statement
 */
class Prepared_Statement
{
	/** @var \mysqli_stmt Statement for the query */
	private $stmt;
	/** @var array The data for the query */
	private $data;
	/** @var \Logger */
	private $logger;

	/**
	 * @param string $query
	 * @param array $data Parameters to query [param_value => param_type]
	 *        {@see http://www.php.net/manual/en/mysqli-stmt.bind-param.php}
	 * @return array Results of query
	 */
	public function __construct(\mysqli $mysqli, $query, array $data = array())
	{
		$stmt = $mysqli->prepare($query);
		if (!$stmt)
		{
			throw new Echelon_Exception('Could not prepare statement from supplied query');
		}

		$this->stmt = $stmt;
		$this->data = $data;
		$this->logger = Log4PHP::getLogger(__CLASS__);
	}

	/**
	 * Prepare the statement and return fetched record set as array
	 */
	public function prepare_and_fetch_as_array()
	{
		$this->bind_param_data();
		$this->logger->debug('Bound data params to statement');

		$fields = $this->bind_select_fields();
		$this->logger->debug('Bound selected fields from statement');

		$this->stmt->execute();
		$this->logger->debug('Query executed');

		$records = array();
		while ($this->stmt->fetch())
		{
			$data = array();
			foreach ($fields as $name => $value)
			{
				$data[$name] = $value;
			}

			$records[] = $data;
		}

		return $records;
	}

	/**
	 * Prepare the statement and return fetched record set as array keyed by
	 * the value of the $key in each recordset
	 * @param string $key Key by which to list records in result set
	 */
	public function prepare_and_fetch_keyed_recordset($key)
	{
		$this->bind_param_data();
		$fields = $this->bind_select_fields();

		$this->stmt->execute();

		$records = array();
		while ($this->stmt->fetch())
		{
			$data = array();
			foreach ($fields as $name => $value)
			{
				$data[$name] = $value;
			}

			$records[$fields[$key]] = $data;
		}

		return $records;
	}

	public function close()
	{
		$this->stmt->close();
	}

	/**
	 * Bind query parameters to statement
	 */
	private function bind_param_data()
	{
		if (!$this->data)
		{
			return;
		}

		// Perform a set of ridiculous statements in order to get around bind_param's
		// insistence that all data parameters be passed by reference
		// See http://www.php.net/manual/en/mysqli-stmt.bind-param.php
		$fields_passed_by_reference = array();
		$extracted_field_values = array();
		$types = '';

		// Parameter data is an array of the parameter type and value tuples
		foreach ($this->data as $index => $param_and_type)
		{
			$types .= $param_and_type[0];

			$value = $param_and_type[1];

			$this->logger->trace("Binding parameter at $index with value $value");

			// Create the actual variable which will hold value to substitute into query
			$extracted_field_values[$index] = $value;
			// Assign a reference to variable for pass-by-ref :bind_param invocation
			$fields_passed_by_reference[] = &$extracted_field_values[$index];
		}

		array_unshift($fields_passed_by_reference, $types);

		// E.g. SELECT 1 FROM tbl WHERE a = ? and b = ?
		// ... => stmt->bind_param('ss', $a, $b);
		call_user_func_array(array($this->stmt, 'bind_param'), $fields_passed_by_reference);
	}

	/**
	 * If not a SELECT statement, this has no effect
	 * @return array Field references associated to each record fetched from data set
	 */
	private function bind_select_fields()
	{
		$fields_passed_by_reference = array();
		$extracted_field_values = array();

		$metadata = $this->stmt->result_metadata();
		while ($field_data = $metadata->fetch_field())
		{
			$this->logger->trace('Binding select field: ' . $field_data->name);

			// Create the actual variable which will hold value to field in record
			$extracted_field_values[$field_data->name] = null;
			// Assign a reference to variable for pass-by-ref :bind_result invocation
			$fields_passed_by_reference[] = &$extracted_field_values[$field_data->name];
		}

		// http://www.php.net/manual/en/mysqli-stmt.fetch.php
		// E.g. SELECT id, name FROM tbl ==> $stmt->bind_result($id, $name)
		call_user_func_array(array($this->stmt, 'bind_result'), $fields_passed_by_reference);

		return $extracted_field_values;
	}
}
