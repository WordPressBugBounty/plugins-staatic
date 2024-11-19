<?php

namespace Staatic\Vendor\AsyncAws\S3\Input;

use Staatic\Vendor\AsyncAws\Core\Input;
use Staatic\Vendor\AsyncAws\Core\Request;
use Staatic\Vendor\AsyncAws\Core\Stream\StreamFactory;
final class ListBucketsRequest extends Input
{
    private $maxBuckets;
    private $continuationToken;
    public function __construct(array $input = [])
    {
        $this->maxBuckets = $input['MaxBuckets'] ?? null;
        $this->continuationToken = $input['ContinuationToken'] ?? null;
        parent::__construct($input);
    }
    public static function create($input): self
    {
        return ($input instanceof self) ? $input : new self($input);
    }
    public function getContinuationToken(): ?string
    {
        return $this->continuationToken;
    }
    public function getMaxBuckets(): ?int
    {
        return $this->maxBuckets;
    }
    public function request(): Request
    {
        $headers = ['content-type' => 'application/xml'];
        $query = [];
        if (null !== $this->maxBuckets) {
            $query['max-buckets'] = (string) $this->maxBuckets;
        }
        if (null !== $this->continuationToken) {
            $query['continuation-token'] = $this->continuationToken;
        }
        $uriString = '/';
        $body = '';
        return new Request('GET', $uriString, $query, $headers, StreamFactory::create($body));
    }
    /**
     * @param string|null $value
     */
    public function setContinuationToken($value): self
    {
        $this->continuationToken = $value;
        return $this;
    }
    /**
     * @param int|null $value
     */
    public function setMaxBuckets($value): self
    {
        $this->maxBuckets = $value;
        return $this;
    }
}
