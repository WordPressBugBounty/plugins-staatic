<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module\Admin;

use DateTimeImmutable;
use Staatic\WordPress\Module\ModuleInterface;
use Staatic\WordPress\Request\TestRequest;
use Staatic\WordPress\Service\Formatter;
use Staatic\WordPress\Service\HealthChecks;
use Staatic\WordPress\Service\SiteUrlProvider;
use Staatic\WordPress\Util\WordpressEnv;

final class ExtendSiteHealth implements ModuleInterface
{
    /**
     * @var Formatter
     */
    private $formatter;

    /**
     * @var HealthChecks
     */
    private $healthChecks;

    /**
     * @var SiteUrlProvider
     */
    private $siteUrlProvider;

    public function __construct(Formatter $formatter, HealthChecks $healthChecks, SiteUrlProvider $siteUrlProvider)
    {
        $this->formatter = $formatter;
        $this->healthChecks = $healthChecks;
        $this->siteUrlProvider = $siteUrlProvider;
    }

    public function hooks(): void
    {
        if (!is_admin() || is_network_admin()) {
            return;
        }
        add_filter('site_status_tests', [$this, 'addSiteStatusTests']);
        add_filter('debug_information', [$this, 'addDebugInformation']);
    }

    /**
     * @param mixed[] $tests
     */
    public function addSiteStatusTests($tests): array
    {
        $tests['direct']['staatic_permalink_structure'] = [
            'label' => __('Permalink structure', 'staatic'),
            'test' => [$this->healthChecks, 'permalinkStructureTest']
        ];
        $tests['direct']['staatic_writable_work_directory'] = [
            'label' => __('Writable work directory', 'staatic'),
            'test' => [$this->healthChecks, 'writableWorkDirectoryTest']
        ];
        if (TestRequest::isEnabled()) {
            $tests['direct']['staatic_publication_task_timeout'] = [
                'label' => __('Publication worker survival', 'staatic'),
                'test' => [$this->healthChecks, 'publicationTaskTimeoutTest']
            ];
        }
        $tests['async']['staatic_loopback_requests'] = [
            'label' => __('Staatic loopback requests', 'staatic'),
            'test' => rest_url('staatic-health/v1/tests/loopback-requests'),
            'has_rest' => \true,
            'async_direct_test' => [$this->healthChecks, 'loopbackRequestsTest']
        ];

        return $tests;
    }

    /**
     * @param mixed[] $info
     */
    public function addDebugInformation($info): array
    {
        $htmlDomParsers = [
            'html5' => 'HTML5-PHP',
            'dom_wrap' => 'PHP DOM Wrapper',
            'simple_html' => 'Simple Html Dom Parser'
        ];
        $sslVerifyBehaviors = [
            'enabled' => __('Enabled', 'staatic'),
            'disabled' => __('Disabled', 'staatic'),
            'path' => __('Enabled using custom certificate', 'staatic')
        ];
        $htmlDomParser = get_option('staatic_crawler_dom_parser');
        $processNotFound = get_option('staatic_crawler_process_not_found');
        $lowercaseUrls = get_option('staatic_crawler_lowercase_urls');
        $deploymentMethod = get_option('staatic_deployment_method');
        $downgradeHttps = get_option('staatic_http_https_to_http');
        $sslVerifyBehavior = get_option('staatic_ssl_verify_behavior');
        $sslVerifyPath = get_option('staatic_ssl_verify_path');
        $testRequestStatus = get_option(TestRequest::OPTION_NAME);
        $testRequestRuntime = TestRequest::runtime();
        $info['staatic'] = [
            'label' => __('Staatic', 'staatic'),
            'fields' => [
                'version' => [
                    'label' => __('Version', 'staatic'),
                    'value' => \STAATIC_VERSION
                ],
                'site_url' => [
                    'label' => __('Site URL', 'staatic'),
                    'value' => (string) ($this->siteUrlProvider)()
                ],
                'wordpress_url' => [
                    'label' => __('WordPress URL', 'staatic'),
                    'value' => WordpressEnv::getWordpressUrl()
                ],
                'destination_url' => [
                    'label' => __('Destination URL', 'staatic'),
                    'value' => get_option('staatic_destination_url') ?: __('Undefined', 'staatic')
                ],
                'deployment_method' => [
                    'label' => __('Deployment method', 'staatic'),
                    'value' => $deploymentMethod ? ucfirst($deploymentMethod) : __('Undefined', 'staatic')
                ],
                'process_not_found' => [
                    'label' => __('Process "Page not found" resources', 'staatic'),
                    'value' => $processNotFound ? __('Enabled', 'staatic') : __('Disabled', 'staatic')
                ],
                'lowercase_urls' => [
                    'label' => __('Lowercase URLs', 'staatic'),
                    'value' => $lowercaseUrls ? __('Enabled', 'staatic') : __('Disabled', 'staatic')
                ],
                'http_auth_username' => [
                    'label' => __('HTTP auth username', 'staatic'),
                    'value' => get_option('staatic_http_auth_username') ?: __('Undefined', 'staatic')
                ],
                'http_concurrency' => [
                    'label' => __('HTTP concurrency', 'staatic'),
                    'value' => get_option('staatic_http_concurrency') ?: __('Undefined', 'staatic')
                ],
                'http_https_to_http' => [
                    'label' => __('Downgrade HTTPS to HTTP while crawling site', 'staatic'),
                    'value' => $downgradeHttps === null ? __('Undefined', 'staatic') : ($downgradeHttps ? __('Enabled', 'staatic') : __('Disabled', 'staatic'))
                ],
                'http_timeout' => [
                    'label' => __('HTTP timeout', 'staatic'),
                    'value' => get_option('staatic_http_timeout') ?: __('Undefined', 'staatic')
                ],
                'ssl_verify_behavior' => [
                    'label' => __('SSL verification', 'staatic'),
                    'value' => $sslVerifyBehaviors[$sslVerifyBehavior] ?? $sslVerifyBehavior ?? __('Undefined', 'staatic'),
                    'debug' => $sslVerifyBehavior
                ],
                'ssl_verify_path' => [
                    'label' => __('CA bundle path', 'staatic'),
                    'value' => $sslVerifyPath ?: __('Undefined', 'staatic')
                ],
                'html_dom_parser' => [
                    'label' => __('HTML DOM parser', 'staatic'),
                    'value' => $htmlDomParsers[$htmlDomParser] ?? $htmlDomParser ?? __('Undefined', 'staatic')
                ],
                'publication_task_timeout' => [
                    'label' => __('Publication task timeout', 'staatic'),
                    'value' => get_option('staatic_background_process_timeout') ?: __('Undefined', 'staatic')
                ],
                'publication_time_limit' => [
                    'label' => __('Publication time limit', 'staatic'),
                    'value' => get_option('staatic_publication_time_limit') ?: __('Undefined', 'staatic')
                ],
                'test_request_status' => [
                    'label' => __('Publication worker diagnostic', 'staatic'),
                    'value' => $this->formatTestRequestStatus($testRequestStatus),
                    'debug' => $testRequestStatus
                ],
                'test_request_runtime' => [
                    'label' => __('Publication worker diagnostic runtime', 'staatic'),
                    'value' => $this->formatTestRequestRuntime($testRequestRuntime),
                    'debug' => $this->debugValues($testRequestRuntime)
                ],
                'test_request_scope' => [
                    'label' => __('Publication worker diagnostic scope', 'staatic'),
                    'value' => __('Backend-worker survival only; does not observe proxy/client responses or prove CPU-bound capacity', 'staatic')
                ]
            ]
        ];

        return $info;
    }

