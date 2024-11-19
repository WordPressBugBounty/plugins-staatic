<?php

namespace Staatic\Vendor\AsyncAws\S3\Result;

use IteratorAggregate;
use Traversable;
use SimpleXMLElement;
use DateTimeImmutable;
use Staatic\Vendor\AsyncAws\Core\Exception\InvalidArgument;
use Staatic\Vendor\AsyncAws\Core\Response;
use Staatic\Vendor\AsyncAws\Core\Result;
use Staatic\Vendor\AsyncAws\S3\Input\ListBucketsRequest;
use Staatic\Vendor\AsyncAws\S3\S3Client;
use Staatic\Vendor\AsyncAws\S3\ValueObject\Bucket;
use Staatic\Vendor\AsyncAws\S3\ValueObject\Owner;
class ListBucketsOutput extends Result implements IteratorAggregate
{
    private $buckets;
    private $owner;
    private $continuationToken;
    /**
     * @param bool $currentPageOnly
     */
    public function getBuckets($currentPageOnly = \false): iterable
    {
        if ($currentPageOnly) {
            $this->initialize();
            yield from $this->buckets;
            return;
        }
        $client = $this->awsClient;
        if (!$client instanceof S3Client) {
            throw new InvalidArgument('missing client injected in paginated result');
        }
        if (!$this->input instanceof ListBucketsRequest) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (\true) {
            $page->initialize();
            if ($page->continuationToken) {
                $input->setContinuationToken($page->continuationToken);
                $this->registerPrefetch($nextPage = $client->listBuckets($input));
            } else {
                $nextPage = null;
            }
            yield from $page->buckets;
            if (null === $nextPage) {
                break;
            }
            $this->unregisterPrefetch($nextPage);
            $page = $nextPage;
        }
    }
    public function getContinuationToken(): ?string
    {
        $this->initialize();
        return $this->continuationToken;
    }
    public function getIterator(): Traversable
    {
        yield from $this->getBuckets();
    }
    public function getOwner(): ?Owner
    {
        $this->initialize();
        return $this->owner;
    }
    /**
     * @param Response $response
     */
    protected function populateResult($response): void
    {
        $data = new SimpleXMLElement($response->getContent());
        $this->buckets = (!$data->Buckets) ? [] : $this->populateResultBuckets($data->Buckets);
        $this->owner = (!$data->Owner) ? null : new Owner(['DisplayName' => ($v = $data->Owner->DisplayName) ? (string) $v : null, 'ID' => ($v = $data->Owner->ID) ? (string) $v : null]);
        $this->continuationToken = ($v = $data->ContinuationToken) ? (string) $v : null;
    }
    private function populateResultBuckets(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml->Bucket as $item) {
            $items[] = new Bucket(['Name' => ($v = $item->Name) ? (string) $v : null, 'CreationDate' => ($v = $item->CreationDate) ? new DateTimeImmutable((string) $v) : null]);
        }
        return $items;
    }
}
