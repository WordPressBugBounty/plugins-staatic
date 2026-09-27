<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module;

use Staatic\Vendor\Psr\Log\LoggerInterface;
use Staatic\WordPress\Publication\Publication;
use Staatic\WordPress\Publication\PublicationManager;
use Staatic\WordPress\Publication\PublicationRepository;
use Staatic\WordPress\Publication\PublicationStatus;
use Staatic\WordPress\Util\DateUtil;
use Throwable;

/**
 * Defers WordPress background updates while a publication runs.
 *
 * A plugin, theme or core update in the middle of a crawl puts the site into maintenance mode,
 * so the crawler gets 503s, and it changes the site between the start and the end of the build.
 * While a publication is running this filter reports the automatic updater as disabled, so the
 * run is skipped and a single retry is scheduled an hour out.
 *
 * Every path that does not defer returns the incoming value unchanged, so a failure leaves
 * updates running as WordPress would. A marker that points at a finished, missing or
 * out-of-time publication does not defer, so a stale marker cannot stop updates for good.
 *
 * Sites can opt out with the `staatic_defer_automatic_updates` filter.
 */
final class DeferAutomaticUpdates implements ModuleInterface
{
    /**
     * @var PublicationManager
     */
    private $publicationManager;

    /**
     * @var PublicationRepository
     */
    private $publicationRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var string */
    public const UPDATER_HOOK = 'wp_maybe_auto_update';

    /** @var string */
    public const OPT_OUT_FILTER = 'staatic_defer_automatic_updates';

    /**
     * Fires each time the background updater run is deferred.
     *
     * Listeners receive two arguments (register with an accepted-args count of 2):
     * - string $publicationId: the id of the running publication that caused the deferral;
     * - int $blogId: the site that publication runs on. Always the main site for now.
     *
     * @var string
     */
    public const DEFERRED_ACTION = 'staatic_automatic_updates_deferred';

    /**
     * How long after a deferral the single early retry of the updater is scheduled. A literal
     * rather than HOUR_IN_SECONDS: class constants are read when the dependency container is
     * compiled, outside WordPress.
     *
     * @var int
     */
    public const RETRY_DELAY_IN_SECONDS = 3600;

    public function __construct(
        PublicationManager $publicationManager,
        PublicationRepository $publicationRepository,
        LoggerInterface $logger
    )
    {
        $this->publicationManager = $publicationManager;
        $this->publicationRepository = $publicationRepository;
        $this->logger = $logger;
    }

    public function hooks(): void
    {
        add_filter('automatic_updater_disabled', [$this, 'deferDuringPublication'], 10, 1);
        // Best effort: preload the classes of the status and age check, so that check does not
        // autoload them from new files if the updater replaces this plugin earlier in the run.
        // Anything else that fails to load throws, and a throw means the run is not deferred.
        class_exists(Publication::class);
        class_exists(PublicationStatus::class);
        class_exists(DateUtil::class);
    }

    /**
     * Callback for the automatic_updater_disabled filter.
     *
     * The parameter is left untyped: another filter may pass something other than a boolean,
     * and a type error here would break the updater.
     *
     * @param mixed $disabled
     * @return mixed
     */
    public function deferDuringPublication($disabled)
    {
        if ($disabled) {
            return $disabled;
        }
        // The filter is also read by the plugins screen, Site Health and update-core.php. Only
        // the background run is deferred.
        if (!doing_action(self::UPDATER_HOOK)) {
            return $disabled;
        }

        // Any failure from here on, including a throwing third-party filter, leaves the updater
        // as WordPress would have it.
        try {
            if (!apply_filters(self::OPT_OUT_FILTER, \true)) {
                return $disabled;
            }
            $publicationId = $this->runningPublicationId();
        } catch (Throwable $e) {
            return $disabled;
        }
        if ($publicationId === null) {
            return $disabled;
        }
        $this->announceDeferral($publicationId);

        return \true;
    }

    /**
     * Returns the id of the publication that is running on this site, or null.
     *
     * Main site only: the updater runs on the main site, so this reads the main site's marker,
     * and a publication on a multisite subsite does not defer the network's updates.
     */
    private function runningPublicationId(): ?string
    {
        //!TODO: on multisite, also check the subsites' markers.
        $publicationId = $this->publicationManager->currentPublicationId();
        if ($publicationId === null) {
            return null;
        }
        $publication = $this->publicationRepository->find($publicationId);
        if ($publication === null) {
            $this->warnStaleMarker($publicationId, 'the publication does not exist');

            return null;
        }
        if (!$publication->status()->isRunning()) {
            $this->warnStaleMarker($publicationId, sprintf('the publication is %s', $publication->status()->status()));

            return null;
        }
        // staatic_publication_timeout can lift the time limit without bound; updates, security
        // fixes included, are never held back longer than the setting's ceiling.
        $timeLimitInHours = min(Publication::timeLimitInHours(), Publication::MAXIMUM_TIME_LIMIT_IN_HOURS);
        if ($publication->exceedsTimeLimit($timeLimitInHours)) {
            $this->warnStaleMarker(
                $publicationId,
                sprintf('the publication started more than %d hours ago', $timeLimitInHours)
            );

            return null;
        }

        return $publicationId;
    }

    private function warnStaleMarker(string $publicationId, string $reason): void
    {
        $this->logger->warning(sprintf('Not deferring WordPress background updates: %s', $reason), [
            'publicationId' => $publicationId
        ]);
    }

    /**
     * Reports the deferral. The decision is already made, so a failing logger, listener or
     * scheduler must not undo it.
     */
    private function announceDeferral(string $publicationId): void
    {
        $this->ignoringFailures(function () use ($publicationId): void {
            $this->logger->notice('WordPress background updates deferred while this publication runs', [
                'publicationId' => $publicationId
            ]);
        });
        // See DEFERRED_ACTION for the arguments listeners may rely on.
        $this->ignoringFailures(static function () use ($publicationId): void {
            do_action(self::DEFERRED_ACTION, $publicationId, get_current_blog_id());
        });
        // WordPress retries on its next twice-daily check. Schedule one earlier retry, unless a
        // run is already queued; if the publication is still running then, it defers again.
        $this->ignoringFailures(static function (): void {
            if (!wp_next_scheduled(self::UPDATER_HOOK)) {
                wp_schedule_single_event(time() + self::RETRY_DELAY_IN_SECONDS, self::UPDATER_HOOK);
            }
        });
    }

    /** Runs a reporting step whose failure must not change the outcome. */
    private function ignoringFailures(callable $step): void
    {
        try {
            $step();
        } catch (Throwable $e) {
            // Deliberately ignored: see announceDeferral().
        }
    }
}
