<?php

declare(strict_types=1);

namespace Staatic\Vendor;

use RuntimeException;
use wpdb;
use Staatic\WordPress\Migrations\AbstractMigration;

return new class extends AbstractMigration {
    /** @var string */
    private const INDEX_NAME = 'build_url';

    /**
     * @param wpdb $wpdb
     */
    public function up($wpdb): void
    {
        $tableName = "{$wpdb->prefix}staatic_results";
        if ($this->indexExists($wpdb, $tableName)) {
            return;
        }
        // Looking up a single result by build and URL scans the entire table without this
        // index. The online variant keeps the table writable while it is built, but is not
        // supported everywhere, so a plain ALTER is tried before giving up. The prefix is
        // 187 because 16 bytes of build_uuid plus 187 * 4 bytes of utf8mb4 stays under the
        // 767 byte index limit of older InnoDB row formats.
        $statements = [
            "ALTER TABLE {$tableName}\n                ADD INDEX " . self::INDEX_NAME . " (build_uuid, url(187)),\n                ALGORITHM=INPLACE, LOCK=NONE",
            "ALTER TABLE {$tableName} ADD INDEX " . self::INDEX_NAME . " (build_uuid, url(187))"
        ];
        foreach ($statements as $statement) {
            if ($wpdb->query($statement) !== \false) {
                return;
            }
        }

        // Without the index the register phase degrades to a full table scan per file, so a
        // failure here must be reported rather than recorded as a completed upgrade.
        throw new RuntimeException(
            "Unable to add the {$tableName} " . self::INDEX_NAME . " index: {$wpdb->last_error}"
        );
    }

    /**
     * @param wpdb $wpdb
     */
    public function down($wpdb): void
    {
        $tableName = "{$wpdb->prefix}staatic_results";
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
