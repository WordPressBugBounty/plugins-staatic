<?php

namespace Staatic\Crawler\KnownUrlsContainer;

use Countable;
use Staatic\Vendor\Psr\Http\Message\UriInterface;
interface KnownUrlsContainerInterface extends Countable
{
    public function clear(): void;
    /**
     * @param UriInterface $url
     */
    public function add($url): void;
    /**
     * @param UriInterface $url
     */
    public function addUncrawlable($url): void;
    /**
     * @param mixed[] $urls
     * @param bool $crawlable
     */
    public function addMany($urls, $crawlable): void;
    /**
     * @param UriInterface $url
     */
    public function isKnown($url): bool;
    public function flush(): void;
    public function count(): int;
}
