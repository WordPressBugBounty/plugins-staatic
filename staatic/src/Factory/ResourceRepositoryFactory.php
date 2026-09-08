<?php

declare(strict_types=1);

namespace Staatic\WordPress\Factory;

use Staatic\Framework\ResourceRepository\FilesystemResourceRepository;
use Staatic\Framework\ResourceRepository\InMemoryResourceRepository;
use Staatic\Framework\ResourceRepository\ResourceRepositoryInterface;
use Staatic\WordPress\Setting\Advanced\WorkDirectorySetting;

final class ResourceRepositoryFactory
{
    /**
     * @var WorkDirectorySetting
     */
    private $workDirectory;

    public function __construct(WorkDirectorySetting $workDirectory)
    {
        $this->workDirectory = $workDirectory;
    }

    /**
     * The directory the resource bodies are stored in, whether or not it exists yet.
     *
     * Exposed so callers that need to describe the store to something else (the Staatic Cloud
     * deploy strategy declares it to the platform) read the same value the repository writes to,
     * rather than recomputing it from the work directory and drifting.
     */
    public function resourceDirectory(): string
    {
        return untrailingslashit($this->workDirectory->value()) . '/resources';
    }

    public function __invoke(): ResourceRepositoryInterface
    {
        $resourceDirectory = $this->resourceDirectory();
        if (!is_dir($resourceDirectory)) {
            if (!mkdir($resourceDirectory, 0777, \true)) {
                return new InMemoryResourceRepository();
            }
        }
        $compress = in_array('compress.zlib', stream_get_wrappers());
        $compress = (bool) apply_filters('staatic_compress_resources', $compress);

        return new FilesystemResourceRepository($resourceDirectory, $compress);
    }
}
