<?php

namespace Staatic\Vendor\AsyncAws\Core\Sts\Result;

use SimpleXMLElement;
use DateTimeImmutable;
use Staatic\Vendor\AsyncAws\Core\Response;
use Staatic\Vendor\AsyncAws\Core\Result;
use Staatic\Vendor\AsyncAws\Core\Sts\ValueObject\AssumedRoleUser;
use Staatic\Vendor\AsyncAws\Core\Sts\ValueObject\Credentials;
class AssumeRoleResponse extends Result
{
    private $credentials;
    private $assumedRoleUser;
    private $packedPolicySize;
    private $sourceIdentity;
    public function getAssumedRoleUser(): ?AssumedRoleUser
    {
        $this->initialize();
        return $this->assumedRoleUser;
    }
    public function getCredentials(): ?Credentials
    {
        $this->initialize();
        return $this->credentials;
    }
    public function getPackedPolicySize(): ?int
    {
        $this->initialize();
        return $this->packedPolicySize;
    }
    public function getSourceIdentity(): ?string
    {
        $this->initialize();
        return $this->sourceIdentity;
    }
    /**
     * @param Response $response
     */
    protected function populateResult($response): void
    {
        $data = new SimpleXMLElement($response->getContent());
        $data = $data->AssumeRoleResult;
        $this->credentials = 0 === $data->Credentials->count() ? null : $this->populateResultCredentials($data->Credentials);
        $this->assumedRoleUser = 0 === $data->AssumedRoleUser->count() ? null : $this->populateResultAssumedRoleUser($data->AssumedRoleUser);
        $this->packedPolicySize = null !== ($v = $data->PackedPolicySize[0]) ? (int) (string) $v : null;
        $this->sourceIdentity = null !== ($v = $data->SourceIdentity[0]) ? (string) $v : null;
    }
    private function populateResultAssumedRoleUser(SimpleXMLElement $xml): AssumedRoleUser
    {
        return new AssumedRoleUser(['AssumedRoleId' => (string) $xml->AssumedRoleId, 'Arn' => (string) $xml->Arn]);
    }
    private function populateResultCredentials(SimpleXMLElement $xml): Credentials
    {
        return new Credentials(['AccessKeyId' => (string) $xml->AccessKeyId, 'SecretAccessKey' => (string) $xml->SecretAccessKey, 'SessionToken' => (string) $xml->SessionToken, 'Expiration' => new DateTimeImmutable((string) $xml->Expiration)]);
    }
}
