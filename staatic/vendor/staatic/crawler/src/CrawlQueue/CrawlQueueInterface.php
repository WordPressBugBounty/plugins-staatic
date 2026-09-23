<?php

namespace Staatic\Crawler\CrawlQueue;

use Countable;
use Staatic\Crawler\CrawlUrl;
interface CrawlQueueInterface extends Countable
{
    public function clear(): void;
    /**
     * @param CrawlUrl $crawlUrl
     * @param int $priority
     */
    public function enqueue($crawlUrl, $priority): void;
    public function dequeue(): CrawlUrl;
    /**
     * @param int $limit
     */
    public function dequeueMany($limit): array;
    public function isEmpty(): bool;
}
