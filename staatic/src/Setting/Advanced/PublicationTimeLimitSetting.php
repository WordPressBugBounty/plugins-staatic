<?php

declare(strict_types=1);

namespace Staatic\WordPress\Setting\Advanced;

use Staatic\WordPress\Publication\Publication;
use Staatic\WordPress\Setting\AbstractSetting;

final class PublicationTimeLimitSetting extends AbstractSetting
{
    /** @var int */
    public const MINIMUM_HOURS = Publication::MINIMUM_TIME_LIMIT_IN_HOURS;

    /** @var int */
    public const MAXIMUM_HOURS = Publication::MAXIMUM_TIME_LIMIT_IN_HOURS;

    public function name(): string
    {
        return 'staatic_publication_time_limit';
    }

    public function type(): string
    {
        return self::TYPE_INTEGER;
    }

    public function label(): string
    {
        return __('Publication Time Limit', 'staatic');
    }

    public function description(): ?string
    {
        return __('The maximum number of hours a publication may take before it is canceled and marked as failed.<br>Raise this only for large sites where a complete publication genuinely needs more time; a higher value also delays detection of a stuck publication. This is checked when each publication task starts, so a new value takes effect at the next task rather than immediately; a publication running under the <code>staatic_publication_timeout</code> filter is not affected at all.', 'staatic');
    }

    public function defaultValue()
    {
        return Publication::TIME_LIMIT_IN_HOURS;
    }

    public function sanitizeValue($value)
    {
        $hours = filter_var($value, \FILTER_VALIDATE_INT);
        if ($hours === \false || $hours < self::MINIMUM_HOURS || $hours > self::MAXIMUM_HOURS) {
            add_settings_error('staatic-settings', 'invalid_publication_time_limit', sprintf(
                /* translators: %1$s: Entered value. %2$d: Minimum number of hours. %3$d: Maximum number of hours. */
                __('The specified Publication Time Limit "%1$s" is out of range. Enter a number of hours between %2$d and %3$d.', 'staatic'),
                esc_html((string) $value),
                self::MINIMUM_HOURS,
                self::MAXIMUM_HOURS
            ));
            if ($hours === \false) {
                // An empty field and a typo both land here, and neither says anything about how
                // long a publication is allowed to take. Falling back to the minimum would cancel
                // every publication after one hour — strictly worse than the site had before the
                // setting was touched — so the built-in default is restored instead.
                return Publication::TIME_LIMIT_IN_HOURS;
            }

            return min(max($hours, self::MINIMUM_HOURS), self::MAXIMUM_HOURS);
        }

        return $hours;
    }

    protected function defaultAttributes(): array
    {
        return array_merge(parent::defaultAttributes(), [
            'min' => self::MINIMUM_HOURS,
            'max' => self::MAXIMUM_HOURS
        ]);
    }
}
