<?php

namespace Tero\Core\Routing;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tero\Core\Error\ErrorHandler;

/**
 * Pipeline - Pipeline de procesamiento de middleware
 * 
 * @package Tero\Core\Routing
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class Pipeline
{
    private ErrorHandler $errorHandler;
    private array $cache = [];
    private bool $cacheEnabled;
    private bool $debugMode;

    public function __construct(ErrorHandler $errorHandler, bool $cacheEnabled = true, bool $debugMode = false)
    {
        $this->errorHandler = $errorHandler;
        $this->cacheEnabled = $cacheEnabled;
        $this->debugMode = $debugMode;
    }

    /**
     * Procesar pipeline de middleware
     */
    public function process(ServerRequestInterface $request, array $middlewareStack, array $route): ResponseInterface
    {
        $pipeline = $this->buildPipeline($middlewareStack, $route);
        
        return $pipeline($request);
    }

    /**
     * Construir pipeline de middleware
     */
    private function buildPipeline(array $middlewareStack, array $route): callable
    {
        $cacheKey = md5(serialize($middlewareStack));
        
        // Verificar cache
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Construir pipeline
        $pipeline = array_reduce(
            array_reverse($middlewareStack),
            $this->carry(),
            $this->prepareDestination($route)
        );

        // Cachear pipeline
        if ($this->cacheEnabled) {
            $this->cache[$cacheKey] = $pipeline;
        }

        return $pipeline;
    }

    /**
     * Crear función carry para el pipeline
     */
    private function carry(): callable
    {
        return function (callable $stack, string $middleware) {
            return function (ServerRequestInterface $request) use ($stack, $middleware) {
                try {
                    return $this->callMiddleware($middleware, $request, $stack);
                } catch (\Throwable $e) {
                    return $this->errorHandler->handleMiddlewareException($request, $e, $middleware);
                }
            };
        };
    }

    /**
     * Preparar destino final (controlador)
     */
    private function prepareDestination(array $route): callable
    {
        return function (ServerRequestInterface $request) use ($route) {
            try {
                return $this->callController($route, $request);
            } catch (\Throwable $e) {
                return $this->errorHandler->handleControllerException($request, $e, $route);
            }
        };
    }

    /**
     * Llamar middleware
     */
    private function callMiddleware(string $middleware, ServerRequestInterface $request, callable $next): ResponseInterface
    {
        if ($this->debugMode) {
            error_log("Executing middleware: $middleware");
        }

        // Verificar si es una clase
        if (class_exists($middleware)) {
            $instance = new $middleware();
            
            // Verificar si tiene método handle
            if (method_exists($instance, 'handle')) {
                return $instance->handle($request, $next);
            }
            
            // Verificar si es invocable
            if (is_callable($instance)) {
                return $instance($request, $next);
            }
        }

        // Verificar si es una función
        if (function_exists($middleware)) {
            return $middleware($request, $next);
        }

        // Verificar si es un callable
        if (is_callable($middleware)) {
            return $middleware($request, $next);
        }

        throw new \RuntimeException("Middleware '$middleware' is not callable");
    }

    /**
     * Llamar controlador
     */
    private function callController(array $route, ServerRequestInterface $request): ResponseInterface
    {
        $handler = $route['handler'];
        
        if ($this->debugMode) {
            error_log("Executing controller: " . (is_string($handler) ? $handler : gettype($handler)));
        }

        // Verificar si es callable
        if (is_callable($handler)) {
            $response = $handler($request);
            
            // Convertir a ResponseInterface si es necesario
            if (!$response instanceof ResponseInterface) {
                $response = $this->createResponse($response);
            }
            
            return $response;
        }

        // Verificar si es una clase
        if (is_string($handler) && class_exists($handler)) {
            $instance = new $handler();
            
            if (method_exists($instance, 'handle')) {
                $response = $instance->handle($request);
                
                if (!$response instanceof ResponseInterface) {
                    $response = $this->createResponse($response);
                }
                
                return $response;
            }
        }

        throw new \RuntimeException("Controller is not callable");
    }

    /**
     * Crear respuesta desde datos
     */
    private function createResponse(mixed $data): ResponseInterface
    {
        // Implementación simplificada
        // En una implementación real, usar PSR-7 Response
        $response = new \stdClass();
        $response->data = $data;
        $response->status = 200;
        $response->headers = [];
        
        return $response;
    }

    /**
     * Limpiar cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Habilitar/deshabilitar cache
     */
    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;
    }

    /**
     * Habilitar/deshabilitar modo debug
     */
    public function setDebugMode(bool $enabled): void
    {
        $this->debugMode = $enabled;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'cache_enabled' => $this->cacheEnabled,
            'debug_mode' => $this->debugMode,
            'cache_size' => count($this->cache),
            'cached_pipelines' => array_keys($this->cache)
        ];
    }

    /**
     * Obtener estadísticas del pipeline
     */
    public function getStats(): array
    {
        return [
            'total_pipelines' => count($this->cache),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
}
