<?php 

namespace Tero; 

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface; 
/**
 * HTTP/2 Response implementation (PSR-7 ResponseInterface)
 */
class Http2Response implements ResponseInterface
{
    /**
     * @var int
     */
    private $statusCode = 200;

    /**
     * @var string
     */
    private $reasonPhrase = '';

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
     * HTTP status codes and reason phrases
     * 
     * @var array
     */
    private static $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * Create a new HTTP/2 Response
     * 
     * @param int $status
     * @param array $headers
     * @param StreamInterface|string|null $body
     * @param string $version
     * @param string $reason
     */
    public function __construct(
        int $status = 200,
        array $headers = [],
        $body = null,
        string $version = '2.0',
        string $reason = ''
    ) {
        $this->statusCode = $status;
        $this->headers = $headers;
        $this->body = $body instanceof StreamInterface ? $body : new HTTP2Stream($body ?? '');
        $this->protocolVersion = $version;
        $this->reasonPhrase = $reason ?: (self::$statusTexts[$status] ?? '');
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
    public function withProtocolVersion($version) : self
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
    public function withHeader($name, $value) : self
    {
        $new = clone $this;
        $new->headers[strtolower($name)] = is_array($value) ? $value : [$value];
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader($name, $value) : self
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
    public function withoutHeader($name) : self
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
    public function withBody(StreamInterface $body) : self
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode() : int
    {
        return $this->statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus($code, $reasonPhrase = '') : self
    {
        $new = clone $this;
        $new->statusCode = (int) $code;
        $new->reasonPhrase = $reasonPhrase ?: (self::$statusTexts[$code] ?? '');
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase() : string
    {
        return $this->reasonPhrase;
    }

    /**
     * Set response content type
     *
     * @param string $contentType
     * @return self
     */
    public function withContentType(string $contentType): self
    {
        return $this->withHeader('Content-Type', $contentType);
    }

    /**
     * Set JSON response
     *
     * @param mixed $data
     * @return self
     */
    public function withJson($data): self
    {
        $body = json_encode($data);
        if ($body === false) {
            throw new \RuntimeException('JSON encoding failed');
        }
        
        return $this
            ->withBody(new HTTP2Stream($body))
            ->withContentType('application/json');
    }

    /**
     * Send the response to the client
     */
    public function send(): void
    {
        // Send status code
        http_response_code($this->statusCode);
        
        // Send headers
        foreach ($this->headers as $name => $values) {
            $values = is_array($values) ? $values : [$values];
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
        
        // Send body
        echo $this->body;
    }

    
}