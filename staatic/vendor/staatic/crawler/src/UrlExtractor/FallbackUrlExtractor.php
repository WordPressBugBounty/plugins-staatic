<?php

namespace Staatic\Crawler\UrlExtractor;

use Generator;
final class FallbackUrlExtractor extends AbstractPatternUrlExtractor
{
    private const CONTEXT_SIZE = 100;
    private const MAX_DISCOVERY_PATTERN_CACHE_SIZE = 20;
    /**
     * @var string|null
     */
    private $filterBasePath;
    /**
     * @var mixed[]
     */
    private $discoveryPatternCache = [];
    public function __construct(?string $filterBasePath = null, ?callable $filterCallback = null, ?callable $transformCallback = null, bool $extendedUrlContext = \false)
    {
        parent::__construct($filterCallback, $transformCallback, $extendedUrlContext);
        $this->setFilterBasePath($filterBasePath);
    }
    /**
     * @param string|null $filterBasePath
     */
    public function setFilterBasePath($filterBasePath): void
    {
        $this->filterBasePath = $filterBasePath;
        $this->clearDiscoveryPatternCache();
    }
    public function clearDiscoveryPatternCache(): void
    {
        $this->discoveryPatternCache = [];
    }
    /**
     * @param mixed[] $pattern
     */
    protected function extractUsingPattern($pattern): Generator
    {
        if (!$this->extendedUrlContext) {
            yield from parent::extractUsingPattern($pattern);
            return;
        }
        yield from $this->extractUsingPatternWithExtendedContext($pattern);
    }
    private function extractUsingPatternWithExtendedContext(array $pattern): Generator
    {
        $this->pattern = $pattern;
        $discoveryPattern = $this->createPatternWithoutContext($pattern['pattern']);
        $matches = [];
        $matchResult = preg_match_all($discoveryPattern, $this->content, $matches, \PREG_OFFSET_CAPTURE | \PREG_SET_ORDER);
        if ($matchResult === \false) {
            $this->logger->warning('Pattern extraction failed: ' . preg_last_error_msg(), ['pattern' => $discoveryPattern]);
            return;
        }
        if ($matchResult === 0) {
            return;
        }
        $urlMatches = [];
        foreach ($matches as $match) {
            $urlMatch = $match['url'] ?? $match[1];
            $urlValue = $urlMatch[0];
            $urlPosition = $urlMatch[1];
            $urlLength = strlen($urlValue);
            $context = $this->extractContextAroundPosition($this->content, $urlPosition, $urlLength, self::CONTEXT_SIZE);
            foreach ($match as $key => $value) {
                if (is_string($key) && $key !== 'url' && $key !== '0') {
                    $context[$key] = is_array($value) ? $value[0] : $value;
                }
            }
            $urlMatches[] = ['position' => $urlPosition, 'fullMatch' => $match[0][0], 'url' => $urlValue, 'context' => $context, 'length' => $urlLength];
        }
        $extractedUrls = [];
        usort($urlMatches, function ($a, $b) {
            return $b['position'] - $a['position'];
        });
        foreach ($urlMatches as $urlMatch) {
            $adjustment = $this->checkAndAdjustForPartialProtocol($urlMatch);
            $replacement = parent::handleMatch($extractedUrls, $adjustment['url'], $adjustment['url'], $urlMatch['context']);
            $this->content = substr_replace($this->content, $replacement, $urlMatch['position'] + $adjustment['positionOffset'], $urlMatch['length'] + $adjustment['lengthOffset']);
        }
        foreach ($extractedUrls as $original => $transformed) {
            yield $original => $transformed;
        }
    }
    private function createPatternWithoutContext(string $patternWithContext): string
    {
        if (isset($this->discoveryPatternCache[$patternWithContext])) {
            return $this->discoveryPatternCache[$patternWithContext];
        }
        $pattern = preg_replace('/\(\?P<before>\.{[^}]+}\)/', '', $patternWithContext);
        $pattern = preg_replace('/\(\?P<after>\.{[^}]+}\)/', '', $pattern);
        if (count($this->discoveryPatternCache) >= self::MAX_DISCOVERY_PATTERN_CACHE_SIZE) {
            array_shift($this->discoveryPatternCache);
        }
        $this->discoveryPatternCache[$patternWithContext] = $pattern;
        return $pattern;
    }
    private function extractContextAroundPosition(string $content, int $position, int $length, int $contextSize): array
    {
        $contentLength = strlen($content);
        $beforeStart = max(0, $position - $contextSize);
        $afterStart = $position + $length;
        return ['before' => substr($content, $beforeStart, $position - $beforeStart) ?: '', 'after' => substr($content, $afterStart, min($contextSize, $contentLength - $afterStart)) ?: ''];
    }
    private function checkAndAdjustForPartialProtocol(array $urlMatch): array
    {
        $url = $urlMatch['url'];
        $positionOffset = 0;
        $lengthOffset = 0;
        if (empty($urlMatch['context']['scheme']) && !empty($urlMatch['context']['before'])) {
            if (preg_match('/(https?:)$/', $urlMatch['context']['before'], $protocolMatch)) {
                $partialProtocol = $protocolMatch[1];
                if (strncmp($url, '//', strlen('//')) === 0 || strncmp($url, '\/\/', strlen('\/\/')) === 0) {
                    $protocolLength = strlen($partialProtocol);
                    $positionOffset = -$protocolLength;
                    $lengthOffset = $protocolLength;
                    $url = $partialProtocol . $url;
                }
            }
        }
        return ['url' => $url, 'positionOffset' => $positionOffset, 'lengthOffset' => $lengthOffset];
    }
    protected function getPatterns(): array
    {
        $formats = ['plain' => ['encode' => function (string $value) {
            return $value;
        }, 'decode' => function (string $value) {
            return $value;
        }], 'jsonEncoded' => ['encode' => function (string $value) {
            return str_replace('/', '\/', $value);
        }, 'decode' => function (string $value) {
            return str_replace('\/', '/', $value);
        }], 'urlEncoded' => ['encode' => function (string $value) {
            return rawurlencode($value);
        }, 'decode' => function (string $value) {
            return rawurldecode($value);
        }]];
        $patterns = [];
        foreach ($formats as $format => $options) {
            $slash = preg_quote($options['encode']('/'), '~');
            $doubleColon = preg_quote($options['encode'](':'), '~');
            $authority = preg_quote($options['encode']($this->baseUrl->getAuthority()), '~');
            $filterBasePath = $this->filterBasePath === null ? '' : preg_quote($options['encode'](trim($this->filterBasePath, '/')), '~');
            $patterns[] = ['pattern' => '~' . ($this->extendedUrlContext ? '(?P<before>.{0,100})' : '') . '(?P<url>
                    (?P<scheme>https?' . $doubleColon . ')?' . $slash . $slash . $authority . '
                    (?P<port>' . $doubleColon . '(?:80|443))?
                    (?P<path>' . (empty($filterBasePath) ? '' : $slash . $filterBasePath) . '

                        # Either the URL has an extra path or in the future it has a non-path char.
                        (' . $slash . '|(?![a-z0-9-._]))

                        # Rest of the path/query chars.
                        (?:' . $slash . '|[a-z0-9-._\~%])*
                    )

                )' . ($this->extendedUrlContext ? '(?P<after>.{0,100})' : '') . '~ix', 'encode' => $options['encode'], 'decode' => $options['decode']];
        }
        return $patterns;
    }
}
