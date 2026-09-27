<?php

declare(strict_types=1);

namespace Staatic\WordPress\Bridge;

use Staatic\Vendor\Psr\Http\Message\RequestInterface;
use Staatic\Vendor\Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Retry decision for GuzzleRetryMiddleware's `should_retry_callback` option.
 *
 * Once that option is set the middleware no longer applies `retry_on_status` itself, so this
 * class does: a response is retried when its status is in that list. In addition a WordPress
 * maintenance-mode response (see wp_maintenance() in wp-includes/load.php) is always retried,
 * because a plugin or theme update holds maintenance mode for seconds and the next attempt is
 * likely to succeed.
 */
final class MaintenanceAwareRetryDecider
{
    /** @var int */
    public const MAINTENANCE_STATUS = 503;

    /** @var string */
    public const MAINTENANCE_RETRY_AFTER = '600';

    /** @var string */
    public const MAINTENANCE_MESSAGE = 'briefly unavailable for scheduled maintenance';

    /** @var string Body marker of the default wp_die() template, independent of the site language. */
    public const WP_DIE_MARKER = 'id="error-page"';

    /** @var int The wp_die() template puts its message after roughly 3 KB of inline styles. */
    public const MAX_SNIFF_BYTES = 16384;

    /**
     * @param array<string,mixed> $options
     */
    public function __invoke(
        array $options,
        ?ResponseInterface $response = null,
        ?RequestInterface $request = null
    ): bool
    {
        if ($response === null) {
            return \false;
        }
        if (self::isMaintenanceResponse($response)) {
            return \true;
        }
        $statuses = array_map('intval', (array) ($options['retry_on_status'] ?? []));

        return in_array($response->getStatusCode(), $statuses, \true);
    }

    /**
     * A 503 whose body carries WordPress' (English) maintenance message, or - for sites running
     * in another language - a 503 with WordPress' fixed `Retry-After: 600` whose body is the
     * default wp_die() page. Only the first MAX_SNIFF_BYTES of the body are read, and the body
     * is left at the position it was found at.
     */
    public static function isMaintenanceResponse(ResponseInterface $response): bool
    {
        if ($response->getStatusCode() !== self::MAINTENANCE_STATUS) {
            return \false;
        }
        $head = strtolower(self::readBodyHead($response));
        if ($head === '') {
            return \false;
        }
        if (strpos($head, self::MAINTENANCE_MESSAGE) !== \false) {
            return \true;
        }

        return trim($response->getHeaderLine('Retry-After')) === self::MAINTENANCE_RETRY_AFTER && strpos(
            $head,
            self::WP_DIE_MARKER
        ) !== \false;
    }

    /**
     * Reads the start of the body without disturbing where later consumers find it. Any stream
     * failure, on the read or on restoring the position, yields '' so that a response the
     * crawler could still use is never turned into a failure by this inspection.
     */
    private static function readBodyHead(ResponseInterface $response): string
    {
        try {
            $body = $response->getBody();
            if (!$body->isSeekable() || !$body->isReadable()) {
                return '';
            }
            $position = $body->tell();
            $head = '';

            try {
                $body->rewind();
                while (!$body->eof() && strlen($head) < self::MAX_SNIFF_BYTES) {
                    $chunk = $body->read(self::MAX_SNIFF_BYTES - strlen($head));
                    if ($chunk === '') {
                        break;
                    }
                    $head .= $chunk;
                }
            } finally {
                $body->seek($position);
            }

            return $head;
        } catch (Throwable $e) {
            return '';
        }
    }
}
