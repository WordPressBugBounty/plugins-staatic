<?php

namespace Staatic\Framework\DeployStrategy;

use Staatic\Framework\Deployment;
interface ResumableDeployStrategyInterface extends DeployStrategyInterface
{
    /**
     * @param Deployment $deployment
     * @param callable $persistMetadata
     */
    public function initiateResumable($deployment, $persistMetadata): bool;
}
