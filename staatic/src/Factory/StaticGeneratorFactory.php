<?php

declare(strict_types=1);

namespace Staatic\WordPress\Factory;

use Staatic\Vendor\GuzzleHttp\ClientInterface;
use Staatic\Vendor\Psr\Log\LoggerAwareInterface;
use Staatic\Vendor\Psr\Log\LoggerInterface;
use Staatic\Crawler\CrawlOptions;
use Staatic\Crawler\CrawlProfile\CrawlProfileInterface;
use Staatic\Crawler\CrawlQueue\CrawlQueueInterface;
use Staatic\Crawler\CrawlUrlProvider\AdditionalPathCrawlUrlProvider;
use Staatic\Crawler\CrawlUrlProvider\AdditionalPathCrawlUrlProvider\AdditionalPath;
use Staatic\Crawler\CrawlUrlProvider\AdditionalUrlCrawlUrlProvider;
use Staatic\Crawler\CrawlUrlProvider\CrawlUrlProviderCollection;
use Staatic\Crawler\CrawlUrlProvider\EntryCrawlUrlProvider;
use Staatic\Crawler\CrawlUrlProvider\PageNotFoundCrawlUrlProvider;
use Staatic\Crawler\Crawler;
use Staatic\Crawler\KnownUrlsContainer\KnownUrlsContainerInterface;
use Staatic\Crawler\UrlTransformer\UrlTransformerInterface;
use Staatic\Framework\Build;
use Staatic\Framework\BuildRepository\BuildRepositoryInterface;
use Staatic\Framework\PostProcessor\AdditionalRedirectsPostProcessor;
use Staatic\Framework\PostProcessor\DuplicatesRemoverPostProcessor;
use Staatic\Framework\PostProcessor\PostProcessorCollection;
use Staatic\Framework\ResourceRepository\ResourceRepositoryInterface;
use Staatic\Framework\ResultRepository\ResultRepositoryInterface;
use Staatic\Framework\StaticGenerator;
use Staatic\Framework\Transformer\FallbackUrlTransformer;
use Staatic\Framework\Transformer\StaaticTransformer;
use Staatic\Framework\Transformer\TransformerCollection;
use Staatic\WordPress\Bridge\HtmlUrlExtractorMapping;
use Staatic\WordPress\Publication\ProcessTimeLimit;
use Staatic\WordPress\Publication\Publication;
use Staatic\WordPress\Publication\PublicationTickObserver;
use Staatic\WordPress\Service\AdditionalPaths;
use Staatic\WordPress\Service\AdditionalRedirects;
use Staatic\WordPress\Service\AdditionalUrls;
use Staatic\WordPress\Setting\Advanced\HttpConcurrencySetting;
use Staatic\WordPress\Setting\Advanced\HttpTimeoutSetting;
use Staatic\WordPress\Util\WordpressEnv;

/**
 * Builds (and, within one PHP process, memoises) the StaticGenerator for a publication's crawl
 * batches - see __invoke()'s docblock for why the memoisation exists at all.
 *
 * What's frozen at first construction for a publication id, for the memoised generator's whole
 * lifetime in this process (recomputed only the next time __invoke() builds a new generator, for
 * a different publication id): the known-urls container, crawl profile, url transformer, dom
 * parser/process-not-found/http-concurrency/extended-url-context settings, the forced-file-
 * extensions pattern, the Guzzle client, the crawler and its PublicationTickObserver, the
 * transformers, and the post-processors.
 *
 * What's refreshed on every __invoke() call, including a reuse of the memoised generator:
 * refreshBatchBudget() re-derives the crawl deadline (and staatic_crawl_batch_size) from
 * $limitedResources every time, since a batch's remaining time budget is a property of *this*
 * request/slice, not of the publication as a whole.
 *
 * $this->publication/$this->build are reassigned at the very top of every __invoke() call
 * (including the memoised-reuse branch), so they always reflect the most recent __invoke() call's
 * publication - but a method that is not called from inside __invoke()'s own call stack,
 * such as createCrawlUrlProviders() (called separately by InitializeCrawlerTask, after __invoke()
 * has already returned), must not rely on that: it takes the publication it means explicitly
 * instead.
 */
