<?php

namespace Staatic\Vendor\AsyncAws\S3\ValueObject;

use DateTimeImmutable;
use DOMElement;
use DOMDocument;
use DateTimeInterface;
use Throwable;
use Staatic\Vendor\AsyncAws\Core\Exception\InvalidArgument;
final class ObjectIdentifier
{
    private $key;
    private $versionId;
    private $etag;
    private $lastModifiedTime;
    private $size;
    public function __construct(array $input)
    {
        $this->key = $input['Key'] ?? $this->throwException(new InvalidArgument('Missing required field "Key".'));
        $this->versionId = $input['VersionId'] ?? null;
        $this->etag = $input['ETag'] ?? null;
        $this->lastModifiedTime = $input['LastModifiedTime'] ?? null;
        $this->size = $input['Size'] ?? null;
    }
    public static function create($input): self
    {
        return $input instanceof self ? $input : new self($input);
    }
    public function getEtag(): ?string
    {
        return $this->etag;
    }
    public function getKey(): string
    {
        return $this->key;
    }
    public function getLastModifiedTime(): ?DateTimeImmutable
    {
        return $this->lastModifiedTime;
    }
    public function getSize(): ?int
    {
        return $this->size;
    }
    public function getVersionId(): ?string
    {
        return $this->versionId;
    }
    public function requestBody(DOMElement $node, DOMDocument $document): void
    {
        $v = $this->key;
        $node->appendChild($document->createElement('Key', $v));
        if (null !== $v = $this->versionId) {
            $node->appendChild($document->createElement('VersionId', $v));
        }
        if (null !== $v = $this->etag) {
            $node->appendChild($document->createElement('ETag', $v));
        }
        if (null !== $v = $this->lastModifiedTime) {
            $node->appendChild($document->createElement('LastModifiedTime', $v->format(DateTimeInterface::RFC822)));
        }
        if (null !== $v = $this->size) {
            $node->appendChild($document->createElement('Size', (string) $v));
        }
    }
    private function throwException(Throwable $exception)
    {
        throw $exception;
    }
}
