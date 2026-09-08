<?php

declare(strict_types=1);

namespace Staatic\Vendor;

use RuntimeException;
use wpdb;
use Staatic\WordPress\Migrations\AbstractMigration;

return new class extends AbstractMigration {
    /** @var string */
    private const INDEX_NAME = 'priority';

    /**
     * @param wpdb $wpdb
     */
    public function up($wpdb): void
    {
        $tableName = "{$wpdb->prefix}staatic_crawl_queue";
        if ($this->indexExists($wpdb, $tableName)) {
            return;
        }
        // Every dequeue reads `ORDER BY priority DESC, id ASC LIMIT 1`, which scans the whole
        // queue and sorts it to hand back one row. The index has to descend on priority to be
        // usable: the two columns are ordered in opposite directions, so an all-ascending
        // (priority, id) index cannot be walked in the requested order and the server keeps
        // filesorting. Servers older than MySQL 8.0 / MariaDB 10.8 parse the DESC keyword and
        // store an ascending index instead, which leaves them exactly where they are today —
        // no gain, but no cost either. The online variant keeps the queue writable while the
        // index is built, but is not supported everywhere, so a plain ALTER is tried before
        // giving up.
        $statements = [
            "ALTER TABLE {$tableName}\n                ADD INDEX " . self::INDEX_NAME . " (priority DESC, id),\n                ALGORITHM=INPLACE, LOCK=NONE",
            "ALTER TABLE {$tableName} ADD INDEX " . self::INDEX_NAME . " (priority DESC, id)"
        ];
        foreach ($statements as $statement) {
            if ($wpdb->query($statement) !== \false) {
                return;
            }
        }

        // Recording the upgrade as complete would leave the install on the full-table-scan
        // path with no way to retry, so a failure here must be reported.
        throw new RuntimeException(
            "Unable to add the {$tableName} " . self::INDEX_NAME . " index: {$wpdb->last_error}"
        );
    }

    /**
     * @param wpdb $wpdb
     */
    public function down($wpdb): void
    {
        $tableName = "{$wpdb->prefix}staatic_crawl_queue";
        if (!$this->indexExists($wpdb, $tableName)) {
            return;
        }
        // Through $this->query() so a rejected DROP throws instead of being swallowed, which
        // would stamp the lower version while the index is still in place.
        $this->query($wpdb, "ALTER TABLE {$tableName} DROP INDEX " . self::INDEX_NAME);
    }

    private function indexExists(wpdb $wpdb, string $tableName): bool
    {
        $rows = $wpdb->get_results(
            $wpdb->prepare("SHOW INDEX FROM {$tableName} WHERE Key_name = %s", self::INDEX_NAME)
        );

        return \is_array($rows) && $rows !== [];
    }
};
