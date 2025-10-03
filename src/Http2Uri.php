<?php

namespace Tero;

use Psr\Http\Message\UriInterface;

/**
 * HTTP/2 Uri implementation (PSR-7 UriInterface)
 */
class Http2Uri implements UriInterface
{
    /**
     * @var string
     */
    private $scheme = '';

    /**
     * @var string
     */
    private $userInfo = '';

    /**
     * @var string
     */
    private $host = '';

    /**
     * @var int|null
     */
    private $port = null;

    /**
     * @var string
     */
    private $path = '';

    /**
     * @var string
     */
    private $query = '';

    /**
     * @var string
     */
    private $fragment = '';

    /**
     * Standard ports for various schemes
     * 
     * @var array
     */
    private static $standardPorts = [
        'http' => 80,
        'https' => 443,
    ];

    /**
     * Create a new HTTP/2 Uri
     * 
     * @param string $uri
     */
    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $parts = parse_url($uri);
            if ($parts === false) {
                throw new \InvalidArgumentException('Unable to parse URI: ' . $uri);
            }
            
            $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
            $this->userInfo = isset($parts['user']) ? $parts['user'] : '';
            if (isset($parts['pass'])) {
                $this->userInfo .= ':' . $parts['pass'];
            }
            $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
            $this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
            $this->path = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
            $this->query = isset($parts['query']) ? $this->filterQueryAndFragment($parts['query']) : '';
            $this->fragment = isset($parts['fragment']) ? $this->filterQueryAndFragment($parts['fragment']) : '';
        }
    }

    /**
     * Filter and normalize the port
     * 
     * @param int $port
     * @return int|null
     */
    private function filterPort($port)
    {
        if ($port === null) {
            return null;
        }
        
        $port = (int) $port;
        if ($port < 1 || $port > 65535) {
            return null;
        }
        
        return $port;
    }

    /**
     * Filter and normalize the path
     * 
     * @param string $path
     * @return string
     */
    private function filterPath($path)
    {
        return preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~:@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/',
            function ($match) {
                return rawurlencode($match[0]);
            },
            $path
        );
    }

    /**
     * Filter and normalize the query string or fragment
     * 
     * @param string $str
     * @return string
     */
    private function filterQueryAndFragment($str)
    {
        return preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~:@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/',
            function ($match) {
                return rawurlencode($match[0]);
            },
            $str
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthority()
    {
        if ($this->host === '') {
            return '';
        }
        
        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }
        
        if ($this->port !== null && !$this->isStandardPort()) {
            $authority .= ':' . $this->port;
        }
        
        return $authority;
    }

    /**
     * Check if the port is standard for the scheme
     * 
     * @return bool
     */
    private function isStandardPort()
    {
        return $this->port === null || 
            (isset(self::$standardPorts[$this->scheme]) && $this->port === self::$standardPorts[$this->scheme]);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserInfo()
    {
        return $this->userInfo;
    }

    /**
     * {@inheritdoc}
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort()
    {
        return $this->isStandardPort() ? null : $this->port;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    /**
     * {@inheritdoc}
     */
    public function withScheme($scheme)
    {
        $scheme = strtolower($scheme);
        if ($this->scheme === $scheme) {
            return $this;
        }
        
        $new = clone $this;
        $new->scheme = $scheme;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withUserInfo($user, $password = null)
    {
        $info = $user;
        if ($password !== null && $password !== '') {
            $info .= ':' . $password;
        }
        
        if ($this->userInfo === $info) {
            return $this;
        }
        
        $new = clone $this;
        $new->userInfo = $info;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHost($host)
    {
        $host = strtolower($host);
        if ($this->host === $host) {
            return $this;
        }
        
        $new = clone $this;
        $new->host = $host;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withPort($port)
    {
        $port = $this->filterPort($port);
        if ($this->port === $port) {
            return $this;
        }
        
        $new = clone $this;
        $new->port = $port;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withPath($path)
    {
        $path = $this->filterPath($path);
        if ($this->path === $path) {
            return $this;
        }
        
        $new = clone $this;
        $new->path = $path;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withQuery($query)
    {
        $query = $this->filterQueryAndFragment($query);
        if ($this->query === $query) {
            return $this;
        }
        
        $new = clone $this;
        $new->query = $query;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withFragment($fragment)
    {
        $fragment = $this->filterQueryAndFragment($fragment);
        if ($this->fragment === $fragment) {
            return $this;
        }
        
        $new = clone $this;
        $new->fragment = $fragment;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        $uri = '';
        
        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }
        
        $authority = $this->getAuthority();
        if ($authority !== '' || $this->scheme === 'file') {
            $uri .= '//' . $authority;
        }
        
        $uri .= $this->path;
        
        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }
        
        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }
        
        return $uri;
    }
}