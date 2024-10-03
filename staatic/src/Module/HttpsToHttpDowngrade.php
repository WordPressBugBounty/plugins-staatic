<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module;

use Staatic\WordPress\Setting\Advanced\HttpToHttpsSetting;

/**
 * This module ensures that internal HTTP requests by the background publisher are
 * forced to the http scheme when http to https setting is enabled.
 *
 * It also tricks WordPress into thinking any request is served over HTTPS when
 * requested by the crawler.
 */
final class HttpsToHttpDowngrade implements ModuleInterface
{
    /**
     * @var bool
     */
    private $httpToHttps;

    public function __construct(HttpToHttpsSetting $httpToHttps)
    {
        $this->httpToHttps = $httpToHttps->value();
        if ($this->shouldEmulateHttps()) {
            $_SERVER['HTTPS'] = \true;
        }
    }

    public function hooks(): void
    {
        add_filter('staatic_background_publisher_query_url', [$this, 'updateQueryUrl'], 10);
    }

    /**
     * @param string $url
     */
    public function updateQueryUrl($url): string
    {
        if ($this->httpToHttps) {
            $url = preg_replace('~^https~', 'http', $url);
        }

        return $url;
    }

    private function shouldEmulateHttps(): bool
    {
        return $this->httpToHttps && isset($_SERVER['HTTP_X_STAATIC_REQUEST']);
    }
}
