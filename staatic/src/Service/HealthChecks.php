<?php

declare(strict_types=1);

namespace Staatic\WordPress\Service;

use Staatic\WordPress\Factory\HttpClientFactory;
use Staatic\WordPress\Module\Admin\Page\TestRequestPage;
use Staatic\WordPress\Module\Admin\Page\SettingsPage;
use Staatic\WordPress\Request\TestRequest;
use Throwable;
use wpdb;

final class HealthChecks
{
    /**
     * @var HttpClientFactory
     */
    private $httpClientFactory;

    /**
     * @var SiteUrlProvider
     */
    private $siteUrlProvider;

    /**
     * @var Formatter
     */
    private $formatter;

    /**
     * @var wpdb
     */
    private $wpdb;

    /**
     * Above this many urls in the last build, a per-request object cache miss on autoloaded
     * options and menus starts costing enough (OneDesk lever 9, ~0.3-0.5 CPU-s/page) to be
     * worth recommending a persistent one for.
     *
     * @var int
     */
    private const LARGE_BUILD_URL_THRESHOLD = 5000;

    public function __construct(
        HttpClientFactory $httpClientFactory,
        SiteUrlProvider $siteUrlProvider,
        Formatter $formatter,
        wpdb $wpdb
    )
    {
        $this->httpClientFactory = $httpClientFactory;
        $this->siteUrlProvider = $siteUrlProvider;
        $this->formatter = $formatter;
        $this->wpdb = $wpdb;
    }

    public function permalinkStructureTest()
    {
        $status = get_option('permalink_structure');
        if (!$status) {
            return $this->buildTestReport([
                'label' => __('Permalink structure is not configured correctly', 'staatic'),
                'status' => 'critical',
                'description' => __('<p>In order to successfully generate a static version of your WordPress site, a permalink structure other than Plain needs to be configured.</p>', 'staatic'),
                'actions' => sprintf(
                    /* translators: 1: Link to Permalink Settings. */
                    __('<p>Please ensure a <a href="%1$s">Permalink Structure</a> other than Plain is selected.</p>', 'staatic'),
                    admin_url('options-permalink.php')
                ),
                'test' => 'staatic_permalink_structure'
            ]);
        }

        return $this->buildTestReport([
            'label' => __('Permalink structure appears to be configured correctly', 'staatic'),
            'status' => 'good',
            'description' => __('<p>In order to successfully generate a static version of your WordPress site, a permalink structure compatible with static sites needs to be configured.</p>', 'staatic'),
            'test' => 'staatic_permalink_structure'
        ]);
    }

    public function writableWorkDirectoryTest()
    {
        $workDirectory = get_option('staatic_work_directory');
        if (is_dir($workDirectory)) {
            if (!is_writable($workDirectory)) {
                return $this->buildTestReport([
                    'label' => __('Staatic work directory is not writable', 'staatic'),
                    'status' => 'critical',
                    'description' => __('<p>In order to successfully generate a static version of your WordPress site, the Staatic work directory needs to be writable.</p>', 'staatic'),
                    'actions' => sprintf(
                        /* translators: 1: Link to Advanced Settings. */
                        __('<p>Please ensure that the Work Directory configured under <a href="%1$s">Advanced Settings</a> is writable.</p>', 'staatic'),
                        admin_url(sprintf('admin.php?page=%s&group=staatic-advanced', SettingsPage::PAGE_SLUG))
                    ),
                    'test' => 'staatic_writable_work_directory'
                ]);
            }
        } elseif (!is_writable(dirname($workDirectory))) {
            return $this->buildTestReport([
                'label' => __('Staatic work directory is not writable and can\'t be created', 'staatic'),
                'status' => 'critical',
                'description' => __('<p>In order to successfully generate a static version of your WordPress site, the Staatic work directory needs to be writable.</p>', 'staatic'),
                'actions' => sprintf(
                    /* translators: 1: Link to Advanced Settings. */
                    __('<p>Please ensure that the Work Directory, configured under <a href="%1$s">Advanced Settings</a>, is writable.</p>', 'staatic'),
                    admin_url(sprintf('admin.php?page=%s&group=staatic-advanced', SettingsPage::PAGE_SLUG))
                ),
                'test' => 'staatic_writable_work_directory'
            ]);
        }

        return $this->buildTestReport([
            'label' => __('Staatic work directory is writable', 'staatic'),
            'status' => 'good',
            'description' => __('<p>The Staatic work directory is used by Staatic to write publication resources and other temporary files.</p>', 'staatic'),
            'test' => 'staatic_writable_work_directory'
        ]);
    }

