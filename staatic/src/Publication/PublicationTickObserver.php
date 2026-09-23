<?php

declare(strict_types=1);

namespace Staatic\WordPress\Publication;

use Staatic\Vendor\Psr\Http\Message\ResponseInterface;
use Staatic\Vendor\Psr\Http\Message\UriInterface;
use Staatic\Crawler\Observer\AbstractObserver;
use Throwable;

/**
 * Fires a source-agnostic keepalive heartbeat while a crawl is running, at most once every
 * MIN_INTERVAL_SECONDS, regardless of whether the publication is driven from wp-admin
 * (WP_Background_Process) or WP-CLI. Cloud's Sablier keepalive today only fires for $fromWeb
 * requests (docker-wordpress/mu-plugin/staatic_helper.php), which is why a CLI publication idles
 * out after 15 minutes; this gives that keepalive - and anything else that needs "a crawl is
 * still making progress" - one hook that exists independent of where the request came from. The
 * mu-plugin change itself is a separate docker-wordpress deployment, out of scope here.
 */
final class PublicationTickObserver extends AbstractObserver
{
    /**
     * @var Publication
     */
    private $publication;

    /** @var int */
    private const MIN_INTERVAL_SECONDS = 60;

    /**
     * Counts crawl responses this observer instance has seen, not this publication overall - see
     * the do_action() docblock below.
     * @var int
     */
    private $numResponsesObserved = 0;

    /**
     * @var float|null
     */
    private $lastTick;

    public function __construct(Publication $publication)
    {
        $this->publication = $publication;
    }

    /**
     * @param UriInterface $url
     * @param UriInterface $transformedUrl
     * @param UriInterface $normalizedUrl
     * @param ResponseInterface $response
     * @param UriInterface|null $foundOnUrl
     * @param mixed[] $tags
     */
    public function crawlFulfilled($url, $transformedUrl, $normalizedUrl, $response, $foundOnUrl, $tags): void
    {
        $this->tick();
    }

    /**
     * @param UriInterface $url
     * @param UriInterface $transformedUrl
     * @param UriInterface $normalizedUrl
     * @param Throwable $transferException
     * @param UriInterface|null $foundOnUrl
     * @param mixed[] $tags
     */
    public function crawlRejected($url, $transformedUrl, $normalizedUrl, $transferException, $foundOnUrl, $tags): void
    {
        $this->tick();
    }

    /**
     * Fires on the very first crawl response regardless of MIN_INTERVAL_SECONDS, because
     * $lastTick starts null: the hook runs `1 + floor(duration / 60)` times, not
     * `floor(duration / 60)` - a strict 60s cadence is not guaranteed. That extra early ping is
     * harmless for a Sablier keepalive and arguably desirable (it proves the crawl is alive
     * before the first minute has even elapsed), so this is deliberate; a consumer that needs an
     * exact cadence should not rely on this hook for that.
     */
    private function tick(): void
    {
        $this->numResponsesObserved++;
        $now = microtime(\true);
        if ($this->lastTick !== null && $now - $this->lastTick < self::MIN_INTERVAL_SECONDS) {
            return;
        }
        $this->lastTick = $now;
        /**
         * Fires only from crawl responses (crawlFulfilled()/crawlRejected() above) - not from
         * anything else that happens during a publication, such as writing results or running
         * post-processors, so a batch that spends most of its time outside the crawl loop is not
         * covered by this heartbeat on its own. The first call always fires immediately
         * regardless of MIN_INTERVAL_SECONDS (see tick()'s docblock), then at most once per
         * MIN_INTERVAL_SECONDS after that.
         *
         * Both the throttle ($lastTick) and the count ($numResponsesObserved) are state on this
         * one observer instance, not on the publication: StaticGeneratorFactory attaches a new
         * PublicationTickObserver every time it builds a new generator (once per publication id
         * per PHP process - see StaticGeneratorFactory::__invoke()), so a publication that spans
         * several wp-admin request slices or CLI batches gets a fresh observer, a reset throttle,
         * and $numResponsesObserved starting back at 1 on every one of them. A consumer of this
         * hook must not read the second argument as "total responses this publication has ever
         * had" - only as "responses this request/slice's observer has seen so far".
         */
        do_action('staatic_publication_tick', $this->publication, $this->numResponsesObserved);
    }
}
