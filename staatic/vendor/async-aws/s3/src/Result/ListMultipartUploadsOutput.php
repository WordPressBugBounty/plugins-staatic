<?php

namespace Staatic\Vendor\AsyncAws\S3\Result;

use IteratorAggregate;
use Traversable;
use SimpleXMLElement;
use DateTimeImmutable;
use Staatic\Vendor\AsyncAws\Core\Exception\InvalidArgument;
use Staatic\Vendor\AsyncAws\Core\Response;
use Staatic\Vendor\AsyncAws\Core\Result;
use Staatic\Vendor\AsyncAws\S3\Enum\EncodingType;
use Staatic\Vendor\AsyncAws\S3\Enum\RequestCharged;
use Staatic\Vendor\AsyncAws\S3\Input\ListMultipartUploadsRequest;
use Staatic\Vendor\AsyncAws\S3\S3Client;
use Staatic\Vendor\AsyncAws\S3\ValueObject\CommonPrefix;
use Staatic\Vendor\AsyncAws\S3\ValueObject\Initiator;
use Staatic\Vendor\AsyncAws\S3\ValueObject\MultipartUpload;
use Staatic\Vendor\AsyncAws\S3\ValueObject\Owner;
class ListMultipartUploadsOutput extends Result implements IteratorAggregate
{
    private $bucket;
    private $keyMarker;
    private $uploadIdMarker;
    private $nextKeyMarker;
    private $prefix;
    private $delimiter;
    private $nextUploadIdMarker;
    private $maxUploads;
    private $isTruncated;
    private $uploads;
    private $commonPrefixes;
    private $encodingType;
    private $requestCharged;
    public function getBucket(): ?string
    {
        $this->initialize();
        return $this->bucket;
    }
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
        if (!$this->input instanceof ListMultipartUploadsRequest) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (\true) {
            $page->initialize();
            if ($page->isTruncated) {
                $input->setKeyMarker($page->nextKeyMarker);
                $input->setUploadIdMarker($page->nextUploadIdMarker);
                $this->registerPrefetch($nextPage = $client->listMultipartUploads($input));
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
        if (!$this->input instanceof ListMultipartUploadsRequest) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (\true) {
            $page->initialize();
            if ($page->isTruncated) {
                $input->setKeyMarker($page->nextKeyMarker);
                $input->setUploadIdMarker($page->nextUploadIdMarker);
                $this->registerPrefetch($nextPage = $client->listMultipartUploads($input));
            } else {
                $nextPage = null;
            }
            yield from $page->getUploads(\true);
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
    public function getMaxUploads(): ?int
    {
        $this->initialize();
        return $this->maxUploads;
    }
    public function getNextKeyMarker(): ?string
    {
        $this->initialize();
        return $this->nextKeyMarker;
    }
    public function getNextUploadIdMarker(): ?string
    {
        $this->initialize();
        return $this->nextUploadIdMarker;
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
    public function getUploadIdMarker(): ?string
    {
        $this->initialize();
        return $this->uploadIdMarker;
    }
    /**
     * @param bool $currentPageOnly
     */
    public function getUploads($currentPageOnly = \false): iterable
    {
        if ($currentPageOnly) {
            $this->initialize();
            yield from $this->uploads;
            return;
        }
        $client = $this->awsClient;
        if (!$client instanceof S3Client) {
            throw new InvalidArgument('missing client injected in paginated result');
        }
        if (!$this->input instanceof ListMultipartUploadsRequest) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (\true) {
            $page->initialize();
            if ($page->isTruncated) {
                $input->setKeyMarker($page->nextKeyMarker);
                $input->setUploadIdMarker($page->nextUploadIdMarker);
                $this->registerPrefetch($nextPage = $client->listMultipartUploads($input));
            } else {
                $nextPage = null;
            }
            yield from $page->uploads;
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
        $this->bucket = null !== ($v = $data->Bucket[0]) ? (string) $v : null;
        $this->keyMarker = null !== ($v = $data->KeyMarker[0]) ? (string) $v : null;
        $this->uploadIdMarker = null !== ($v = $data->UploadIdMarker[0]) ? (string) $v : null;
        $this->nextKeyMarker = null !== ($v = $data->NextKeyMarker[0]) ? (string) $v : null;
        $this->prefix = null !== ($v = $data->Prefix[0]) ? (string) $v : null;
        $this->delimiter = null !== ($v = $data->Delimiter[0]) ? (string) $v : null;
        $this->nextUploadIdMarker = null !== ($v = $data->NextUploadIdMarker[0]) ? (string) $v : null;
        $this->maxUploads = null !== ($v = $data->MaxUploads[0]) ? (int) (string) $v : null;
        $this->isTruncated = null !== ($v = $data->IsTruncated[0]) ? filter_var((string) $v, \FILTER_VALIDATE_BOOLEAN) : null;
        $this->uploads = 0 === ($v = $data->Upload)->count() ? [] : $this->populateResultMultipartUploadList($v);
        $this->commonPrefixes = 0 === ($v = $data->CommonPrefixes)->count() ? [] : $this->populateResultCommonPrefixList($v);
        $this->encodingType = null !== ($v = $data->EncodingType[0]) ? (string) $v : null;
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
    private function populateResultInitiator(SimpleXMLElement $xml): Initiator
    {
        return new Initiator(['ID' => null !== ($v = $xml->ID[0]) ? (string) $v : null, 'DisplayName' => null !== ($v = $xml->DisplayName[0]) ? (string) $v : null]);
    }
    private function populateResultMultipartUpload(SimpleXMLElement $xml): MultipartUpload
    {
        return new MultipartUpload(['UploadId' => null !== ($v = $xml->UploadId[0]) ? (string) $v : null, 'Key' => null !== ($v = $xml->Key[0]) ? (string) $v : null, 'Initiated' => null !== ($v = $xml->Initiated[0]) ? new DateTimeImmutable((string) $v) : null, 'StorageClass' => null !== ($v = $xml->StorageClass[0]) ? (string) $v : null, 'Owner' => 0 === $xml->Owner->count() ? null : $this->populateResultOwner($xml->Owner), 'Initiator' => 0 === $xml->Initiator->count() ? null : $this->populateResultInitiator($xml->Initiator), 'ChecksumAlgorithm' => null !== ($v = $xml->ChecksumAlgorithm[0]) ? (string) $v : null]);
    }
    private function populateResultMultipartUploadList(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = $this->populateResultMultipartUpload($item);
        }
        return $items;
    }
    private function populateResultOwner(SimpleXMLElement $xml): Owner
    {
        return new Owner(['DisplayName' => null !== ($v = $xml->DisplayName[0]) ? (string) $v : null, 'ID' => null !== ($v = $xml->ID[0]) ? (string) $v : null]);
    }
}
