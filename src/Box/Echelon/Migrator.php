<?php
namespace Box\Echelon;
use Bart\Diesel;

/**
 * Coordinates the actual migrations
 */
class Migrator
{
	/**
	 * Prevent rolling back more than two migrations at a time
	 * @Note designed to avoid human error
	 */
	const MAX_ROLLBACKS_ALLOWED = 2;
	const TBL_NAME = 'schema_migrations';

	private $offline;
	protected $db;
	protected $show_git_log = false;

	private $dump_schema = false;
	private $migrations = array();
	private $migrated_versions;
	private $starting_max;
	// target version when rolling back
	private $target_version;

	/**
	 * @var string Directory to find the schema migration files
	 */
	protected static $migrations_root_dir;

	/**
	 * @param Db_Wrapper $db Wrap access to database
	 * @param $offline Boolean Migration should be run online or offline?
	 */
	public function __construct(Db_Wrapper $db, $offline = false, $show_git_log = false)
	{
		$this->logger = \Logger::getLogger(__CLASS__);

		/** @var \Box\Echelon\Echelon_Configs $configs */
		$configs = Diesel::create('\Box\Echelon\Echelon_Configs');
		$this->db = $db;
		$this->offline = $offline;
		$this->show_git_log = $show_git_log;
		$this->load_migrations();
		$this->load_versions_from_db();
	}

	/**
	 * Apply any unapplied forward migrations
	 */
	public function migrate_up()
	{
		$this->run(true);
	}

	/**
	 * Rollback database to given version (or latest version not larger than $version)
	 * @param string $version Version hash to rollback to
	 */
	public function migrate_down($version)
	{
		// run migrations from top down
		$this->target_version = $version;
		$this->migrations = array_reverse($this->migrations);

		if (!$this->is_rollback_version_allowed())
		{
			throw new Migration_Exception('Target version exceeds rollback limit');
		}

		$this->run(false);
	}

	/**
	 * Determine difference between applied migrations tracked in database and
	 * the migrations listed on disk
	 * @return array('unapplied' => [], 'unknown' => [])
	 * "unapplied" contains an array of Migration_Proxy instances representing the unapplied migrations
	 * "uknown" contains an array of strings listing the "version hash" and "name" of any migrations
	 * ...found in the database that do not have matching migration files
	 */
	public function get_diff()
	{
		$migrated_file_versions = array();

		$this->logger->debug('Looking for migration files that have NOT been run (unapplied)');
		$diff = array(
			'unapplied' => array(),
			'unknown' => array(),
		);
		foreach ($this->migrations as $migration)
		{
			if (array_key_exists($migration->get_version(), $this->migrated_versions))
			{
				$migrated_file_versions[] = $migration->get_version();
				continue;
			}

			$diff['unapplied'][] = $migration;
		}

		$this->logger->debug('Looking for applied migs that do not have migration files (unknown)');
		foreach ($this->migrated_versions as $version => $name)
		{
			if (in_array($version, $migrated_file_versions)) continue;

			$diff['unapplied'][] = "{$version} {$name}";
		}

		$this->logger->trace('Diff completed');
	}

	/**
	 * Backfill a new database that already has the migrations manually applied,
	 * but doesn't have the schema migrations table.
	 *
	 * Use with caution.
	 */
	public function backfill_new_db()
	{
		$this->logger->debug('Beginning to backfill untracked migrations.');

		foreach ($this->migrations as $migration)
		{
			if ($this->should_skip(true, $migration))
			{
				$this->logger->debug("Skipping $migration");
				continue;
			}

			$this->logger->debug("Tracking $migration");
			$this->track_version_up($migration);
		}
	}

	/**
	 * Load all files from the migrations directory and wrap them in proxies
	 */
	private function load_migrations()
	{
		$dir = self::get_migrations_dir();

		// find all php files in migrations sorted ASC
		foreach (scandir($dir) as $file_name)
		{
			$proxy = Migrator_Migration_Proxy::create_or_not($file_name, $this->show_git_log);

			// file name was a valid format?
			if ($proxy != null)
			{
				self::info("Loaded $proxy");
				$this->migrations[] = $proxy;
			}
		}

		// Be extra sure that the migrations are in order
		sort($this->migrations);
	}

	private function load_versions_from_db()
	{
		$migs_db = DB_Manager::instance()->get_db_by_name('application', null, true);

		$query = 'SELECT version, name FROM ' .
				self::TBL_NAME .
				' ORDER BY version';

		$versions = $migs_db->getAll($query);

		$this->migrated_versions = array();
		foreach ($versions as $record)
		{
			self::info("Loaded from db: {$record['version']}");
			$this->migrated_versions[$record['version']] = $record['name'];
		}

		$this->starting_max = $record;
	}

	public static function init_schema_table()
	{
		$migs_db = DB_Manager::instance()->get_db_by_name('application', null, true);
		$sql = 'CREATE TABLE IF NOT EXISTS ' . self::TBL_NAME . ' (
			id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
			version VARCHAR(22) NOT NULL,
			name VARCHAR(255) NOT NULL
		) ';

