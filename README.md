Echelon
--------

A straightforward database schema management toolkit.

It can be used as standalone system for any application stack or integrated into a PHP application.

Overview
--------

Database schema management is a critical part of the continuous application deployment life cycle. Echelon's purpose is to make the process as straightforward as possible so that developers can focus on the schema migrations themselves and not worry about the process.

Database schema migrations, henceforth referred to as "migrations", are discrete changes to the schema (DDL) of a database. Migrations have a forward and a backward application. As a best practice, forward migrations should be backwards compatible.

Instructions
------------

System Dependencies
------------
------------
`Logger` log4php provided by Pear
`Composer` Use the (Composer)[http://getcomposer.org/] Tool to install all composable dependencies

__TO DO__
* Configurations
 * Schema migrations home
 * Database connection information
 * Shard information

* Generating migrations

* Migrating forward
* Migrating backwards

Getting Started
---------------

```php
<?php

$app_root = __DIR__;
require_once "{$app_root}/vendor/box/bart/src/bart-common.php";
\Bart\Autoloader::register_autoload_path("{$app_root}/vendor/box/bart/src");
\Bart\Autoloader::register_autoload_path("{$app_root}/src");

\Box\Echelon\Echelon_Config::initialize("path to configurations directory");

require_once 'log4php/Logger.php';
\Bart\Log4PHP::initForConsole('debug');

$adaptor_factory = new My_Adapter_Factory();
\Box\Echelon\Databases::initialize_with_factory($adaptor_factory);

// Print the migrations; do not apply them to any of the schemas
$liaison = new \Box\Echelon\Db_Liaison\Echo_Only();

$migrator = new \Box\Echelon\Migrator();
$migrator->migrate_up($liaison);

/**
 * You'll need to implement this class for your app
 */
class My_Adapter_Factory implements \Box\Echelon\Engine\Adapter_Factory
{
	/**
	 * @param string $name Name of the database handle as used by your app. E.g. "main"
	 * or "services" or "authentication"
	 * @return \Box\Echelon\Engine\Engine_Adapter
	 */
	public function create_adapter_for($name)
	{
	    // If you have multiple databases, you can switch on the $name here
		return new \Box\Echelon\Engine\Mysql_Adapter('host', 'user', 'password');
	}
}

```

Key Classes
-----------

`Echelon_Config` provides access to the configurations for the app




