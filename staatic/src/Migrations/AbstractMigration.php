<?php

declare(strict_types=1);

namespace Staatic\WordPress\Migrations;

use RuntimeException;
use WP_Role;
use wpdb;

abstract class AbstractMigration implements MigrationInterface
{
    /**
     * @param wpdb $wpdb
     * @param string $query
     */
    protected function query($wpdb, $query)
    {
        $result = $wpdb->query($query);
        if ($result === \false) {
            throw new RuntimeException("Unable to execute query: '{$query}': {$wpdb->last_error}");
        }

        return $result;
    }

    /**
     * @param string $oldName
     * @param string $newName
     */
    protected function renameOption($oldName, $newName): void
    {
        $value = get_option($oldName);
        if ($value === \false) {
            return;
        }
        update_option($newName, $value);
        delete_option($oldName);
    }

    /**
     * @param string $roleName
     * @param string $capability
     */
    protected function addCapabilityToRole($roleName, $capability): void
    {
        $role = $this->role($roleName);
        if (!$role) {
            return;
        }
        $role->add_cap($capability, \true);
    }

    /**
     * @param string $roleName
     * @param string $capability
     */
    protected function removeCapabilityFromRole($roleName, $capability): void
    {
        $role = $this->role($roleName);
        if (!$role) {
            return;
        }
        $role->remove_cap($capability);
    }

    private function role(string $roleName): ?WP_Role
    {
        $role = get_role($roleName);
        if (!$role instanceof WP_Role) {
            return null;
        }

        return $role;
    }
}
