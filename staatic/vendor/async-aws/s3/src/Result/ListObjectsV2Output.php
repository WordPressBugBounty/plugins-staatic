<?php

namespace Staatic\Vendor\AsyncAws\S3\Result;

use IteratorAggregate;
use Traversable;
use SimpleXMLElement;
use DateTimeImmutable;
use Staatic\Vendor\AsyncAws\Core\Exception\InvalidArgument;
use Staatic\Vendor\AsyncAws\Core\Response;
use Staatic\Vendor\AsyncAws\Core\Result;
use Staatic\Vendor\AsyncAws\S3\Enum\ChecksumAlgorithm;
use Staatic\Vendor\AsyncAws\S3\Enum\EncodingType;
use Staatic\Vendor\AsyncAws\S3\Enum\RequestCharged;
use Staatic\Vendor\AsyncAws\S3\Input\ListObjectsV2Request;
use Staatic\Vendor\AsyncAws\S3\S3Client;
use Staatic\Vendor\AsyncAws\S3\ValueObject\AwsObject;
use Staatic\Vendor\AsyncAws\S3\ValueObject\CommonPrefix;
use Staatic\Vendor\AsyncAws\S3\ValueObject\Owner;
use Staatic\Vendor\AsyncAws\S3\ValueObject\RestoreStatus;
class ListObjectsV2Output extends Result implements IteratorAggregate
{
    private $isTruncated;
    private $contents;
    private $name;
    private $prefix;
    private $delimiter;
    private $maxKeys;
    private $commonPrefixes;
    private $encodingType;
    private $keyCount;
    private $continuationToken;
    private $nextContinuationToken;
    private $startAfter;
    private $requestCharged;
    /**
     * @param bool $currentPageOnly
     */
    public function getCommonPrefixes($currentPageOnly = \false): iterable
    {
        if ($currentPageOnly) {
            $this->initialize();
            yield from $this->commonPrefixes;
            return;
        }
        $client = $this->awsClient;
        if (!$client instanceof S3Client) {
            throw new InvalidArgument('missing client injected in paginated result');
        }
        if (!$this->input instanceof ListObjectsV2Request) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (\true) {
            $page->initialize();
            if (null !== $page->nextContinuationToken) {
                $input->setContinuationToken($page->nextContinuationToken);
                $this->registerPrefetch($nextPage = $client->listObjectsV2($input));
            } else {
                $nextPage = null;
            }
            yield from $page->commonPrefixes;
            if (null === $nextPage) {
                break;
            }
            $this->unregisterPrefetch($nextPage);
            $page = $nextPage;
        }
    }
    /**
     * @param bool $currentPageOnly
     */
    public function getContents($currentPageOnly = \false): iterable
    {
        if ($currentPageOnly) {
            $this->initialize();
            yield from $this->contents;
            return;
        }
        $client = $this->awsClient;
        if (!$client instanceof S3Client) {
            throw new InvalidArgument('missing client injected in paginated result');
        }
        if (!$this->input instanceof ListObjectsV2Request) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (\true) {
            $page->initialize();
            if (null !== $page->nextContinuationToken) {
                $input->setContinuationToken($page->nextContinuationToken);
                $this->registerPrefetch($nextPage = $client->listObjectsV2($input));
            } else {
                $nextPage = null;
            }
            yield from $page->contents;
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
    public function getDelimiter(): ?string
    {
        $this->initialize();
        return $this->delimiter;
    }
    public function getEncodingType(): ?string
    {
        $this->initialize();
        return $this->encodingType;
    }
    public function getIsTruncated(): ?bool
    {
        $this->initialize();
        return $this->isTruncated;
    }
    public function getIterator(): Traversable
    {
        $client = $this->awsClient;
        if (!$client instanceof S3Client) {
            throw new InvalidArgument('missing client injected in paginated result');
        }
        if (!$this->input instanceof ListObjectsV2Request) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (\true) {
            $page->initialize();
            if (null !== $page->nextContinuationToken) {
                $input->setContinuationToken($page->nextContinuationToken);
                $this->registerPrefetch($nextPage = $client->listObjectsV2($input));
            } else {
                $nextPage = null;
            }
            yield from $page->getContents(\true);
            yield from $page->getCommonPrefixes(\true);
            if (null === $nextPage) {
                break;
            }
            $this->unregisterPrefetch($nextPage);
            $page = $nextPage;
        }
    }
    public function getKeyCount(): ?int
    {
        $this->initialize();
        return $this->keyCount;
    }
    public function getMaxKeys(): ?int
    {
        $this->initialize();
        return $this->maxKeys;
    }
    public function getName(): ?string
    {
        $this->initialize();
        return $this->name;
    }
    public function getNextContinuationToken(): ?string
    {
        $this->initialize();
        return $this->nextContinuationToken;
    }
    public function getPrefix(): ?string
    {
        $this->initialize();
        return $this->prefix;
    }
    public function getRequestCharged(): ?string
    {
        $this->initialize();
        return $this->requestCharged;
    }
    public function getStartAfter(): ?string
    {
        $this->initialize();
        return $this->startAfter;
    }
    /**
     * @param Response $response
     */
    protected function populateResult($response): void
    {
        $headers = $response->getHeaders();
        $this->requestCharged = $headers['x-amz-request-charged'][0] ?? null;
        $data = new SimpleXMLElement($response->getContent());
        $this->isTruncated = null !== ($v = $data->IsTruncated[0]) ? filter_var((string) $v, \FILTER_VALIDATE_BOOLEAN) : null;
        $this->contents = 0 === ($v = $data->Contents)->count() ? [] : $this->populateResultObjectList($v);
        $this->name = null !== ($v = $data->Name[0]) ? (string) $v : null;
        $this->prefix = null !== ($v = $data->Prefix[0]) ? (string) $v : null;
        $this->delimiter = null !== ($v = $data->Delimiter[0]) ? (string) $v : null;
        $this->maxKeys = null !== ($v = $data->MaxKeys[0]) ? (int) (string) $v : null;
        $this->commonPrefixes = 0 === ($v = $data->CommonPrefixes)->count() ? [] : $this->populateResultCommonPrefixList($v);
        $this->encodingType = null !== ($v = $data->EncodingType[0]) ? (string) $v : null;
        $this->keyCount = null !== ($v = $data->KeyCount[0]) ? (int) (string) $v : null;
        $this->continuationToken = null !== ($v = $data->ContinuationToken[0]) ? (string) $v : null;
        $this->nextContinuationToken = null !== ($v = $data->NextContinuationToken[0]) ? (string) $v : null;
        $this->startAfter = null !== ($v = $data->StartAfter[0]) ? (string) $v : null;
    }
    private function populateResultChecksumAlgorithmList(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = (string) $item;
        }
        return $items;
    }
    private function populateResultCommonPrefix(SimpleXMLElement $xml): CommonPrefix
    {
        return new CommonPrefix(['Prefix' => null !== ($v = $xml->Prefix[0]) ? (string) $v : null]);
    }
    private function populateResultCommonPrefixList(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = $this->populateResultCommonPrefix($item);
        }
        return $items;
    }
    private function populateResultObject(SimpleXMLElement $xml): AwsObject
    {
        return new AwsObject(['Key' => null !== ($v = $xml->Key[0]) ? (string) $v : null, 'LastModified' => null !== ($v = $xml->LastModified[0]) ? new DateTimeImmutable((string) $v) : null, 'ETag' => null !== ($v = $xml->ETag[0]) ? (string) $v : null, 'ChecksumAlgorithm' => 0 === ($v = $xml->ChecksumAlgorithm)->count() ? null : $this->populateResultChecksumAlgorithmList($v), 'Size' => null !== ($v = $xml->Size[0]) ? (int) (string) $v : null, 'StorageClass' => null !== ($v = $xml->StorageClass[0]) ? (string) $v : null, 'Owner' => 0 === $xml->Owner->count() ? null : $this->populateResultOwner($xml->Owner), 'RestoreStatus' => 0 === $xml->RestoreStatus->count() ? null : $this->populateResultRestoreStatus($xml->RestoreStatus)]);
    }
    private function populateResultObjectList(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = $this->populateResultObject($item);
        }
        return $items;
    }
    private function populateResultOwner(SimpleXMLElement $xml): Owner
    {
        return new Owner(['DisplayName' => null !== ($v = $xml->DisplayName[0]) ? (string) $v : null, 'ID' => null !== ($v = $xml->ID[0]) ? (string) $v : null]);
    }
    private function populateResultRestoreStatus(SimpleXMLElement $xml): RestoreStatus
    {
        return new RestoreStatus(['IsRestoreInProgress' => null !== ($v = $xml->IsRestoreInProgress[0]) ? filter_var((string) $v, \FILTER_VALIDATE_BOOLEAN) : null, 'RestoreExpiryDate' => null !== ($v = $xml->RestoreExpiryDate[0]) ? new DateTimeImmutable((string) $v) : null]);
    }
}
