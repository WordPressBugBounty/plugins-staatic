<?php

declare(strict_types=1);

namespace Staatic\WordPress\Publication\Task;

use Staatic\Framework\DeployStrategy\ResumableDeployStrategyInterface;
use Staatic\WordPress\Factory\StaticDeployerFactory;
use Staatic\WordPress\Publication\Publication;
use Throwable;

/**
 * Restartable only where the deploy strategy says so. Initiating a deployment of a large site
 * does not fit in one pass of the background publisher — declaring the manifest to the platform
 * alone can take half a minute, and taking the results nothing is waiting on out of the deploy
 * queue is a walk over every result — so a resumable strategy has to be allowed a second pass.
 *
 * What must not happen twice — creating the remote deployment — is recorded the instant it
 * exists, so a resumable pass that is cut short resumes against it rather than creating another.
 * A strategy without that property has no such record: reaching this task twice means the
 * previous attempt died mid-flight, and re-entering it would create a second remote deployment.
 * Those strategies keep the publisher's crash-loop guard.
 */
final class InitiateDeploymentTask implements ConditionallyRestartableTaskInterface
{
    /**
     * @var StaticDeployerFactory
     */
    private $factory;

    public function __construct(StaticDeployerFactory $factory)
    {
        $this->factory = $factory;
    }

    public static function name(): string
    {
        return 'initiate_deployment';
    }

    public function description(): string
    {
        return __('Initializing deployment', 'staatic');
    }

    /**
     * @param Publication $publication
     */
    public function supports($publication): bool
    {
        if ($publication->metadataByKey('skipDeploy')) {
            return \false;
        }

        return \true;
    }

    /**
     * Resolves the deploy strategy for this publication and reports whether it can resume an
     * interrupted initiation.
     *
     * Anything that goes wrong resolving it answers false. The guard is what turns a crash loop
     * into a failed publication with a diagnostic, so an unanswerable question has to fall on the
     * side that keeps the guard rather than the side that silently removes it.
     * @param Publication $publication
     */
    public function isRestartable($publication): bool
    {
        try {
            $deployStrategy = apply_filters('staatic_deployment_strategy', null, $publication);
        } catch (Throwable $failure) {
            return \false;
        }

        return $deployStrategy instanceof ResumableDeployStrategyInterface;
    }

    /**
     * @param Publication $publication
     * @param bool $limitedResources
     */
    public function execute($publication, $limitedResources): bool
    {
        $staticDeployer = ($this->factory)($publication);

        return $staticDeployer->initiateDeployment($publication->deployment());
    }
}
