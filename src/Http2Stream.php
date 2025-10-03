<?php 

namespace Tero;

use Psr\Http\Message\StreamInterface; 

/**
 * HTTP/2 Stream implementation (PSR-7 StreamInterface)
 */
class Http2Stream implements StreamInterface
{
    /**
     * @var resource|null
     */
    private $stream;

    /**
     * @var bool
     */
    private $seekable;

    /**
     * @var bool
     */
    private $readable;

    /**
     * @var bool
     */
    private $writable;

    /**
     * @var string|null
     */
    private $uri;

    /**
     * @var int|null
     */
    private $size;

    /**
     * Create a new HTTP/2 Stream
     * 
     * @param string|resource $content
     */
    public function __construct($content = '')
    {
        if (is_string($content)) {
            $stream = fopen('php://temp', 'r+b');
            if ($content !== '') {
                fwrite($stream, $content);
                fseek($stream, 0);
            }
            $this->stream = $stream;
        } elseif (is_resource($content)) {
            $this->stream = $content;
        } else {
            throw new \InvalidArgumentException('Stream must be a string or resource');
        }
        
        $meta = stream_get_meta_data($this->stream);
        $this->seekable = $meta['seekable'];
        $this->readable = strpos($meta['mode'], 'r') !== false || strpos($meta['mode'], '+') !== false;
        $this->writable = strpos($meta['mode'], 'w') !== false || 
                          strpos($meta['mode'], 'a') !== false || 
                          strpos($meta['mode'], '+') !== false;
        $this->uri = $meta['uri'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        try {
            if ($this->isSeekable()) {
                $this->seek(0);
            }
            return $this->getContents();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close() : void
    {
        if ($this->stream) {
            fclose($this->stream);
            $this->stream = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        $stream = $this->stream;
        $this->stream = null;
        $this->size = null;
        $this->uri = null;
        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;
        
        return $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): null | int
    {
        if ($this->size !== null) {
            return $this->size;
        }
        
        if ($this->stream === null) {
            return null;
        }
        
        $stats = fstat($this->stream);
        return $this->size = $stats['size'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function tell() : int
    {
        if ($this->stream === null) {
            throw new \RuntimeException('Stream is detached');
        }
        
        $position = ftell($this->stream);
        if ($position === false) {
            throw new \RuntimeException('Unable to determine stream position');
        }
        
        return $position;
    }

    /**
     * {@inheritdoc}
     */
    public function eof() : bool
    {
        return $this->stream === null || feof($this->stream);
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable() : bool
    {
        return $this->seekable;
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET) : void
    {
        if ($this->stream === null) {
            throw new \RuntimeException('Stream is detached');
        }
        
        if (!$this->seekable) {
            throw new \RuntimeException('Stream is not seekable');
        }
        
        if (fseek($this->stream, $offset, $whence) === -1) {
            throw new \RuntimeException('Unable to seek to stream position ' . $offset);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rewind() : void
    {
        $this->seek(0);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable() : bool
    {
        return $this->writable;
    }

    /**
     * {@inheritdoc}
     */
    public function write($string) : int
    {
        if ($this->stream === null) {
            throw new \RuntimeException('Stream is detached');
        }
        
        if (!$this->writable) {
            throw new \RuntimeException('Stream is not writable');
        }
        
        $bytes = fwrite($this->stream, $string);
        if ($bytes === false) {
            throw new \RuntimeException('Unable to write to stream');
        }
        
        $this->size = null;
        return $bytes;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable() : bool
    {
        return $this->readable;
    }

    /**
     * {@inheritdoc}
     */
    public function read($length) : string
    {
        if ($this->stream === null) {
            throw new \RuntimeException('Stream is detached');
        }
        
        if (!$this->readable) {
            throw new \RuntimeException('Stream is not readable');
        }
        
        $data = fread($this->stream, $length);
        if ($data === false) {
            throw new \RuntimeException('Unable to read from stream');
        }
        
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getContents() : string
    {
        if ($this->stream === null) {
            throw new \RuntimeException('Stream is detached');
        }
        
        if (!$this->readable) {
            throw new \RuntimeException('Stream is not readable');
        }
        
        $contents = stream_get_contents($this->stream);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read stream contents');
        }
        
        return $contents;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($key = null)
    {
        if ($this->stream === null) {
            return $key ? null : [];
        }
        
        $meta = stream_get_meta_data($this->stream);
        if ($key === null) {
            return $meta;
        }
        
        return $meta[$key] ?? null;
    }
}