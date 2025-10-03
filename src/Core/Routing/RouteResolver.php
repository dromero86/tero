<?php

namespace Tero\Core\Routing;

use Psr\Http\Message\ServerRequestInterface;
use Tero\Core\Routing\RouteCompiler;
use Tero\Config\ConfigManager;

/**
 * RouteResolver - Resolvedor de rutas HTTP y CLI
 * 
 * @package Tero\Core\Routing
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class RouteResolver
{
    private array $routes = [];
    private array $cliRoutes = [];
    private RouteCompiler $compiler;
    private ConfigManager $config;
    private array $cache = [];

    public function __construct(RouteCompiler $compiler, ConfigManager $config)
    {
        $this->compiler = $compiler;
        $this->config = $config;
    }

    /**
     * Registrar ruta HTTP
     */
    public function registerRoute(string $pattern, callable $handler, array $options = []): void
    {
        $route = [
            'pattern' => $pattern,
            'handler' => $handler,
            'methods' => $options['methods'] ?? ['GET'],
            'middleware' => $options['middleware'] ?? [],
            'name' => $options['name'] ?? null,
            'domain' => $options['domain'] ?? null,
            'prefix' => $options['prefix'] ?? null,
            'where' => $options['where'] ?? [],
            'compiled' => null
        ];

        $this->routes[] = $route;
    }

    /**
     * Registrar ruta CLI
     */
    public function registerCliRoute(string $command, callable $handler, array $options = []): void
    {
        $this->cliRoutes[$command] = [
            'command' => $command,
            'handler' => $handler,
            'description' => $options['description'] ?? '',
            'arguments' => $options['arguments'] ?? [],
            'options' => $options['options'] ?? []
        ];
    }

    /**
     * Resolver ruta HTTP
     */
    public function resolve(ServerRequestInterface $request): ?array
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        
        // Verificar cache
        $cacheKey = $method . ':' . $path;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        foreach ($this->routes as $route) {
            // Verificar método HTTP
            if (!in_array($method, $route['methods'])) {
                continue;
            }

            // Compilar patrón si no está compilado
            if ($route['compiled'] === null) {
                $route['compiled'] = $this->compiler->compile($route['pattern'], $route['where']);
            }

            // Verificar coincidencia
            if (preg_match($route['compiled']['regex'], $path, $matches)) {
                $params = [];
                foreach ($route['compiled']['params'] as $index => $param) {
                    if (isset($matches[$index + 1])) {
                        $params[$param] = $matches[$index + 1];
                    }
                }

                $resolvedRoute = array_merge($route, [
                    'params' => $params,
                    'path' => $path,
                    'method' => $method
                ]);

                // Cachear resultado
                if ($this->config->get('ROUTE_CACHE', false, 'bool')) {
                    $this->cache[$cacheKey] = $resolvedRoute;
                }

                return $resolvedRoute;
            }
        }

        return null;
    }

    /**
     * Resolver ruta CLI
     */
    public function resolveCli(string $command, ?string $subCommand = null): ?array
    {
        $fullCommand = $subCommand ? $command . ':' . $subCommand : $command;
        
        if (isset($this->cliRoutes[$fullCommand])) {
            return $this->cliRoutes[$fullCommand];
        }

        if (isset($this->cliRoutes[$command])) {
            return $this->cliRoutes[$command];
        }

        return null;
    }

    /**
     * Obtener todas las rutas registradas
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Obtener todas las rutas CLI registradas
     */
    public function getCliRoutes(): array
    {
        return $this->cliRoutes;
    }

    /**
     * Limpiar cache de rutas
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Cargar rutas desde archivos
     */
    public function loadRoutesFromFiles(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                $routes = require $file;
                if (is_array($routes)) {
                    foreach ($routes as $route) {
                        $this->registerRoute(
                            $route['pattern'],
                            $route['handler'],
                            $route['options'] ?? []
                        );
                    }
                }
            }
        }
    }

    /**
     * Auto-descubrir controladores
     */
    public function autoDiscoverControllers(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->loadControllerRoutes($file->getPathname());
            }
        }
    }

    /**
     * Cargar rutas de un controlador
     */
    private function loadControllerRoutes(string $file): void
    {
        $content = file_get_contents($file);
        
        // Buscar atributos Route
        if (preg_match_all('/#\[Route\(([^)]+)\)\]/', $content, $matches)) {
            foreach ($matches[1] as $match) {
                // Parsear atributo Route (simplificado)
                // En una implementación real, usar Reflection API
                $this->parseRouteAttribute($match, $file);
            }
        }
    }

    /**
     * Parsear atributo Route (simplificado)
     */
    private function parseRouteAttribute(string $attribute, string $file): void
    {
        // Implementación simplificada
        // En una implementación real, usar Reflection API para parsear correctamente
        if (preg_match('/"([^"]+)"/', $attribute, $matches)) {
            $path = $matches[1];
            $this->registerRoute($path, function() {
                return "Route from $file";
            });
        }
    }
}
