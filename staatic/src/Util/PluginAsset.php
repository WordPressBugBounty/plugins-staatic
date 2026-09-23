<?php

declare(strict_types=1);

namespace Staatic\WordPress\Util;

/**
 * Guards reads of files that ship inside the plugin ZIP (assets/, *.asset.php build
 * manifests). A packaging error that drops one of them must degrade whatever feature
 * reads it, not fatal every request that touches it — a missing assets/ directory in a
 * customer ZIP once made file_get_contents() return false into base64_encode(), which
 * throws a TypeError and takes down all of wp-admin.
 */
final class PluginAsset
{
    public static function read(string $path): ?string
    {
        if (!is_readable($path)) {
            return null;
        }
        $contents = file_get_contents($path);

        return $contents !== \false && $contents !== '' ? $contents : null;
    }

    /** @return array{dependencies: array<int, string>, version: string} */
    public static function manifest(string $path, string $fallbackVersion): array
    {
        $fallback = [
            'dependencies' => [],
            'version' => $fallbackVersion
        ];
        if (!is_readable($path)) {
            return $fallback;
        }
        $manifest = include $path;
        if (!is_array($manifest) || !isset($manifest['dependencies'], $manifest['version'])) {
            return $fallback;
        }

        return $manifest;
    }
}