    public function publicationTaskTimeoutTest()
    {
        $status = TestRequest::status();
        // Following the link dispatches the diagnostic, which occupies a PHP worker for up to
        // five minutes and rewrites the recorded status, so it carries a nonce that load()
        // checks — the same shape PublishPage and BuildResultPage use for their action links.
        $manualUrl = TestRequestPage::dispatchUrl();
        $introduction = sprintf(
            /* translators: 1: Diagnostic runtime. */
            __('<p>This backend-worker survival diagnostic checks whether one Staatic asynchronous PHP worker remains alive for %1$s. It does not observe the proxy or client HTTP response, so it cannot prove that visitors avoid 504 responses or that CPU-bound publication work has enough capacity.</p>', 'staatic'),
            $this->formatSeconds($status['targetRuntime'])
        );
        if ($status['usesFallback']) {
            $introduction .= sprintf(
                /* translators: 1: Configured timeout value, 2: Effective fallback runtime. */
                __('<p>The configured timeout value is %1$s. Because it is not positive, the effective fallback is %2$s.</p>', 'staatic'),
                $this->formatter->number($status['configuredRuntime']),
                $this->formatSeconds($status['effectiveRuntime'])
            );
        } elseif ($status['capped']) {
            $introduction .= sprintf(
                /* translators: 1: Effective publication task runtime, 2: Diagnostic cap. */
                __('<p>The effective publication task runtime is %1$s, but the diagnostic cap is %2$s.</p>', 'staatic'),
                $this->formatSeconds($status['effectiveRuntime']),
                $this->formatSeconds($status['targetRuntime'])
            );
        }
        $report = [
            'status' => 'recommended',
            'description' => $introduction,
            'test' => 'staatic_publication_task_timeout'
        ];
        switch ($status['state']) {
            // Defensive: ExtendSiteHealth does not register this test at all while the diagnostic
            // is disabled, so the disabled state cannot reach here today. Kept because a site
            // that unregisters the filter between registration and the test running would
            // otherwise fall through to "has not been run", which reads as a fault.
            case TestRequest::STATE_DISABLED:
                $report['label'] = __('Publication worker diagnostic is intentionally disabled', 'staatic');
                $report['status'] = 'good';
                $report['description'] .= __('<p>The diagnostic is disabled by the <code>staatic_test_request_enabled</code> filter. This is an intentional configuration state, not a recommended Site Health improvement.</p>', 'staatic');

                break;
            case TestRequest::STATE_RUNNING:
                $report['label'] = __('Publication worker diagnostic is running', 'staatic');
                $report['description'] .= __('<p>A fresh worker heartbeat was received, but the diagnostic has not recorded completion yet.</p>', 'staatic');
                $report['actions'] = __('<p>Wait for the diagnostic runtime to pass, then run Site Health again.</p>', 'staatic');

                break;
            case TestRequest::STATE_COMPLETE:
                $report['label'] = $status['capped'] ? __('Publication worker survived the diagnostic cap', 'staatic') : __('Publication worker survival diagnostic completed', 'staatic');
                $report['status'] = 'good';
                $report['description'] .= sprintf(
                    /* translators: 1: Observed diagnostic runtime. */
                    __('<p>The worker explicitly completed the diagnostic after %1$s.</p>', 'staatic'),
                    $this->formatSeconds($status['duration'])
                );
                if ($status['capped']) {
                    $report['description'] .= sprintf(
                        /* translators: 1: Verified diagnostic cap, 2: Effective publication task runtime. */
                        __('<p>The diagnostic successfully verified its %1$s cap. It did not verify the full %2$s effective publication task runtime.</p>', 'staatic'),
                        $this->formatSeconds($status['targetRuntime']),
                        $this->formatSeconds($status['effectiveRuntime'])
                    );
                }

                break;
            case TestRequest::STATE_INCOMPLETE:
                if ($status['done']) {
                    $report['label'] = __('Publication worker diagnostic did not reach its target', 'staatic');
                    $report['description'] .= sprintf(
                        /* translators: 1: Observed diagnostic runtime. */
                        __('<p>The last run recorded completion after only %1$s.</p>', 'staatic'),
                        $this->formatSeconds($status['duration'])
                    );
                } else {
                    $report['label'] = __('Publication worker diagnostic did not complete', 'staatic');
                    $report['description'] .= sprintf(
                        /* translators: 1: Last observed diagnostic runtime. */
                        __('<p>The last worker heartbeat was recorded after %1$s without an explicit completion marker.</p>', 'staatic'),
                        $this->formatSeconds($status['duration'])
                    );
                }
                $report['actions'] = sprintf(
                    /* translators: 1: Diagnostic runtime, 2: Link to Trigger Test Request. */
                    __('<p>Check PHP and background-worker process limits for at least %1$s, then <a href="%2$s">run the diagnostic again</a>.</p>', 'staatic'),
                    $this->formatSeconds($status['targetRuntime']),
                    $manualUrl
                );

                break;
            case TestRequest::STATE_STALE:
                $report['label'] = __('Publication worker has not been recently verified', 'staatic');
                $report['description'] .= __('<p>The last completed result is more than 36 hours old. This means the worker has not been recently verified; it is not evidence of a runtime failure.</p>', 'staatic');
                $report['actions'] = sprintf(
                    /* translators: 1: Link to Trigger Test Request. */
                    __('<p><a href="%1$s">Run the publication worker diagnostic again</a> to refresh the result.</p>', 'staatic'),
                    $manualUrl
                );

                break;
            default:
                $report['label'] = __('Publication worker diagnostic has not been run', 'staatic');
                $report['description'] .= __('<p>No valid publication worker diagnostic status is available.</p>', 'staatic');
                $report['actions'] = sprintf(
                    /* translators: 1: Link to Trigger Test Request. */
                    __('<p>Ensure WP-Cron is functioning, or <a href="%1$s">run the diagnostic manually</a>.</p>', 'staatic'),
                    $manualUrl
                );
        }

        return $this->buildTestReport($report);
    }

