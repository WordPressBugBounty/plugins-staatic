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
        $this->addCapabilityToRole('administrator', 'staatic_manage_settings');
    }

    /**
     * @param wpdb $wpdb
     */
    public function down($wpdb): void
    {
        $this->removeCapabilityFromRole('administrator', 'staatic_manage_settings');
    }
};
