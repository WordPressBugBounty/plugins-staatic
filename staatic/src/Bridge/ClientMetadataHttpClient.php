<?php

declare(strict_types=1);

namespace Staatic\WordPress\Bridge;

use Staatic\WordPress\Util\ClientMetadata;
use Staatic\Vendor\Symfony\Component\HttpClient\DecoratorTrait;
use Staatic\Vendor\Symfony\Contracts\HttpClient\HttpClientInterface;
use Staatic\Vendor\Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Decides per request whether the X-Staatic-Client header goes out: only to the Staatic API
 * host, and never anywhere when no API host is known (the community edition).
 *
 * On any other host the header is removed, whether the caller passed it with the request or
 * through withOptions(). A request that does carry it is sent with `max_redirects` 0, so the
 * transport cannot carry the header to whatever host a redirect points at (Symfony only strips
 * Authorization and Cookie on a cross-host redirect); the caller sees the 3xx instead. The
 * Staatic API does not redirect, so nothing is lost, and following redirects here would mean
 * waiting on every response, which would defeat the client's concurrency.
 */
final class ClientMetadataHttpClient implements HttpClientInterface
{
    /**
     * @var string|null
     */
    private $apiHost;

    use DecoratorTrait;

    /**
     * Base URI set through withOptions(), against which relative request URLs resolve.
     *
     * @var string|null
     */
    private $baseUri;

    public function __construct(HttpClientInterface $client, ?string $apiHost)
    {
        $this->apiHost = $apiHost;
        $this->client = $client;
    }

    /**
     * @param string $method
     * @param string $url
     * @param mixed[] $options
     */
    public function request($method, $url, $options = []): ResponseInterface
    {
        $headers = self::withoutMetadataHeader((array) ($options['headers'] ?? []));
        if (ClientMetadata::isStaaticApiHost($this->hostOf($url, $options), $this->apiHost)) {
            $headers[ClientMetadata::HEADER_NAME] = ClientMetadata::current()->headerValue();
            $options['max_redirects'] = 0;
        } else {
            // An empty value overrides a default the wrapped client may hold, so nothing is sent.
            $headers[ClientMetadata::HEADER_NAME] = [];
        }
        $options['headers'] = $headers;

        return $this->client->request($method, $url, $options);
    }

    /**
     * @param mixed[] $options
     * @return static
     */
    public function withOptions($options)
    {
        $clone = clone $this;
        if (isset($options['headers'])) {
            $options['headers'] = self::withoutMetadataHeader((array) $options['headers']);
        }
        if (array_key_exists('base_uri', $options)) {
            $clone->baseUri = is_string($options['base_uri']) ? $options['base_uri'] : null;
        }
        $clone->client = $this->client->withOptions($options);

        return $clone;
    }

    private function hostOf(string $url, array $options): ?string
    {
        $host = parse_url($url, \PHP_URL_HOST);
        if (is_string($host)) {
            return $host;
        }
        // A relative URL resolves against the request's base_uri, else the inherited one; a host
        // that cannot be determined is never the API host.
        $baseUri = isset($options['base_uri']) && is_string(
            $options['base_uri']
        ) ? $options['base_uri'] : $this->baseUri;
        $host = $baseUri !== null ? parse_url($baseUri, \PHP_URL_HOST) : null;

        return is_string($host) ? $host : null;
    }

    /**
     * Removes the header in any spelling, including "Name: value" list entries.
     */
    private static function withoutMetadataHeader(array $headers): array
    {
        $name = strtolower(ClientMetadata::HEADER_NAME);
        foreach ($headers as $key => $value) {
            $headerName = is_int($key) ? is_string($value) ? explode(':', $value, 2)[0] : '' : $key;
            if (strtolower(trim($headerName)) === $name) {
                unset($headers[$key]);
            }
        }

        return $headers;
    }
}
