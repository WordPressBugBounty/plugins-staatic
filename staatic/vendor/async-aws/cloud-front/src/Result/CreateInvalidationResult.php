<?php

namespace Staatic\Vendor\AsyncAws\CloudFront\Result;

use SimpleXMLElement;
use DateTimeImmutable;
use Staatic\Vendor\AsyncAws\CloudFront\ValueObject\Invalidation;
use Staatic\Vendor\AsyncAws\CloudFront\ValueObject\InvalidationBatch;
use Staatic\Vendor\AsyncAws\CloudFront\ValueObject\Paths;
use Staatic\Vendor\AsyncAws\Core\Response;
use Staatic\Vendor\AsyncAws\Core\Result;
class CreateInvalidationResult extends Result
{
    private $location;
    private $invalidation;
    public function getInvalidation(): ?Invalidation
    {
        $this->initialize();
        return $this->invalidation;
    }
    public function getLocation(): ?string
    {
        $this->initialize();
        return $this->location;
    }
    /**
     * @param Response $response
     */
    protected function populateResult($response): void
    {
        $headers = $response->getHeaders();
        $this->location = $headers['location'][0] ?? null;
        $data = new SimpleXMLElement($response->getContent());
        $this->invalidation = $this->populateResultInvalidation($data);
    }
    private function populateResultInvalidation(SimpleXMLElement $xml): Invalidation
    {
        return new Invalidation(['Id' => (string) $xml->Id, 'Status' => (string) $xml->Status, 'CreateTime' => new DateTimeImmutable((string) $xml->CreateTime), 'InvalidationBatch' => $this->populateResultInvalidationBatch($xml->InvalidationBatch)]);
    }
    private function populateResultInvalidationBatch(SimpleXMLElement $xml): InvalidationBatch
    {
        return new InvalidationBatch(['Paths' => $this->populateResultPaths($xml->Paths), 'CallerReference' => (string) $xml->CallerReference]);
    }
    private function populateResultPathList(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml->Path as $item) {
            $items[] = (string) $item;
        }
        return $items;
    }
    private function populateResultPaths(SimpleXMLElement $xml): Paths
    {
        return new Paths(['Quantity' => (int) (string) $xml->Quantity, 'Items' => 0 === ($v = $xml->Items)->count() ? null : $this->populateResultPathList($v)]);
    }
}
