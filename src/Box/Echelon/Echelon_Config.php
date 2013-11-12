<?php
namespace Box\Echelon;
use Bart\Configuration\Configuration;

/**
 * Configurations for the Echelon module
 */
class Echelon_Config extends Configuration
{
	/**
	 * @return string Sample of how configuration is intended to be defined
	 */
	public function README()
	{
		return <<<README
[default]
; Required section

; Required; the directory to find the migration files
schema_migration_root_dir = /var/code/app-name/db/migrations

; Required; This defines the database handle name passed by the Migrator to
; the Adapter_Factory.
; This database will, at the least, store all of the schema_migration history records
database_name = app

README;
	}

	/**
	 * @return string The directory to find the migration files
	 */
	public function migrations_root_dir()
	{
		return $this->getValue('default', 'schema_migration_root_dir');
	}

	/**
	 * @return string The name of the database used to track the schema migrations
	 */
	public function default_database_name()
	{
		return $this->getValue('default', 'database_name');
	}
}
