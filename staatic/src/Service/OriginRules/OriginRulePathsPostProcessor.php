<?php

declare(strict_types=1);

namespace Staatic\WordPress\Service\OriginRules;

use Staatic\Vendor\Psr\Log\LoggerAwareInterface;
use Staatic\Vendor\Psr\Log\LoggerAwareTrait;
use Staatic\Vendor\Psr\Log\NullLogger;
use Staatic\Framework\Build;
use Staatic\Framework\PostProcessor\PostProcessorInterface;
use Staatic\Framework\ResultRepository\ResultRepositoryInterface;

/**
 * Removes results whose published path an origin rule forwards to an origin.
 *
 * The crawl profile already keeps such URLs out of the crawl (see OriginRulePathEvaluator), but
 * results also reach a build without being crawled: a partial publication copies every result
 * of its parent build it did not crawl itself, and post processors create results of their own.
 * A parent published before a pattern was added would otherwise carry the excluded resource
 * into every later partial publication. Idempotent, as post processors have to be.
 */
final class OriginRulePathsPostProcessor implements PostProcessorInterface, LoggerAwareInterface
{
    /**
     * @var ResultRepositoryInterface
     */
    private $resultRepository;

    /**
     * @var OriginRulePathMatcher
     */
    private $matcher;

    use LoggerAwareTrait;

    public function __construct(ResultRepositoryInterface $resultRepository, OriginRulePathMatcher $matcher)
    {
        $this->resultRepository = $resultRepository;
        $this->matcher = $matcher;
        $this->logger = new NullLogger();
    }

    public function createsOrRemovesResults(): bool
    {
        return \true;
    }

    /**
     * @param Build $build
     */
    public function apply($build): void
    {
        if ($this->matcher->isEmpty()) {
            return;
        }
        $this->logger->info('Applying origin rule paths post processor', [
            'buildId' => $build->id()
        ]);
        $numRemoved = 0;
        foreach ($this->resultRepository->findByBuildId($build->id()) as $result) {
            if (!$this->matcher->matches($result->url()->getPath())) {
                continue;
            }
            $this->logger->info(
                "Removing result with url '{$result->url()}': an origin rule forwards its path to an origin",
                [
                'buildId' => $build->id()
            ]
            );
            $this->resultRepository->delete($result);
            $numRemoved++;
        }
        if ($numRemoved > 0) {
            $this->logger->notice(
                sprintf('Removed %d result(s) whose path an origin rule forwards to an origin', $numRemoved),
                [
                'buildId' => $build->id()
            ]
            );
        }
    }
}
