<?php

namespace Tero\Core\Http;

use Tero\Core\Routing\RouteResolver;
use Tero\Core\Routing\AttributeParser;
use Tero\Core\Routing\MiddlewareStack;
use Tero\Core\Routing\Pipeline;
use Tero\Core\Error\ErrorHandler;
use Tero\Core\Console\ConsoleManager;
use Tero\Config\ConfigManager;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HttpHandler - Manejador principal de peticiones HTTP
 * 
 * @package Tero\Core\Http
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class HttpHandler
{
    private RouteResolver $routeResolver;
    private AttributeParser $attributeParser;
    private MiddlewareStack $middlewareStack;
    private Pipeline $pipeline;
    private ErrorHandler $errorHandler;
    private ConsoleManager $consoleManager;
    private ConfigManager $config;

    public function __construct(
        RouteResolver $routeResolver,
        AttributeParser $attributeParser,
        MiddlewareStack $middlewareStack,
        Pipeline $pipeline,
        ErrorHandler $errorHandler,
        ConsoleManager $consoleManager,
        ConfigManager $config
    ) {
        $this->routeResolver = $routeResolver;
        $this->attributeParser = $attributeParser;
        $this->middlewareStack = $middlewareStack;
        $this->pipeline = $pipeline;
        $this->errorHandler = $errorHandler;
        $this->consoleManager = $consoleManager;
        $this->config = $config;
    }

    /**
     * Manejar petición HTTP
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // 1. Resolver ruta
            $route = $this->routeResolver->resolve($request);
            
            if (!$route) {
                return $this->errorHandler->handleNotFound($request);
            }

            // 2. Parsear atributos de la ruta
            $attributes = $this->attributeParser->parse($route);
            
            // 3. Construir stack de middleware
            $middlewareStack = $this->middlewareStack->build($route, $attributes);
            
            // 4. Ejecutar pipeline
            $response = $this->pipeline->process($request, $middlewareStack, $route);
            
            return $response;

        } catch (\Throwable $e) {
            return $this->errorHandler->handleException($request, $e);
        }
    }

    /**
     * Manejar petición CLI
     */
    public function handleCli(array $args): int
    {
        try {
            // Procesar argumentos CLI
            $command = $args[1] ?? 'help';
            $subCommand = $args[2] ?? null;
            
            // Usar ConsoleManager para ejecutar comandos
            return $this->consoleManager->executeCommand($command, array_slice($args, 2));

        } catch (\Throwable $e) {
            $this->errorHandler->handleCliException($e);
            return 1;
        }
    }

    /**
     * Ejecutar comando CLI
     */
    private function executeCliCommand(array $route, array $args): int
    {
        $handler = $route['handler'];
        
        if (is_callable($handler)) {
            return $handler($args);
        }
        
        if (is_string($handler) && class_exists($handler)) {
            $instance = new $handler();
            if (method_exists($instance, 'handle')) {
                return $instance->handle($args);
            }
        }
        
        return 0;
    }

    /**
     * Verificar si es petición CLI
     */
    public function isCli(): bool
    {
        return php_sapi_name() === 'cli';
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'route_resolver' => get_class($this->routeResolver),
            'attribute_parser' => get_class($this->attributeParser),
            'middleware_stack' => get_class($this->middlewareStack),
            'pipeline' => get_class($this->pipeline),
            'error_handler' => get_class($this->errorHandler),
            'config' => get_class($this->config),
            'is_cli' => $this->isCli(),
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
}
