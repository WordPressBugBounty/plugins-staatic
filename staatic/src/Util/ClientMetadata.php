<?php

declare(strict_types=1);

namespace Staatic\WordPress\Util;

/**
 * Describes the runtime this plugin executes in (plugin, WordPress and PHP versions, multisite
 * and blog), as reported to Staatic Cloud in the `X-Staatic-Client` request header and in the
 * `client` object of a deployment create.
 *
 * Header grammar, version 1:
 *
 *     v=1; plugin=<version>; wordpress=<version>; php=<version>; multisite=<0|1>[; blog=<id>]
 *
 * Values are restricted to [A-Za-z0-9.+-] and at most 64 characters each; a field whose value is
 * empty after sanitising is left out rather than sent empty, so the receiver keeps what it knew.
 * `blog` is only sent on multisite. The whole value never exceeds 255 characters: fields that
 * would push it over are dropped from the end.
 *
 * The User-Agent and `X-Staatic-Request` headers are kept unchanged for older receivers; this
 * header is the structured contract. It is only ever sent to the Staatic API host (see
 * isStaaticApiHost()), never to the crawled site or a third-party deployment target.
 */
final class ClientMetadata
{
    /**
     * @var string
     */
    private $plugin;

    /**
     * @var string
     */
    private $wordpress;

    /**
     * @var string
     */
    private $php;

    /**
     * @var bool
     */
    private $multisite;

    /**
     * @var int|null
     */
    private $blogId;

    public const HEADER_NAME = 'X-Staatic-Client';

    public const GRAMMAR_VERSION = '1';

    public const MAX_VALUE_LENGTH = 64;

    public const MAX_HEADER_LENGTH = 255;

    private function __construct(string $plugin, string $wordpress, string $php, bool $multisite, ?int $blogId)
    {
        $this->plugin = $plugin;
        $this->wordpress = $wordpress;
        $this->php = $php;
        $this->multisite = $multisite;
        $this->blogId = $blogId;
    }

    public static function current(): self
    {
        global $wp_version;
        $multisite = is_multisite();

        return self::create(
            defined('STAATIC_VERSION') ? (string) \STAATIC_VERSION : '',
            is_string($wp_version) ? $wp_version : '',
            \PHP_VERSION,
            $multisite,
            $multisite ? get_current_blog_id() : null
        );
    }

    public static function create(
        string $plugin,
        string $wordpress,
        string $php,
        bool $multisite,
        ?int $blogId = null
    ): self
    {
        return new self(
            self::sanitize($plugin),
            self::sanitize($wordpress),
            self::sanitize($php),
            $multisite,
            $multisite && $blogId !== null && $blogId > 0 ? $blogId : null
        );
    }

    /**
     * Canonical host of a URL (see canonicalHost()); null when the URL has no usable host.
     */
    public static function hostOf(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $host = parse_url($url, \PHP_URL_HOST);

        return is_string($host) ? self::canonicalHost($host) : null;
    }

    /**
     * Whether a request host is the Staatic API host: both hosts are canonicalised (IDNA ASCII
     * form when ext-intl is available, lower case, trailing dot removed) and must then be equal.
     * Scheme, port and path play no part. Subdomains and look-alikes such as
     * api.staatic.com.example.org do not match, a host that fails conversion does not match, and
     * nothing matches when no API host is known.
     */
    public static function isStaaticApiHost(?string $requestHost, ?string $apiHost): bool
    {
        if ($requestHost === null || $apiHost === null) {
            return \false;
        }
        $requestHost = self::canonicalHost($requestHost);
        $apiHost = self::canonicalHost($apiHost);

        return $requestHost !== null && $apiHost !== null && $requestHost === $apiHost;
    }

    /**
     * Lower-case ASCII form of a host name without a trailing dot, or null when it is empty or
     * cannot be converted (fail closed). Without ext-intl a host is only lower-cased, so a
     * Unicode spelling then never equals its punycode spelling, which also fails closed.
     */
    public static function canonicalHost(string $host): ?string
    {
        $host = rtrim(trim($host), '.');
        if ($host === '') {
            return null;
        }
        if (function_exists('idn_to_ascii') && defined('INTL_IDNA_VARIANT_UTS46')) {
            $ascii = idn_to_ascii($host, \IDNA_DEFAULT, \INTL_IDNA_VARIANT_UTS46);
            if (!is_string($ascii) || $ascii === '') {
                return null;
            }
            $host = $ascii;
        }

        return strtolower($host);
    }

    public static function sanitize(string $value): string
    {
        $value = (string) preg_replace('/[^A-Za-z0-9.+\-]/', '', $value);

        return substr($value, 0, self::MAX_VALUE_LENGTH);
    }

    /**
     * The same facts as the header, typed, for JSON request bodies. Empty versions and the blog
     * of a single site are left out, as in the header.
     *
     * @return array{plugin?: string, wordpress?: string, php?: string, multisite: bool, blog?: int}
     */
    public function toArray(): array
    {
        $data = array_filter([
            'plugin' => $this->plugin,
            'wordpress' => $this->wordpress,
            'php' => $this->php
        ], function (string $value) {
    return $value !== '';
});
        $data['multisite'] = $this->multisite;
        if ($this->blogId !== null) {
            $data['blog'] = $this->blogId;
        }

        return $data;
    }

    public function headerValue(): string
    {
        $fields = [
            'v' => self::GRAMMAR_VERSION
        ];
        foreach ($this->toArray() as $key => $value) {
            $fields[$key] = is_bool($value) ? $value ? '1' : '0' : (string) $value;
        }
        $header = '';
        foreach ($fields as $key => $value) {
            $part = ($header === '' ? '' : '; ') . $key . '=' . $value;
            if (strlen($header) + strlen($part) > self::MAX_HEADER_LENGTH) {
                break;
            }
            $header .= $part;
        }

        return $header;
    }
}
