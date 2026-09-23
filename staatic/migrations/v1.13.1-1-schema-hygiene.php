<?php

declare(strict_types=1);

namespace Staatic\Vendor;

use wpdb;
use Staatic\WordPress\Migrations\AbstractMigration;

return new class extends AbstractMigration {
    /**
     * @param wpdb $wpdb
     */
    public function up($wpdb): void
    {
        $this->fixResultsDeploymentPrimaryKeyOrder($wpdb);
        $this->addLogDateIndex($wpdb);
    }

    /**
     * Forward-only: down() drops only the log_date index this migration added and never touches
     * the primary key. The v1.4.4-beta4 migration owns reverting the primary key order (its own
     * down() unconditionally restores (result_uuid, deployment_uuid)); duplicating that here would
     * make two migrations responsible for undoing the same schema change.
     * @param wpdb $wpdb
     */
    public function down($wpdb): void
    {
        $this->dropLogDateIndex($wpdb);
    }

    /**
     * A fresh install used to create this table with the primary key in (result_uuid,
     * deployment_uuid) order - every query filters on deployment_uuid first
     * (ResultRepository.php:216, 249, 293) - while the v1.4.4-beta4 migration already flipped it
     * to (deployment_uuid, result_uuid) for sites that upgraded through it. Fresh installs skip
     * migrations entirely (Migrator::migrate() treats installedVersion 0.0.0 as already current),
     * so every site installed since beta4 and before this fix has the wrong order regardless of
     * how it was installed. This runs for both: it is a no-op on a site the beta4 migration
     * already fixed, and it corrects a fresh install that setup.php now creates correctly, so it
     * only ever has real work to do on an install that predates both fixes.
     *
     * Forward-only, by design: there is no matching revert here. Rolling this migration back
     * leaves whatever primary key order was already in place - see down() above.
     */
    private function fixResultsDeploymentPrimaryKeyOrder(wpdb $wpdb): void
    {
        $tableName = "{$wpdb->prefix}staatic_results_deployment";
        if ($this->primaryKeyStartsWithDeploymentUuid($wpdb, $tableName)) {
            return;
        }
        $this->query($wpdb, "ALTER TABLE {$tableName} DROP PRIMARY KEY");
        $this->query($wpdb, "ALTER TABLE {$tableName} ADD PRIMARY KEY (deployment_uuid, result_uuid)");
    }

    private function primaryKeyStartsWithDeploymentUuid(wpdb $wpdb, string $tableName): bool
    {
        $firstColumn = $wpdb->get_var(
            $wpdb->prepare("SELECT COLUMN_NAME FROM information_schema.STATISTICS\n                WHERE TABLE_SCHEMA = DATABASE()\n                    AND TABLE_NAME = %s\n                    AND INDEX_NAME = 'PRIMARY'\n                    AND SEQ_IN_INDEX = 1", $tableName)
        );

        return $firstColumn === 'deployment_uuid';
    }

    /**
     * DatabaseLogger inserts one row per log line and LogEntryCleanup deletes everything older
     * than a cutoff date (LogEntryRepository::deleteOlderThan(), `WHERE log_date < ...`), which
     * had no index to use and fell back to a full table scan. Only bites when an operator raises
     * the logging level above the default (minimal drops per-url INFO/DEBUG lines), but cleanup
     * itself always runs, so a table left over from a troubleshooting session pays for it forever
     * afterwards.
     */
    private function addLogDateIndex(wpdb $wpdb): void
    {
        $tableName = "{$wpdb->prefix}staatic_log_entries";
        if ($this->indexExists($wpdb, $tableName, 'log_date')) {
            return;
        }
        $this->query($wpdb, "ALTER TABLE {$tableName} ADD INDEX log_date (log_date)");
    }

    private function dropLogDateIndex(wpdb $wpdb): void
    {
        $tableName = "{$wpdb->prefix}staatic_log_entries";
        if (!$this->indexExists($wpdb, $tableName, 'log_date')) {
            return;
        }
        $this->query($wpdb, "ALTER TABLE {$tableName} DROP INDEX log_date");
    }

    private function indexExists(wpdb $wpdb, string $tableName, string $indexName): bool
    {
        $rows = $wpdb->get_results($wpdb->prepare("SHOW INDEX FROM {$tableName} WHERE Key_name = %s", $indexName));

        return \is_array($rows) && $rows !== [];
    }
};
