<?php

declare(strict_types=1);

namespace Staatic\WordPress\Service;

use DateTimeImmutable;
use DateTimeInterface;

final class Formatter
{
    public function identifier(string $id): string
    {
        return substr($id, strrpos($id, '-') + 1);
    }

    public function bytes(?int $bytes, $decimals = 0): string
    {
        if ($bytes === null) {
            return '-';
        }
        if ($bytes < 1024) {
            return "{$bytes} bytes";
        }
        $result = size_format($bytes, $decimals);
        $result = str_replace('&nbsp;', ' ', $result);

        return $result;
    }

    public function number($number, int $decimals = 0): string
    {
        if ($number === null) {
            return '-';
        }
        $result = number_format_i18n($number, $decimals);
        $result = str_replace('&nbsp;', ' ', $result);

        return $result;
    }

    /**
     * A duration in seconds, as a localized number with its unit.
     *
     * Single-sourced here because three surfaces render the same publication-worker durations —
     * the Site Health test, its debug rows and the manual dispatch page — and they had drifted
     * into two spellings: number_format_i18n() leaves a non-breaking space in place, which shows
     * up as a literal &nbsp; wherever the value is not rendered as HTML.
     */
    public function seconds(int $seconds): string
    {
        return sprintf(
            /* translators: %s: Number of seconds. */
            _n('%s second', '%s seconds', $seconds, 'staatic'),
            $this->number($seconds)
        );
    }

    /**
     * A boolean as a word, for the copy-and-paste Site Health report, where a raw false renders
     * as an empty string and reads as a missing value rather than as "no".
     */
    public function yesNo($value): string
    {
        return $value ? __('Yes', 'staatic') : __('No', 'staatic');
    }

    public function date(?DateTimeInterface $date): string
    {
        if ($date === null) {
            return '-';
        }
        $localizedDate = $this->localizeDate($date);

        return sprintf(__('%1$s at %2$s'), $localizedDate->format(__('Y/m/d')), $localizedDate->format(__('g:i a')));
    }

    public function shortDate(?DateTimeInterface $date): string
    {
        if ($date === null) {
            return '-';
        }
        $timestamp = $date->getTimestamp();
        $difference = (new DateTimeImmutable())->getTimestamp() - $timestamp;
        if ($difference === 0) {
            return __('now', 'staatic');
        } elseif ($difference > 0 && $difference < \DAY_IN_SECONDS) {
            return sprintf(__('%s ago'), human_time_diff($timestamp));
        } else {
            $localizedDate = $this->localizeDate($date);

            return $localizedDate->format(__('Y/m/d'));
        }
    }

    public function difference(?DateTimeInterface $dateFrom, ?DateTimeInterface $dateTo): string
    {
        if ($dateFrom === null || $dateTo === null) {
            return '-';
        }

        return human_time_diff($dateFrom->getTimestamp(), $dateTo->getTimestamp());
    }

    private function localizeDate(DateTimeInterface $date): DateTimeInterface
    {
        return Polyfill::dateTimeFromInterface($date)->setTimezone(Polyfill::wp_timezone());
    }

    public function logMessage(string $message): string
    {
        return wp_kses(preg_replace_callback('~([\'\s])(https?://[^\'\s]+)([\'\s]|$)~', function ($match) {
            return $match[1] . sprintf('<a href="%1$s" target="_blank">%1$s</a>', esc_url($match[2])) . $match[3];
        }, $message), [
            'a' => [
                'href' => \true,
                'target' => \true
            ]
        ]);
    }
}
