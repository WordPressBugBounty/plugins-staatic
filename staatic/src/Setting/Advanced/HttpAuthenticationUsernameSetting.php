<?php

declare(strict_types=1);

namespace Staatic\WordPress\Setting\Advanced;

use Staatic\WordPress\Setting\AbstractSetting;
use Staatic\WordPress\Setting\ReadsFromEnvInterface;
use Staatic\WordPress\Setting\ReadsFromEnvTrait;

final class HttpAuthenticationUsernameSetting extends AbstractSetting implements ReadsFromEnvInterface
{
    use ReadsFromEnvTrait;

    public function name(): string
    {
        return 'staatic_http_auth_username';
    }

    public function type(): string
    {
        return self::TYPE_STRING;
    }

    public function label(): string
    {
        return __('Username', 'staatic');
    }

    public function envName(): string
    {
        return 'STAATIC_HTTP_AUTH_USERNAME';
    }
}