		$migs_db->query($sql);
	}

	/**
	 * @param $msg String Log message if debugging
	 */
	public static function debug($msg)
	{
		if ($GLOBALS['console.params']['log_level'] & 2)
		{
			echo $msg . PHP_EOL;
		}
	}

	/**
	 * @param $msg String Log message if info logging enabled
	 */
	public static function info($msg)
	{
		if ($GLOBALS['console.params']['log_level'] & 1)
		{
			echo $msg . PHP_EOL;
		}
	}

	/**
	 * Run the migration.  Either upgrade or rollback the database
	 * @param  $upgrade
	 */
	private function run($upgrade)
	{
		$dir = $upgrade ? 'up' : 'down';
		$migrate = 'migrate_' . $dir;
		$track = 'track_version_' . $dir;

		$max = $this->starting_max;
		self::debug('Database has ' . sizeof($this->migrated_versions) .
				" migration(s) applied; max is {$max['version']} - {$max['name']}");
		self::debug('Found ' . sizeof($this->migrations) . ' migration(s) in migrations/');
		self::debug('Determining unapplied/applied migrations');
		self::debug("Beginning to migrate $dir");

		if ($this->offline)
		{
			// Stop replication before offline migrations
			$this->db->execute('STOP SLAVE SQL_THREAD');
			$this->db->execute('SET SESSION SQL_LOG_BIN = 0');
		}

		// run each migration in order as necessary
		foreach ($this->migrations as $migration)
		{
			if ($this->should_skip($upgrade, $migration))
			{
				continue;
			}

			if ($this->is_completed($upgrade, $migration))
			{
				self::debug('Reached target version ' . $this->target_version);
				break;
			}

			try
			{
				// @NOTE no transaction support currently
				self::debug('Migrating: ' . $migration);
				$migration->$migrate($this->db);
			}
			catch (Exception $e)
			{
				self::debug('Uh oh, migration encountered problem: ' . $e->getMessage());
				self::debug('Prematurely shutting down migration');
				self::debug('See application log (migration) for details');
				throw $e;
			}

			// persist version to db
			$this->$track($migration);
			$this->dump_schema = true;

			self::debug("Migrated $dir: version $migration");
		}

		if ($this->offline)
		{
			// Migrations complete, time for slave to catch up
			$this->db->execute('START SLAVE SQL_THREAD');
		}

		self::debug('Migration complete');
	}

	/**
	 * @param  $is_up
	 * @param Migrator_Migration_Proxy $migration
	 * @return bool Is the rollback complete?
	 */
	private function is_completed($is_up, Migrator_Migration_Proxy $migration)
	{
		// migration is not complete if upgrading or rolling back to first revision
		// @NOTE may want to raise an error when target version is undefined in this case
		if ($is_up || !isset($this->target_version))
		{
			return false;
		}

		// If we have reached the target, then no further must we go
		return $migration->get_version() <= $this->target_version;
	}

	/**
	 * @param  $is_up
	 * @param Migrator_Migration_Proxy $migration
	 * @return bool if this migration should be skipped
	 */
	private function should_skip($is_up, Migrator_Migration_Proxy $migration)
	{
		// skip applied migrations on upgrade
		$is_migrated = array_key_exists($migration->get_version(), $this->migrated_versions);
		if ($is_up && $is_migrated)
		{
			return true;
		}

		// skip non-applied migrations on rollback
		if (!$is_up && !$is_migrated)
		{
			self::debug('Version never applied, skipping roll back on: ' . $migration->get_version());
			return true;
		}

		return false;
	}

	/**
	 * Determine if rolling back to the target version will rollback the database
	 * back an acceptable number of migrations.
	 */
	private function is_rollback_version_allowed()
	{
		$pending_rollback_count = 0;

		foreach ($this->migrations as $migration)
		{
			if ($this->is_completed(false, $migration))
			{
				// Target version reached
				break;
			}

			if ($this->should_skip(false, $migration))
			{
				// Migration has not been applied
				continue;
			}

			// Migration would be rolled back
			$pending_rollback_count++;
		}

		// Allow rollback to version proceed only if won't exceed allowed limit
		return $pending_rollback_count <= self::MAX_ROLLBACKS_ALLOWED;
	}

	/**
	 * Track migration occurred in db and local store
	 * @param Migrator_Migration_Proxy $migration
	 */
	private function track_version_up(Migrator_Migration_Proxy $migration)
	{
		self::info("# Tracking migrate up to {$migration}");

		// @note re-sort? Only if we need to re-display max version
		$this->migrated_versions[$migration->get_version()] = $migration->get_name();

		$this->db->execute('INSERT INTO ' . self::TBL_NAME
					. ' (version, name) VALUES (?, ?)',
			array($migration->get_version(), $migration->get_name()));
	}

	/**
	 * Track migration occurred in db and local store
	 * @param Migrator_Migration_Proxy $migration
	 */
	private function track_version_down(Migrator_Migration_Proxy $migration)
	{
		self::info("# Tracking rollback of {$migration}");

		// version is no longer applied, remove and reset array
		unset($this->migrated_versions[$migration->get_version()]);

		$this->db->execute('DELETE FROM ' . self::TBL_NAME .
				' WHERE version = ?', array($migration->get_version()));
	}
}

