<?php

namespace Staatic\Framework\DeployStrategy;

use InvalidArgumentException;
final class NetlifyFunction
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $archive;
    /**
     * @var string
     */
    private $runtime;
    /**
     * @var string
     */
    private $sha256;
    public function __construct(string $name, string $archive, string $runtime = 'js')
    {
        if (!preg_match('~^[A-Za-z0-9_-]{1,64}$~', $name)) {
            throw new InvalidArgumentException(sprintf('Invalid Netlify function name "%s"', $name));
        }
        if ($archive === '') {
            throw new InvalidArgumentException(sprintf('Netlify function "%s" has an empty archive', $name));
        }
        if (!preg_match('~^[a-z0-9]{1,16}$~', $runtime)) {
            throw new InvalidArgumentException(sprintf('Invalid Netlify function runtime "%s"', $runtime));
        }
        $this->name = $name;
        $this->archive = $archive;
        $this->runtime = $runtime;
        $this->sha256 = hash('sha256', $archive);
    }
    public function name(): string
    {
        return $this->name;
    }
    public function archive(): string
    {
        return $this->archive;
    }
    public function runtime(): string
    {
        return $this->runtime;
    }
    public function sha256(): string
    {
        return $this->sha256;
    }
}
