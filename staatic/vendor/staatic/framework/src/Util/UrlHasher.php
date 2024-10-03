<?php

namespace Staatic\Framework\Util;

use Staatic\Vendor\Psr\Http\Message\UriInterface;
final class UrlHasher
{
    /**
     * @param UriInterface|string $url
     */
    public static function hash($url): string
    {
        $string = (string) $url;
        return md5(($string === '/') ? '/' : rtrim($string, '/'));
    }
}
