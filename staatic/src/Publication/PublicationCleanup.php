<?php

declare(strict_types=1);

namespace Staatic\WordPress\Publication;

use DateTimeImmutable;
use Staatic\Vendor\Psr\Log\LoggerInterface;

final class PublicationCleanup
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PublicationRepository
     */
    private $repository;

    /** @var int */
    public const DEFAULT_NUM_DAYS = 7;

    /**
     * Default number of most recent publications to keep, on top of the age-based cleanup.
     * Every retained publication carries its own results and results_deployment rows, which
     * multiplies index maintenance on every result insert; a count-based cap bounds that no
     * matter how the age-based cleanup is configured.
     *
     * @var int
     */
    public const DEFAULT_RETENTION_COUNT = 10;

    /** @var int */
    public const MINIMUM_RETENTION_COUNT = 2;

    public function __construct(LoggerInterface $logger, PublicationRepository $repository)
    {
        $this->logger = $logger;
        $this->repository = $repository;
    }

    public function cleanup(): void
    {
        $numDays = (int) apply_filters('staatic_publication_cleanup_num_days', self::DEFAULT_NUM_DAYS);
        $retentionCount = $this->retentionCount();
        $now = new DateTimeImmutable();
        // Bypass a possibly stale autoloaded-options cache entry; a stale
        // publication id here could shield the wrong publications from cleanup.
        wp_cache_delete('alloptions', 'options');
        $protectedIds = [
            get_option('staatic_current_publication_id'),
            get_option('staatic_latest_publication_id'),
            get_option('staatic_active_publication_id'),
            get_option('staatic_active_preview_publication_id')
        ];
        $publications = $this->repository->findAll();
        $idsBeyondRetentionCount = $this->idsBeyondRetentionCount($publications, $protectedIds, $retentionCount);
        foreach ($publications as $publication) {
            if (in_array($publication->id(), $protectedIds, \true)) {
                continue;
            }
            $exceedsAge = $publication->dateCreated()->diff($now)->days > $numDays;
            $exceedsRetentionCount = in_array($publication->id(), $idsBeyondRetentionCount, \true);
            if ($exceedsAge || $exceedsRetentionCount) {
                $this->logger->info($exceedsAge ? sprintf(
                    /* translators: %s: Publication ID. */
                    __('Cleaning up publication #%s (older than the retention age)', 'staatic'),
                    $publication->id()
                ) : sprintf(
                    /* translators: %s: Publication ID. */
                    __('Cleaning up publication #%s (beyond the retention count)', 'staatic'),
                    $publication->id()
                ), [
                    'publicationId' => $publication->id()
                ]);
                $this->repository->delete($publication);
            }
            if ($publication->status()->isInProgress()) {
                $this->logger->info(sprintf(
                    /* translators: %s: Publication ID. */
                    __('Marking publication #%s as failed (no longer running)', 'staatic'),
                    $publication->id()
                ), [
                    'publicationId' => $publication->id()
                ]);
                $publication->markFailed();
                $this->repository->update($publication);
            }
        }
    }

    /**
     * The ids of every unprotected publication beyond the $retentionCount most recent. A
     * protected publication is never itself returned here - it survives regardless of count -
     * but it still occupies one of the retained slots, so the slots left for everything else
     * shrink by however many protected publications exist. Without that, the total retained
     * could exceed $retentionCount by however many publications happen to be protected right now.
     *
     * @param Publication[] $publications
     * @param array<int, string|false> $protectedIds
     * @return string[]
     */
    private function idsBeyondRetentionCount(array $publications, array $protectedIds, int $retentionCount): array
    {
        $numProtected = 0;
        $unprotected = [];
        foreach ($publications as $publication) {
            if (in_array($publication->id(), $protectedIds, \true)) {
                $numProtected++;
            } else {
                $unprotected[] = $publication;
            }
        }
        $remainingSlots = max(0, $retentionCount - $numProtected);
        usort($unprotected, function (Publication $a, Publication $b) {
            return $b->dateCreated() <=> $a->dateCreated();
        });
        $beyondRetentionCount = array_slice($unprotected, $remainingSlots);

        return array_map(function (Publication $publication) {
            return $publication->id();
        }, $beyondRetentionCount);
    }

    private function retentionCount(): int
    {
        $retentionCount = (int) get_option('staatic_publication_retention_count');
        if ($retentionCount < self::MINIMUM_RETENTION_COUNT) {
            $retentionCount = self::DEFAULT_RETENTION_COUNT;
        }

        return $retentionCount;
    }
}
