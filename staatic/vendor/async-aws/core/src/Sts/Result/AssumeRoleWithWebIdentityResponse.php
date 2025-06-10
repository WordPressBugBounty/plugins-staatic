<?php

namespace Staatic\Vendor\AsyncAws\Core\Sts\Result;

use SimpleXMLElement;
use DateTimeImmutable;
use Staatic\Vendor\AsyncAws\Core\Response;
use Staatic\Vendor\AsyncAws\Core\Result;
use Staatic\Vendor\AsyncAws\Core\Sts\ValueObject\AssumedRoleUser;
use Staatic\Vendor\AsyncAws\Core\Sts\ValueObject\Credentials;
class AssumeRoleWithWebIdentityResponse extends Result
{
    private $credentials;
    private $subjectFromWebIdentityToken;
    private $assumedRoleUser;
    private $packedPolicySize;
    private $provider;
    private $audience;
    private $sourceIdentity;
    public function getAssumedRoleUser(): ?AssumedRoleUser
    {
        $this->initialize();
        return $this->assumedRoleUser;
    }
    public function getAudience(): ?string
    {
        $this->initialize();
        return $this->audience;
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
    public function getProvider(): ?string
    {
        $this->initialize();
        return $this->provider;
    }
    public function getSourceIdentity(): ?string
    {
        $this->initialize();
        return $this->sourceIdentity;
    }
    public function getSubjectFromWebIdentityToken(): ?string
    {
        $this->initialize();
        return $this->subjectFromWebIdentityToken;
    }
    /**
     * @param Response $response
     */
    protected function populateResult($response): void
    {
        $data = new SimpleXMLElement($response->getContent());
        $data = $data->AssumeRoleWithWebIdentityResult;
        $this->credentials = 0 === $data->Credentials->count() ? null : $this->populateResultCredentials($data->Credentials);
        $this->subjectFromWebIdentityToken = null !== ($v = $data->SubjectFromWebIdentityToken[0]) ? (string) $v : null;
        $this->assumedRoleUser = 0 === $data->AssumedRoleUser->count() ? null : $this->populateResultAssumedRoleUser($data->AssumedRoleUser);
        $this->packedPolicySize = null !== ($v = $data->PackedPolicySize[0]) ? (int) (string) $v : null;
        $this->provider = null !== ($v = $data->Provider[0]) ? (string) $v : null;
        $this->audience = null !== ($v = $data->Audience[0]) ? (string) $v : null;
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