    private function formatSeconds(int $seconds): string
    {
        return $this->formatter->seconds($seconds);
    }

    public function loopbackRequestsTest()
    {
        $httpClient = $this->httpClientFactory->createInternalClient([], \false);
        $introduction = __('<p>Loopback requests are used by the Staatic crawler component to generate the static version of your WordPress site.</p>', 'staatic');

        try {
            $httpClient->request('GET', ($this->siteUrlProvider)(), [
                'headers' => [
                    'Accept' => 'text/html, application/xhtml+xml, application/xml;q=0.9, */*;q=0.8'
                ],
                'timeout' => 10
            ]);
        } catch (Throwable $e) {
            return $this->buildTestReport([
                'label' => __('Staatic is unable to perform loopback requests', 'staatic'),
                'status' => 'critical',
                'description' => $introduction . sprintf(
                    /* translators: 1: Error message. */
                    __('<p>A test request resulted in the following error:</p><p><code>%1$s</code></p>', 'staatic'),
                    esc_html($e->getMessage())
                ),
                'actions' => sprintf(
                    /* translators: 1: Link to Advanced Settings. */
                    __('<p>Please ensure the following: 1) your server’s IP address is allowed, 2) HTTP authentication credentials are valid in <a href="%1$s">Advanced Settings</a>, if HTTP authentication is enabled, and 3) SSL verification is properly configured in <a href="%1$s">Advanced Settings</a> for HTTPS connections.</p>', 'staatic'),
                    admin_url(sprintf('admin.php?page=%s&group=staatic-advanced', SettingsPage::PAGE_SLUG))
                ),
                'test' => 'staatic_loopback_requests'
            ]);
        }

        return $this->buildTestReport([
            'label' => __('Staatic can perform loopback requests', 'staatic'),
            'status' => 'good',
            'description' => $introduction,
            'test' => 'staatic_loopback_requests'
        ]);
    }

    /**
     * A page render without a persistent object cache re-fetches alloptions and menus from the
     * database every request; on a small site that is noise, but the crawler makes one such
     * request per url, so on a large build it is a real, avoidable constant-factor tax.
     */
    public function objectCacheTest()
    {
        // A build in progress has num_urls_crawlable near 0 for most of its life, so ordering by
        // id alone reads the newest (possibly still-running) build instead of the last completed
        // one, and the recommendation flickers off while a publication is running.
        $numUrlsCrawlable = (int) $this->wpdb->get_var(
            "SELECT num_urls_crawlable FROM {$this->wpdb->prefix}staatic_builds\n                WHERE date_crawl_finished IS NOT NULL\n                ORDER BY id DESC LIMIT 1"
        );
        if (wp_using_ext_object_cache() || $numUrlsCrawlable <= self::LARGE_BUILD_URL_THRESHOLD) {
            return $this->buildTestReport([
                'label' => __('No persistent object cache recommendation needed', 'staatic'),
                'status' => 'good',
                'description' => __('<p>Either a persistent object cache is already active, or the last build was small enough that one is unlikely to make a noticeable difference.</p>', 'staatic'),
                'test' => 'staatic_object_cache'
            ]);
        }

        return $this->buildTestReport([
            'label' => __('A persistent object cache is recommended for this site', 'staatic'),
            'status' => 'recommended',
            'description' => sprintf(
                /* translators: %1$s: Number of urls in the last build. */
                __('<p>The last build crawled %1$s urls without a persistent object cache active. Without one, every crawled url re-fetches options and menus from the database, which adds up on a site this size.</p>', 'staatic'),
                $this->formatter->number($numUrlsCrawlable)
            ),
            'actions' => __('<p>Installing and enabling a persistent object cache (such as Redis or Memcached) is likely to reduce publication time on this site.</p>', 'staatic'),
            'test' => 'staatic_object_cache'
        ]);
    }

