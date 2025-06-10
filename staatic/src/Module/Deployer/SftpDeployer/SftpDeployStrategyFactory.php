<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module\Deployer\SftpDeployer;

use Staatic\Vendor\Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Staatic\Framework\DeployStrategy\SftpDeployStrategy;
use Staatic\Framework\DeployStrategy\DeployStrategyInterface;
use Staatic\Framework\ResourceRepository\ResourceRepositoryInterface;
use Staatic\Framework\ResultRepository\ResultRepositoryInterface;
use Staatic\WordPress\Factory\HttpClientFactory;
use Staatic\WordPress\Publication\Publication;

final class SftpDeployStrategyFactory
{
    /**
     * @var ResultRepositoryInterface
     */
    private $resultRepository;

    /**
     * @var ResourceRepositoryInterface
     */
    private $resourceRepository;

    /**
     * @var HttpClientFactory
     */
    private $httpClientFactory;

    public function __construct(
        ResultRepositoryInterface $resultRepository,
        ResourceRepositoryInterface $resourceRepository,
        HttpClientFactory $httpClientFactory
    )
    {
        $this->resultRepository = $resultRepository;
        $this->resourceRepository = $resourceRepository;
        $this->httpClientFactory = $httpClientFactory;
    }

    public function __invoke(Publication $publication): DeployStrategyInterface
    {
        return new SftpDeployStrategy($this->resultRepository, $this->resourceRepository, $this->options($publication));
    }

    public function shouldRetryCallback(array $options, ?ResponseInterface $response = null): bool
    {
        return (($nullsafeVariable1 = $response) ? $nullsafeVariable1->getReasonPhrase() : null) === 'rate limit exceeded';
    }

    private function options(Publication $publication): array
    {
        $host = get_option('staatic_sftp_host') ?: null;
        if (empty($host)) {
            throw new RuntimeException('SFTP host is not configured.');
        }
        $username = get_option('staatic_sftp_username') ?: null;
        if (empty($username)) {
            throw new RuntimeException('SFTP username is not configured.');
        }
        $targetDirectory = get_option('staatic_sftp_target_directory') ?: null;
        if (empty($targetDirectory)) {
            throw new RuntimeException('SFTP target directory is not configured.');
        }
        $password = get_option('staatic_sftp_password') ?: null;
        $sshKey = get_option('staatic_sftp_ssh_key') ?: null;
        $sshKeyPassword = get_option('staatic_sftp_ssh_key_password') ?: null;
        if (empty($password) && empty($sshKey)) {
            throw new RuntimeException('SFTP password and SSH key are not configured. Please configure one of them.');
        }
        $authOptions = $sshKey ? [
            'sshKey' => $sshKey,
            'sshKeyPassword' => $sshKeyPassword
        ] : [
            'password' => $password
        ];

        return array_merge([
            'basePath' => $publication->build()->destinationUrl()->getPath(),
            'host' => $host,
            'username' => $username,
            'targetDirectory' => $targetDirectory,
            'timeout' => get_option('staatic_sftp_timeout')
        ], is_array($authOptions) ? $authOptions : iterator_to_array($authOptions));
    }
}
