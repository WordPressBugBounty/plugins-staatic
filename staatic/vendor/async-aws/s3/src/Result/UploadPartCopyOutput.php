<?php

namespace Staatic\Vendor\AsyncAws\S3\Result;

use SimpleXMLElement;
use DateTimeImmutable;
use Staatic\Vendor\AsyncAws\Core\Response;
use Staatic\Vendor\AsyncAws\Core\Result;
use Staatic\Vendor\AsyncAws\S3\Enum\RequestCharged;
use Staatic\Vendor\AsyncAws\S3\Enum\ServerSideEncryption;
use Staatic\Vendor\AsyncAws\S3\ValueObject\CopyPartResult;
class UploadPartCopyOutput extends Result
{
    private $copySourceVersionId;
    private $copyPartResult;
    private $serverSideEncryption;
    private $sseCustomerAlgorithm;
    private $sseCustomerKeyMd5;
    private $sseKmsKeyId;
    private $bucketKeyEnabled;
    private $requestCharged;
    public function getBucketKeyEnabled(): ?bool
    {
        $this->initialize();
        return $this->bucketKeyEnabled;
    }
    public function getCopyPartResult(): ?CopyPartResult
    {
        $this->initialize();
        return $this->copyPartResult;
    }
    public function getCopySourceVersionId(): ?string
    {
        $this->initialize();
        return $this->copySourceVersionId;
    }
    public function getRequestCharged(): ?string
    {
        $this->initialize();
        return $this->requestCharged;
    }
    public function getServerSideEncryption(): ?string
    {
        $this->initialize();
        return $this->serverSideEncryption;
    }
    public function getSseCustomerAlgorithm(): ?string
    {
        $this->initialize();
        return $this->sseCustomerAlgorithm;
    }
    public function getSseCustomerKeyMd5(): ?string
    {
        $this->initialize();
        return $this->sseCustomerKeyMd5;
    }
    public function getSseKmsKeyId(): ?string
    {
        $this->initialize();
        return $this->sseKmsKeyId;
    }
    /**
     * @param Response $response
     */
    protected function populateResult($response): void
    {
        $headers = $response->getHeaders();
        $this->copySourceVersionId = $headers['x-amz-copy-source-version-id'][0] ?? null;
        $this->serverSideEncryption = $headers['x-amz-server-side-encryption'][0] ?? null;
        $this->sseCustomerAlgorithm = $headers['x-amz-server-side-encryption-customer-algorithm'][0] ?? null;
        $this->sseCustomerKeyMd5 = $headers['x-amz-server-side-encryption-customer-key-md5'][0] ?? null;
        $this->sseKmsKeyId = $headers['x-amz-server-side-encryption-aws-kms-key-id'][0] ?? null;
        $this->bucketKeyEnabled = isset($headers['x-amz-server-side-encryption-bucket-key-enabled'][0]) ? filter_var($headers['x-amz-server-side-encryption-bucket-key-enabled'][0], \FILTER_VALIDATE_BOOLEAN) : null;
        $this->requestCharged = $headers['x-amz-request-charged'][0] ?? null;
        $data = new SimpleXMLElement($response->getContent());
        $this->copyPartResult = $this->populateResultCopyPartResult($data);
    }
    private function populateResultCopyPartResult(SimpleXMLElement $xml): CopyPartResult
    {
        return new CopyPartResult(['ETag' => null !== ($v = $xml->ETag[0]) ? (string) $v : null, 'LastModified' => null !== ($v = $xml->LastModified[0]) ? new DateTimeImmutable((string) $v) : null, 'ChecksumCRC32' => null !== ($v = $xml->ChecksumCRC32[0]) ? (string) $v : null, 'ChecksumCRC32C' => null !== ($v = $xml->ChecksumCRC32C[0]) ? (string) $v : null, 'ChecksumSHA1' => null !== ($v = $xml->ChecksumSHA1[0]) ? (string) $v : null, 'ChecksumSHA256' => null !== ($v = $xml->ChecksumSHA256[0]) ? (string) $v : null]);
    }
}