    /**
     * Every dequeue sorts the whole crawl queue unless its index actually descends on priority
     * (CreateIndexOnCrawlQueuePriorityTest pins why: the two ordering columns run in opposite
     * directions). MySQL < 8.0 and MariaDB < 10.8 parse the DESC keyword but store an ascending
     * index anyway, which leaves the site exactly where it was before that index existed.
     */
    public function crawlQueuePriorityIndexTest()
    {
        $tableName = "{$this->wpdb->prefix}staatic_crawl_queue";
        if (!$this->tableExists($tableName)) {
            // A table that does not exist yet - activation still in progress, or a multisite
            // bulk-activate that has not reached this site - answers this same "SHOW INDEX"
            // query with zero rows, exactly like an existing table whose index migration
            // failed. Those are different conditions: the former resolves itself once setup
            // runs and is not a performance regression to warn about, the latter genuinely
            // costs every dequeue a filesort. Reporting "critical" for a table Staatic simply
            // has not created yet would be a false alarm.
            return $this->buildTestReport([
                'label' => __('Crawl queue priority index', 'staatic'),
                'status' => 'good',
                'description' => __('<p>The crawl queue table does not exist yet.</p>', 'staatic'),
                'test' => 'staatic_crawl_queue_priority_index'
            ]);
        }
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SHOW INDEX FROM {$tableName} WHERE Key_name = %s AND Column_name = %s",
                'priority',
                'priority'
            ),
            \ARRAY_A
        );
        // An install whose v1.13.0-beta4 migration failed (it throws) has no priority index at
        // all, which is strictly worse than an ascending one - dequeuing filesorts the whole
        // queue on every batch instead of just missing the (harmless) DESC optimisation. That
        // must not be reported as "good". The table-existence check above already ruled out the
        // other reason this same query answers empty.
        if (!is_array($rows) || $rows === []) {
            return $this->buildTestReport([
                'label' => __('Crawl queue priority index is missing', 'staatic'),
                'status' => 'critical',
                'description' => __('<p>The crawl queue has no index on its priority column, so dequeuing has to sort the whole queue on every batch.</p>', 'staatic'),
                'actions' => __('<p>Deactivating and reactivating Staatic re-runs its database migrations, which re-creates this index.</p>', 'staatic'),
                'test' => 'staatic_crawl_queue_priority_index'
            ]);
        }
        $isStoredAscending = ($rows[0]['Collation'] ?? null) !== 'D';
        if (!$isStoredAscending) {
            return $this->buildTestReport([
                'label' => __('Crawl queue priority index is in good shape', 'staatic'),
                'status' => 'good',
                'description' => __('<p>The database server honours a descending index on the crawl queue, so dequeuing does not require sorting the whole queue.</p>', 'staatic'),
                'test' => 'staatic_crawl_queue_priority_index'
            ]);
        }

        return $this->buildTestReport([
            'label' => __('Crawl queue priority index is stored ascending', 'staatic'),
            'status' => 'recommended',
            'description' => __('<p>This database server accepts a descending index but stores it ascending, so every dequeue still has to sort the whole crawl queue. This is a known limitation of MySQL versions older than 8.0 and MariaDB versions older than 10.8.</p>', 'staatic'),
            'actions' => __('<p>Upgrading to MySQL 8.0+ or MariaDB 10.8+ lets this index be stored in the order the crawler actually reads it in.</p>', 'staatic'),
            'test' => 'staatic_crawl_queue_priority_index'
        ]);
    }

    private function tableExists(string $tableName): bool
    {
        $pattern = $this->wpdb->esc_like($tableName);

        return $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $pattern)) === $tableName;
    }

    private function buildTestReport(array $args): array
    {
        $args = array_merge([
            'status' => 'recommended',
            'badge' => [
                'label' => __('Staatic', 'staatic'),
                'color' => $args['status'] === 'good' ? 'blue' : 'red'
            ]
        ], $args);
        if (!empty($args['actions'])) {
            $args['actions'] .= '<p class="staatic-site-health-signature"><img src="' . esc_url(
                plugin_dir_url(\STAATIC_FILE) . 'assets/logo.svg'
            ) . '" alt="" height="20" width="20" class="staatic-site-health-signature-icon">' . sprintf(
                /* translators: 1: expands to 'Staatic' */
                esc_html__('This was reported by %1$s', 'staatic'),
                'Staatic'
            ) . '</p>';
        }

        return $args;
    }
}
