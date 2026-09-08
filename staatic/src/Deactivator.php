<?php

declare(strict_types=1);

namespace Staatic\WordPress;

use Staatic\WordPress\Module\Cleanup;
use Staatic\WordPress\Module\ScheduleTestRequest;
use Staatic\WordPress\Service\Scheduler;

final class Deactivator
{
    /**
     * @var Scheduler
     */
    private $scheduler;

    public function __construct(Scheduler $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    public function deactivate(): void
    {
        $this->unscheduleEvents();
    }

    private function unscheduleEvents(): void
    {
        // clear() for both: unschedule() throws when the cron write fails, and a throw on the
        // first hook used to skip the second cleanup entirely. Every hook is attempted, and each
        // one that could not be cleared is reported rather than passing silently.
        foreach ([Cleanup::HOOK, ScheduleTestRequest::HOOK] as $hook) {
            if (!$this->scheduler->clear($hook)) {
                $this->reportUnclearedSchedule($hook);
            }
        }
    }

    private function reportUnclearedSchedule(string $hook): void
    {
        $message = sprintf("Staatic could not clear the '%s' schedule during deactivation.", $hook);
        // The action below is how a site observes this; the log line is a debugging aid and is
        // the only error_log() in plugin/src, so it stays behind WP_DEBUG.
        if (defined('WP_DEBUG') && \WP_DEBUG) {
            error_log($message);
        }
        do_action('staatic_deactivation_schedule_cleanup_failed', $hook, $message);
    }
}
