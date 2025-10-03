<?php

namespace Tero\Core\AutoLoad;

use Tero\Config\ConfigManager;
use Tero\Core\Routing\RouteResolver;

/**
 * AutoLoader - Cargador automático de rutas y controladores
 * 
 * @package Tero\Core\AutoLoad
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class AutoLoader
{
    private ConfigManager $config;
    private RouteResolver $routeResolver;
    private array $loadedFiles = [];
    private array $discoveredControllers = [];

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    /**
     * Establecer RouteResolver
     */
    public function setRouteResolver(RouteResolver $routeResolver): void
    {
        $this->routeResolver = $routeResolver;
    }

    /**
     * Cargar rutas desde archivos
     */
    public function loadRoutes(): void
    {
        $routeFiles = $this->getRouteFiles();
        
        foreach ($routeFiles as $file) {
            if (file_exists($file) && !in_array($file, $this->loadedFiles)) {
                $this->loadRouteFile($file);
                $this->loadedFiles[] = $file;
            }
        }
    }

    /**
     * Obtener archivos de rutas
     */
    private function getRouteFiles(): array
    {
        $files = [];
        
        // Rutas por defecto
        $defaultRoutes = [
            __DIR__ . '/../../routes/web.php',
            __DIR__ . '/../../routes/api.php',
            __DIR__ . '/../../routes/console.php'
        ];

        foreach ($defaultRoutes as $file) {
            if (file_exists($file)) {
                $files[] = $file;
            }
        }

        // Rutas personalizadas desde configuración
        $customRoutes = $this->config->get('ROUTE_FILES', [], 'array');
        foreach ($customRoutes as $file) {
            if (file_exists($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Cargar archivo de rutas
     */
    private function loadRouteFile(string $file): void
    {
        try {
            $routes = require $file;
            
            if (is_array($routes)) {
                foreach ($routes as $route) {
                    if (isset($route['pattern'], $route['handler'])) {
                        $this->routeResolver->registerRoute(
                            $route['pattern'],
                            $route['handler'],
                            $route['options'] ?? []
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("Error loading route file $file: " . $e->getMessage());
        }
    }

    /**
     * Auto-descubrir controladores
     */
    public function discoverControllers(): void
    {
        $controllerPaths = $this->getControllerPaths();
        
        foreach ($controllerPaths as $path) {
            if (is_dir($path)) {
                $this->scanDirectory($path);
            }
        }
    }

    /**
     * Obtener rutas de controladores
     */
    private function getControllerPaths(): array
    {
        $paths = [];
        
        // Rutas por defecto
        $defaultPaths = [
            __DIR__ . '/../../Controllers',
            __DIR__ . '/../../Http/Controllers',
            __DIR__ . '/../../Api/Controllers'
        ];

        foreach ($defaultPaths as $path) {
            if (is_dir($path)) {
                $paths[] = $path;
            }
        }

        // Rutas personalizadas desde configuración
        $customPaths = $this->config->get('CONTROLLER_PATHS', [], 'array');
        foreach ($customPaths as $path) {
            if (is_dir($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Escanear directorio de controladores
     */
    private function scanDirectory(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->loadController($file->getPathname());
            }
        }
    }

    /**
     * Cargar controlador
     */
    private function loadController(string $file): void
    {
        try {
            $className = $this->getClassNameFromFile($file);
            
            if ($className && class_exists($className)) {
                $this->discoveredControllers[] = $className;
                $this->parseControllerAttributes($className);
            }
        } catch (\Throwable $e) {
            error_log("Error loading controller $file: " . $e->getMessage());
        }
    }

    /**
     * Obtener nombre de clase desde archivo
     */
    private function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        
        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
            $namespace = $namespaceMatches[1];
        } else {
            $namespace = '';
        }

        if (preg_match('/class\s+(\w+)/', $content, $classMatches)) {
            $className = $classMatches[1];
            return $namespace ? "$namespace\\$className" : $className;
        }

        return null;
    }

    /**
     * Parsear atributos del controlador
     */
    private function parseControllerAttributes(string $className): void
    {
        try {
            $reflection = new \ReflectionClass($className);
            
            foreach ($reflection->getMethods() as $method) {
                $this->parseMethodAttributes($className, $method);
            }
        } catch (\ReflectionException $e) {
            error_log("Error parsing controller attributes for $className: " . $e->getMessage());
        }
    }

    /**
     * Parsear atributos del método
     */
    private function parseMethodAttributes(string $className, \ReflectionMethod $method): void
    {
        $attributes = $method->getAttributes();
        
        foreach ($attributes as $attribute) {
            $attributeName = $attribute->getName();
            
            // Verificar si es un atributo Route
            if (strpos($attributeName, 'Route') !== false) {
                $this->registerRouteFromAttribute($className, $method, $attribute);
            }
        }
    }

    /**
     * Registrar ruta desde atributo
     */
    private function registerRouteFromAttribute(string $className, \ReflectionMethod $method, \ReflectionAttribute $attribute): void
    {
        try {
            $routeAttribute = $attribute->newInstance();
            
            if (method_exists($routeAttribute, 'getFullPath')) {
                $pattern = $routeAttribute->getFullPath();
                $methods = $routeAttribute->methods ?? ['GET'];
                
                $handler = function($request) use ($className, $method) {
                    $instance = new $className();
                    return $instance->{$method->getName()}($request);
                };

                $this->routeResolver->registerRoute($pattern, $handler, [
                    'methods' => $methods,
                    'controller' => $className,
                    'action' => $method->getName()
                ]);
            }
        } catch (\Throwable $e) {
            error_log("Error registering route from attribute: " . $e->getMessage());
        }
    }

    /**
     * Cargar rutas desde cache
     */
    public function loadFromCache(): bool
    {
        $cacheFile = $this->config->get('ROUTE_CACHE_PATH', 'storage/cache/routes.php');
        
        if (file_exists($cacheFile)) {
            try {
                $cachedRoutes = require $cacheFile;
                
                if (is_array($cachedRoutes)) {
                    foreach ($cachedRoutes as $route) {
                        $this->routeResolver->registerRoute(
                            $route['pattern'],
                            $route['handler'],
                            $route['options'] ?? []
                        );
                    }
                    return true;
                }
            } catch (\Throwable $e) {
                error_log("Error loading routes from cache: " . $e->getMessage());
            }
        }

        return false;
    }

    /**
     * Guardar rutas en cache
     */
    public function saveToCache(): void
    {
        if (!$this->config->get('ROUTE_CACHE', false, 'bool')) {
            return;
        }

        $cacheFile = $this->config->get('ROUTE_CACHE_PATH', 'storage/cache/routes.php');
        $cacheDir = dirname($cacheFile);
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $routes = $this->routeResolver->getRoutes();
        $cacheContent = "<?php\nreturn " . var_export($routes, true) . ";\n";
        
        file_put_contents($cacheFile, $cacheContent);
    }

    /**
     * Limpiar cache de rutas
     */
    public function clearCache(): void
    {
        $cacheFile = $this->config->get('ROUTE_CACHE_PATH', 'storage/cache/routes.php');
        
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * Obtener archivos cargados
     */
    public function getLoadedFiles(): array
    {
        return $this->loadedFiles;
    }

    /**
     * Obtener controladores descubiertos
     */
    public function getDiscoveredControllers(): array
    {
        return $this->discoveredControllers;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'loaded_files' => $this->loadedFiles,
            'discovered_controllers' => $this->discoveredControllers,
            'cache_enabled' => $this->config->get('ROUTE_CACHE', false, 'bool'),
            'auto_discovery_enabled' => $this->config->get('ROUTING_AUTO_DISCOVERY', true, 'bool')
        ];
    }
}
