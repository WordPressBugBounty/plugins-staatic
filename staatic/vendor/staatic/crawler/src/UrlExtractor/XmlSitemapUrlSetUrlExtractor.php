<?php

namespace Staatic\Crawler\UrlExtractor;

final class XmlSitemapUrlSetUrlExtractor extends AbstractPatternUrlExtractor
{
    protected function getPatterns(): array
    {
        return ['~<loc(?:[^>]*)>\s*([^<]+?)\s*</loc>~', '~<loc(?:[^>]*)>\s*<!\[CDATA\[(.*?)\]\]>\s*</loc>~', '~<image:loc(?:[^>]*)>\s*([^<]+?)\s*</image:loc>~', '~<image:loc(?:[^>]*)>\s*<!\[CDATA\[(.*?)\]\]>\s*</image:loc>~'];
    }
}
