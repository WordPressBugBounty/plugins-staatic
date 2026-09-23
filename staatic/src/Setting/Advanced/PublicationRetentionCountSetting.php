<?php

declare(strict_types=1);

namespace Staatic\WordPress\Setting\Advanced;

use Staatic\WordPress\Publication\PublicationCleanup;
use Staatic\WordPress\Setting\AbstractSetting;

final class PublicationRetentionCountSetting extends AbstractSetting
{
    public function name(): string
    {
        return 'staatic_publication_retention_count';
    }

    public function type(): string
    {
        return self::TYPE_INTEGER;
    }

    public function label(): string
    {
        return __('Publication Retention Count', 'staatic');
    }

    public function description(): ?string
    {
        return __('The number of most recent publications to keep, on top of the age-based cleanup. Each retained publication adds rows to the results table and its indexes, so a site with very large builds may want to lower this.', 'staatic');
    }

    public function defaultValue()
    {
        return PublicationCleanup::DEFAULT_RETENTION_COUNT;
    }

    public function sanitizeValue($value)
    {
        $count = filter_var($value, \FILTER_VALIDATE_INT);
        if ($count === \false || $count < PublicationCleanup::MINIMUM_RETENTION_COUNT) {
            add_settings_error('staatic-settings', 'invalid_publication_retention_count', sprintf(
                /* translators: %1$s: Entered value. %2$d: Minimum number of publications. */
                __('The specified Publication Retention Count "%1$s" is invalid. Enter a number of publications of %2$d or higher.', 'staatic'),
                esc_html((string) $value),
                PublicationCleanup::MINIMUM_RETENTION_COUNT
            ));

            return PublicationCleanup::DEFAULT_RETENTION_COUNT;
        }

        return $count;
    }

    protected function defaultAttributes(): array
    {
        return array_merge(parent::defaultAttributes(), [
            'min' => PublicationCleanup::MINIMUM_RETENTION_COUNT
        ]);
    }
}
