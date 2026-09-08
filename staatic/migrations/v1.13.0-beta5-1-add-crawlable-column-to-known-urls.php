<?php

declare(strict_types=1);

namespace Staatic\Vendor;

use wpdb;
use Staatic\WordPress\Migrations\AbstractMigration;

return new class extends AbstractMigration {
    /** @var string */
    private const COLUMN_NAME = 'crawlable';

    /**
     * @param wpdb $wpdb
     */
    public function up($wpdb): void
    {
        $tableName = "{$wpdb->prefix}staatic_known_urls";
        if ($this->columnExists($wpdb, $tableName)) {
            return;
        }
        // The container counts its rows to report how much crawling is left. Uploads Sync marks
        // media registered from disk as known so the crawler skips it, which used to inflate that
        // count by the whole uploads directory. The column separates the two, and defaulting to 1
        // keeps every row already in the table counted exactly as it is today.
        //
        // The table holds the current publication's state, not durable history, and the crawler
        // truncates it when it initializes. So the default is right for every row a settled
        // install holds. The one case it cannot tell apart is an upgrade that lands midway
        // through a publication that had already registered uploads: those rows stay counted for
        // the rest of that run, and the next publication starts from an empty table.
        $this->query(
            $wpdb,
            "\n            ALTER TABLE {$tableName}\n            ADD " . self::COLUMN_NAME . " tinyint(1) NOT NULL DEFAULT 1 AFTER hash\n        "
        );
    }

    /**
     * @param wpdb $wpdb
     */
    public function down($wpdb): void
    {
        $tableName = "{$wpdb->prefix}staatic_known_urls";
        if (!$this->columnExists($wpdb, $tableName)) {
            return;
        }
        $this->query($wpdb, "ALTER TABLE {$tableName} DROP " . self::COLUMN_NAME);
    }

    private function columnExists(wpdb $wpdb, string $tableName): bool
    {
        $rows = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$tableName} LIKE %s", self::COLUMN_NAME));

        return \is_array($rows) && $rows !== [];
    }
};
