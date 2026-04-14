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
        $this->addCapabilityToRole('administrator', 'staatic_publish_subset');
        $this->addCapabilityToRole('administrator', 'staatic_publish');
        $this->addCapabilityToRole('editor', 'staatic_publish');
    }

    /**
     * @param wpdb $wpdb
     */
    public function down($wpdb): void
    {
        $this->removeCapabilityFromRole('editor', 'staatic_publish');
        $this->removeCapabilityFromRole('administrator', 'staatic_publish');
        $this->removeCapabilityFromRole('administrator', 'staatic_publish_subset');
    }
};
