<?php

declare (strict_types=1);
namespace Staatic\Vendor\AsyncAws\Core\Credentials;

use Staatic\Vendor\AsyncAws\Core\Exception\RuntimeException;
trait TokenFileLoader
{
    /**
     * @param string $tokenFile
     */
    public function getTokenFileContent($tokenFile): string
    {
        $token = @file_get_contents($tokenFile);
        if (\false !== $token) {
            return $token;
        }
        $tokenDir = \dirname($tokenFile);
        $tokenLink = readlink($tokenFile);
        clearstatcache(\true, $tokenDir . \DIRECTORY_SEPARATOR . $tokenLink);
        clearstatcache(\true, $tokenDir . \DIRECTORY_SEPARATOR . \dirname($tokenLink));
        clearstatcache(\true, $tokenFile);
        if (\false === $token = file_get_contents($tokenFile)) {
            throw new RuntimeException('Failed to read data');
        }
        return $token;
    }
}
