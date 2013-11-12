<?php
namespace Box\Echelon\Engine;

/**
 * Procudes Engine_Adapter instances
 * @Note factory will be used by @see \Box\Echelon\Databases, which will memoize instances
 */
interface Adapter_Factory
{
	/**
	 * @param string $name Name of the database handle as used by your app. E.g. "main"
	 * or "services" or "authentication"
	 * @return Engine_Adapter
	 */
	public function create_adapter_for($name);
}
