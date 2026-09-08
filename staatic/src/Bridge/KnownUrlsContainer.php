<?php

declare(strict_types=1);

namespace Staatic\WordPress\Bridge;

use Staatic\Vendor\Psr\Http\Message\UriInterface;
use Staatic\Vendor\Psr\Log\LoggerAwareInterface;
use Staatic\Vendor\Psr\Log\LoggerAwareTrait;
use Staatic\Vendor\Psr\Log\NullLogger;
use RuntimeException;
use Staatic\Crawler\KnownUrlsContainer\KnownUrlsContainerInterface;
use wpdb;

final class KnownUrlsContainer implements KnownUrlsContainerInterface, LoggerAwareInterface
{
    /**
     * @var wpdb
     */
    private $wpdb;

    use LoggerAwareTrait;

    /**
     * @var string
     */
    private $tableName;

    public function __construct(wpdb $wpdb, string $tableName = 'staatic_known_urls')
    {
        $this->wpdb = $wpdb;
        $this->logger = new NullLogger();
        $this->tableName = $wpdb->prefix . $tableName;
    }

    public function clear(): void
    {
        $this->logger->debug('Clearing container');
        $result = $this->wpdb->query("DELETE FROM {$this->tableName}");
        if ($result === \false) {
            throw new RuntimeException("Unable to clear container: {$this->wpdb->last_error}");
        }
    }

    /**
     * @param UriInterface $url
     */
    public function add($url): void
    {
        $this->addUrl($url, \true);
    }

    /**
     * @param UriInterface $url
     */
    public function addUncrawlable($url): void
    {
        $this->addUrl($url, \false);
    }

    private function addUrl(UriInterface $url, bool $crawlable): void
    {
        $this->logger->debug("Adding url '{$url}' to container");
        $result = $this->wpdb->insert($this->tableName, [
            'hash' => md5((string) $url),
            'crawlable' => $crawlable ? 1 : 0
        ]);
        if ($result === \false) {
            throw new RuntimeException("Unable to add url '{$url}' to container: {$this->wpdb->last_error}");
        }
    }

    /**
     * @param UriInterface $url
     */
    public function isKnown($url): bool
    {
        return (bool) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->tableName} WHERE hash = %s", md5((string) $url))
        );
    }

    /**
     * Counts only the urls that stand for a request the crawler is going to make. Urls added
     * through addUncrawlable() - media registered straight from disk by Uploads Sync - are
     * known so the crawler skips them, but they are not work that is left to do.
     */
    public function count(): int
    {
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tableName} WHERE crawlable = 1");
    }
}
