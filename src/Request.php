<?php 

namespace Tero;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

// Implementación de RequestInterface (PSR-7)
class Request implements RequestInterface {
    private $method;
    private $uri;
    private $headers = [];
    private $body;
    private $protocolVersion = '1.1';

    public function __construct($method, $uri) {
        $this->method = $method;
        $this->uri = $uri;
    }

    // Métodos de MessageInterface
    public function getProtocolVersion() : string {
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

    public function getHeader($name) : array {
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

    public function getBody() : StreamInterface {
        return $this->body;
    }

    public function withBody(StreamInterface $body) : self {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    // Métodos específicos de RequestInterface
    public function getRequestTarget() : string {
        return $this->uri->getPath() . ($this->uri->getQuery() ? '?' . $this->uri->getQuery() : '');
    }

    public function withRequestTarget($requestTarget) : self {
        $new = clone $this;
        // Aquí se debería actualizar el URI según el target
        return $new;
    }

    public function getMethod() :string {
        return $this->method;
    }

    public function withMethod($method) : self {
        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    public function getUri() : UriInterface {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false) : self {
        $new = clone $this;
        $new->uri = $uri;
        return $new;
    }
}