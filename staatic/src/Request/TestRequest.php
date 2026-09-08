<?php

declare(strict_types=1);

namespace Staatic\WordPress\Request;

use Closure;
use Staatic\WordPress\Publication\BackgroundPublisher;
use Staatic\Vendor\WP_Async_Request;

final class TestRequest extends WP_Async_Request
{
    /** @var string */
    public const OPTION_NAME = 'staatic_test_request_status';

    /** @var string */
    public const LOCK_OPTION_NAME = 'staatic_test_request_lock';

    /** @var int */
    public const HEARTBEAT_INTERVAL = 30;

    /** @var int */
    public const HEARTBEAT_FRESHNESS = 60;

    /** @var int */
    public const RESULT_FRESHNESS = 129600;

    /** @var int */
    public const COMPLETION_COOLDOWN = 300;

    /** @var int */
    public const MAX_DIAGNOSTIC_RUNTIME = 300;

    /** @var int */
    public const LOCK_GRACE = 900;

    /** @var int */
    public const LOCK_FUTURE_SKEW = 300;

    /** @var string */
    public const STATE_DISABLED = 'disabled';

    /** @var string */
    public const STATE_NOT_RUN = 'not_run';

    /** @var string */
    public const STATE_RUNNING = 'running';

    /** @var string */
    public const STATE_COMPLETE = 'complete';

    /** @var string */
    public const STATE_INCOMPLETE = 'incomplete';

    /** @var string */
    public const STATE_STALE = 'stale';

    /** @var string */
    protected $prefix = 'staatic';

    /** @var string */
    protected $action = 'test_request';

    /**
     * @var Closure
     */
    private $wallClock;

    /**
     * @var Closure
     */
    private $sleeper;

    /**
     * @var Closure
     */
    private $monotonicClock;

    public function __construct(?Closure $clock = null, ?Closure $sleeper = null, ?Closure $monotonicClock = null)
    {
        $this->wallClock = $clock ?? static function () : int {
            return time();
        };
        $this->sleeper = $sleeper ?? static function (int $seconds): void {
            sleep($seconds);
        };
        $this->monotonicClock = $monotonicClock ?? static function (): float {
            return function_exists('hrtime') ? (float) hrtime(\true) / 1000000000 : microtime(\true);
        };
        parent::__construct();
    }

    public static function isEnabled(): bool
    {
        return (bool) apply_filters('staatic_test_request_enabled', \true);
    }

    public static function targetRuntime(): int
    {
        return self::runtime()['targetRuntime'];
    }

    public static function runtime(): array
    {
        $configuredRuntime = (int) apply_filters(
            'staatic_publication_task_timeout',
            (int) get_option('staatic_background_process_timeout')
        );
        $usesFallback = $configuredRuntime <= 0;
        $effectiveRuntime = $usesFallback ? BackgroundPublisher::DEFAULT_PROCESS_TIME_LIMIT : $configuredRuntime;

        return [
            'configuredRuntime' => $configuredRuntime,
            'effectiveRuntime' => $effectiveRuntime,
            'targetRuntime' => min($effectiveRuntime, self::MAX_DIAGNOSTIC_RUNTIME),
            'usesFallback' => $usesFallback,
            'capped' => $effectiveRuntime > self::MAX_DIAGNOSTIC_RUNTIME
        ];
    }

    /**
     * @param string $value
     */
    public static function parseStatus($value): ?array
    {
        // v2:<wall-start>:<wall-last>:<monotonic-elapsed>:<r|d>
        if (strncmp($value, 'v2:', strlen('v2:')) === 0) {
            $parts = explode(':', $value);
            if (count($parts) !== 5 || !ctype_digit($parts[1]) || (int) $parts[1] <= 0 || !ctype_digit(
                $parts[2]
            ) || (int) $parts[2] <= 0 || !ctype_digit(
                $parts[3]
            ) || !in_array(
                $parts[4],
                ['r', 'd'],
                \true
            )) {
                return null;
            }

            return [
                'version' => 2,
                'start' => (int) $parts[1],
                'last' => (int) $parts[2],
                'elapsed' => (int) $parts[3],
                'done' => $parts[4] === 'd'
            ];
        }
        $parts = explode('_', $value);
        if (count($parts) < 1 || count($parts) > 3 || !ctype_digit($parts[0]) || (int) $parts[0] <= 0) {
            return null;
        }
        $start = (int) $parts[0];
        $last = null;
        if (isset($parts[1])) {
            if (!ctype_digit($parts[1]) || (int) $parts[1] < $start) {
                return null;
            }
            $last = (int) $parts[1];
        }
        if (isset($parts[2]) && $parts[2] !== 'done') {
            return null;
        }

        return [
            'version' => 1,
            'start' => $start,
            'last' => $last,
            'elapsed' => ($last ?? $start) - $start,
            'done' => isset($parts[2]) && $parts[2] === 'done'
        ];
    }

    /**
     * @param string|null $value
     * @param int|null $now
     */
    public static function status($value = null, $now = null): array
    {
        $result = array_merge(self::runtime(), [
            'state' => self::STATE_NOT_RUN,
            'start' => null,
            'last' => null,
            'done' => \false,
            'duration' => null,
            'version' => null
        ]);
        if (!self::isEnabled()) {
            $result['state'] = self::STATE_DISABLED;

            return $result;
        }
        $parsed = self::parseStatus($value ?? (string) get_option(self::OPTION_NAME));
        if (!$parsed) {
            return $result;
        }
        $now = $now ?? time();
        $result = array_merge($result, $parsed, [
            'duration' => $parsed['elapsed']
        ]);
        if ($parsed['version'] === 1 && $parsed['start'] > $now || $parsed['last'] !== null && $parsed['last'] > $now) {
            $result['state'] = self::STATE_INCOMPLETE;

            return $result;
        }
        if ($parsed['done']) {
            if ($result['duration'] < $result['targetRuntime']) {
                $result['state'] = self::STATE_INCOMPLETE;
            } elseif ($now - $parsed['last'] > self::RESULT_FRESHNESS) {
                $result['state'] = self::STATE_STALE;
            } else {
                $result['state'] = self::STATE_COMPLETE;
            }

            return $result;
        }
        $heartbeat = $parsed['last'] ?? $parsed['start'];
        $result['state'] = $now - $heartbeat <= self::HEARTBEAT_FRESHNESS ? self::STATE_RUNNING : self::STATE_INCOMPLETE;

        return $result;
    }

