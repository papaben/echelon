<?php
namespace Box\Echelon\Versions;
use Bart\Diesel;
use Bart\Log4PHP;
use Box\Echelon\Databases;
use Box\Echelon\Db_Facade;
use Box\Echelon\Migration_Proxy;

/**
 * Keeps track of what versions have been applied to the database(s)
 */
abstract class Applied_Versions
{
	const TBL_NAME = 'schema_migrations';

	/** @var array Maximum migration record when system first loaded */
	private  $starting_max;

	/** @var \Logger */
	protected $logger;

	/** @var \Box\Echelon\Db_Facade */
	protected $db;
	/** @var array All the versions in the database */
	protected $migrated_versions = array();

	/**
	 * @param Db_Facade $db
	 */
	public function __construct(Db_Facade $db)
	{
		$this->logger = Log4PHP::getLogger(get_called_class());

		$this->load_versions_from_actual_db();

		$this->db = $db;
	}

	/**
	 * Call this the *very* first time you use the system to configure your
	 * default database with the schema_migrations table
	 */
	public static function init_schema_table()
	{
		/** @var \Box\Echelon\Echelon_Config $configs */
		$configs = Diesel::create('\Box\Echelon\Echelon_Configs');
		$default_db_name = $configs->default_database_name();

		$migs_db = Databases::get($default_db_name);

		$sql = '
		CREATE TABLE IF NOT EXISTS ' . self::TBL_NAME . ' (
			id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
			version VARCHAR(22) NOT NULL,
			name VARCHAR(255) NOT NULL
		)';

		$migs_db->query($sql);
	}

	/**
	 * Track migration occurred in db and local store
	 * @param Migration_Proxy $migration The migration to be added or removed
	 */
	public abstract function track_version_affected(Migration_Proxy $migration);

	/**
	 * @return string
	 */
	public function __toString()
	{
		$max = $this->starting_max;
		return count($this->migrated_versions) . " migration(s) applied; max is {$max['version']} - {$max['name']}";
	}

	/**
	 * @param Migration_Proxy $migration
	 * @return bool If migration was already applied
	 */
	public function is_migrated(Migration_Proxy $migration)
	{
		return array_key_exists($migration->get_version(), $this->migrated_versions);
	}

	/**
	 * @param Migration_Proxy[] $proxies The proxies from scanning the migration directory
	 * @return array
	 */
	public function get_diff(array $proxies)
	{
		$migrated_file_versions = array();

		$diff = array(
			'unapplied' => array(),
			'unknown' => array(),
		);

		$this->logger->debug('Looking for migrations files that have not been applied (unapplied)');
		foreach ($proxies as $migration)
		{
			if ($this->is_migrated($migration))
			{
				$migrated_file_versions[] = $migration->get_version();
				continue;
			}

			$diff['unapplied'][] = $migration;
		}

		$this->logger->debug('Looking for applied migrations that do not have migration files (unknown)');
		foreach ($this->migrated_versions as $version => $name)
		{
			if (in_array($version, $migrated_file_versions)) continue;

			$diff['unknown'][] = "{$version} {$name}";
		}

		$this->logger->trace('Diff completed');
		return $diff;
	}

	/**
	 * Loads all the versions from the database
	 * @return array {version, name} of the maximum migration record found
	 */
	private function load_versions_from_actual_db()
	{
		/** @var \Box\Echelon\Echelon_Config $configs */
		$configs = Diesel::create('\Box\Echelon\Echelon_Configs');
		/* @var string The name of the database handle to use for tracking schema migrations */
		$default_db_name = $configs->default_database_name();

		$migs_db = Databases::get($default_db_name);
		// $migs_db = DB_Manager::instance()->get_db_by_name('application', null, true);

		$query = 'SELECT version, name FROM ' .
			self::TBL_NAME .
			' ORDER BY version';

		$versions = $migs_db->query($query);

		$this->migrated_versions = array();
		foreach ($versions as $record)
		{
			$this->logger->debug("Loaded from db: {$record['version']}");
			$this->migrated_versions[$record['version']] = $record['name'];
		}

		$this->starting_max = $record;
	}
}
