<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module\Deployer\NetlifyDeployer;

use Staatic\Framework\DeployStrategy\DeployStrategyInterface;
use Staatic\Framework\DeployStrategy\NetlifyDeployStrategy;
use Staatic\Framework\DeployStrategy\NetlifyFunction;
use Staatic\Framework\ResourceRepository\ResourceRepositoryInterface;
use Staatic\Framework\ResultRepository\ResultRepositoryInterface;
use Staatic\WordPress\Factory\HttpClientFactory;
use Staatic\WordPress\Publication\Publication;

final class NetlifyDeployStrategyFactory
{
    /**
     * @var ResultRepositoryInterface
     */
    private $resultRepository;

    /**
     * @var ResourceRepositoryInterface
     */
    private $resourceRepository;

    /**
     * @var HttpClientFactory
     */
    private $httpClientFactory;

    public function __construct(
        ResultRepositoryInterface $resultRepository,
        ResourceRepositoryInterface $resourceRepository,
        HttpClientFactory $httpClientFactory
    )
    {
        $this->resultRepository = $resultRepository;
        $this->resourceRepository = $resourceRepository;
        $this->httpClientFactory = $httpClientFactory;
    }

    public function __invoke(Publication $publication): DeployStrategyInterface
    {
        return new NetlifyDeployStrategy(
            $this->resultRepository,
            $this->resourceRepository,
            $this->httpClientFactory->createClient(),
            $this->options(
            $publication
        )
        );
    }

    private function options(Publication $publication): array
    {
        $options = [
            'basePath' => $publication->build()->destinationUrl()->getPath(),
            'accessToken' => get_option('staatic_netlify_access_token'),
            'siteId' => get_option('staatic_netlify_site_id'),
            'concurrency' => get_option('staatic_http_concurrency')
        ];
        /**
         * Filters the Netlify Functions shipped with a deploy.
         *
         * @since 1.15.0
         *
         * @param NetlifyFunction[] $functions   Functions to deploy alongside the static files.
         * @param Publication       $publication The publication being deployed.
         */
        $functions = apply_filters('staatic_netlify_functions', [], $publication);
        // Without functions the options, and so the deploy, are exactly what they were before.
        if (is_array($functions) && $functions !== []) {
            $options['functions'] = array_values($functions);
        }

        return $options;
    }
}
