<?php

declare(strict_types=1);

namespace Staatic\WordPress\Service\OriginRules;

use Staatic\Vendor\GuzzleHttp\Psr7\UriResolver;

/**
 * Matches published paths against origin rule path patterns, with the platform's path-pattern
 * semantics rather than those of the exclude rules evaluator:
 *
 * - the pattern is matched against the path only, never the query string or fragment;
 * - matching is case-sensitive;
 * - `*` matches zero or more characters (including `/`), `?` matches exactly one character,
 *   every other character matches itself;
 * - the whole path has to match, not a prefix of it;
 * - a leading `/` is implied when the pattern does not start with one.
 *
 * The matching layer normalizes the request path before matching (repeated slashes and dot
 * segments are collapsed), so normalizePath() does the same: it collapses and resolves exactly
 * those on the path a published resource will be requested under. Percent-encoding is left as is and case is kept:
 * the patterns cannot contain `%`, so an encoded path only ever matches through a wildcard.
 *
 * The platform's OriginRulePatterns implements the same normalization and matching; both assert
 * the shared fixture tests/Fixtures/origin-rule-patterns.json, which has to stay byte-identical
 * to the platform's copy.
 */
final class OriginRulePathMatcher
{
    /** @var string[] Compiled regular expressions, one per pattern. */
    private $expressions;

    /** @param string[] $patterns */
    public function __construct(array $patterns)
    {
        $this->expressions = array_map([self::class, 'compile'], array_values($patterns));
    }

    public function isEmpty(): bool
    {
        return $this->expressions === [];
    }

    /**
     * Whether the (published) path matches any of the patterns. The path is normalized first.
     */
    public function matches(string $path): bool
    {
        $path = self::normalizePath($path);
        foreach ($this->expressions as $expression) {
            if (preg_match($expression, $path) === 1) {
                return \true;
            }
        }

        return \false;
    }

    public static function normalizePattern(string $pattern): string
    {
        return strncmp($pattern, '/', strlen('/')) === 0 ? $pattern : '/' . $pattern;
    }

    public static function normalizePath(string $path): string
    {
        $path = (string) preg_replace('~/{2,}~', '/', $path);
        $path = UriResolver::removeDotSegments($path);

        return $path === '' ? '/' : $path;
    }

    private static function compile(string $pattern): string
    {
        $expression = preg_quote(self::normalizePattern($pattern), '~');
        $expression = strtr($expression, [
            '\*' => '.*',
            '\?' => '.'
        ]);

        return '~\A' . $expression . '\z~s';
    }
}
