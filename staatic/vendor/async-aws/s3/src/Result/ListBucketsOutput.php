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
    private $prefix;
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
            if (null !== $page->continuationToken) {
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
    public function getPrefix(): ?string
    {
        $this->initialize();
        return $this->prefix;
    }
    /**
     * @param Response $response
     */
    protected function populateResult($response): void
    {
        $data = new SimpleXMLElement($response->getContent());
        $this->buckets = 0 === ($v = $data->Buckets)->count() ? [] : $this->populateResultBuckets($v);
        $this->owner = 0 === $data->Owner->count() ? null : $this->populateResultOwner($data->Owner);
        $this->continuationToken = null !== ($v = $data->ContinuationToken[0]) ? (string) $v : null;
        $this->prefix = null !== ($v = $data->Prefix[0]) ? (string) $v : null;
    }
    private function populateResultBucket(SimpleXMLElement $xml): Bucket
    {
        return new Bucket(['Name' => null !== ($v = $xml->Name[0]) ? (string) $v : null, 'CreationDate' => null !== ($v = $xml->CreationDate[0]) ? new DateTimeImmutable((string) $v) : null, 'BucketRegion' => null !== ($v = $xml->BucketRegion[0]) ? (string) $v : null]);
    }
    private function populateResultBuckets(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml->Bucket as $item) {
            $items[] = $this->populateResultBucket($item);
        }
        return $items;
    }
    private function populateResultOwner(SimpleXMLElement $xml): Owner
    {
        return new Owner(['DisplayName' => null !== ($v = $xml->DisplayName[0]) ? (string) $v : null, 'ID' => null !== ($v = $xml->ID[0]) ? (string) $v : null]);
    }
}
