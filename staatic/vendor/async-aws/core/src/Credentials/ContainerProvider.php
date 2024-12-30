<?php

declare (strict_types=1);
namespace Staatic\Vendor\AsyncAws\Core\Credentials;

use Exception;
use DateTimeImmutable;
use Staatic\Vendor\AsyncAws\Core\Configuration;
use Staatic\Vendor\Psr\Log\LoggerInterface;
use Staatic\Vendor\Psr\Log\NullLogger;
use Staatic\Vendor\Symfony\Component\HttpClient\HttpClient;
use Staatic\Vendor\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Staatic\Vendor\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Staatic\Vendor\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Staatic\Vendor\Symfony\Contracts\HttpClient\HttpClientInterface;
final class ContainerProvider implements CredentialProvider
{
    use TokenFileLoader;
    private const ECS_HOST = '169.254.170.2';
    private const EKS_HOST_IPV4 = '169.254.170.23';
    private const EKS_HOST_IPV6 = 'fd00:ec2::23';
    private $logger;
    private $httpClient;
    private $timeout;
    public function __construct(?HttpClientInterface $httpClient = null, ?LoggerInterface $logger = null, float $timeout = 1.0)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->httpClient = $httpClient ?? HttpClient::create();
        $this->timeout = $timeout;
    }
    /**
     * @param Configuration $configuration
     */
    public function getCredentials($configuration): ?Credentials
    {
        $fullUri = $this->getFullUri($configuration);
        if (empty($fullUri)) {
            return null;
        }
        if (!$this->isUriValid($fullUri)) {
            $this->logger->warning('Invalid URI "{uri}" provided.', ['uri' => $fullUri]);
            return null;
        }
        $tokenFile = $configuration->get(Configuration::OPTION_POD_IDENTITY_AUTHORIZATION_TOKEN_FILE);
        if (!empty($tokenFile)) {
            try {
                $tokenFileContent = $this->getTokenFileContent($tokenFile);
            } catch (Exception $e) {
                $this->logger->warning('"Error reading PodIdentityTokenFile "{tokenFile}.', ['tokenFile' => $tokenFile, 'exception' => $e]);
                return null;
            }
        }
        try {
            $response = $this->httpClient->request('GET', $fullUri, ['headers' => $this->getHeaders($tokenFileContent ?? null), 'timeout' => $this->timeout]);
            $result = $response->toArray();
        } catch (DecodingExceptionInterface $e) {
            $this->logger->info('Failed to decode Credentials.', ['exception' => $e]);
            return null;
        } catch (TransportExceptionInterface|HttpExceptionInterface $e) {
            $this->logger->info('Failed to fetch Profile from Instance Metadata.', ['exception' => $e]);
            return null;
        }
        if (null !== $date = $response->getHeaders(\false)['date'][0] ?? null) {
            $date = new DateTimeImmutable($date);
        }
        return new Credentials($result['AccessKeyId'], $result['SecretAccessKey'], $result['Token'], Credentials::adjustExpireDate(new DateTimeImmutable($result['Expiration']), $date));
    }
    private function isLoopBackAddress(string $host)
    {
        if (!filter_var($host, \FILTER_VALIDATE_IP)) {
            return \false;
        }
        $packedIp = inet_pton($host);
        if (4 === \strlen($packedIp)) {
            return 127 === \ord($packedIp[0]);
        }
        if (16 === \strlen($packedIp)) {
            return $packedIp === inet_pton('::1');
        }
        return \false;
    }
    private function getFullUri(Configuration $configuration): ?string
    {
        $relativeUri = $configuration->get(Configuration::OPTION_CONTAINER_CREDENTIALS_RELATIVE_URI);
        if (null !== $relativeUri) {
            return 'http://' . self::ECS_HOST . $relativeUri;
        }
        return $configuration->get(Configuration::OPTION_POD_IDENTITY_CREDENTIALS_FULL_URI);
    }
    private function getHeaders(?string $tokenFileContent): array
    {
        return $tokenFileContent ? ['Authorization' => $tokenFileContent] : [];
    }
    private function isUriValid(string $uri): bool
    {
        $parsedUri = parse_url($uri);
        if (\false === $parsedUri) {
            return \false;
        }
        if (!isset($parsedUri['scheme'])) {
            return \false;
        }
        if ('https' !== $parsedUri['scheme']) {
            $host = trim($parsedUri['host'] ?? '', '[]');
            if (self::EKS_HOST_IPV4 === $host || self::EKS_HOST_IPV6 === $host) {
                return \true;
            }
            if (self::ECS_HOST === $host) {
                return \true;
            }
            return $this->isLoopBackAddress($host);
        }
        return \true;
    }
}
