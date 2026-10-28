<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module\Integration;

use Staatic\Vendor\GuzzleHttp\Psr7\Exception\MalformedUriException;
use Staatic\Crawler\CrawlUrlProvider\AdditionalUrlCrawlUrlProvider\AdditionalUrl;
use Staatic\WordPress\Module\ModuleInterface;

final class YoastPlugin implements ModuleInterface
{
    public function hooks(): void
    {
        add_action('wp_loaded', [$this, 'setupIntegration']);
    }

    public function setupIntegration(): void
    {
        if (!defined('Staatic\Vendor\WPSEO_VERSION')) {
            return;
        }
        add_filter('staatic_additional_urls', [$this, 'addSchemaLogoUrl']);
        add_filter('staatic_partial_publication_additional_urls', [$this, 'addSchemaLogoUrl']);
    }

    /**
     * The schema logo is usually not linked from any page, so the crawler would not find it.
     *
     * @param AdditionalUrl[] $additionalUrls
     */
    public function addSchemaLogoUrl($additionalUrls): array
    {
        $titles = get_option('wpseo_titles');
        if (!is_array($titles)) {
            return $additionalUrls;
        }
        $setting = ($titles['company_or_person'] ?? '') === 'person' ? 'person_logo' : 'company_logo';
        // The schema prints the cached meta URL; before Yoast caches it, the original is used.
        $url = $titles[$setting . '_meta']['url'] ?? null ?: $titles[$setting] ?? null;
        if (!is_string($url) || $url === '') {
            return $additionalUrls;
        }

        try {
            $additionalUrls[] = new AdditionalUrl($url);
        } catch (MalformedUriException $exception) {
            // A malformed setting must not stop the publication.
        }

        return $additionalUrls;
    }
}
