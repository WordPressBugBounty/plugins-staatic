<?php

namespace Staatic\Vendor\AsyncAws\S3\Result;

use SimpleXMLElement;
use Staatic\Vendor\AsyncAws\Core\Response;
use Staatic\Vendor\AsyncAws\Core\Result;
use Staatic\Vendor\AsyncAws\S3\Enum\RequestCharged;
use Staatic\Vendor\AsyncAws\S3\ValueObject\DeletedObject;
use Staatic\Vendor\AsyncAws\S3\ValueObject\Error;
class DeleteObjectsOutput extends Result
{
    private $deleted;
    private $requestCharged;
    private $errors;
    public function getDeleted(): array
    {
        $this->initialize();
        return $this->deleted;
    }
    public function getErrors(): array
    {
        $this->initialize();
        return $this->errors;
    }
    public function getRequestCharged(): ?string
    {
        $this->initialize();
        return $this->requestCharged;
    }
    /**
     * @param Response $response
     */
    protected function populateResult($response): void
    {
        $headers = $response->getHeaders();
        $this->requestCharged = $headers['x-amz-request-charged'][0] ?? null;
        $data = new SimpleXMLElement($response->getContent());
        $this->deleted = 0 === ($v = $data->Deleted)->count() ? [] : $this->populateResultDeletedObjects($v);
        $this->errors = 0 === ($v = $data->Error)->count() ? [] : $this->populateResultErrors($v);
    }
    private function populateResultDeletedObject(SimpleXMLElement $xml): DeletedObject
    {
        return new DeletedObject(['Key' => null !== ($v = $xml->Key[0]) ? (string) $v : null, 'VersionId' => null !== ($v = $xml->VersionId[0]) ? (string) $v : null, 'DeleteMarker' => null !== ($v = $xml->DeleteMarker[0]) ? filter_var((string) $v, \FILTER_VALIDATE_BOOLEAN) : null, 'DeleteMarkerVersionId' => null !== ($v = $xml->DeleteMarkerVersionId[0]) ? (string) $v : null]);
    }
    private function populateResultDeletedObjects(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = $this->populateResultDeletedObject($item);
        }
        return $items;
    }
    private function populateResultError(SimpleXMLElement $xml): Error
    {
        return new Error(['Key' => null !== ($v = $xml->Key[0]) ? (string) $v : null, 'VersionId' => null !== ($v = $xml->VersionId[0]) ? (string) $v : null, 'Code' => null !== ($v = $xml->Code[0]) ? (string) $v : null, 'Message' => null !== ($v = $xml->Message[0]) ? (string) $v : null]);
    }
    private function populateResultErrors(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml as $item) {
            $items[] = $this->populateResultError($item);
        }
        return $items;
    }
}
