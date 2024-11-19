<?php

declare(strict_types=1);

namespace Staatic\WordPress\Logging;

final class LogEntryCleanup
{
    /**
     * @var LogEntryRepository
     */
    private $logEntryRepository;

    /** @var int */
    public const DEFAULT_NUM_DAYS = 7;

    public function __construct(LogEntryRepository $logEntryRepository)
    {
        $this->logEntryRepository = $logEntryRepository;
    }

    public function cleanup(): void
    {
        $numDays = (int) apply_filters('staatic_log_cleanup_num_days', self::DEFAULT_NUM_DAYS);
        $excludePublicationIds = array_filter([
            get_option('staatic_current_publication_id'),
            get_option('staatic_latest_publication_id'),
            get_option('staatic_active_publication_id'),
            get_option('staatic_active_preview_publication_id')
        ]);
        $this->logEntryRepository->deleteOlderThan($numDays, $excludePublicationIds);
    }
}
