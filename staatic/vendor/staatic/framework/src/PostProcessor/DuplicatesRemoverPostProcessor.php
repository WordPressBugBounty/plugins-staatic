<?php

namespace Staatic\Framework\PostProcessor;

use Staatic\Vendor\Psr\Log\LoggerAwareInterface;
use Staatic\Vendor\Psr\Log\LoggerAwareTrait;
use Staatic\Vendor\Psr\Log\NullLogger;
use Staatic\Crawler\CrawlProfile\CrawlProfileInterface;
use Staatic\Framework\Build;
use Staatic\Framework\PostProcessor\AdditionalRedirectsPostProcessor\AdditionalRedirect;
use Staatic\Framework\Result;
use Staatic\Framework\ResultRepository\ResultRepositoryInterface;
use Staatic\Framework\Util\PathHelper;
final class DuplicatesRemoverPostProcessor implements PostProcessorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    private const REASON_SELF_REDIRECT = 'self-redirecting';
    private const REASON_UNPROCESSABLE = 'unprocessable';
    /**
     * @var ResultRepositoryInterface
     */
    private $resultRepository;
    /**
     * @var mixed[]
     */
    private $operatorRedirectRanks = [];
    /**
     * @var CrawlProfileInterface|null
     */
    private $crawlProfile;
    public function __construct(ResultRepositoryInterface $resultRepository, iterable $additionalRedirects = [], ?CrawlProfileInterface $crawlProfile = null)
    {
        $this->resultRepository = $resultRepository;
        $this->logger = new NullLogger();
        $this->crawlProfile = $crawlProfile;
        if ($crawlProfile === null) {
            return;
        }
        $rank = 0;
        foreach ($additionalRedirects as $additionalRedirect) {
            $url = (string) AdditionalRedirectsPostProcessor::resultUrl($crawlProfile, $additionalRedirect->origin());
            if (!isset($this->operatorRedirectRanks[$url])) {
                $this->operatorRedirectRanks[$url] = $rank;
            }
            $rank++;
        }
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
        $this->logger->info('Applying duplicates remover post processor', ['buildId' => $build->id()]);
        $candidates = [];
        $wantedGroups = [];
        $deletedIds = [];
        $redirectResults = $this->resultRepository->findByBuildIdWithRedirectUrl($build->id());
        foreach ($redirectResults as $result) {
            $reason = $this->deletionReason($result);
            if ($reason === null) {
                continue;
            }
            $candidates[] = [$result, $reason];
            foreach ($this->groupsFor($result->url()->getPath(), $reason) as $group => $displayPath) {
                $wantedGroups[$group] = \true;
            }
        }
        usort($candidates, static function (array $left, array $right): int {
            $leftReason = $left[1] === self::REASON_UNPROCESSABLE ? 0 : 1;
            $rightReason = $right[1] === self::REASON_UNPROCESSABLE ? 0 : 1;
            if ($leftReason !== $rightReason) {
                return $leftReason <=> $rightReason;
            }
            return strcmp((string) $left[0]->url(), (string) $right[0]->url());
        });
        $groupSizes = $this->countResultsPerGroup($build, $wantedGroups);
        $numDeleted = 0;
        $numKept = 0;
        foreach ($candidates as [$result, $reason]) {
            $groups = $this->groupsFor($result->url()->getPath(), $reason);
            $blockingPath = $this->firstGroupThisIsTheLastMemberOf($groups, $groupSizes);
            if ($blockingPath !== null) {
                $this->logger->warning(sprintf('Keeping result with url \'%s\' (redirects to \'%s\'): it is the only ' . 'remaining result for file path "%s"', (string) $result->url(), (string) $result->redirectUrl(), $blockingPath), ['buildId' => $build->id(), 'resultId' => $result->id()]);
                $numKept++;
                continue;
            }
            $this->logger->debug("Deleting {$reason} result with url '{$result->url()}' (redirects to '{$result->redirectUrl()}')", ['buildId' => $build->id(), 'resultId' => $result->id()]);
            $this->resultRepository->delete($result);
            $deletedIds[$result->id()] = \true;
            foreach ($this->groupsFor($result->url()->getPath(), self::REASON_SELF_REDIRECT) as $group => $displayPath) {
                if (isset($groupSizes[$group])) {
                    $groupSizes[$group]--;
                }
            }
            $numDeleted++;
        }
        $this->logger->info("Removed {$numDeleted} duplicates", ['buildId' => $build->id()]);
        if ($numKept > 0) {
            $this->logger->info(sprintf('%d duplicate %s kept because nothing else would have been left to deploy ' . 'for its file path', $numKept, $numKept === 1 ? 'result was' : 'results were'), ['buildId' => $build->id()]);
        }
        if ($this->operatorRedirectRanks !== []) {
            $this->displaceByOperatorRedirects($build, $redirectResults, $deletedIds);
        }
    }
    private function displaceByOperatorRedirects(Build $build, array $redirectResults, array $deletedIds): void
    {
        $selfAddressed = [];
        $candidates = [];
        foreach ($redirectResults as $result) {
            $url = (string) $result->url();
            if (!isset($this->operatorRedirectRanks[$url]) || $result->redirectUrl() === null || $result->originalUrl() !== null || isset($deletedIds[$result->id()])) {
                continue;
            }
            $destination = $this->destinationKey($result->url()->getPath());
            if ($this->isSelfAddressed($result)) {
                $selfAddressed[$result->id()] = [$result, $destination];
            } else {
                $candidates[] = [$result, $destination];
            }
        }
        $winners = [];
        foreach ($candidates as [$result, $destination]) {
            if (!isset($winners[$destination])) {
                $winners[$destination] = $result;
                continue;
            }
            if ($this->operatorRedirectComesBefore($result, $winners[$destination])) {
                $winners[$destination] = $result;
            }
        }
        $operatorLosers = [];
        foreach ($candidates as [$result, $destination]) {
            if ($result->id() !== $winners[$destination]->id()) {
                $operatorLosers[$result->id()] = [$result, $winners[$destination]];
            }
        }
        $collapsedOnly = [];
        foreach ($winners as $winner) {
            $collapsed = PathHelper::determineCollapsedPath($winner->url()->getPath());
            if (!isset($collapsedOnly[$collapsed])) {
                $collapsedOnly[$collapsed] = $winner;
                continue;
            }
            if ($this->operatorRedirectComesBefore($winner, $collapsedOnly[$collapsed])) {
                $collapsedOnly[$collapsed] = $winner;
            }
        }
        if ($winners === [] && $selfAddressed === []) {
            return;
        }
        $displaced = [];
        $overlaps = [];
        $hasNonSelfOccupant = [];
        foreach ($this->resultRepository->findByBuildId($build->id()) as $result) {
            if (isset($deletedIds[$result->id()])) {
                continue;
            }
            $destination = $this->destinationKey($result->url()->getPath());
            if (!isset($selfAddressed[$result->id()])) {
                $hasNonSelfOccupant[$destination] = \true;
            }
            if (isset($winners[$destination]) && $result->id() !== $winners[$destination]->id()) {
                if (!isset($operatorLosers[$result->id()]) && !isset($selfAddressed[$result->id()])) {
                    $displaced[$result->id()] = [$result, $winners[$destination]];
                }
                continue;
            }
            $collapsed = PathHelper::determineCollapsedPath($result->url()->getPath());
            if (isset($collapsedOnly[$collapsed]) && $result->id() !== $collapsedOnly[$collapsed]->id()) {
                $overlaps[$result->id()] = [$result, $collapsedOnly[$collapsed]];
            }
        }
        $selfAddressedWinners = [];
        foreach ($selfAddressed as $resultId => [$result, $destination]) {
            if (isset($hasNonSelfOccupant[$destination])) {
                $displaced[$resultId] = [$result, null];
                continue;
            }
            if (!isset($selfAddressedWinners[$destination]) || $this->operatorRedirectComesBefore($result, $selfAddressedWinners[$destination])) {
                $selfAddressedWinners[$destination] = $result;
            }
        }
        foreach ($selfAddressed as $resultId => [$result, $destination]) {
            if (isset($hasNonSelfOccupant[$destination])) {
                continue;
            }
            $winner = $selfAddressedWinners[$destination];
            if ($result->id() !== $winner->id()) {
                $operatorLosers[$resultId] = [$result, $winner];
                continue;
            }
            $this->logger->debug("Keeping self-addressed additional redirect with url '{$result->url()}': nothing else occupies its file path", ['buildId' => $build->id(), 'resultId' => $result->id()]);
        }
        $displaced = array_values($displaced);
        usort($displaced, static function (array $left, array $right): int {
            return strcmp((string) $left[0]->url(), (string) $right[0]->url());
        });
        foreach ($displaced as [$result, $winner]) {
            if ($winner === null) {
                $this->logger->warning("Additional redirect '{$result->url()}' redirects to its own address; the generated result is kept", ['buildId' => $build->id(), 'resultId' => $result->id()]);
            } else {
                $redirectDescription = $result->redirectUrl() === null ? '' : sprintf(" to '%s'", (string) $result->redirectUrl());
                $message = sprintf("Additional redirect '%s' supersedes the result with URL '%s' (%d%s)", (string) $winner->url(), (string) $result->url(), $result->statusCode(), $redirectDescription);
                if ($this->isEquivalentRedirect($result, $winner)) {
                    $this->logger->info('[equivalent target] ' . $message, ['buildId' => $build->id(), 'resultId' => $result->id()]);
                } else {
                    if ($result->redirectUrl() !== null) {
                        $message .= sprintf("; configured target is '%s'", (string) $winner->redirectUrl());
                    }
                    $this->logger->warning($message, ['buildId' => $build->id(), 'resultId' => $result->id()]);
                }
            }
            $this->resultRepository->delete($result);
        }
        $operatorLosers = array_values($operatorLosers);
        usort($operatorLosers, static function (array $left, array $right): int {
            return strcmp((string) $left[0]->url(), (string) $right[0]->url());
        });
        foreach ($operatorLosers as [$result, $winner]) {
            $this->logger->warning(sprintf("Additional redirect '%s' to '%s' was dropped because earlier additional redirect '%s' to '%s' claims the same file path", (string) $result->url(), (string) $result->redirectUrl(), (string) $winner->url(), (string) $winner->redirectUrl()), ['buildId' => $build->id(), 'resultId' => $result->id()]);
            $this->resultRepository->delete($result);
        }
        $overlaps = array_values($overlaps);
        usort($overlaps, static function (array $left, array $right): int {
            return strcmp((string) $left[0]->url(), (string) $right[0]->url());
        });
        foreach ($overlaps as [$result, $winner]) {
            $collapsed = PathHelper::determineCollapsedPath($winner->url()->getPath());
            $this->logger->warning(sprintf("Additional redirect '%s' and result '%s' share file path \"%s\" but deploy to different files on some methods; spell the origin as '%s' to replace it", (string) $winner->url(), (string) $result->url(), $collapsed, $result->url()->getPath()), ['buildId' => $build->id(), 'resultId' => $result->id()]);
        }
        $this->logger->info(sprintf('%d results were replaced by additional redirects for the same file path', count($displaced)), ['buildId' => $build->id()]);
        if ($operatorLosers !== []) {
            $this->logger->info(sprintf('%d additional redirects were dropped because an earlier line claims the same file path', count($operatorLosers)), ['buildId' => $build->id()]);
        }
    }
    private function destinationKey(string $uriPath): string
    {
        $destination = PathHelper::determineCollapsedPath($uriPath);
        if ($this->isExtensionBearingTrailingSlash($uriPath)) {
            return $destination . "\x00extension-bearing-directory";
        }
        return $destination;
    }
    private function isExtensionBearingTrailingSlash(string $uriPath): bool
    {
        if (substr_compare($uriPath, '/', -strlen('/')) !== 0) {
            return \false;
        }
        $path = rtrim(rawurldecode($uriPath), '/');
        $lastSlash = strrpos($path, '/');
        $lastSegment = $lastSlash === \false ? $path : substr($path, $lastSlash + 1);
        return strrpos($lastSegment, '.') !== \false;
    }
    private function operatorRedirectComesBefore(Result $candidate, Result $current): bool
    {
        $candidateRank = $this->operatorRedirectRanks[(string) $candidate->url()];
        $currentRank = $this->operatorRedirectRanks[(string) $current->url()];
        return $candidateRank < $currentRank || $candidateRank === $currentRank && strcmp($candidate->id(), $current->id()) < 0;
    }
    private function isSelfAddressed(Result $result): bool
    {
        $target = $result->redirectUrl();
        $targetAuthority = $target->getAuthority();
        $destinationAuthority = $this->crawlProfile ? $this->crawlProfile->destinationUrl()->getAuthority() : '';
        if ($targetAuthority !== '' && $targetAuthority !== $result->url()->getAuthority() && $targetAuthority !== $destinationAuthority) {
            return \false;
        }
        return $this->destinationKey($target->getPath()) === $this->destinationKey($result->url()->getPath());
    }
    private function isEquivalentRedirect(Result $loser, Result $winner): bool
    {
        if ($loser->redirectUrl() === null || $loser->statusCode() !== $winner->statusCode()) {
            return \false;
        }
        $loserTarget = $loser->redirectUrl();
        $winnerTarget = $winner->redirectUrl();
        if ($loserTarget->getAuthority() !== '' && $winnerTarget->getAuthority() !== '' && $loserTarget->getAuthority() !== $winnerTarget->getAuthority()) {
            return \false;
        }
        return PathHelper::determineCollapsedPath($loserTarget->getPath()) === PathHelper::determineCollapsedPath($winnerTarget->getPath());
    }
    private function deletionReason(Result $result): ?string
    {
        if ($result->url()->getAuthority() !== $result->redirectUrl()->getAuthority()) {
            return null;
        }
        $path = $result->url()->getPath();
        $redirectPath = $result->redirectUrl()->getPath();
        if ($redirectPath === $path) {
            return self::REASON_SELF_REDIRECT;
        }
        $comparePath = substr_compare($path, '/', -strlen('/')) === 0 ? rtrim($path, '/') : sprintf('%s/', $path);
        if ($redirectPath === $comparePath) {
            return self::REASON_UNPROCESSABLE;
        }
        return null;
    }
    private function groupsFor(string $uriPath, string $reason): array
    {
        $collapsedPath = PathHelper::determineCollapsedPath($uriPath);
        if ($reason === self::REASON_UNPROCESSABLE) {
            return ["collapsed\x00{$collapsedPath}" => $collapsedPath];
        }
        $filePath = PathHelper::determineFilePath($uriPath);
        $htmlAsDirectoriesPath = PathHelper::determineFilePath($uriPath, \true);
        $rawPath = rtrim($uriPath, '/');
        return ["collapsed\x00{$collapsedPath}" => $collapsedPath, "file\x00{$filePath}" => $filePath, "htmlAsDirectories\x00{$htmlAsDirectoriesPath}" => $htmlAsDirectoriesPath, "raw\x00{$rawPath}" => $uriPath];
    }
    private function firstGroupThisIsTheLastMemberOf(array $groups, array $groupSizes): ?string
    {
        foreach ($groups as $group => $displayPath) {
            if (($groupSizes[$group] ?? 0) <= 1) {
                return $displayPath;
            }
        }
        return null;
    }
    private function countResultsPerGroup(Build $build, array $wantedGroups): array
    {
        if ($wantedGroups === []) {
            return [];
        }
        $groupSizes = [];
        foreach ($this->resultRepository->findByBuildId($build->id()) as $result) {
            $groups = $this->groupsFor($result->url()->getPath(), self::REASON_SELF_REDIRECT);
            foreach ($groups as $group => $displayPath) {
                if (!isset($wantedGroups[$group])) {
                    continue;
                }
                $groupSizes[$group] = ($groupSizes[$group] ?? 0) + 1;
            }
        }
        return $groupSizes;
    }
}
