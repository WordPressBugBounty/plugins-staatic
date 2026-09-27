<?php

declare(strict_types=1);

namespace Staatic\WordPress\Factory;

use Staatic\Vendor\Psr\Http\Message\UriInterface;
use RuntimeException;
use Staatic\Crawler\CrawlProfile\CrawlProfileInterface;
use Staatic\Crawler\UrlEvaluator\CallbackUrlEvaluator;
use Staatic\Crawler\UrlEvaluator\ChainUrlEvaluator;
use Staatic\Crawler\UrlEvaluator\ExcludeRulesUrlEvaluator;
use Staatic\Crawler\UrlEvaluator\InternalUrlEvaluator;
use Staatic\Crawler\UrlEvaluator\UrlEvaluatorInterface;
use Staatic\WordPress\Bridge\CrawlProfile;
use Staatic\WordPress\Service\ExcludeUrls;
use Staatic\WordPress\Service\OriginRules\OriginRulePathEvaluator;
use Staatic\WordPress\Service\OriginRules\OriginRulePathMatcher;
use Staatic\WordPress\Service\OriginRules\OriginRulePaths;
use Staatic\WordPress\Service\SiteUrlProvider;
use Staatic\WordPress\Setting\Build\ExcludeUrlsSetting;
use Staatic\WordPress\Util\WordpressEnv;

final class CrawlProfileFactory
{
    /**
     * @var SiteUrlProvider
     */
    private $siteUrlProvider;

    /**
     * @var ExcludeUrlsSetting
     */
    private $excludeUrls;

    /**
     * @var UrlTransformerFactory
     */
    private $urlTransformerFactory;

    /**
     * @var OriginRulePaths
     */
    private $originRulePaths;

    public function __construct(
        SiteUrlProvider $siteUrlProvider,
        ExcludeUrlsSetting $excludeUrls,
        UrlTransformerFactory $urlTransformerFactory,
        OriginRulePaths $originRulePaths
    )
    {
        $this->siteUrlProvider = $siteUrlProvider;
        $this->excludeUrls = $excludeUrls;
        $this->urlTransformerFactory = $urlTransformerFactory;
        $this->originRulePaths = $originRulePaths;
    }

    /**
     * @param bool $isPreview Whether the publication targets the preview site; selects which
     *                        origin rule paths are kept out of the crawl.
     */
    public function __invoke(
        UriInterface $baseUrl,
        UriInterface $destinationUrl,
        bool $isPreview
    ): CrawlProfileInterface
    {
        $lowercaseUrls = (bool) get_option('staatic_crawler_lowercase_urls');
        $urlEvaluator = $this->createUrlEvaluator($baseUrl);
        $urlTransformer = ($this->urlTransformerFactory)($baseUrl, $destinationUrl);
        $crawlProfile = new CrawlProfile($baseUrl, $destinationUrl, $lowercaseUrls, $urlEvaluator, $urlTransformer);
        $originRuleMatcher = new OriginRulePathMatcher($this->originRulePaths->patterns($isPreview));
        if ($originRuleMatcher->isEmpty()) {
            return $crawlProfile;
        }

        // Origin rule patterns describe published paths, so the evaluator transforms each URL
        // through this very profile (lowercasing included) before matching it.
        return $crawlProfile->withUrlEvaluator(
            new ChainUrlEvaluator([$urlEvaluator, new OriginRulePathEvaluator($originRuleMatcher, $crawlProfile)])
        );
    }

    private function createUrlEvaluator(UriInterface $baseUrl): UrlEvaluatorInterface
    {
        $evaluators = [new InternalUrlEvaluator($baseUrl)];
        $excludeUrls = ExcludeUrls::resolve($this->excludeUrls->value(), ($this->siteUrlProvider)());
        $excludeUrls = $this->addBaseExcludeUrls($excludeUrls);
        $excludeUrls = apply_filters('staatic_exclude_urls', $excludeUrls, $baseUrl);
        if (count($excludeUrls)) {
            $evaluators[] = new ExcludeRulesUrlEvaluator($excludeUrls);
        }
        if (has_filter('staatic_should_crawl_url')) {
            $evaluators[] = new CallbackUrlEvaluator(function (UriInterface $resolvedUrl, array $context) {
                return (bool) apply_filters('staatic_should_crawl_url', \true, $resolvedUrl, $context);
            });
        }
        $evaluators = array_values(apply_filters('staatic_url_evaluators', $evaluators));
        if (empty($evaluators)) {
            throw new RuntimeException('No URL evaluators configured.');
        }

        return count($evaluators) > 1 ? new ChainUrlEvaluator($evaluators) : $evaluators[0];
    }

    private function addBaseExcludeUrls(array $excludeUrls): array
    {
        $wordpressPrefix = rtrim(WordpressEnv::getWordpressUrlPath(), '/');
        $patterns = array_map(function ($pattern) use ($wordpressPrefix) {
            return sprintf($pattern, preg_quote($wordpressPrefix, '~'));
        }, [
            '~^%s/(xmlrpc|wp-comments-post|wp-login)\.php~',
            '~%s/wp-admin/?$~',
            '~/\?(p|page_id|attachment_id|cat|author|m)=\d+~',
            '~/\?(s|tag|feed)=.+~',
            '~\.htaccess$~'
        ]);

        return array_merge($excludeUrls, $patterns);
    }
}
