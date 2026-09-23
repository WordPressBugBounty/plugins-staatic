<?php

declare(strict_types=1);

namespace Staatic\WordPress\Publication;

/**
 * The timeout policy shared by BackgroundPublisher's own process time limit and
 * StaticGeneratorFactory's wp-admin batch deadline: both need "how long is one task/batch allowed
 * to run" derived from the same staatic_background_process_timeout-derived value, and both must
 * treat "not configured" (0) the same way. Extracted so that derivation exists in exactly one
 * place instead of the two call sites separately reimplementing
 * `timeout === 0 ? DEFAULT : timeout / 3`.
 */
final class ProcessTimeLimit
{
    /**
     * Used when no timeout is configured (staatic_background_process_timeout is 0, whether an
     * admin cleared the setting or it was never set).
     *
     * @var int
     */
    public const DEFAULT_PROCESS_TIME_LIMIT = 300;

    /**
     * BackgroundPublisher reserves the other two thirds of the request's time budget as headroom
     * before PHP's own set_time_limit($timeout) kills the request, so a task/batch only ever gets
     * a third of the configured timeout to run in - or DEFAULT_PROCESS_TIME_LIMIT, when no
     * timeout is configured at all.
     */
    public static function fromTimeout(int $timeout): int
    {
        return $timeout === 0 ? self::DEFAULT_PROCESS_TIME_LIMIT : (int) ($timeout / 3);
    }
}
