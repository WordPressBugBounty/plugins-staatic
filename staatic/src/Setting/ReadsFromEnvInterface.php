<?php

declare(strict_types=1);

namespace Staatic\WordPress\Setting;

interface ReadsFromEnvInterface
{
    public function envName(): string;

    /**
     * @param mixed $default
     * @return mixed
     */
    public function envValue($default = null);
}
