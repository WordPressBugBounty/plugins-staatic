<?php

declare(strict_types=1);

namespace Staatic\WordPress\Setting;

trait ReadsFromEnvTrait
{
    /**
     * @param mixed $default
     * @return mixed
     */
    public function envValue($default = null)
    {
        if (!empty($_ENV['STAATIC_DISABLE_ENV']) || !empty($_SERVER['STAATIC_DISABLE_ENV'])) {
            return $default;
        }
        $envName = $this->envName();

        return $_ENV[$envName] ?? $_SERVER[$envName] ?? $default;
    }
}
