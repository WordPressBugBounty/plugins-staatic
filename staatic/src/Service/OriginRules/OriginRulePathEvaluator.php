<?php

declare(strict_types=1);

namespace Staatic\WordPress\Service\OriginRules;

use InvalidArgumentException;
use Staatic\Vendor\Psr\Http\Message\UriInterface;
use Staatic\Crawler\CrawlProfile\CrawlProfileInterface;
use Staatic\Crawler\UrlEvaluator\UrlEvaluatorInterface;

/**
 * Keeps URLs out of the crawl whose published path an origin rule forwards to an origin.
 *
 * The rule patterns describe paths on the published site, so each resolved source URL is first
 * transformed exactly as the crawl profile transforms it (source base path stripped,
 * destination base path added, lowercased when that option is on) and the resulting path is
 * matched with the platform's path-pattern semantics (see OriginRulePathMatcher).
 */
final class OriginRulePathEvaluator implements UrlEvaluatorInterface
{
    /**
     * @var OriginRulePathMatcher
     */
    private $matcher;

    /**
     * @var CrawlProfileInterface
     */
    private $crawlProfile;

    public function __construct(OriginRulePathMatcher $matcher, CrawlProfileInterface $crawlProfile)
    {
        $this->matcher = $matcher;
        $this->crawlProfile = $crawlProfile;
    }

    /**
     * @param UriInterface $resolvedUrl
     * @param mixed[] $context
     */
    public function shouldCrawl($resolvedUrl, $context = []): bool
    {
        try {
            $publishedUrl = $this->crawlProfile->transformUrl($resolvedUrl)->transformedUrl();
        } catch (InvalidArgumentException $exception) {
            // A URL outside the source base path has no published path; deciding whether it is
            // crawled at all is up to the internal URL evaluator.
            return \true;
        }

        return !$this->matcher->matches($publishedUrl->getPath());
    }
}
