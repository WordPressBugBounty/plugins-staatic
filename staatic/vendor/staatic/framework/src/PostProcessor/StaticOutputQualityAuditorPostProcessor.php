<?php

namespace Staatic\Framework\PostProcessor;

use Staatic\Vendor\GuzzleHttp\Psr7\Uri;
use Staatic\Vendor\GuzzleHttp\Psr7\UriResolver;
use InvalidArgumentException;
use Staatic\Vendor\Psr\Http\Message\UriInterface;
use Staatic\Vendor\Psr\Log\LoggerAwareInterface;
use Staatic\Vendor\Psr\Log\LoggerAwareTrait;
use Staatic\Vendor\Psr\Log\NullLogger;
use Staatic\Framework\Build;
use Staatic\Framework\ResourceRepository\ResourceRepositoryInterface;
use Staatic\Framework\Result;
use Staatic\Framework\ResultRepository\ResultRepositoryInterface;
final class StaticOutputQualityAuditorPostProcessor implements PostProcessorInterface, LoggerAwareInterface
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
     * @var int
     */
    private $maxIssues = 100;
    use LoggerAwareTrait;
    public function __construct(ResultRepositoryInterface $resultRepository, ResourceRepositoryInterface $resourceRepository, int $maxIssues = 100)
    {
        $this->resultRepository = $resultRepository;
        $this->resourceRepository = $resourceRepository;
        $this->maxIssues = $maxIssues;
        $this->logger = new NullLogger();
    }
    public function createsOrRemovesResults(): bool
    {
        return \false;
    }
    /**
     * @param Build $build
     */
    public function apply($build): void
    {
        $this->logger->info('Applying static output quality audit', ['buildId' => $build->id()]);
        $numIssues = 0;
        foreach ($this->resultRepository->findByBuildId($build->id()) as $result) {
            if (!$this->shouldAudit($result)) {
                continue;
            }
            if (!$result->sha1()) {
                continue;
            }
            $resource = $this->resourceRepository->find($result->sha1());
            if ($resource === null) {
                $this->warning('Static output audit found a missing resource body.', $build, $result, null, 'missing_resource');
                if (++$numIssues >= $this->maxIssues) {
                    $this->limitReached($build, $numIssues);
                    return;
                }
                continue;
            }
            $content = (string) $resource->content();
            $resource->content()->rewind();
            foreach ($this->extractUrls($result, $content) as $url) {
                $normalizedUrl = $this->withoutFragment($url);
                if ($this->isOriginUrl($normalizedUrl, $build)) {
                    $this->warning('Static output audit found a live WordPress URL in generated output.', $build, $result, $normalizedUrl, 'live_url');
                    if (++$numIssues >= $this->maxIssues) {
                        $this->limitReached($build, $numIssues);
                        return;
                    }
                }
                if ($this->isDestinationInternalUrl($normalizedUrl, $build) && !$this->resultRepository->findOneByBuildIdAndUrlResolved($build->id(), $normalizedUrl)) {
                    $this->warning('Static output audit found an internal URL without a generated result.', $build, $result, $normalizedUrl, 'missing_internal_result');
                    if (++$numIssues >= $this->maxIssues) {
                        $this->limitReached($build, $numIssues);
                        return;
                    }
                }
            }
        }
        $this->logger->notice('Static output quality audit completed.', ['buildId' => $build->id(), 'numIssues' => $numIssues]);
    }
    private function shouldAudit(Result $result): bool
    {
        $mimeType = (string) $result->mimeType();
        return strncmp($mimeType, 'text/html', strlen('text/html')) === 0 || strncmp($mimeType, 'text/css', strlen('text/css')) === 0;
    }
    private function extractUrls(Result $result, string $content): iterable
    {
        $candidates = [];
        preg_match_all('~\b(?:href|src|data-src)=["\']([^"\']+)["\']~i', $content, $attributeMatches);
        foreach ($attributeMatches[1] ?? [] as $candidate) {
            $candidates[] = $candidate;
        }
        preg_match_all('~\b(?:srcset|data-srcset)=["\']([^"\']+)["\']~i', $content, $srcsetMatches);
        foreach ($srcsetMatches[1] ?? [] as $srcset) {
            foreach (explode(',', $srcset) as $candidate) {
                $candidates[] = trim(explode(' ', trim($candidate))[0] ?? '');
            }
        }
        preg_match_all('~url\([\s"\']*([^\)"\']+)[\s"\']*\)~i', $content, $cssMatches);
        foreach ($cssMatches[1] ?? [] as $candidate) {
            $candidates[] = $candidate;
        }
        preg_match_all('~https?://[^\s"\'<>),]+~i', $content, $absoluteMatches);
        foreach ($absoluteMatches[0] ?? [] as $candidate) {
            $candidates[] = $candidate;
        }
        foreach (array_unique(array_filter($candidates)) as $candidate) {
            try {
                yield UriResolver::resolve($result->url(), new Uri($candidate));
            } catch (InvalidArgumentException $exception) {
                continue;
            }
        }
    }
    private function isOriginUrl(UriInterface $url, Build $build): bool
    {
        return $build->entryUrl()->getHost() !== $build->destinationUrl()->getHost() && $url->getAuthority() === $build->entryUrl()->getAuthority();
    }
    private function isDestinationInternalUrl(UriInterface $url, Build $build): bool
    {
        return $url->getAuthority() === $build->destinationUrl()->getAuthority();
    }
    private function withoutFragment(UriInterface $url): UriInterface
    {
        return (new Uri((string) $url))->withFragment('');
    }
    private function warning(string $message, Build $build, Result $result, ?UriInterface $referencedUrl, string $type): void
    {
        $this->logger->warning($message, ['auditType' => $type, 'buildId' => $build->id(), 'resultId' => $result->id(), 'sourceUrl' => (string) $result->url(), 'referencedUrl' => $referencedUrl ? (string) $referencedUrl : null, 'mimeType' => $result->mimeType()]);
    }
    private function limitReached(Build $build, int $numIssues): void
    {
        $this->logger->warning('Static output quality audit stopped after reaching the issue limit.', ['buildId' => $build->id(), 'numIssues' => $numIssues, 'maxIssues' => $this->maxIssues]);
    }
}
