<?php
namespace Box\Echelon;
use Bart\Diesel;
use Bart\Log4PHP;
use Box\Echelon\Versions\Applied_Versions;
use Box\Echelon\Versions\Migrating_Forward;
use Box\Echelon\Versions\Rolling_Backward;

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

	/** @var \Box\Echelon\Db_Liaison */
	private $db;
	private $show_git_log;

	private $dump_schema = false;
	private $migrations = array();
	/** @var Applied_Versions The forward or backward auditor of the versions */
	private $applied_versions;

	/** @var string The target version used for roll backs */
	private $target_version;

	/** @var string Directory in which to find the schema migration files */
	protected $migrations_dir;

	/**
	 * @param Db_Liaison $db Wrap access to database schemas
	 */
	public function __construct(Db_Liaison $db, $show_git_log = false)
	{
		$this->logger = Log4PHP::getLogger(__CLASS__);

		/** @var \Box\Echelon\Echelon_Config $configs */
		$configs = Diesel::create('\Box\Echelon\Echelon_Configs');
		$this->migrations_dir = $configs->migrations_root_dir();

		$this->db = $db;
		$this->show_git_log = $show_git_log;

		$this->load_migrations_from_disk();

		$this->logger->debug("Configured $this");
	}

	public function __toString()
	{
		return "migrator for {$this->migrations_dir}";
	}

	/**
	 * Apply any unapplied forward migrations
	 */
	public function migrate_up()
	{
		$this->applied_versions = new Migrating_Forward($this->db);
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

		$this->applied_versions = new Rolling_Backward($this->db);
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
		$applied_versions = new Migrating_Forward($this->db);

		return $applied_versions->get_diff($this->migrations);
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

		// Do not clobber class scope of this variable
		$applied_versions = new Migrating_Forward($this->db);

		foreach ($this->migrations as $migration)
		{
			if ($this->should_skip(true, $migration))
			{
				$this->logger->debug("Skipping $migration");
				continue;
			}

			$this->logger->debug("Tracking $migration");
			$applied_versions->track_version_affected($migration);
		}
	}

	/**
	 * Load all files from the migrations directory and wrap them in proxies
	 */
	private function load_migrations_from_disk()
	{
		// find all php files in migrations sorted ASC
		foreach (scandir($this->migrations_dir) as $file_name)
		{
			$proxy = Migration_Proxy::create_or_not($file_name, $this->show_git_log);

			// file name was a valid format?
			if ($proxy != null)
			{
				$this->logger->debug("Loaded $proxy");
				$this->migrations[] = $proxy;
			}
		}

		// Be extra sure that the migrations are in order
		sort($this->migrations);
	}

	/**
	 * Run the migration. Either upgrade or rollback the database
	 * @param  $upgrade
	 */
	private function run($upgrade)
	{
		$direction = $upgrade ? 'up' : 'down';
		$migrate_method = "migrate_{$direction}";

		$this->logger->info("Beginning migration starting with {$this->applied_versions}");
		$this->logger->info('Found ' . count($this->migrations) . " migration(s) in {$this->migrations_dir}/");

		$this->logger->debug("Beginning to apply each applicable $direction migration in order");
		foreach ($this->migrations as $migration)
		{
			if ($this->should_skip($upgrade, $migration))
			{
				continue;
			}

			if ($this->is_completed($upgrade, $migration))
			{
				$this->logger->debug('Reached target version ' . $this->target_version);
				break;
			}

			try
			{
				// @NOTE no transaction support currently
				$this->logger->debug('Migrating: ' . $migration);
				$migration->$migrate_method($this->db);
			}
			catch (\Exception $e)
			{
				$this->logger->warn('Migration encountered problem. Prematurely shutting down.', $e);
				throw $e;
			}

			// persist version to db
			$this->applied_versions->track_version_affected($migration);
			// If we've applied at least one migration, then the schema will have changed
			$this->dump_schema = true;

			$this->logger->debug("Migrated $direction: version $migration");
		}

		$this->logger->debug('Migration complete');
	}

	/**
	 * @param  $is_up
	 * @param Migration_Proxy $migration
	 * @return bool Is the rollback complete?
	 */
	private function is_completed($is_up, Migration_Proxy $migration)
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
	 * @param Migration_Proxy $migration
	 * @return bool if this migration should be skipped
	 */
	private function should_skip($is_up, Migration_Proxy $migration)
	{
		// skip applied migrations on upgrade
		$is_migrated = $this->applied_versions->is_migrated($migration);
		if ($is_up && $is_migrated)
		{
			return true;
		}

		// skip non-applied migrations on rollback
		if (!$is_up && !$is_migrated)
		{
			$this->logger->debug('Version never applied, skipping roll back on: ' . $migration->get_version());
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
}

