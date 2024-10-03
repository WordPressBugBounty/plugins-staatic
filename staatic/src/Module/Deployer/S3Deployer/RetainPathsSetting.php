<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module\Deployer\S3Deployer;

use Staatic\WordPress\Setting\AbstractSetting;

final class RetainPathsSetting extends AbstractSetting
{
    /**
     * @var S3PrefixSetting
     */
    private $prefix;

    public function __construct(S3PrefixSetting $prefix)
    {
        $this->prefix = $prefix;
    }

    public function name(): string
    {
        return 'staatic_aws_retain_paths';
    }

    public function type(): string
    {
        return self::TYPE_STRING;
    }

    protected function template(): string
    {
        return 'retain_paths';
    }

    public function label(): string
    {
        return __('Retain Files/Directories', 'staatic');
    }

    public function description(): ?string
    {
        return sprintf(
            /* translators: %s: Example paths. */
            __('Optionally add file or directory paths (absolute or relative to the bucket prefix) that need to be left intact (one path per line).<br>Files existing in the target bucket that are not part of the build and not in this list will be deleted during deployment.<br>Examples: %s.', 'staatic'),
            implode(
                ', ',
                ['<code>favicon.ico</code>',
                '<code>robots.txt</code>',
                __('a Bing/Google/Yahoo/etc. verification file', 'staatic')
            ])
        );
    }

    public function sanitizeValue($value)
    {
        $result = RetainPaths::validateAndResolve($value, $this->prefix->value());
        foreach ($result['errors']->get_error_messages() as $message) {
            add_settings_error('staatic-settings', 'retain_paths', __('Skipped: ', 'staatic') . $message);
        }

        return $result['newValue'];
    }
}
