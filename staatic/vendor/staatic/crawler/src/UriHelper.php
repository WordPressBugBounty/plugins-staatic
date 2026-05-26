<?php

namespace Staatic\Crawler;

final class UriHelper
{
    public static function isProtocolRelativeUrl(string $url): bool
    {
        return strncmp($url, '//', strlen('//')) === 0;
    }
    public static function isReplaceableUrl(string $url): bool
    {
        return $url && preg_match('~^(#|%23|data:|mailto:|javascript:)~', $url) === 0;
    }
}
