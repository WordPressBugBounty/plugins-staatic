<?php

namespace Staatic\Framework\ConfigGenerator;

use Staatic\Framework\Result;
abstract class AbstractConfigGenerator implements ConfigGeneratorInterface
{
    private const DEFAULT_MIME_TYPES = ['apng' => ['image/apng'], 'avif' => ['image/avif'], 'bmp' => ['image/bmp', 'image/x-ms-bmp'], 'gif' => ['image/gif'], 'ico' => ['image/x-icon'], 'cur' => ['image/x-icon'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'svg' => ['image/svg+xml'], 'tif' => ['image/tiff'], 'tiff' => ['image/tiff'], 'webp' => ['image/webp'], 'atom' => ['application/atom+xml'], 'rss' => ['application/rss+xml'], 'eot' => ['application/vnd.ms-fontobject'], 'otf' => ['font/otf'], 'ttf' => ['font/ttf'], 'woff' => ['font/woff', 'application/font-woff'], 'woff2' => ['font/woff2', 'application/font-woff2'], 'mp3' => ['audio/mpeg'], 'm4a' => ['audio/mp4'], 'aac' => ['audio/aac'], 'ogg' => ['audio/ogg'], 'wav' => ['audio/wav'], 'flac' => ['audio/flac'], 'weba' => ['audio/webm'], 'mid' => ['audio/midi'], 'midi' => ['audio/midi'], 'aiff' => ['audio/aiff'], 'ra' => ['audio/x-realaudio'], 'wma' => ['audio/x-ms-wma'], 'pdf' => ['application/pdf'], 'doc' => ['application/msword'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], 'xls' => ['application/vnd.ms-excel'], 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], 'ppt' => ['application/vnd.ms-powerpoint'], 'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'], 'rtf' => ['application/rtf'], 'odt' => ['application/vnd.oasis.opendocument.text'], 'ods' => ['application/vnd.oasis.opendocument.spreadsheet'], 'epub' => ['application/epub+zip'], 'zip' => ['application/zip'], 'gz' => ['application/gzip'], 'tar' => ['application/x-tar'], '7z' => ['application/x-7z-compressed'], 'rar' => ['application/vnd.rar', 'application/x-rar-compressed'], 'css' => ['text/css'], 'js' => ['application/javascript', 'application/x-javascript', 'application/ecmascript', 'application/x-ecmascript', 'text/javascript', 'text/x-javascript', 'text/ecmascript', 'text/x-ecmascript'], 'txt' => ['text/plain'], 'xml' => ['application/xml', 'text/xml'], 'xsl' => ['application/xslt+xml'], 'json' => ['application/json'], 'csv' => ['text/csv'], 'yaml' => ['application/x-yaml', 'text/yaml']];
    /**
     * @param Result $result
     */
    protected function hasNonStandardMimeType($result): bool
    {
        if (!$result->mimeType()) {
            return \false;
        }
        $pathExtension = pathinfo($result->url()->getPath(), \PATHINFO_EXTENSION);
        if ($pathExtension && isset(self::DEFAULT_MIME_TYPES[$pathExtension])) {
            return !in_array($result->mimeType(), self::DEFAULT_MIME_TYPES[$pathExtension]);
        }
        return $result->mimeType() !== 'text/html';
    }
    /**
     * @param Result $result
     */
    protected function hasNonUtf8Charset($result): bool
    {
        if (!$result->charset()) {
            return \false;
        }
        $charset = str_replace('-', '', strtolower($result->charset()));
        return $charset !== 'utf8';
    }
}
