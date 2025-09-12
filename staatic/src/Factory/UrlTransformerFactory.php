<?php

declare(strict_types=1);

namespace Staatic\WordPress\Factory;

use Staatic\Vendor\GuzzleHttp\Psr7\Uri;
use Staatic\Crawler\UrlTransformer\UrlTransformerInterface;
use Staatic\Vendor\Psr\Http\Message\UriInterface;
use Staatic\Crawler\UrlTransformer\OfflineUrlTransformer;
use Staatic\Crawler\UrlTransformer\StandardUrlTransformer;
use Staatic\WordPress\Bridge\FilterableUrlTransformer;
use Staatic\WordPress\Service\SiteUrlProvider;
use Staatic\WordPress\Setting\Build\DestinationUrlSetting;

final class UrlTransformerFactory
{
    /**
     * @var SiteUrlProvider
     */
    private $siteUrlProvider;

    /**
     * @var DestinationUrlSetting
     */
    private $destinationUrl;

    public function __construct(SiteUrlProvider $siteUrlProvider, DestinationUrlSetting $destinationUrl)
    {
        $this->siteUrlProvider = $siteUrlProvider;
        $this->destinationUrl = $destinationUrl;
    }

    public function __invoke(
        ?UriInterface $baseUrl = null,
        ?UriInterface $destinationUrl = null
    ): UrlTransformerInterface
    {
        if ($destinationUrl === null) {
            $destinationUrl = new Uri($this->destinationUrl->value());
        }
        $destinationUrlStr = (string) $destinationUrl;
        if ($destinationUrlStr === '' || $destinationUrlStr === '#no-index') {
            $transformer = new OfflineUrlTransformer($destinationUrlStr === '');
        } else {
            if ($baseUrl === null) {
                $baseUrl = ($this->siteUrlProvider)();
            }
            $transformer = new StandardUrlTransformer($baseUrl, $destinationUrl);
        }
        if (has_filter('staatic_should_transform_url')) {
            $transformer = new FilterableUrlTransformer($transformer);
        }

        return $transformer;
    }
}
