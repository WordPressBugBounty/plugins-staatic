<?php

declare(strict_types=1);

namespace Staatic\WordPress\Bridge;

use Staatic\Vendor\Psr\Http\Message\UriInterface;
use Staatic\Crawler\UrlTransformer\UrlTransformerInterface;
use Staatic\Crawler\UrlTransformer\UrlTransformation;

final class FilterableUrlTransformer implements UrlTransformerInterface
{
    /**
     * @var UrlTransformerInterface
     */
    private $innerTransformer;

    public function __construct(UrlTransformerInterface $innerTransformer)
    {
        $this->innerTransformer = $innerTransformer;
    }

    /**
     * @param UriInterface $url
     * @param UriInterface|null $foundOnUrl
     * @param mixed[] $context
     */
    public function transform($url, $foundOnUrl = null, $context = []): UrlTransformation
    {
        /**
         * Filter whether a URL should be transformed.
         *
         * This filter allows fine-grained control over URL transformation separate from
         * the crawling decision. When this filter returns false, the URL will remain
         * unchanged in the static output, even if it was crawled.
         *
         * Common use cases:
         * - Keep canonical URLs absolute for SEO
         * - Keep Open Graph URLs absolute for social media
         * - Keep schema.org/JSON-LD URLs absolute
         * - Selectively apply CDN transformation
         *
         * @since 1.12.0
         *
         * @param bool $shouldTransform Whether the URL should be transformed (default: true)
         * @param UriInterface $url The resolved URL being considered for transformation
         * @param UriInterface|null $foundOnUrl The URL where this URL was found
         * @param array $context {
         *     Context information about the URL.
         *
         *     @type string $extractor The extractor class that found this URL
         *     @type string $htmlTagName The HTML tag name (e.g., 'link', 'a', 'img') if applicable
         *     @type string $htmlAttributeName The HTML attribute (e.g., 'href', 'src') if applicable
         *     @type string $htmlElement The full HTML element if extendedUrlContext is enabled
         *     @type string $before Text before the URL if extendedUrlContext is enabled (FallbackUrlExtractor)
         *     @type string $after Text after the URL if extendedUrlContext is enabled (FallbackUrlExtractor)
         * }
         */
        $shouldTransform = apply_filters('staatic_should_transform_url', \true, $url, $foundOnUrl, $context);
        if (!$shouldTransform) {
            // Return unchanged URL when transformation is prevented by filter
            return new UrlTransformation($url, $url);
        }

        // Proceed with normal transformation
        return $this->innerTransformer->transform($url, $foundOnUrl, $context);
    }
}