    private function formatTestRequestStatus($status): string
    {
        $status = TestRequest::status((string) $status);
        if ($status['state'] === TestRequest::STATE_DISABLED) {
            return __('Disabled', 'staatic');
        }
        if ($status['state'] === TestRequest::STATE_NOT_RUN) {
            return __('Not run', 'staatic');
        }
        $date = $this->formatter->shortDate(new DateTimeImmutable("@{$status['start']}"));
        switch ($status['state']) {
            case TestRequest::STATE_RUNNING:
                return sprintf(
                    /* translators: 1: Test request start time. */
                    __('Backend worker running (started %1$s)', 'staatic'),
                    $date
                );
            case TestRequest::STATE_COMPLETE:
                return $this->formatCompleteTestRequestStatus($status, $date);
            case TestRequest::STATE_STALE:
                return sprintf(
                    /* translators: 1: Test request start time. */
                    __('Not recently verified (last run started %1$s)', 'staatic'),
                    $date
                );
            default:
                return sprintf(
                    /* translators: 1: Last observed run time, 2: Test request start time. */
                    __('Incomplete after %1$s (started %2$s)', 'staatic'),
                    $this->formatSeconds($status['duration']),
                    $date
                );
        }
    }

    private function formatCompleteTestRequestStatus(array $status, string $date): string
    {
        if ($status['capped']) {
            return sprintf(
                /* translators: 1: Verified run time, 2: Effective publication task runtime, 3: Test request start time. */
                __('Complete after %1$s; diagnostic capped below the %2$s effective runtime (started %3$s)', 'staatic'),
                $this->formatSeconds($status['duration']),
                $this->formatSeconds($status['effectiveRuntime']),
                $date
            );
        }

        return sprintf(
            /* translators: 1: Run time, 2: Test request start time. */
            __('Complete after %1$s (started %2$s)', 'staatic'),
            $this->formatSeconds($status['duration']),
            $date
        );
    }

    private function formatTestRequestRuntime(array $runtime): string
    {
        if ($runtime['usesFallback']) {
            return sprintf(
                /* translators: 1: Effective fallback runtime, 2: Configured timeout value. */
                __('%1$s effective fallback (configured value: %2$s)', 'staatic'),
                $this->formatSeconds($runtime['effectiveRuntime']),
                $this->formatter->number($runtime['configuredRuntime'])
            );
        }
        if ($runtime['capped']) {
            return sprintf(
                /* translators: 1: Diagnostic cap, 2: Effective publication task runtime. */
                __('%1$s diagnostic cap (effective runtime: %2$s)', 'staatic'),
                $this->formatSeconds($runtime['targetRuntime']),
                $this->formatSeconds($runtime['effectiveRuntime'])
            );
        }

        return $this->formatSeconds($runtime['targetRuntime']);
    }

    private function formatSeconds(int $seconds): string
    {
        return $this->formatter->seconds($seconds);
    }

    /**
     * Site Health renders a debug array by printing each value, and a boolean false prints as an
     * empty string — so `usesFallback: ` in a pasted report says nothing at all, and `capped: 1`
     * says less than it should. Booleans are spelled out; everything else is left as it is.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function debugValues(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_bool($value)) {
                $values[$key] = $this->formatter->yesNo($value);
            }
        }

        return $values;
    }
}
