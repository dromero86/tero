<?php 

namespace Tero;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

// Implementación de ResponseInterface (PSR-7)
class Response implements ResponseInterface {
    private $statusCode = 200;
    private $reasonPhrase = 'OK';
    private $headers = [];
    private $body;
    private $protocolVersion = '1.1';

    public function __construct($status = 200, array $headers = []) {
        $this->statusCode = $status;
        $this->headers = $headers;
    }

    // Métodos de MessageInterface
    public function getProtocolVersion() : string{
        return $this->protocolVersion;
    }

    public function withProtocolVersion($version) : self {
        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    public function getHeaders() : array {
        return $this->headers;
    }

    public function hasHeader($name) : bool {
        return isset($this->headers[$name]);
    }

    public function getHeader($name) : array  {
        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine($name) : string {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value) : self {
        $new = clone $this;
        $new->headers[$name] = is_array($value) ? $value : [$value];
        return $new;
    }

    public function withAddedHeader($name, $value) : self {
        $new = clone $this;
        if (!isset($new->headers[$name])) {
            $new->headers[$name] = [];
        }
        $new->headers[$name][] = $value;
        return $new;
    }

    public function withoutHeader($name) : self {
        $new = clone $this;
        unset($new->headers[$name]);
        return $new;
    }

    public function getBody()  : StreamInterface {
        return $this->body;
    }

    public function withBody(StreamInterface $body) : self {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    // Métodos específicos de ResponseInterface
    public function getStatusCode() : int {
        return $this->statusCode;
    }

    public function withStatus($code, $reasonPhrase = '') : self {
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase ?: self::$phrases[$code] ?? 'Unknown';
        return $new;
    }

    public function getReasonPhrase() : string {
        return $this->reasonPhrase;
    }
}