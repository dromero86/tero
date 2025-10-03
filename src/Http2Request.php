<?php 

namespace Tero;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP/2 Request implementation (PSR-7 RequestInterface)
 */
class Http2Request implements RequestInterface
{
    /**
     * @var string
     */
    private $method;

    /**
     * @var UriInterface
     */
    private $uri;

    /**
     * @var array
     */
    private $headers = [];

    /**
     * @var string
     */
    private $protocolVersion = '2.0';

    /**
     * @var StreamInterface
     */
    private $body;

    /**
     * @var array
     */
    private $serverParams = [];

    /**
     * Create a new HTTP/2 Request
     * 
     * @param string $method
     * @param UriInterface|string $uri
     * @param array $headers
     * @param StreamInterface|string|null $body
     * @param string $protocolVersion
     */
    public function __construct(
        string $method = 'GET',
        $uri = '',
        array $headers = [],
        $body = null,
        string $protocolVersion = '2.0'
    ) {
        $this->method = $method;
        $this->uri = $uri instanceof UriInterface ? $uri : new HTTP2Uri($uri);
        $this->headers = $headers;
        $this->body = $body instanceof StreamInterface ? $body : new HTTP2Stream($body ?? '');
        $this->protocolVersion = $protocolVersion;
    }

    /**
     * Create request from globals
     * 
     * @return self
     */
    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
        $uri .= $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri .= $_SERVER['REQUEST_URI'] ?? '/';
        
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }
        
        $body = file_get_contents('php://input');
        
        return new self($method, $uri, $headers, $body);
    }

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion() : string
    {
        return $this->protocolVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function withProtocolVersion($version) : RequestInterface
    {
        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders() : array
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader($name) : bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($name) : array
    {
        $name = strtolower($name);
        if (!$this->hasHeader($name)) {
            return [];
        }
        
        $value = $this->headers[$name];
        return is_array($value) ? $value : [$value];
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderLine($name) : string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader($name, $value) : RequestInterface
    {
        $new = clone $this;
        $new->headers[strtolower($name)] = is_array($value) ? $value : [$value];
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader($name, $value) : RequestInterface
    {
        $new = clone $this;
        $name = strtolower($name);
        if (!isset($new->headers[$name])) {
            $new->headers[$name] = [];
        }
        
        if (!is_array($new->headers[$name])) {
            $new->headers[$name] = [$new->headers[$name]];
        }
        
        if (is_array($value)) {
            foreach ($value as $val) {
                $new->headers[$name][] = $val;
            }
        } else {
            $new->headers[$name][] = $value;
        }
        
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader($name) : RequestInterface
    {
        $new = clone $this;
        unset($new->headers[strtolower($name)]);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getBody() : StreamInterface
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(StreamInterface $body) : RequestInterface
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestTarget() : string
    {
        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }
        
        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }
        
        return $target;
    }

    /**
     * {@inheritdoc}
     */
    public function withRequestTarget($requestTarget) : RequestInterface
    {
        $new = clone $this;
        // Parse target into path and query
        $parts = explode('?', $requestTarget, 2);
        $path = $parts[0];
        $query = $parts[1] ?? '';
        
        // Create new URI with updated path and query
        $new->uri = $new->uri
            ->withPath($path)
            ->withQuery($query);
        
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod() : string
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function withMethod($method) : RequestInterface
    {
        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri() : UriInterface
    {
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     */
    public function withUri(UriInterface $uri, $preserveHost = false) : RequestInterface
    {
        $new = clone $this;
        $new->uri = $uri;
        
        if ($preserveHost && $this->hasHeader('Host')) {
            return $new;
        }
        
        $host = $uri->getHost();
        if ($host === '') {
            return $new;
        }
        
        if (($port = $uri->getPort()) !== null) {
            $host .= ':' . $port;
        }
        
        return $new->withHeader('Host', $host);
    }
}