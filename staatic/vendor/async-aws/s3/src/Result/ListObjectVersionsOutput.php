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
use Staatic\Vendor\AsyncAws\S3\Input\ListObjectVersionsRequest;
use Staatic\Vendor\AsyncAws\S3\S3Client;
use Staatic\Vendor\AsyncAws\S3\ValueObject\CommonPrefix;
use Staatic\Vendor\AsyncAws\S3\ValueObject\DeleteMarkerEntry;
use Staatic\Vendor\AsyncAws\S3\ValueObject\ObjectVersion;
use Staatic\Vendor\AsyncAws\S3\ValueObject\Owner;
use Staatic\Vendor\AsyncAws\S3\ValueObject\RestoreStatus;
class ListObjectVersionsOutput extends Result implements IteratorAggregate
{
    private $isTruncated;
    private $keyMarker;
    private $versionIdMarker;
    private $nextKeyMarker;
    private $nextVersionIdMarker;
    private $versions;
    private $deleteMarkers;
    private $name;
    private $prefix;
    private $delimiter;
    private $maxKeys;
    private $commonPrefixes;
    private $encodingType;
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
        if (!$this->input instanceof ListObjectVersionsRequest) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (\true) {
            $page->initialize();
            if ($page->isTruncated) {
                $input->setKeyMarker($page->nextKeyMarker);
                $input->setVersionIdMarker($page->nextVersionIdMarker);
                $this->registerPrefetch($nextPage = $client->listObjectVersions($input));
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
    public function getDeleteMarkers($currentPageOnly = \false): iterable
    {
        if ($currentPageOnly) {
            $this->initialize();
            yield from $this->deleteMarkers;
            return;
        }
        $client = $this->awsClient;
        if (!$client instanceof S3Client) {
            throw new InvalidArgument('missing client injected in paginated result');
        }
        if (!$this->input instanceof ListObjectVersionsRequest) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (\true) {
            $page->initialize();
            if ($page->isTruncated) {
                $input->setKeyMarker($page->nextKeyMarker);
                $input->setVersionIdMarker($page->nextVersionIdMarker);
                $this->registerPrefetch($nextPage = $client->listObjectVersions($input));
            } else {
                $nextPage = null;
            }
            yield from $page->deleteMarkers;
            if (null === $nextPage) {
                break;
            }
            $this->unregisterPrefetch($nextPage);
            $page = $nextPage;
        }
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
        if (!$this->input instanceof ListObjectVersionsRequest) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (\true) {
            $page->initialize();
            if ($page->isTruncated) {
                $input->setKeyMarker($page->nextKeyMarker);
                $input->setVersionIdMarker($page->nextVersionIdMarker);
                $this->registerPrefetch($nextPage = $client->listObjectVersions($input));
            } else {
                $nextPage = null;
            }
            yield from $page->getVersions(\true);
            yield from $page->getDeleteMarkers(\true);
            yield from $page->getCommonPrefixes(\true);
            if (null === $nextPage) {
                break;
            }
            $this->unregisterPrefetch($nextPage);
            $page = $nextPage;
        }
    }
    public function getKeyMarker(): ?string
    {
        $this->initialize();
        return $this->keyMarker;
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
    public function getNextKeyMarker(): ?string
    {
        $this->initialize();
        return $this->nextKeyMarker;
    }
    public function getNextVersionIdMarker(): ?string
    {
        $this->initialize();
        return $this->nextVersionIdMarker;
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
    public function getVersionIdMarker(): ?string
    {
        $this->initialize();
        return $this->versionIdMarker;
    }
    /**
     * @param bool $currentPageOnly
     */
    public function getVersions($currentPageOnly = \false): iterable
    {
        if ($currentPageOnly) {
            $this->initialize();
            yield from $this->versions;
            return;
        }
        $client = $this->awsClient;
        if (!$client instanceof S3Client) {
            throw new InvalidArgument('missing client injected in paginated result');
        }
        if (!$this->input instanceof ListObjectVersionsRequest) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (\true) {
            $page->initialize();
            if ($page->isTruncated) {
                $input->setKeyMarker($page->nextKeyMarker);
                $input->setVersionIdMarker($page->nextVersionIdMarker);
                $this->registerPrefetch($nextPage = $client->listObjectVersions($input));
            } else {
                $nextPage = null;
            }
            yield from $page->versions;
            if (null === $nextPage) {
                break;
            }
            $this->unregisterPrefetch($nextPage);
            $page = $nextPage;
        }
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
        $this->keyMarker = null !== ($v = $data->KeyMarker[0]) ? (string) $v : null;
        $this->versionIdMarker = null !== ($v = $data->VersionIdMarker[0]) ? (string) $v : null;
        $this->nextKeyMarker = null !== ($v = $data->NextKeyMarker[0]) ? (string) $v : null;
        $this->nextVersionIdMarker = null !== ($v = $data->NextVersionIdMarker[0]) ? (string) $v : null;
        $this->versions = 0 === ($v = $data->Version)->count() ? [] : $this->populateResultObjectVersionList($v);
        $this->deleteMarkers = 0 === ($v = $data->DeleteMarker)->count() ? [] : $this->populateResultDeleteMarkers($v);
        $this->name = null !== ($v = $data->Name[0]) ? (string) $v : null;
        $this->prefix = null !== ($v = $data->Prefix[0]) ? (string) $v : null;
        $this->delimiter = null !== ($v = $data->Delimiter[0]) ? (string) $v : null;
        $this->maxKeys = null !== ($v = $data->MaxKeys[0]) ? (int) (string) $v : null;
        $this->commonPrefixes = 0 === ($v = $data->CommonPrefixes)->count() ? [] : $this->populateResultCommonPrefixList($v);
        $this->encodingType = null !== ($v = $data->EncodingType[0]) ? (string) $v : null;
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
    private function populateResultDeleteMarkerEntry(SimpleXMLElement $xml): DeleteMarkerEntry
    {
        return new DeleteMarkerEntry(['Owner' => 0 === $xml->Owner->count() ? null : $this->populateResultOwner($xml->Owner), 'Key' => null !== ($v = $xml->Key[0]) ? (string) $v : null, 'VersionId' => null !== ($v = $xml->VersionId[0]) ? (string) $v : null, 'IsLatest' => null !== ($v = $xml->IsLatest[0]) ? filter_var((string) $v, \FILTER_VALIDATE_BOOLEAN) : null, 'LastModified' => null !== ($v = $xml->LastModified[0]) ? new DateTimeImmutable((string) $v) : null]);
    }
    private function populateResultDeleteMarkers(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = $this->populateResultDeleteMarkerEntry($item);
        }
        return $items;
    }
    private function populateResultObjectVersion(SimpleXMLElement $xml): ObjectVersion
    {
        return new ObjectVersion(['ETag' => null !== ($v = $xml->ETag[0]) ? (string) $v : null, 'ChecksumAlgorithm' => 0 === ($v = $xml->ChecksumAlgorithm)->count() ? null : $this->populateResultChecksumAlgorithmList($v), 'Size' => null !== ($v = $xml->Size[0]) ? (int) (string) $v : null, 'StorageClass' => null !== ($v = $xml->StorageClass[0]) ? (string) $v : null, 'Key' => null !== ($v = $xml->Key[0]) ? (string) $v : null, 'VersionId' => null !== ($v = $xml->VersionId[0]) ? (string) $v : null, 'IsLatest' => null !== ($v = $xml->IsLatest[0]) ? filter_var((string) $v, \FILTER_VALIDATE_BOOLEAN) : null, 'LastModified' => null !== ($v = $xml->LastModified[0]) ? new DateTimeImmutable((string) $v) : null, 'Owner' => 0 === $xml->Owner->count() ? null : $this->populateResultOwner($xml->Owner), 'RestoreStatus' => 0 === $xml->RestoreStatus->count() ? null : $this->populateResultRestoreStatus($xml->RestoreStatus)]);
    }
    private function populateResultObjectVersionList(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = $this->populateResultObjectVersion($item);
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
