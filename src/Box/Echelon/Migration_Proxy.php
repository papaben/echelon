<?php
namespace Box\Echelon;

/**
 * Wrap around a given migration and provide ability to perform it
 */
class Migration_Proxy
{
	private static $analyze = false;

	protected $file_name;
	private $version;
	private $name;

	/**
	 * @private Instantiate using MigrationProxy::createOrNot
	 */
	private function __construct($file_name, $version, $name)
	{
		$this->file_name = $file_name;
		$this->version = $version;
		$this->name = $name;
	}

	/**
	 * Enable proxy factory to create migration proxies which will submit their
	 * migrations through the analyzer
	 */
	public static function enable_analysis()
	{
		self::$analyze = true;
	}

	/**
	 * Stop analyzing migrations
	 */
	public static function disable_analysis()
	{
		self::$analyze = false;
	}

	/**
	 * @static
	 * @param  $file_name
	 * @return Migration_Proxy proxy around the migration or null if the file name isn't valid
	 */
	public static function create_or_not($file_name, $show_git_log)
	{
		$matched = array();

		// YEAR-MM-DD-hh-dd-rando_migration_name
		if (preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[a-z0-9]{5})_([a-z0-9_]+).php$/i', $file_name, $matched))
		{
			return $show_git_log ?
				new Migrator_Migration_Proxy_With_Git($matched[0], $matched[1], $matched[2]) :
				new self($matched[0], $matched[1], $matched[2]);
		}

		return null;
	}

	public function __toString()
	{
		return $this->get_version() . ' - ' . $this->get_name();
	}

	public function get_version()
	{
		return $this->version;
	}

	public function get_name()
	{
		return $this->name;
	}

	/**
	 * @param Db_Wrapper $db_wrapper
	 * @return Migrator_Migration_Base instance of requested migration class
	 */
	private function load(Db_Wrapper $db_wrapper)
	{
		$dir = Migrator::get_migrations_dir();
		require_once $dir . $this->file_name;

		$db_wrapper = $this->tweak_db_wrapper($db_wrapper);

		return new $this->name($db_wrapper);
	}

	/**
	 * Perform forward migration
	 * @param Db_Wrapper $db_wrapper
	 */
	public function migrate_up(Db_Wrapper $db_wrapper)
	{
		$migration = $this->load($db_wrapper);
		$start_time = microtime(true);
		$migration->up();
		$end_time = microtime(true);
		$exec_time = $end_time - $start_time;
		Migrator::info("# $this execution: $exec_time seconds");
	}

	/**
	 * Perform rollback migration
	 * @param Db_Wrapper $db_wrapper
	 */
	public function migrate_down(Db_Wrapper $db_wrapper)
	{
		$migration = $this->load($db_wrapper);
		$migration->down();
	}

	/**
	 * Run db wrapper through any composition required per migration
	 */
	protected function tweak_db_wrapper(Db_Wrapper $db_wrapper)
	{
		if (self::$analyze)
		{
			return new Migrator_Db_Analyzer($this, $db_wrapper);
		}

		return $db_wrapper;
	}
}

