<?php

namespace Tero\Core\Routing;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionClass;
use Tero\Core\Routing\Attributes\Route;
use Tero\Core\Routing\Attributes\HttpPayload;
use Tero\Core\Routing\Attributes\Authorization\Bearer;
use Tero\Core\Routing\Attributes\Authorization\Cookie;
use Tero\Core\Routing\Attributes\Authorization\InsecureUserPass;

/**
 * AttributeParser - Parser de atributos PHP 8+
 * 
 * @package Tero\Core\Routing
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class AttributeParser
{
    private array $cache = [];
    private bool $cacheEnabled;

    public function __construct(bool $cacheEnabled = true)
    {
        $this->cacheEnabled = $cacheEnabled;
    }

    /**
     * Parsear atributos de una ruta
     */
    public function parse(array $route): array
    {
        $handler = $route['handler'];
        $cacheKey = $this->getCacheKey($handler);
        
        // Verificar cache
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $attributes = [
            'route' => null,
            'payload' => null,
            'auth' => [],
            'middleware' => []
        ];

        try {
            if (is_callable($handler)) {
                $reflection = new ReflectionFunction($handler);
            } elseif (is_string($handler) && class_exists($handler)) {
                $reflection = new ReflectionClass($handler);
            } else {
                return $attributes;
            }

            // Parsear atributos
            $attributes['route'] = $this->parseRouteAttribute($reflection);
            $attributes['payload'] = $this->parsePayloadAttribute($reflection);
            $attributes['auth'] = $this->parseAuthAttributes($reflection);
            $attributes['middleware'] = $this->parseMiddlewareAttributes($reflection);

        } catch (\ReflectionException $e) {
            // Manejar error de reflexión
            error_log("Error parsing attributes: " . $e->getMessage());
        }

        // Cachear resultado
        if ($this->cacheEnabled) {
            $this->cache[$cacheKey] = $attributes;
        }

        return $attributes;
    }

    /**
     * Parsear atributo Route
     */
    private function parseRouteAttribute($reflection): ?Route
    {
        $attributes = $reflection->getAttributes(Route::class);
        
        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Parsear atributo HttpPayload
     */
    private function parsePayloadAttribute($reflection): ?HttpPayload
    {
        $attributes = $reflection->getAttributes(HttpPayload::class);
        
        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Parsear atributos de autenticación
     */
    private function parseAuthAttributes($reflection): array
    {
        $auth = [];

        // Bearer Token
        $bearerAttributes = $reflection->getAttributes(Bearer::class);
        if (!empty($bearerAttributes)) {
            $auth['bearer'] = $bearerAttributes[0]->newInstance();
        }

        // Cookie Auth
        $cookieAttributes = $reflection->getAttributes(Cookie::class);
        if (!empty($cookieAttributes)) {
            $auth['cookie'] = $cookieAttributes[0]->newInstance();
        }

        // Basic Auth
        $basicAttributes = $reflection->getAttributes(InsecureUserPass::class);
        if (!empty($basicAttributes)) {
            $auth['basic'] = $basicAttributes[0]->newInstance();
        }

        return $auth;
    }

    /**
     * Parsear atributos de middleware
     */
    private function parseMiddlewareAttributes($reflection): array
    {
        $middleware = [];

        // Buscar atributos de middleware personalizados
        $allAttributes = $reflection->getAttributes();
        
        foreach ($allAttributes as $attribute) {
            $attributeName = $attribute->getName();
            
            // Verificar si es un atributo de middleware
            if (strpos($attributeName, 'Middleware') !== false) {
                $middleware[] = $attribute->newInstance();
            }
        }

        return $middleware;
    }

    /**
     * Obtener clave de cache
     */
    private function getCacheKey($handler): string
    {
        if (is_callable($handler)) {
            if (is_string($handler)) {
                return $handler;
            } elseif (is_array($handler)) {
                return get_class($handler[0]) . '::' . $handler[1];
            } else {
                return spl_object_hash($handler);
            }
        }
        
        return (string) $handler;
    }

    /**
     * Limpiar cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Verificar si un handler tiene atributos específicos
     */
    public function hasAttribute($handler, string $attributeClass): bool
    {
        $attributes = $this->parse($handler);
        
        return match ($attributeClass) {
            Route::class => $attributes['route'] !== null,
            HttpPayload::class => $attributes['payload'] !== null,
            Bearer::class => isset($attributes['auth']['bearer']),
            Cookie::class => isset($attributes['auth']['cookie']),
            InsecureUserPass::class => isset($attributes['auth']['basic']),
            default => false
        };
    }

    /**
     * Obtener middleware de autenticación
     */
    public function getAuthMiddleware(array $attributes): array
    {
        $middleware = [];

        foreach ($attributes['auth'] as $type => $auth) {
            if ($auth && method_exists($auth, 'getMiddleware')) {
                $middleware[] = $auth->getMiddleware();
            }
        }

        return $middleware;
    }

    /**
     * Validar atributos
     */
    public function validateAttributes(array $attributes): array
    {
        $errors = [];

        // Validar Route
        if ($attributes['route']) {
            $route = $attributes['route'];
            if (empty($route->path)) {
                $errors[] = 'Route path cannot be empty';
            }
            if (empty($route->methods)) {
                $errors[] = 'Route methods cannot be empty';
            }
        }

        // Validar HttpPayload
        if ($attributes['payload']) {
            $payload = $attributes['payload'];
            if ($payload->maxSize && $payload->maxSize < 0) {
                $errors[] = 'Payload max size must be positive';
            }
        }

        return $errors;
    }
}
