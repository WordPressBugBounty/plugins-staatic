<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module;

use Closure;
use Staatic\WordPress\Request\TestRequest;
use Staatic\WordPress\Service\Scheduler;

final class ScheduleTestRequest implements ModuleInterface
{
    /**
     * @var Scheduler
     */
    private $scheduler;

    /** @var string */
    public const HOOK = 'staatic_test_request';

    /** @var string */
    public const SCHEDULE = 'staatic_maintenance_cron_interval';

    /** @var int */
    public const INTERVAL = 43200;

    /**
     * @var TestRequest
     */
    public $testRequest;

    /**
     * @var Closure
     */
    private $clock;

    public function __construct(Scheduler $scheduler, ?Closure $clock = null)
    {
        $this->scheduler = $scheduler;
        $this->clock = $clock ?? static function () : int {
            return time();
        };
    }

    public function hooks(): void
    {
        $this->testRequest = new TestRequest();
        add_action(self::HOOK, [$this->testRequest, 'dispatch']);
        add_action('wp_loaded', [$this, 'setupSchedule']);
    }

    public function setupSchedule(): void
    {
        $isScheduled = $this->scheduler->isScheduled(self::HOOK);
        if (!TestRequest::isEnabled()) {
            if ($isScheduled) {
                $this->scheduler->clear(self::HOOK);
            }

            return;
        }
        if ($isScheduled || !$this->scheduler->scheduleExists(self::SCHEDULE)) {
            return;
        }
        if ($this->scheduler->schedule(self::HOOK, self::SCHEDULE, ($this->clock)() + self::INTERVAL)) {
            $this->testRequest->dispatch();
        }
    }
}
