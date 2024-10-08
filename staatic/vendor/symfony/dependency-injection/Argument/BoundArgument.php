<?php

namespace Staatic\Vendor\Symfony\Component\DependencyInjection\Argument;

final class BoundArgument implements ArgumentInterface
{
    public const SERVICE_BINDING = 0;
    public const DEFAULTS_BINDING = 1;
    public const INSTANCEOF_BINDING = 2;
    /**
     * @var int
     */
    private static $sequence = 0;
    /**
     * @var mixed
     */
    private $value;
    /**
     * @var int|null
     */
    private $identifier;
    /**
     * @var bool|null
     */
    private $used;
    /**
     * @var int
     */
    private $type;
    /**
     * @var string|null
     */
    private $file;
    /**
     * @param mixed $value
     */
    public function __construct($value, bool $trackUsage = \true, int $type = 0, string $file = null)
    {
        $this->value = $value;
        if ($trackUsage) {
            $this->identifier = ++self::$sequence;
        } else {
            $this->used = \true;
        }
        $this->type = $type;
        $this->file = $file;
    }
    public function getValues(): array
    {
        return [$this->value, $this->identifier, $this->used, $this->type, $this->file];
    }
    /**
     * @param mixed[] $values
     */
    public function setValues($values)
    {
        if (5 === \count($values)) {
            [$this->value, $this->identifier, $this->used, $this->type, $this->file] = $values;
        } else {
            [$this->value, $this->identifier, $this->used] = $values;
        }
    }
}
