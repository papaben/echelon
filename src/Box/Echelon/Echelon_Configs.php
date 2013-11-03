<?php
namespace Box\Echelon;
use Bart\Configuration\Configuration;

/**
 * Configurations for the Echelon module
 */
class Echelon_Configs extends Configuration
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

; Required; This defines the \Box\Echelon\Database_Type class name to be used by the Migrator
; This database will, at the least, store all of the schema_migration history records
provided_by = db_type_1

[db_type_1]
; This section is defines the configurations for the class Db_Type_1
; There should be one section like this for each Database schema you'll need to connect to
; Expects all the same fields as master
server =
user =
password =
schema_name =
; Defaults to 3306
port = 3306

README;
	}

	/**
	 * @return string The directory to find the migration files
	 */
	public function migrations_root_dir()
	{
		return $this->getValue('default', 'schema_migration_root_dir');
	}
}
