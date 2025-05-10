<?php

namespace Tero;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tero\Http2Response;
use Tero\Http2Stream;
/**
 * HTTP/2 Router implementation that follows PSR-7 interfaces
 */
class Http2Router
{
    /**
     * @var array
     */
    protected $routes = [];

    /**
     * @var array
     */
    protected $middleware = [];

    /**
     * Add a route to the router
     *
     * @param string $method HTTP method
     * @param string $path Route path
     * @param callable $handler Route handler
     * @return $this
     */
    public function addRoute(string $method, string $path, callable $handler): self
    {
        $this->routes[strtoupper($method)][$path] = $handler;
        return $this;
    }

    /**
     * Add middleware to the router
     *
     * @param callable $middleware
     * @return $this
     */
    public function addMiddleware(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Handle the request and return a response
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Apply middleware
        $response = $this->applyMiddleware($request);
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        // Find route handler
        if (isset($this->routes[$method][$path])) {
            $handler = $this->routes[$method][$path];
            return $handler($request, new HTTP2Response());
        }

        // Handle route not found
        return $this->notFound();
    }

    /**
     * Apply middleware to request
     *
     * @param RequestInterface $request
     * @return ResponseInterface|null
     */
    protected function applyMiddleware(RequestInterface $request)
    {
        foreach ($this->middleware as $middleware) {
            $response = $middleware($request);
            if ($response instanceof ResponseInterface) {
                return $response;
            }
        }
        return null;
    }

    /**
     * Create a 404 Not Found response
     *
     * @return ResponseInterface
     */
    protected function notFound(): ResponseInterface
    {
        return (new HTTP2Response())
            ->withStatus(404)
            ->withBody(new HTTP2Stream('Route not found'));
    }
}