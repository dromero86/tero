<?php
namespace Tero;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Http1Router implements RequestInterface, ResponseInterface
{
    // Propiedades para almacenar estado de Request
    
    private $method;
    private $uri;
    private $headers = [];
    private $body;
    private $protocolVersion = '1.1';

    // Propiedades para almacenar estado de Response
    private $statusCode = 200;
    private $reasonPhrase = '';

    // Almacenamiento de rutas y handlers
    private $routes = [];

    public function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[$method][$path] = $handler;
    }

    public function handleRequest(): ResponseInterface
    {
        $path = $this->uri->getPath();
        $method = $this->method;

        if (isset($this->routes[$method][$path])) {
            $handler = $this->routes[$method][$path];
            return $handler($this);
        }

        // Si no hay ruta, devolver 404
        return $this->withStatus(404, 'Not Found');
    }

    // ------------------------------------------------------------------------
    // Implementación de RequestInterface
    // ------------------------------------------------------------------------

    public function getRequestTarget(): string
    {
        return $this->uri->getPath() ?? '/';
    }

    public function withRequestTarget($requestTarget): self
    {
        $new = clone $this;
        $new->uri = $new->uri->withPath($requestTarget);
        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): self
    {
        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        $new = clone $this;
        $new->uri = $uri;
        return $new;
    }

    // ... Otros métodos de RequestInterface (similares, manejando headers, etc.)

    // ------------------------------------------------------------------------
    // Implementación de ResponseInterface
    // ------------------------------------------------------------------------

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus($code, $reasonPhrase = ''): self
    {
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase;
        return $new;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    // ... Otros métodos de ResponseInterface (headers, body, etc.)

    // ------------------------------------------------------------------------
    // Métodos comunes a ambas interfaces (simplificados)
    // ------------------------------------------------------------------------

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion($version): self
    {
        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader($name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value): self
    {
        $new = clone $this;
        $new->headers[strtolower($name)] = (array) $value;
        return $new;
    }

    // ... Métodos similares para withAddedHeader y withoutHeader

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): self
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    public function withAddedHeader($name, $value): self
    {
        // Implementación mostrada arriba
        $new = clone $this;
        $new->headers[strtolower($name)] = (array) $value;
        return $new;
    }

    public function withoutHeader($name): self
    {
        // Implementación mostrada arriba
        $new = clone $this;
        unset($new->headers[strtolower($name)]);
        return $new;
    }
}