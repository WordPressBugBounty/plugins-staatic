<?php

namespace Staatic\Vendor\Symfony\Component\DependencyInjection\Compiler;

use Staatic\Vendor\Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Staatic\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;
use Staatic\Vendor\Symfony\Component\DependencyInjection\Definition;
use Staatic\Vendor\Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Staatic\Vendor\Symfony\Component\DependencyInjection\Reference;
class InlineServiceDefinitionsPass extends AbstractRecursivePass
{
    /**
     * @var AnalyzeServiceReferencesPass|null
     */
    private $analyzingPass;
    /**
     * @var mixed[]
     */
    private $cloningIds = [];
    /**
     * @var mixed[]
     */
    private $connectedIds = [];
    /**
     * @var mixed[]
     */
    private $notInlinedIds = [];
    /**
     * @var mixed[]
     */
    private $inlinedIds = [];
    /**
     * @var mixed[]
     */
    private $notInlinableIds = [];
    /**
     * @var ServiceReferenceGraph|null
     */
    private $graph;
    public function __construct(AnalyzeServiceReferencesPass $analyzingPass = null)
    {
        $this->analyzingPass = $analyzingPass;
    }
    /**
     * @param ContainerBuilder $container
     */
    public function process($container)
    {
        $this->container = $container;
        if ($this->analyzingPass) {
            $analyzedContainer = new ContainerBuilder();
            $analyzedContainer->setAliases($container->getAliases());
            $analyzedContainer->setDefinitions($container->getDefinitions());
            foreach ($container->getExpressionLanguageProviders() as $provider) {
                $analyzedContainer->addExpressionLanguageProvider($provider);
            }
        } else {
            $analyzedContainer = $container;
        }
        try {
            $remainingInlinedIds = [];
            $this->connectedIds = $this->notInlinedIds = $container->getDefinitions();
            do {
                if ($this->analyzingPass) {
                    $analyzedContainer->setDefinitions(array_intersect_key($analyzedContainer->getDefinitions(), $this->connectedIds));
                    $this->analyzingPass->process($analyzedContainer);
                }
                $this->graph = $analyzedContainer->getCompiler()->getServiceReferenceGraph();
                $notInlinedIds = $this->notInlinedIds;
                $this->connectedIds = $this->notInlinedIds = $this->inlinedIds = [];
                foreach ($analyzedContainer->getDefinitions() as $id => $definition) {
                    if (!$this->graph->hasNode($id)) {
                        continue;
                    }
                    foreach ($this->graph->getNode($id)->getOutEdges() as $edge) {
                        if (isset($notInlinedIds[$edge->getSourceNode()->getId()])) {
                            $this->currentId = $id;
                            $this->processValue($definition, \true);
                            break;
                        }
                    }
                }
                foreach ($this->inlinedIds as $id => $isPublicOrNotShared) {
                    if ($isPublicOrNotShared) {
                        $remainingInlinedIds[$id] = $id;
                    } else {
                        $container->removeDefinition($id);
                        $analyzedContainer->removeDefinition($id);
                    }
                }
            } while ($this->inlinedIds && $this->analyzingPass);
            foreach ($remainingInlinedIds as $id) {
                if (isset($this->notInlinableIds[$id])) {
                    continue;
                }
                $definition = $container->getDefinition($id);
                if (!$definition->isShared() && !$definition->isPublic()) {
                    $container->removeDefinition($id);
                }
            }
        } finally {
            $this->container = null;
            $this->connectedIds = $this->notInlinedIds = $this->inlinedIds = [];
            $this->notInlinableIds = [];
            $this->graph = null;
        }
    }
    /**
     * @param mixed $value
     * @param bool $isRoot
     * @return mixed
     */
    protected function processValue($value, $isRoot = \false)
    {
        if ($value instanceof ArgumentInterface) {
            return $value;
        }
        if ($value instanceof Definition && $this->cloningIds) {
            if ($value->isShared()) {
                return $value;
            }
            $value = clone $value;
        }
        if (!$value instanceof Reference) {
            return parent::processValue($value, $isRoot);
        } elseif (!$this->container->hasDefinition($id = (string) $value)) {
            return $value;
        }
        $definition = $this->container->getDefinition($id);
        if (!$this->isInlineableDefinition($id, $definition)) {
            $this->notInlinableIds[$id] = \true;
            return $value;
        }
        $this->container->log($this, sprintf('Inlined service "%s" to "%s".', $id, $this->currentId));
        $this->inlinedIds[$id] = $definition->isPublic() || !$definition->isShared();
        $this->notInlinedIds[$this->currentId] = \true;
        if ($definition->isShared()) {
            return $definition;
        }
        if (isset($this->cloningIds[$id])) {
            $ids = array_keys($this->cloningIds);
            $ids[] = $id;
            throw new ServiceCircularReferenceException($id, \array_slice($ids, array_search($id, $ids)));
        }
        $this->cloningIds[$id] = \true;
        try {
            return $this->processValue($definition);
        } finally {
            unset($this->cloningIds[$id]);
        }
    }
    private function isInlineableDefinition(string $id, Definition $definition): bool
    {
        if ($definition->hasErrors() || $definition->isDeprecated() || $definition->isLazy() || $definition->isSynthetic() || $definition->hasTag('container.do_not_inline')) {
            return \false;
        }
        if (!$definition->isShared()) {
            if (!$this->graph->hasNode($id)) {
                return \true;
            }
            foreach ($this->graph->getNode($id)->getInEdges() as $edge) {
                $srcId = $edge->getSourceNode()->getId();
                $this->connectedIds[$srcId] = \true;
                if ($edge->isWeak() || $edge->isLazy()) {
                    return !$this->connectedIds[$id] = \true;
                }
            }
            return \true;
        }
        if ($definition->isPublic()) {
            return \false;
        }
        if (!$this->graph->hasNode($id)) {
            return \true;
        }
        if ($this->currentId == $id) {
            return \false;
        }
        $this->connectedIds[$id] = \true;
        $srcIds = [];
        $srcCount = 0;
        foreach ($this->graph->getNode($id)->getInEdges() as $edge) {
            $srcId = $edge->getSourceNode()->getId();
            $this->connectedIds[$srcId] = \true;
            if ($edge->isWeak() || $edge->isLazy()) {
                return \false;
            }
            $srcIds[$srcId] = \true;
            ++$srcCount;
        }
        if (1 !== \count($srcIds)) {
            $this->notInlinedIds[$id] = \true;
            return \false;
        }
        if ($srcCount > 1 && \is_array($factory = $definition->getFactory()) && ($factory[0] instanceof Reference || $factory[0] instanceof Definition)) {
            return \false;
        }
        return $this->container->getDefinition($srcId)->isShared();
    }
}
