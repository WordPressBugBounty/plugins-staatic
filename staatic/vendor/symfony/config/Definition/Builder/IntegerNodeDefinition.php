<?php

namespace Staatic\Vendor\Symfony\Component\Config\Definition\Builder;

use Staatic\Vendor\Symfony\Component\Config\Definition\ScalarNode;
use Staatic\Vendor\Symfony\Component\Config\Definition\IntegerNode;
class IntegerNodeDefinition extends NumericNodeDefinition
{
    protected function instantiateNode(): ScalarNode
    {
        return new IntegerNode($this->name, $this->parent, $this->min, $this->max, $this->pathSeparator);
    }
}
