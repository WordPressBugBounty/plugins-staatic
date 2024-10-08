<?php

namespace Staatic\Vendor\Symfony\Component\CssSelector\Parser\Tokenizer;

class TokenizerEscaping
{
    /**
     * @var TokenizerPatterns
     */
    private $patterns;
    public function __construct(TokenizerPatterns $patterns)
    {
        $this->patterns = $patterns;
    }
    /**
     * @param string $value
     */
    public function escapeUnicode($value): string
    {
        $value = $this->replaceUnicodeSequences($value);
        return preg_replace($this->patterns->getSimpleEscapePattern(), '$1', $value);
    }
    /**
     * @param string $value
     */
    public function escapeUnicodeAndNewLine($value): string
    {
        $value = preg_replace($this->patterns->getNewLineEscapePattern(), '', $value);
        return $this->escapeUnicode($value);
    }
    private function replaceUnicodeSequences(string $value): string
    {
        return preg_replace_callback($this->patterns->getUnicodeEscapePattern(), function ($match) {
            $c = hexdec($match[1]);
            if (0x80 > $c %= 0x200000) {
                return \chr($c);
            }
            if (0x800 > $c) {
                return \chr(0xc0 | $c >> 6) . \chr(0x80 | $c & 0x3f);
            }
            if (0x10000 > $c) {
                return \chr(0xe0 | $c >> 12) . \chr(0x80 | $c >> 6 & 0x3f) . \chr(0x80 | $c & 0x3f);
            }
            return '';
        }, $value);
    }
}
