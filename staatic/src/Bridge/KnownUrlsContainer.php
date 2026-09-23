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
     * Caps the number of rows in a single INSERT IGNORE statement. Per-response flushes are
     * already bounded (~200 links per page), but Crawler::initialize()'s flush emits one
     * insertMany() call per provided-url batch: an additional_paths directory scan can hand it
     * tens of thousands of urls in one call, and one unchunked statement for all of them risks
     * max_allowed_packet (1-16 MB depending on host) and a prepare() over a very large arg list.
     * Matches UploadsIndexRepository::UPSERT_BATCH_SIZE.
     */
    private const INSERT_CHUNK_SIZE = 1000;

    /**
     * @var string
     */
    private $tableName;

    /**
     * Every hash this process has established as known, whether that came from a DB hit
     * (isKnown(), filled in on read) or from an add()/addMany() call in this process
     * (write-through). Consulted before ever going to the database: on a page with ~200 links,
     * this is what turns most isKnown() calls into an array lookup instead of a round trip.
     * Bounded only by the length of the run - about 40 bytes/hash, so ~3 MB for OneDesk's ~76k
     * known urls - which is fine for a single crawl process and is never persisted.
     *
     * @var array<string, true>
     */
    private $localHashes = [];

    /** @var array<string, true> Hashes buffered by addMany() as crawlable, pending flush(). */
    private $pendingCrawlable = [];

    /** @var array<string, true> Hashes buffered by addMany() as uncrawlable, pending flush(). */
    private $pendingUncrawlable = [];

    /**
     * The crawlable total, maintained instead of recomputed. Seeded once from the table here so
     * a fresh worker (a new wp-admin request, or a new CLI process) starts from the real total
     * rather than zero; from there it only moves by what this process itself adds. wp-admin
     * publications hold a lock so only one worker touches this table at a time, which is what
     * keeps that seed-then-increment approach from drifting across workers.
     * @var int
     */
    private $crawlableCount;

    public function __construct(wpdb $wpdb, string $tableName = 'staatic_known_urls')
    {
        $this->wpdb = $wpdb;
        $this->logger = new NullLogger();
        $this->tableName = $wpdb->prefix . $tableName;
        $this->crawlableCount = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tableName} WHERE crawlable = 1"
        );
    }

    public function clear(): void
    {
        $this->logger->debug('Clearing container');
        $result = $this->wpdb->query("DELETE FROM {$this->tableName}");
        if ($result === \false) {
            throw new RuntimeException("Unable to clear container: {$this->wpdb->last_error}");
        }
        $this->localHashes = [];
        $this->pendingCrawlable = [];
        $this->pendingUncrawlable = [];
        $this->crawlableCount = 0;
    }

    /**
     * @param UriInterface $url
     */
    public function add($url): void
    {
        $this->addMany([$url], \true);
    }

    /**
     * @param UriInterface $url
     */
    public function addUncrawlable($url): void
    {
        $this->addMany([$url], \false);
    }

    /**
     * @param mixed[] $urls
     * @param bool $crawlable
     */
    public function addMany($urls, $crawlable): void
    {
        foreach ($urls as $url) {
            $hash = md5((string) $url);
            if (isset($this->localHashes[$hash])) {
                continue;
            }
            $this->logger->debug("Adding url '{$url}' to container");
            // Write-through: known immediately, in this process, regardless of when the
            // physical insert happens below.
            $this->localHashes[$hash] = \true;
            if ($crawlable) {
                $this->pendingCrawlable[$hash] = \true;
                // Optimistic: isKnown() already keeps addMany() from being asked to add the same
                // hash twice in the normal crawl path, so the insert below is expected to
                // succeed. flush() corrects this count down if the server's affected-row count
                // says otherwise - the only way that happens is a hash another worker inserted
                // between this process's isKnown() check and this flush(), which the single-
                // worker-at-a-time lock is what actually keeps rare.
                $this->crawlableCount++;
            } else {
                $this->pendingUncrawlable[$hash] = \true;
            }
        }
    }

    /**
     * @param UriInterface $url
     */
    public function isKnown($url): bool
    {
        $hash = md5((string) $url);
        if (isset($this->localHashes[$hash])) {
            return \true;
        }
        $isKnown = (bool) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT 1 FROM {$this->tableName} WHERE hash = %s LIMIT 1", $hash)
        );
        if ($isKnown) {
            // Fill-on-read: a hash another worker (or an earlier flush) already stored is known
            // from here on without asking the database again.
            $this->localHashes[$hash] = \true;
        }

        return $isKnown;
    }

    public function flush(): void
    {
        if ($this->pendingCrawlable !== []) {
            $inserted = $this->insertMany(array_keys($this->pendingCrawlable), \true);
            $numPending = count($this->pendingCrawlable);
            if ($inserted < $numPending) {
                // A duplicate the server rejected under IGNORE was still counted optimistically
                // above; correct for exactly the rows that were not actually new. This cannot
                // drive the count negative: $numPending hashes were added to $crawlableCount by
                // addMany() above (one increment per hash, this same batch) and nothing else
                // decrements it except this correction, which subtracts at most $numPending. The
                // one thing that resets both together is clear(), and it zeroes pendingCrawlable
                // in the same call, so a flush() reaching here always has its own numPending
                // still reflected in the count.
                $this->crawlableCount -= $numPending - $inserted;
            }
            $this->pendingCrawlable = [];
        }
        if ($this->pendingUncrawlable !== []) {
            $this->insertMany(array_keys($this->pendingUncrawlable), \false);
            $this->pendingUncrawlable = [];
        }
    }

    /**
     * @param string[] $hashes
     */
    private function insertMany(array $hashes, bool $crawlable): int
    {
        $inserted = 0;
        foreach (array_chunk($hashes, self::INSERT_CHUNK_SIZE) as $chunk) {
            $inserted += $this->insertChunk($chunk, $crawlable);
        }

        return $inserted;
    }

    /**
     * @param string[] $hashes
     */
    private function insertChunk(array $hashes, bool $crawlable): int
    {
        $rowPlaceholders = implode(', ', array_fill(0, count($hashes), '(%s, %d)'));
        $values = [];
        foreach ($hashes as $hash) {
            $values[] = $hash;
            $values[] = $crawlable ? 1 : 0;
        }
        $result = $this->wpdb->query(
            $this->wpdb->prepare("INSERT IGNORE INTO {$this->tableName} (hash, crawlable) VALUES {$rowPlaceholders}", $values)
        );
        if ($result === \false) {
            throw new RuntimeException("Unable to add urls to container: {$this->wpdb->last_error}");
        }

        return is_int($result) ? $result : count($hashes);
    }

    /**
     * Counts only the urls that stand for a request the crawler is going to make. Urls added
     * through addUncrawlable() - media registered straight from disk by Uploads Sync - are
     * known so the crawler skips them, but they are not work that is left to do.
     */
    public function count(): int
    {
        return $this->crawlableCount;
    }
}
