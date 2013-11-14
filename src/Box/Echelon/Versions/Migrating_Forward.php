<?php
namespace Box\Echelon\Versions;
use Box\Echelon\Migration_Proxy;

/**
 * Versions management for migrating forward
 */
class Migrating_Forward extends Applied_Versions
{
	/**
	 * @param Migration_Proxy $proxy The migration to be added or removed
	 */
	public function track_version_affected(Migration_Proxy $migration)
	{
		$this->logger->trace("Tracking migrate up to {$migration}");

		// @note re-sort? Only if we need to re-display max version
		$this->migrated_versions[$migration->get_version()] = $migration->get_name();

		$sql = sprintf('INSERT INTO ' . self::TBL_NAME . " (version, name) VALUES ('%s', '%s')",
			$migration->get_version(), $migration->get_name());

		$this->db->execute($this->default_db_name, $sql);
	}
}