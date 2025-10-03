<?php

namespace Tero\Core\Routing;

use Tero\Core\Routing\Attributes\Authorization\Bearer;
use Tero\Core\Routing\Attributes\Authorization\Cookie;
use Tero\Core\Routing\Attributes\Authorization\InsecureUserPass;

/**
 * MiddlewareStack - Constructor de stack de middleware
 * 
 * @package Tero\Core\Routing
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class MiddlewareStack
{
    private array $globalMiddleware = [];
    private array $routeMiddleware = [];
    private array $middlewareGroups = [];
    private array $cache = [];
    private bool $cacheEnabled;

    public function __construct(bool $cacheEnabled = true)
    {
        $this->cacheEnabled = $cacheEnabled;
        $this->registerDefaultMiddleware();
    }

    /**
     * Registrar middleware global
     */
    public function registerGlobal(string $middleware): void
    {
        if (!in_array($middleware, $this->globalMiddleware)) {
            $this->globalMiddleware[] = $middleware;
        }
    }

    /**
     * Registrar middleware de ruta
     */
    public function registerRoute(string $name, string $middleware): void
    {
        $this->routeMiddleware[$name] = $middleware;
    }

    /**
     * Registrar grupo de middleware
     */
    public function registerGroup(string $name, array $middleware): void
    {
        $this->middlewareGroups[$name] = $middleware;
    }

    /**
     * Construir stack de middleware para una ruta
     */
    public function build(array $route, array $attributes): array
    {
        $cacheKey = $this->getCacheKey($route, $attributes);
        
        // Verificar cache
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $stack = [];

        // 1. Middleware global
        $stack = array_merge($stack, $this->globalMiddleware);

        // 2. Middleware de ruta específica
        if (isset($route['middleware']) && is_array($route['middleware'])) {
            foreach ($route['middleware'] as $middleware) {
                if (is_string($middleware)) {
                    $stack[] = $middleware;
                } elseif (is_array($middleware)) {
                    $stack = array_merge($stack, $middleware);
                }
            }
        }

        // 3. Middleware de atributos de autenticación
        $authMiddleware = $this->buildAuthMiddleware($attributes);
        $stack = array_merge($stack, $authMiddleware);

        // 4. Middleware de atributos personalizados
        $customMiddleware = $this->buildCustomMiddleware($attributes);
        $stack = array_merge($stack, $customMiddleware);

        // 5. Resolver grupos de middleware
        $stack = $this->resolveMiddlewareGroups($stack);

        // 6. Eliminar duplicados
        $stack = array_unique($stack);

        // Cachear resultado
        if ($this->cacheEnabled) {
            $this->cache[$cacheKey] = $stack;
        }

        return $stack;
    }

    /**
     * Construir middleware de autenticación
     */
    private function buildAuthMiddleware(array $attributes): array
    {
        $middleware = [];

        if (isset($attributes['auth'])) {
            foreach ($attributes['auth'] as $type => $auth) {
                if ($auth && method_exists($auth, 'getMiddleware')) {
                    $middleware[] = $auth->getMiddleware();
                }
            }
        }

        return $middleware;
    }

    /**
     * Construir middleware personalizado
     */
    private function buildCustomMiddleware(array $attributes): array
    {
        $middleware = [];

        if (isset($attributes['middleware']) && is_array($attributes['middleware'])) {
            foreach ($attributes['middleware'] as $middlewareInstance) {
                if (method_exists($middlewareInstance, 'getMiddleware')) {
                    $middleware[] = $middlewareInstance->getMiddleware();
                }
            }
        }

        return $middleware;
    }

    /**
     * Resolver grupos de middleware
     */
    private function resolveMiddlewareGroups(array $stack): array
    {
        $resolved = [];

        foreach ($stack as $item) {
            if (isset($this->middlewareGroups[$item])) {
                $resolved = array_merge($resolved, $this->middlewareGroups[$item]);
            } else {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }

    /**
     * Registrar middleware por defecto
     */
    private function registerDefaultMiddleware(): void
    {
        // Middleware global por defecto
        $this->registerGlobal('Tero\\Core\\Security\\Middleware\\SecurityHeadersMiddleware');
        $this->registerGlobal('Tero\\Core\\Security\\Middleware\\CorsMiddleware');

        // Middleware de ruta por defecto
        $this->registerRoute('auth', 'Tero\\Core\\Security\\Middleware\\AuthMiddleware');
        $this->registerRoute('csrf', 'Tero\\Core\\Security\\Middleware\\CsrfMiddleware');
        $this->registerRoute('rate_limit', 'Tero\\Core\\Security\\Middleware\\RateLimitMiddleware');

        // Grupos de middleware por defecto
        $this->registerGroup('web', [
            'Tero\\Core\\Security\\Middleware\\CsrfMiddleware',
            'Tero\\Core\\Security\\Middleware\\SessionMiddleware'
        ]);

        $this->registerGroup('api', [
            'Tero\\Core\\Security\\Middleware\\ApiMiddleware',
            'Tero\\Core\\Security\\Middleware\\RateLimitMiddleware'
        ]);

        $this->registerGroup('auth', [
            'Tero\\Core\\Security\\Middleware\\AuthMiddleware'
        ]);
    }

    /**
     * Obtener clave de cache
     */
    private function getCacheKey(array $route, array $attributes): string
    {
        $routeKey = md5(serialize($route));
        $attrKey = md5(serialize($attributes));
        return $routeKey . ':' . $attrKey;
    }

    /**
     * Limpiar cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Obtener middleware global
     */
    public function getGlobalMiddleware(): array
    {
        return $this->globalMiddleware;
    }

    /**
     * Obtener middleware de ruta
     */
    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
    }

    /**
     * Obtener grupos de middleware
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Verificar si existe un middleware
     */
    public function hasMiddleware(string $name): bool
    {
        return isset($this->routeMiddleware[$name]) || 
               isset($this->middlewareGroups[$name]) ||
               in_array($name, $this->globalMiddleware);
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'global_middleware' => $this->globalMiddleware,
            'route_middleware' => $this->routeMiddleware,
            'middleware_groups' => $this->middlewareGroups,
            'cache_enabled' => $this->cacheEnabled,
            'cache_size' => count($this->cache)
        ];
    }
}
