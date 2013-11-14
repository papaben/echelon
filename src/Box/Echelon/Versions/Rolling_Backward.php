<?php
namespace Box\Echelon\Versions;
use Box\Echelon\Migration_Proxy;

/**
 * Please provide a concise description.
 */
class Rolling_Backward extends Applied_Versions
{
	/**
	 * @param Migration_Proxy $proxy The migration to be added or removed
	 */
	public function track_version_affected(Migration_Proxy $migration)
	{
		$this->logger->trace("Tracking rollback of {$migration}");

		// version is no longer applied, remove and reset array
		unset($this->migrated_versions[$migration->get_version()]);

		$sql = sprintf('DELETE FROM ' . self::TBL_NAME . " WHERE version = '%s'",
			$migration->get_version());

		$this->db->execute($this->default_db_name, $sql);
	}
}