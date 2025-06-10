<?php

namespace Staatic\Vendor\AsyncAws\S3\Result;

use SimpleXMLElement;
use Staatic\Vendor\AsyncAws\Core\Response;
use Staatic\Vendor\AsyncAws\Core\Result;
use Staatic\Vendor\AsyncAws\S3\ValueObject\CORSRule;
class GetBucketCorsOutput extends Result
{
    private $corsRules;
    public function getCorsRules(): array
    {
        $this->initialize();
        return $this->corsRules;
    }
    /**
     * @param Response $response
     */
    protected function populateResult($response): void
    {
        $data = new SimpleXMLElement($response->getContent());
        $this->corsRules = 0 === ($v = $data->CORSRule)->count() ? [] : $this->populateResultCORSRules($v);
    }
    private function populateResultAllowedHeaders(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = (string) $item;
        }
        return $items;
    }
    private function populateResultAllowedMethods(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = (string) $item;
        }
        return $items;
    }
    private function populateResultAllowedOrigins(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = (string) $item;
        }
        return $items;
    }
    private function populateResultCORSRule(SimpleXMLElement $xml): CORSRule
    {
        return new CORSRule(['ID' => null !== ($v = $xml->ID[0]) ? (string) $v : null, 'AllowedHeaders' => 0 === ($v = $xml->AllowedHeader)->count() ? null : $this->populateResultAllowedHeaders($v), 'AllowedMethods' => $this->populateResultAllowedMethods($xml->AllowedMethod), 'AllowedOrigins' => $this->populateResultAllowedOrigins($xml->AllowedOrigin), 'ExposeHeaders' => 0 === ($v = $xml->ExposeHeader)->count() ? null : $this->populateResultExposeHeaders($v), 'MaxAgeSeconds' => null !== ($v = $xml->MaxAgeSeconds[0]) ? (int) (string) $v : null]);
    }
    private function populateResultCORSRules(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = $this->populateResultCORSRule($item);
        }
        return $items;
    }
    private function populateResultExposeHeaders(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = (string) $item;
        }
        return $items;
    }
}
