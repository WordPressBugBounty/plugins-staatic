<?php

namespace Staatic\Vendor\Symfony\Component\HttpClient\Response;

use BadMethodCallException;
use Throwable;
use Staatic\Vendor\Symfony\Component\HttpClient\Exception\ClientException;
use Staatic\Vendor\Symfony\Component\HttpClient\Exception\JsonException;
use Staatic\Vendor\Symfony\Component\HttpClient\Exception\RedirectionException;
use Staatic\Vendor\Symfony\Component\HttpClient\Exception\ServerException;
use Staatic\Vendor\Symfony\Component\HttpClient\Exception\TransportException;
trait CommonResponseTrait
{
    private $initializer;
    private $shouldBuffer;
    private $content;
    /**
     * @var int
     */
    private $offset = 0;
    /**
     * @var mixed[]|null
     */
    private $jsonData;
    /**
     * @param bool $throw
     */
    public function getContent($throw = \true): string
    {
        if ($this->initializer) {
            self::initialize($this);
        }
        if ($throw) {
            $this->checkStatusCode();
        }
        if (null === $this->content) {
            $content = null;
            foreach (self::stream([$this]) as $chunk) {
                if (!$chunk->isLast()) {
                    $content .= $chunk->getContent();
                }
            }
            if (null !== $content) {
                return $content;
            }
            if (null === $this->content) {
                throw new TransportException('Cannot get the content of the response twice: buffering is disabled.');
            }
        } else {
            foreach (self::stream([$this]) as $chunk) {
            }
        }
        rewind($this->content);
        return stream_get_contents($this->content);
    }
    /**
     * @param bool $throw
     */
    public function toArray($throw = \true): array
    {
        if ('' === $content = $this->getContent($throw)) {
            throw new JsonException('Response body is empty.');
        }
        if (null !== $this->jsonData) {
            return $this->jsonData;
        }
        try {
            $content = json_decode($content, \true, 512, \JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new JsonException($e->getMessage() . sprintf(' for "%s".', $this->getInfo('url')), $e->getCode());
        }
        if (!\is_array($content)) {
            throw new JsonException(sprintf('JSON content was expected to decode to an array, "%s" returned for "%s".', get_debug_type($content), $this->getInfo('url')));
        }
        if (null !== $this->content) {
            return $this->jsonData = $content;
        }
        return $content;
    }
    /**
     * @param bool $throw
     */
    public function toStream($throw = \true)
    {
        if ($throw) {
            $this->getHeaders($throw);
        }
        $stream = StreamWrapper::createResource($this);
        stream_get_meta_data($stream)['wrapper_data']->bindHandles($this->handle, $this->content);
        return $stream;
    }
    public function __sleep(): array
    {
        throw new BadMethodCallException('Cannot serialize ' . __CLASS__);
    }
    public function __wakeup()
    {
        throw new BadMethodCallException('Cannot unserialize ' . __CLASS__);
    }
    abstract protected function close(): void;
    private static function initialize(self $response): void
    {
        if (null !== $response->getInfo('error')) {
            throw new TransportException($response->getInfo('error'));
        }
        try {
            if (($response->initializer)($response, -0.0)) {
                foreach (self::stream([$response], -0.0) as $chunk) {
                    if ($chunk->isFirst()) {
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
            $response->info['error'] = $e->getMessage();
            $response->close();
            throw $e;
        }
        $response->initializer = null;
    }
    private function checkStatusCode()
    {
        $code = $this->getInfo('http_code');
        if (500 <= $code) {
            throw new ServerException($this);
        }
        if (400 <= $code) {
            throw new ClientException($this);
        }
        if (300 <= $code) {
            throw new RedirectionException($this);
        }
    }
}