    public function dispatch()
    {
        if (!self::isEnabled()) {
            return \false;
        }

        return parent::dispatch();
    }

    protected function handle()
    {
        $now = ($this->wallClock)();
        $rawStatus = get_option(self::OPTION_NAME, null);
        $status = self::status(is_scalar($rawStatus) ? (string) $rawStatus : '', $now);
        if ($status['state'] === self::STATE_DISABLED || $status['state'] === self::STATE_RUNNING || $status['done'] && $status['last'] !== null && $status['last'] <= $now && $now - $status['last'] <= self::COMPLETION_COOLDOWN) {
            return;
        }
        $start = $now;
        $lock = $this->acquireLock($start, $status['targetRuntime']);
        if ($lock === null) {
            return;
        }
        $monotonicStart = ($this->monotonicClock)();
        $statusWasAutoloaded = $this->statusIsAutoloaded();

        try {
            if (!$this->writeStatus($lock, $this->serializeStatus($start, $start, 0, \false), $statusWasAutoloaded)) {
                return;
            }
            $elapsed = 0;
            do {
                ($this->sleeper)(self::HEARTBEAT_INTERVAL);
                $measuredElapsed = (int) floor(($this->monotonicClock)() - $monotonicStart);
                $elapsed = max($elapsed, $measuredElapsed, 0);
                $now = ($this->wallClock)();
                if (!$this->writeStatus($lock, $this->serializeStatus($start, $now, $elapsed, \false))) {
                    return;
                }
            } while ($elapsed < $status['targetRuntime']);
            if (!$this->writeStatus($lock, $this->serializeStatus($start, $now, $elapsed, \true))) {
                return;
            }
        } finally {
            $this->deleteLock($lock);
        }
    }

    private function acquireLock(int $start, int $targetRuntime): ?string
    {
        $lock = "{$start}:{$targetRuntime}:" . bin2hex(random_bytes(16));
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if ($this->insertLock($lock)) {
                return $lock;
            }
            $existingLock = $this->selectLock();
            if ($existingLock === null) {
                continue;
            }
            if (!$this->isReclaimableLock($existingLock, $start) || !$this->deleteLock($existingLock)) {
                return null;
            }
        }

        return null;
    }

    private function insertLock(string $lock): bool
    {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", self::LOCK_OPTION_NAME, $lock)
        ) === 1;
    }

    private function selectLock(): ?string
    {
        global $wpdb;
        $value = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::LOCK_OPTION_NAME)
        );

        return is_string($value) ? $value : null;
    }

    private function deleteLock(string $lock): bool
    {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = %s", self::LOCK_OPTION_NAME, $lock)
        ) === 1;
    }

    private function isReclaimableLock(string $lock, int $now): bool
    {
        if (!preg_match('/^(\d+):(\d+):[a-f0-9]{32}$/', $lock, $matches)) {
            return \true;
        }
        $start = (int) $matches[1];
        $targetRuntime = (int) $matches[2];
        if ($start <= 0 || $targetRuntime <= 0) {
            return \true;
        }
        if ($start > $now) {
            return $start - $now > self::LOCK_FUTURE_SKEW;
        }

        return $now > $start + $targetRuntime + self::LOCK_GRACE;
    }

    private function statusIsAutoloaded(): bool
    {
        global $wpdb;
        $autoload = $wpdb->get_var(
            $wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::OPTION_NAME)
        );

        return is_string($autoload) && !in_array($autoload, ['no', 'off', 'auto-off'], \true);
    }

    private function serializeStatus(int $start, int $last, int $elapsed, bool $done): string
    {
        return sprintf('v2:%d:%d:%d:%s', $start, $last, $elapsed, $done ? 'd' : 'r');
    }

    private function writeStatus(string $lock, string $status, bool $invalidateAlloptions = \false): bool
    {
        global $wpdb;
        $result = $wpdb->query(
            $wpdb->prepare("INSERT INTO {$wpdb->options} (option_name, option_value, autoload)\n                SELECT %s, %s, 'no'\n                FROM {$wpdb->options} AS run_lock\n                WHERE run_lock.option_name = %s AND BINARY run_lock.option_value = %s\n                ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)", self::OPTION_NAME, $status, self::LOCK_OPTION_NAME, $lock)
        );
        if (!$result) {
            return \false;
        }
        $this->publishStatusToCache($status, $invalidateAlloptions);

        return \true;
    }

    private function publishStatusToCache(string $status, bool $invalidateAlloptions): void
    {
        if ($invalidateAlloptions) {
            wp_cache_delete('alloptions', 'options');
        }
        $notoptions = wp_cache_get('notoptions', 'options');
        if (is_array($notoptions) && array_key_exists(self::OPTION_NAME, $notoptions)) {
            unset($notoptions[self::OPTION_NAME]);
            wp_cache_set('notoptions', $notoptions, 'options');
        }
        wp_cache_set(self::OPTION_NAME, $status, 'options');
    }
}