final class StaticGeneratorFactory
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var HttpClientFactory
     */
    private $httpClientFactory;

    /**
     * @var CrawlProfileFactory
     */
    private $crawlProfileFactory;

    /**
     * @var CrawlQueueInterface
     */
    private $crawlQueue;

    /**
     * @var KnownUrlsContainerFactory
     */
    private $knownUrlsContainerFactory;

    /**
     * @var BuildRepositoryInterface
     */
    private $buildRepository;

    /**
     * @var ResultRepositoryInterface
     */
    private $resultRepository;

    /**
     * @var ResourceRepositoryInterface
     */
    private $resourceRepository;

    /**
     * @var UrlTransformerFactory
     */
    private $urlTransformerFactory;

    /**
     * @var HtmlUrlExtractorMapping
     */
    private $htmlUrlExtractorMapping;

    /**
     * @var HttpTimeoutSetting
     */
    private $httpTimeout;

    /**
     * CLI's batch time budget. Unlike wp-admin there is no hard request time limit to stay
     * under; this only exists so PublishesFromCli's progress bar gets to update every so often -
     * see StaticGenerator::STATS_UPDATE_FREQUENCY for the finer-grained stats cadence within it.
     *
     * @var int
     */
    private const CLI_BATCH_SECONDS = 60;

    /**
     * Floor for the margin subtracted from wp-admin's estimated remaining request time, so a
     * batch that is already running has room to finish (persist state, update stats) before the
     * request's own time limit cuts it off mid-write.
     *
     * A fixed 15s only covers that "finish the last response" cost, not "finish the last
     * request" - the worst case for a batch already in flight is roughly 2x the slowest page
     * (concurrency*2 urls per batch, at `concurrency` in flight at once), bounded from above by
     * the HTTP client's own per-request timeout. wpAdminDeadlineMarginSeconds() below derives the
     * actual margin from that setting instead of trusting the fixed floor alone.
     *
     * @var int
     */
    private const WP_ADMIN_DEADLINE_MARGIN_SECONDS = 15;

    /**
     * The list of file extensions that is used to determine whether
     * a linked resource on a page is an asset that needs to be crawled
     * as well, even if it exceeds the configured maximum depth value.
     *
     * @var string[]
     */
    public const DEFAULT_FORCED_FILE_EXTENSIONS = [
        'js',
        'css',
        'svg',
        'ico',
        'png',
        'jpg',
        'jpeg',
        'gif',
        'webp',
        'avif',
        'eot',
        'woff',
        'woff2',
        'ttf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'pdf'
    ];

    /**
     * @var Publication
     */
    private $publication;

    /**
     * @var Build
     */
    private $build;

    /**
     * @var KnownUrlsContainerInterface
     */
    private $knownUrlsContainer;

    /**
     * @var CrawlProfileInterface
     */
    private $crawlProfile;

    /**
     * @var UrlTransformerInterface
     */
    private $urlTransformer;

    /**
     * @var bool
     */
    private $extendedUrlContext;

    /**
     * @var TransformerCollection
     */
    private $transformers;

    /**
     * @var StaticGenerator|null
     */
    private $memoizedGenerator;

    /**
     * @var CrawlOptions|null
     */
    private $memoizedCrawlOptions;

    /**
     * @var string|null
     */
    private $memoizedGeneratorPublicationId;

    public function __construct(
        LoggerInterface $logger,
        HttpClientFactory $httpClientFactory,
        CrawlProfileFactory $crawlProfileFactory,
        CrawlQueueInterface $crawlQueue,
        KnownUrlsContainerFactory $knownUrlsContainerFactory,
        BuildRepositoryInterface $buildRepository,
        ResultRepositoryInterface $resultRepository,
        ResourceRepositoryInterface $resourceRepository,
        UrlTransformerFactory $urlTransformerFactory,
        HtmlUrlExtractorMapping $htmlUrlExtractorMapping,
        HttpTimeoutSetting $httpTimeout
    )
    {
        $this->logger = $logger;
        $this->httpClientFactory = $httpClientFactory;
        $this->crawlProfileFactory = $crawlProfileFactory;
        $this->crawlQueue = $crawlQueue;
        $this->knownUrlsContainerFactory = $knownUrlsContainerFactory;
        $this->buildRepository = $buildRepository;
        $this->resultRepository = $resultRepository;
        $this->resourceRepository = $resourceRepository;
        $this->urlTransformerFactory = $urlTransformerFactory;
        $this->htmlUrlExtractorMapping = $htmlUrlExtractorMapping;
        $this->httpTimeout = $httpTimeout;
    }

    public function __invoke(Publication $publication, bool $limitedResources = \true): StaticGenerator
    {
        $this->publication = $publication;
        $this->build = $publication->build();
        // Memoised per publication for the lifetime of this PHP process: rebuilding the crawler,
        // its Guzzle client, transformers and post-processors on every batch (P4) was the
        // largest constant-factor cost on the plugin side, and none of it depends on how far the
        // crawl has progressed. A new process (the next wp-admin request, or a fresh CLI run)
        // starts this over from scratch, which is also what keeps a crashed/canceled publication
        // from resuming into a stale generator.
        if ($this->memoizedGenerator !== null && $this->memoizedGeneratorPublicationId === $publication->id()) {
            $this->refreshBatchBudget($this->memoizedCrawlOptions, $limitedResources);

            return $this->memoizedGenerator;
        }
        $this->knownUrlsContainer = ($this->knownUrlsContainerFactory)(!$limitedResources);
        $this->crawlProfile = ($this->crawlProfileFactory)($this->build->entryUrl(), $this->build->destinationUrl());
        $this->urlTransformer = ($this->urlTransformerFactory)($this->build->entryUrl(), $this->build->destinationUrl());
        $domParser = get_option('staatic_crawler_dom_parser') ?: null;
        $processNotFound = (bool) get_option('staatic_crawler_process_not_found');
        // The setting clamps on write, but the option can also reach the database without passing
        // through the settings screen. A stored 0 would leave the crawl with no concurrency at
        // all, so the read side holds the same floor.
        $httpConcurrency = max(
            HttpConcurrencySetting::MINIMUM_CONCURRENCY,
            (int) get_option('staatic_http_concurrency')
        );
        $this->extendedUrlContext = (bool) apply_filters('staatic_extended_url_context', \false);
        $forcedFileExtensions = apply_filters('staatic_forced_file_extensions', self::DEFAULT_FORCED_FILE_EXTENSIONS);
        $forcedFileExtensions = array_map(function ($extension) {
            return preg_quote($extension, '/');
        }, $forcedFileExtensions);
        $shallow = $this->build->parentId() || $this->publication->metadataByKey('subset');
        $crawlOptions = apply_filters('staatic_crawl_options', new CrawlOptions([
            'concurrency' => $httpConcurrency,
            'maxDepth' => $shallow ? 1 : null,
            'forceAssets' => $shallow ? \true : \false,
            'assetsPattern' => sprintf('/\.(%s)$/', implode('|', $forcedFileExtensions)),
            'domParser' => $domParser,
            'processNotFound' => $processNotFound,
            'htmlUrlExtractorMapping' => $this->htmlUrlExtractorMapping,
            'extendedUrlContext' => $this->extendedUrlContext
        ], $publication));
        $this->refreshBatchBudget($crawlOptions, $limitedResources);
        $crawler = new Crawler(
            $this->createHttpClient(),
            $this->crawlProfile,
            $this->crawlQueue,
            $this->knownUrlsContainer,
            $crawlOptions
        );
        if ($crawler instanceof LoggerAwareInterface) {
            $crawler->setLogger($this->logger);
        }
        // Source-agnostic keepalive: fires regardless of wp-admin vs CLI, unlike the Cloud
        // mu-plugin's own $fromWeb-gated keepalive. Attached once per publication, alongside the
        // rest of the memoised crawler, not on every batch.
        $crawler->attach(new PublicationTickObserver($publication));
        $this->transformers = $this->createTransformers();
        $generator = new StaticGenerator(
            $crawler,
            $this->buildRepository,
            $this->resultRepository,
            $this->resourceRepository,
            $this->transformers,
            $this->createPostProcessors(),
            $this->logger
        );
        $this->memoizedGenerator = $generator;
        $this->memoizedCrawlOptions = $crawlOptions;
        $this->memoizedGeneratorPublicationId = $publication->id();

        return $generator;
    }

    /**
     * Sizes the batch by remaining time instead of a fixed url count (P4): the pool used to
     * drain to zero every 12 urls regardless of concurrency, because a batch ended after a fixed
     * count rather than when there was no time left to start more. staatic_crawl_batch_size keeps
     * working as an optional url-count cap on top of the deadline, for back-compat with the
     * mu-plugin template (mu-plugins/staatic_batch_size.php).
     */
    private function refreshBatchBudget(CrawlOptions $crawlOptions, bool $limitedResources): void
    {
        $seconds = $limitedResources ? $this->wpAdminBatchSeconds() : self::CLI_BATCH_SECONDS;
        $crawlOptions->setDeadline(microtime(\true) + $seconds);
        $maxCrawls = apply_filters('staatic_crawl_batch_size', null);
        $crawlOptions->setMaxCrawls($maxCrawls !== null ? (int) $maxCrawls : null);
    }

    /**
     * Approximates BackgroundPublisher::processTimeLimit() from the same
     * staatic_background_process_timeout option, minus a margin, without this factory depending
     * on the runner class - both derive $processTimeLimit through the shared
     * ProcessTimeLimit::fromTimeout() policy, so they cannot drift apart from each other. The one
     * path this does not reproduce is set_time_limit() itself failing inside BackgroundPublisher,
     * which only ever makes that runner's real limit lower than this estimate - in which case the
     * request's own time limit cuts the batch short and the next task call resumes it, exactly as
     * an undersized deadline here would.
     *
     * $processTimeLimit is already timeout/3 - BackgroundPublisher reserves the other two thirds
     * of the request's time budget as headroom before PHP's own set_time_limit($timeout) kills
     * the request, and that 1/3 split *is* the margin. Subtracting wpAdminDeadlineMarginSeconds()
     * (up to 2x the HTTP timeout) from the full processTimeLimit would double-count that headroom
     * and can collapse the deadline to ~1s on a default install (timeout=180 -> ptl=60,
     * margin=max(15,120)=120 -> max(1,60-120)=1), which starves the pool exactly like the
     * pre-C3 sawtooth this change exists to remove. Capping the subtracted margin at ptl/2 keeps
     * at least half of the already-reduced slice for the batch while still absorbing the
     * worst-case in-flight batch (2x httpTimeout) whenever that fits within the other half.
     */
    private function wpAdminBatchSeconds(): int
    {
        $timeout = (int) apply_filters(
            'staatic_publication_task_timeout',
            (int) get_option('staatic_background_process_timeout')
        );
        $processTimeLimit = ProcessTimeLimit::fromTimeout($timeout);

        return max(1, $processTimeLimit - min((int) ($processTimeLimit / 2), $this->wpAdminDeadlineMarginSeconds()));
    }

    /**
     * Worst case for a batch already in flight when the deadline is crossed is roughly
     * `2 * slowest page`: a batch is `concurrency * 2` urls wide with `concurrency` requests in
     * flight at once, so the last-started request can still be waiting when the first
     * `concurrency` responses come back, and every request is bounded above by the configured
     * HTTP timeout. A fixed 15s margin does not cover that on a slow origin with the default
     * 60s timeout (2 * 60s = 120s > 15s); deriving it from the timeout closes that gap while
     * `max(15, ...)` keeps today's margin as a floor for fast origins with a short timeout.
     * wpAdminBatchSeconds() caps how much of this margin actually gets subtracted (at most
     * processTimeLimit/2) so this value alone is no longer safe to use as-is against the full
     * processTimeLimit - see that method's docblock.
     */
    private function wpAdminDeadlineMarginSeconds(): int
    {
        return max(self::WP_ADMIN_DEADLINE_MARGIN_SECONDS, 2 * (int) $this->httpTimeout->value());
    }

    private function createHttpClient(): ClientInterface
    {
        $defaultHeaders = [];
        if ($this->publication->isPreview()) {
            $defaultHeaders['X-Staatic-Preview'] = $this->publication->isPreview() ? 1 : 0;
        }

        return $this->httpClientFactory->createInternalClient([
            'headers' => $defaultHeaders
        ]);
    }

    private function createTransformers(): TransformerCollection
    {
        $transformers = [];
        if ($this->build->entryUrl()->getHost() !== $this->build->destinationUrl()->getHost()) {
            // Fallback URL transformer is only supported when entry URL and destination URL have a different
            // host; otherwise transformations could occur multiple times, messing up the end result.
            $transformers[] = new FallbackUrlTransformer(
                $this->urlTransformer,
                $this->build->entryUrl()->getPath(),
                $this->extendedUrlContext
            );
        }
        $transformers = apply_filters('staatic_transformers', $transformers, $this->publication);
        $transformers[] = new StaaticTransformer();
        foreach ($transformers as $transformer) {
            if ($transformer instanceof LoggerAwareInterface) {
                $transformer->setLogger($this->logger);
            }
        }

        return new TransformerCollection($transformers);
    }

    private function createPostProcessors(): PostProcessorCollection
    {
        $postProcessors = [];
        $additionalRedirects = $this->getAdditionalRedirects();
        if (count($additionalRedirects)) {
            $postProcessors[] = new AdditionalRedirectsPostProcessor(
                $this->resultRepository,
                $this->resourceRepository,
                $additionalRedirects,
                $this->crawlProfile,
                $this->transformers
            );
        }
        $postProcessors[] = new DuplicatesRemoverPostProcessor(
            $this->resultRepository,
            $additionalRedirects,
            $this->crawlProfile
        );
        $postProcessors = apply_filters('staatic_post_processors', $postProcessors, $this->publication);
        foreach ($postProcessors as $postProcessor) {
            if ($postProcessor instanceof LoggerAwareInterface) {
                $postProcessor->setLogger($this->logger);
            }
        }

        return new PostProcessorCollection($postProcessors);
    }

    /**
     * Takes $publication explicitly rather than reading $this->publication/$this->build: unlike
     * __invoke(), this is called separately by InitializeCrawlerTask, after __invoke() has
     * already returned - so it must not depend on those properties still reflecting the
     * publication the caller means, whether or not that happens to be true of every call site
     * today. See the class docblock for which of the factory's own state is safe to read
     * implicitly (frozen/refreshed per __invoke() call) versus what a method called outside
     * __invoke() must be handed explicitly, as this is.
     */
    public function createCrawlUrlProviders(Publication $publication): CrawlUrlProviderCollection
    {
        $build = $publication->build();
        $providers = new CrawlUrlProviderCollection();
        if ($subset = $publication->metadataByKey('subset')) {
            $additionalUrls = AdditionalUrls::resolve((string) $subset['urls'], $build->entryUrl());
            if (count($additionalUrls)) {
                $providers->addProvider(new AdditionalUrlCrawlUrlProvider($additionalUrls, $build->entryUrl()));
            }
            $additionalPaths = AdditionalPaths::resolve((string) $subset['paths'], WordpressEnv::getWordpressUrlPath());
            if (count($additionalPaths)) {
                $providers->addProvider(
                    new AdditionalPathCrawlUrlProvider(
                        $additionalPaths,
                        $build->entryUrl(),
                        $this->getAdditionalPathExcludes()
                    )
                );
            }
        } else {
            $providers->addProvider(new EntryCrawlUrlProvider($build->entryUrl()));
            if ($notFoundPath = get_option('staatic_page_not_found_path')) {
                $providers->addProvider(new PageNotFoundCrawlUrlProvider($build->entryUrl()->withPath($notFoundPath)));
            }
            $additionalUrls = $this->getAdditionalUrls($build);
            if (count($additionalUrls)) {
                $providers->addProvider(new AdditionalUrlCrawlUrlProvider($additionalUrls, $build->entryUrl()));
            }
            $additionalPaths = $this->getAdditionalPaths();
            if (count($additionalPaths)) {
                $providers->addProvider(
                    new AdditionalPathCrawlUrlProvider(
                        $additionalPaths,
                        $build->entryUrl(),
                        $this->getAdditionalPathExcludes()
                    )
                );
            }
        }
        $providers = apply_filters('staatic_crawl_url_providers', $providers, $publication);
        foreach ($providers as $provider) {
            if ($provider instanceof LoggerAwareInterface) {
                $provider->setLogger($this->logger);
            }
        }

        return $providers;
    }

    private function getAdditionalRedirects(): array
    {
        $additionalRedirects = AdditionalRedirects::resolve(get_option('staatic_additional_redirects') ?: null);

        return apply_filters('staatic_additional_redirects', $additionalRedirects);
    }

    private function getAdditionalUrls(Build $build): array
    {
        $additionalUrls = AdditionalUrls::resolve(get_option('staatic_additional_urls') ?: null, $build->entryUrl());

        return apply_filters('staatic_additional_urls', $additionalUrls);
    }

    /** @return AdditionalPath[] */
    private function getAdditionalPaths(): array
    {
        $additionalPaths = AdditionalPaths::resolve(
            get_option('staatic_additional_paths') ?: null,
            WordpressEnv::getWordpressUrlPath()
        );

        return apply_filters('staatic_additional_paths', $additionalPaths);
    }

    private function getAdditionalPathExcludes(): array
    {
        $excludePaths = [];
        if ($workDirectory = get_option('staatic_work_directory')) {
            $excludePaths[] = $workDirectory;
        }

        return apply_filters('staatic_additional_paths_exclude_paths', $excludePaths);
    }
}
