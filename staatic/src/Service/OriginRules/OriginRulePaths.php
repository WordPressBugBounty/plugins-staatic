<?php

declare(strict_types=1);

namespace Staatic\WordPress\Service\OriginRules;

/**
 * Resolves the origin rule path patterns that have to stay out of a publication.
 *
 * An origin rule forwards requests for a path pattern from the published site to an origin, so
 * whatever the crawler would publish under such a path is never served. On sites whose
 * distributions Staatic Cloud manages, the platform writes the raw path patterns for each
 * target into two options, each a JSON array of pattern strings. Their presence is what marks a
 * site as platform-managed: the platform writes both, empty or not, for every managed site.
 * Elsewhere the list comes from the `staatic_origin_rule_paths` filter (the Premium setting
 * hooks in there).
 */
final class OriginRulePaths
{
    public const OPTION_LIVE = 'staatic_origin_rule_paths_live';

    public const OPTION_PREVIEW = 'staatic_origin_rule_paths_preview';

    public function isPlatformManaged(): bool
    {
        return get_option(self::OPTION_LIVE, \false) !== \false || get_option(self::OPTION_PREVIEW, \false) !== \false;
    }

    /**
     * The patterns for a publication's target: preview (draft) publications use the preview
     * list, every other publication the live list.
     *
     * @return string[]
     */
    public function patterns(bool $isPreview): array
    {
        $patterns = $this->platformPatterns($isPreview ? self::OPTION_PREVIEW : self::OPTION_LIVE);
        /**
         * Filters the origin rule path patterns excluded from a publication.
         *
         * @since 1.13.2
         *
         * @param string[] $patterns  Path patterns with the platform's path-pattern semantics.
         * @param bool     $isPreview Whether the publication targets the preview site.
         */
        $patterns = apply_filters('staatic_origin_rule_paths', $patterns, $isPreview);

        return self::strings(is_array($patterns) ? $patterns : []);
    }

    /** @return string[] */
    private function platformPatterns(string $optionName): array
    {
        $value = get_option($optionName, \false);
        if (is_string($value)) {
            $value = json_decode($value, \true);
        }

        return is_array($value) ? self::strings($value) : [];
    }

    /**
     * @param mixed[] $values
     * @return string[]
     */
    private static function strings(array $values): array
    {
        return array_values(array_filter($values, function ($value): bool {
            return is_string($value) && $value !== '';
        }));
    }
}
