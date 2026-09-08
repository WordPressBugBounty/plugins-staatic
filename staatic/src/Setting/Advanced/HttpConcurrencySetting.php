<?php

declare(strict_types=1);

namespace Staatic\WordPress\Setting\Advanced;

use Staatic\WordPress\Setting\AbstractSetting;

final class HttpConcurrencySetting extends AbstractSetting
{
    /** @var int */
    public const MINIMUM_CONCURRENCY = 1;

    public function name(): string
    {
        return 'staatic_http_concurrency';
    }

    public function type(): string
    {
        return self::TYPE_INTEGER;
    }

    public function label(): string
    {
        return __('HTTP Concurrency', 'staatic');
    }

    public function description(): ?string
    {
        return __('The number of simultaneous HTTP connections allowed.', 'staatic');
    }

    public function defaultValue()
    {
        return 4;
    }

    public function sanitizeValue($value)
    {
        $concurrency = filter_var($value, \FILTER_VALIDATE_INT);
        if ($concurrency === \false || $concurrency < self::MINIMUM_CONCURRENCY) {
            add_settings_error('staatic-settings', 'invalid_http_concurrency', sprintf(
                /* translators: %1$s: Entered value. %2$d: Minimum number of connections. */
                __('The specified HTTP Concurrency "%1$s" is invalid. Enter a number of simultaneous connections of %2$d or higher.', 'staatic'),
                esc_html((string) $value),
                self::MINIMUM_CONCURRENCY
            ));

            return self::MINIMUM_CONCURRENCY;
        }

        return $concurrency;
    }

    protected function defaultAttributes(): array
    {
        return array_merge(parent::defaultAttributes(), [
            'min' => self::MINIMUM_CONCURRENCY
        ]);
    }
}
