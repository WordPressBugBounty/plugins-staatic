<?php

declare(strict_types=1);

namespace Staatic\WordPress\Publication;

use InvalidArgumentException;
use RuntimeException;

final class PublicationType
{
    /**
     * @var string
     */
    private $type;

    public const TYPE_FULL = 'full';

    public const TYPE_PARTIAL = 'partial';

    public const TYPE_SUBSET = 'subset';

    private function __construct(string $type)
    {
        $this->type = $type;
    }

    public function __toString()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public static function create($type): self
    {
        if (!in_array($type, [self::TYPE_FULL, self::TYPE_PARTIAL, self::TYPE_SUBSET])) {
            throw new InvalidArgumentException(sprintf('Invalid type supplied: %s', $type));
        }

        return new self($type);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function label(): string
    {
        $labels = self::labels();
        if (!isset($labels[$this->type])) {
            throw new RuntimeException(sprintf('Unknown type %s', $this->type));
        }

        return $labels[$this->type];
    }

    public static function labels(): array
    {
        return [
            self::TYPE_FULL => __('Full-Site', 'staatic'),
            self::TYPE_PARTIAL => __('Changes', 'staatic'),
            self::TYPE_SUBSET => __('Selection', 'staatic')
        ];
    }
}
