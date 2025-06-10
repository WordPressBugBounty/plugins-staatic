<?php

namespace Staatic\Vendor\AsyncAws\Core\Sts\Result;

use SimpleXMLElement;
use Staatic\Vendor\AsyncAws\Core\Response;
use Staatic\Vendor\AsyncAws\Core\Result;
class GetCallerIdentityResponse extends Result
{
    private $userId;
    private $account;
    private $arn;
    public function getAccount(): ?string
    {
        $this->initialize();
        return $this->account;
    }
    public function getArn(): ?string
    {
        $this->initialize();
        return $this->arn;
    }
    public function getUserId(): ?string
    {
        $this->initialize();
        return $this->userId;
    }
    /**
     * @param Response $response
     */
    protected function populateResult($response): void
    {
        $data = new SimpleXMLElement($response->getContent());
        $data = $data->GetCallerIdentityResult;
        $this->userId = null !== ($v = $data->UserId[0]) ? (string) $v : null;
        $this->account = null !== ($v = $data->Account[0]) ? (string) $v : null;
        $this->arn = null !== ($v = $data->Arn[0]) ? (string) $v : null;
    }
}
