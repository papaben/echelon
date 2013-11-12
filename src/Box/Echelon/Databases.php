<?php
namespace Box\Echelon;
use Box\Echelon\Engine\Adapter_Factory;
use Box\Echelon\Engine\Engine_Adapter;

/**
 * A memoization class for database engine adapter resources
 */
class Databases
{
	/** @var Engine_Adapter[] */
	private static $cache = array();
	/** @var Adapter_Factory */
	private static $factory = null;

	// Intended for static use only
	private function __construct()
	{
	}

	/**
	 * @param string $databases_class_name Name of class in your code that can
	 * create database engine adapters
	 */
	public static function initialize_with_factory($adapter_factory)
	{
		self::$factory = $adapter_factory;
	}

	/**
	 * @param string $name Name of the database resource to get
	 * @return Engine_Adapter
	 */
	public static function get($name)
	{
		if (!array_key_exists($name, self::$cache))
		{
			if (!self::$factory)
			{
				throw new Echelon_Exception("Engine adapter factory not configured; don't know how to create new adapters!");
			}

			self::$cache[$name] = self::$factory->create_adapter_for($name);
		}

		return self::$cache[$name];
	}
}
