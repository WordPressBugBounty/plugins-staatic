<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module\Deployer\S3Deployer;

use Staatic\Vendor\Symfony\Component\DependencyInjection\ServiceLocator;
use Staatic\WordPress\Setting\AbstractSetting;
use Staatic\WordPress\Setting\ComposedSettingInterface;

final class CloudFrontSetting extends AbstractSetting implements ComposedSettingInterface
{
    /**
     * @var ServiceLocator
     */
    private $settingLocator;

    public function __construct(ServiceLocator $settingLocator)
    {
        $this->settingLocator = $settingLocator;
    }

    public function name(): string
    {
        return 'staatic_aws_cloudfront';
    }

    public function type(): string
    {
        return self::TYPE_COMPOSED;
    }

    public function label(): string
    {
        return __('CloudFront', 'staatic');
    }

    public function settings(): array
    {
        return [
            $this->settingLocator->get(CloudFrontDistributionIdSetting::class),
            $this->settingLocator->get(CloudFrontMaxInvalidationPathsSetting::class),
            $this->settingLocator->get(CloudFrontInvalidateEverythingPath::class)
        ];
    }
}
