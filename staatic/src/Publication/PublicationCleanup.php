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

    public function __construct(LoggerInterface $logger, PublicationRepository $repository)
    {
        $this->logger = $logger;
        $this->repository = $repository;
    }

    public function cleanup(): void
    {
        $numDays = (int) apply_filters('staatic_publication_cleanup_num_days', self::DEFAULT_NUM_DAYS);
        $now = new DateTimeImmutable();
        foreach ($this->repository->findAll() as $publication) {
            if (in_array(
                $publication->id(),
                [
                    get_option('staatic_current_publication_id'),
                    get_option('staatic_latest_publication_id'),
                    get_option('staatic_active_publication_id'),
                    get_option('staatic_active_preview_publication_id')
                ],
                \true
            )) {
                continue;
            }
            if ($publication->dateCreated()->diff($now)->days > $numDays) {
                $this->logger->info(sprintf(
                    /* translators: %s: Publication ID. */
                    __('Cleaning up publication #%s', 'staatic'),
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
}
