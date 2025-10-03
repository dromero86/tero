<?php

namespace Tero\Core;

use Tero\Config\ConfigManager;
use Tero\Core\Http\HttpHandler;
use Tero\Core\Routing\RouteResolver;
use Tero\Core\Routing\RouteCompiler;
use Tero\Core\Routing\AttributeParser;
use Tero\Core\Routing\MiddlewareStack;
use Tero\Core\Routing\Pipeline;
use Tero\Core\Error\ErrorHandler;
use Tero\Core\AutoLoad\AutoLoader;
use Tero\Core\Console\ConsoleManager;
use Tero\Core\Security\SecurityManager;
use Tero\Database\DatabaseManager;
use Tero\Tools\CronManager;
use Tero\Tools\LoggingManager;
use Tero\Database\Migrations\MigrationManager;
use Tero\Tools\OutputManager;
use Tero\Templates\TemplateEngine;
use Tero\Views\ViewManager;
use Tero\Views\LayoutManager;
use Tero\Data\Dataset;
use Tero\Data\DatasetCollection;
use Tero\Session\SessionManager;
use Tero\Cache\CacheManager;
use Tero\Events\EventManager;
use Tero\Queue\QueueManager;
use Tero\Notifications\NotificationManager;
use Tero\Storage\StorageManager;
use Closure;
use Exception;

/**
 * Core - Núcleo principal del framework Tero
 * 
 * @package Tero\Core
 * @author Daniel Romero
 * @version 4.2.2-dev
 * @link https://github.com/dromero86/tero
 */
class Core
{
    /**
     * Versión actual del framework
     */
    const VERSION = '4.2.2-dev';

    private ConfigManager $config;
    private HttpHandler $httpHandler;
    private RouteResolver $routeResolver;
    private ConsoleManager $consoleManager;
    private SecurityManager $securityManager;
    private AutoLoader $autoLoader;
    private DatabaseManager $databaseManager;
    private CronManager $cronManager;
    private LoggingManager $loggingManager;
    private MigrationManager $migrationManager;
    private OutputManager $outputManager;
    private TemplateEngine $templateEngine;
    private ViewManager $viewManager;
    private LayoutManager $layoutManager;
    private SessionManager $sessionManager;
    private CacheManager $cacheManager;
    private EventManager $eventManager;
    private QueueManager $queueManager;
    private NotificationManager $notificationManager;
    private StorageManager $storageManager;
    private bool $initialized = false;
    private bool $autoRun = true;

    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Inicializar el framework
     */
    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // 1. Configurar headers básicos
        $this->setBasicHeaders();

        // 2. Inicializar configuración
        $this->config = ConfigManager::getInstance();

        // 3. Configurar entorno
        $this->configureEnvironment();

        // 4. Inicializar componentes principales
        $this->initializeComponents();

        // 5. Configurar auto-carga
        $this->setupAutoLoad();

        // 6. Configurar auto-ejecución
        $this->setupAutoRun();

        $this->initialized = true;
    }

    /**
     * Configurar headers básicos
     */
    private function setBasicHeaders(): void
    {
        if (!headers_sent()) {
            header("X-Core: Tero " . self::VERSION);
            header("X-Powered-By: Tero Framework");
        }
    }

    /**
     * Configurar entorno
     */
    private function configureEnvironment(): void
    {
        // Configurar timezone
        $timezone = $this->config->get('APP_TIMEZONE', 'America/Argentina/Buenos_Aires');
        date_default_timezone_set($timezone);

        // Configurar encoding
        $encoding = $this->config->get('APP_ENCODING', 'UTF-8');
        mb_internal_encoding($encoding);
        mb_http_output($encoding);

        // Configurar memoria
        $memoryLimit = $this->config->get('APP_MEMORY_LIMIT', '10M');
        ini_set('memory_limit', $memoryLimit);

        // Configurar errores
        $debug = $this->config->get('APP_DEBUG', false, 'bool');
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting($debug ? E_ALL : E_ERROR | E_WARNING | E_PARSE);
    }

    /**
     * Inicializar componentes principales
     */
    private function initializeComponents(): void
    {
        // Inicializar ErrorHandler
        $errorHandler = new ErrorHandler($this->config);

        // Inicializar RouteCompiler
        $routeCompiler = new RouteCompiler();

        // Inicializar RouteResolver
        $this->routeResolver = new RouteResolver($routeCompiler, $this->config);

        // Inicializar AttributeParser
        $attributeParser = new AttributeParser(
            $this->config->get('ATTRIBUTE_CACHE_ENABLED', true, 'bool')
        );

        // Inicializar MiddlewareStack
        $middlewareStack = new MiddlewareStack(
            $this->config->get('MIDDLEWARE_CACHE_ENABLED', true, 'bool')
        );

        // Inicializar Pipeline
        $pipeline = new Pipeline(
            $errorHandler,
            $this->config->get('PIPELINE_CACHE_ENABLED', true, 'bool'),
            $this->config->get('PIPELINE_DEBUG', false, 'bool')
        );

        // Inicializar ConsoleManager
        $this->consoleManager = new ConsoleManager($this->config);

        // Inicializar HttpHandler
        $this->httpHandler = new HttpHandler(
            $this->routeResolver,
            $attributeParser,
            $middlewareStack,
            $pipeline,
            $errorHandler,
            $this->consoleManager,
            $this->config
        );

        // Inicializar SecurityManager
        $this->securityManager = new SecurityManager($this->config);

        // Inicializar AutoLoader
        $this->autoLoader = new AutoLoader($this->config);

        // Inicializar DatabaseManager
        $this->databaseManager = new DatabaseManager($this->config);

        // Inicializar LoggingManager
        $this->loggingManager = new LoggingManager($this->config);

        // Inicializar MigrationManager
        $this->migrationManager = new MigrationManager($this->config, $this->databaseManager);

        // Inicializar CronManager
        $this->cronManager = new CronManager($this->config, $this->databaseManager);

        // Inicializar OutputManager
        $this->outputManager = new OutputManager($this->config);

        // Inicializar TemplateEngine
        $this->templateEngine = new TemplateEngine($this->config);

        // Inicializar ViewManager
        $this->viewManager = new ViewManager($this->config, $this->templateEngine);

        // Inicializar LayoutManager
        $this->layoutManager = new LayoutManager($this->config, $this->templateEngine);

        // Inicializar SessionManager
        $this->sessionManager = new SessionManager($this->config, $this->databaseManager);

        // Inicializar CacheManager
        $this->cacheManager = new CacheManager($this->config, $this->databaseManager);

        // Inicializar EventManager
        $this->eventManager = new EventManager($this->config);

        // Inicializar QueueManager
        $this->queueManager = new QueueManager($this->config, $this->databaseManager);

        // Inicializar NotificationManager
        $this->notificationManager = new NotificationManager($this->config);

        // Inicializar StorageManager
        $this->storageManager = new StorageManager($this->config);
    }

    /**
     * Configurar auto-carga
     */
    private function setupAutoLoad(): void
    {
        if ($this->config->get('AUTO_LOAD_ROUTES', true, 'bool')) {
            $this->autoLoader->loadRoutes();
        }

        if ($this->config->get('ROUTING_AUTO_DISCOVERY', true, 'bool')) {
            $this->autoLoader->discoverControllers();
        }
    }

    /**
     * Configurar auto-ejecución
     */
    private function setupAutoRun(): void
    {
        $this->autoRun = $this->config->get('AUTO_RUN', true, 'bool');
        
        if ($this->autoRun) {
            register_shutdown_function([$this, 'autoExecute']);
        }
    }

    /**
     * Auto-ejecutar al final del script
     */
    public function autoExecute(): void
    {
        if ($this->httpHandler->isCli()) {
            $this->handleCli();
        } else {
            $this->handleWeb();
        }
    }

    /**
     * Manejar petición web
     */
    public function handleWeb(): void
    {
        try {
            $request = $this->createRequest();
            $response = $this->httpHandler->handle($request);
            $this->sendResponse($response);
        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Manejar petición CLI
     */
    public function handleCli(): void
    {
        $args = $_SERVER['argv'] ?? [];
        $exitCode = $this->httpHandler->handleCli($args);
        exit($exitCode);
    }

    /**
     * Registrar ruta GET
     */
    public function get(string $pattern, callable $callback, array $options = []): void
    {
        $this->registerRoute($pattern, $callback, array_merge($options, ['methods' => ['GET']]));
    }

    /**
     * Registrar ruta POST
     */
    public function post(string $pattern, callable $callback, array $options = []): void
    {
        $this->registerRoute($pattern, $callback, array_merge($options, ['methods' => ['POST']]));
    }

    /**
     * Registrar ruta PUT
     */
    public function put(string $pattern, callable $callback, array $options = []): void
    {
        $this->registerRoute($pattern, $callback, array_merge($options, ['methods' => ['PUT']]));
    }

    /**
     * Registrar ruta DELETE
     */
    public function delete(string $pattern, callable $callback, array $options = []): void
    {
        $this->registerRoute($pattern, $callback, array_merge($options, ['methods' => ['DELETE']]));
    }

    /**
     * Registrar ruta PATCH
     */
    public function patch(string $pattern, callable $callback, array $options = []): void
    {
        $this->registerRoute($pattern, $callback, array_merge($options, ['methods' => ['PATCH']]));
    }

    /**
     * Registrar ruta OPTIONS
     */
    public function options(string $pattern, callable $callback, array $options = []): void
    {
        $this->registerRoute($pattern, $callback, array_merge($options, ['methods' => ['OPTIONS']]));
    }

    /**
     * Registrar ruta para cualquier método
     */
    public function any(string $pattern, callable $callback, array $options = []): void
    {
        $this->registerRoute($pattern, $callback, array_merge($options, ['methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS']]));
    }

    /**
     * Registrar ruta con métodos específicos
     */
    public function match(array $methods, string $pattern, callable $callback, array $options = []): void
    {
        $this->registerRoute($pattern, $callback, array_merge($options, ['methods' => $methods]));
    }

    /**
     * Registrar comando CLI
     */
    public function cli(string $command, callable $callback, array $options = []): void
    {
        $this->routeResolver->registerCliRoute($command, $callback, $options);
    }

    /**
     * Registrar comando de sistema
     */
    public function system(string $command, callable $callback, array $options = []): void
    {
        $this->consoleManager->registerSystemCommand($command, $callback, $options);
    }

    /**
     * Registrar ruta con atributos (nuevo método)
     */
    public function route(string $name, callable $callback): void
    {
        // Este método será usado con atributos PHP 8+
        // La ruta se registrará automáticamente cuando se parseen los atributos
        $this->registerRoute($name, $callback);
    }

    /**
     * Registrar ruta con payload (nuevo método)
     */
    public function payload(string $pattern, callable $middleware, callable $callback): void
    {
        $wrappedCallback = function($request) use ($middleware, $callback) {
            $middleware($request);
            return $callback($request);
        };

        $this->registerRoute($pattern, $wrappedCallback, ['methods' => ['POST', 'PUT', 'PATCH']]);
    }

    /**
     * Registrar ruta genérica
     */
    private function registerRoute(string $pattern, callable $callback, array $options = []): void
    {
        $this->routeResolver->registerRoute($pattern, $callback, $options);
    }

    /**
     * Ejecutar manualmente (para compatibilidad)
     */
    public function run(): void
    {
        $this->autoExecute();
    }

    /**
     * Crear request desde variables globales
     */
    private function createRequest(): \Psr\Http\Message\ServerRequestInterface
    {
        // Crear un request PSR-7 simple
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = getallheaders() ?: [];
        $body = file_get_contents('php://input');
        
        // Crear request básico PSR-7
        $request = new \GuzzleHttp\Psr7\ServerRequest(
            $method,
            $uri,
            $headers,
            $body,
            '1.1',
            $_SERVER
        );
        
        return $request;
    }

    /**
     * Enviar respuesta
     */
    private function sendResponse($response): void
    {
        // Implementación simplificada
        if (isset($response->status)) {
            http_response_code($response->status);
        }

        if (isset($response->headers)) {
            foreach ($response->headers as $name => $value) {
                header("$name: $value");
            }
        }

        if (isset($response->data)) {
            echo $response->data;
        }
    }

    /**
     * Manejar error
     */
    private function handleError(\Throwable $e): void
    {
        if ($this->config->get('APP_DEBUG', false, 'bool')) {
            echo "Error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
            echo "Trace:\n" . $e->getTraceAsString() . "\n";
        } else {
            echo "An error occurred. Please try again later.";
        }
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'version' => self::VERSION,
            'initialized' => $this->initialized,
            'auto_run' => $this->autoRun,
            'config' => $this->config->getAppConfig(),
            'routes_count' => count($this->routeResolver->getRoutes()),
            'cli_routes_count' => count($this->routeResolver->getCliRoutes()),
            'http_handler' => $this->httpHandler->getDebugInfo(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }

    /**
     * Obtener instancia de configuración
     */
    public function getConfig(): ConfigManager
    {
        return $this->config;
    }

    /**
     * Obtener instancia de HttpHandler
     */
    public function getHttpHandler(): HttpHandler
    {
        return $this->httpHandler;
    }

    /**
     * Obtener instancia de RouteResolver
     */
    public function getRouteResolver(): RouteResolver
    {
        return $this->routeResolver;
    }

    /**
     * Obtener instancia de ConsoleManager
     */
    public function getConsoleManager(): ConsoleManager
    {
        return $this->consoleManager;
    }

    /**
     * Obtener instancia de SecurityManager
     */
    public function getSecurityManager(): SecurityManager
    {
        return $this->securityManager;
    }

    /**
     * Obtener instancia de DatabaseManager
     */
    public function getDatabaseManager(): DatabaseManager
    {
        return $this->databaseManager;
    }

    /**
     * Obtener instancia de CronManager
     */
    public function getCronManager(): CronManager
    {
        return $this->cronManager;
    }

    /**
     * Obtener instancia de LoggingManager
     */
    public function getLoggingManager(): LoggingManager
    {
        return $this->loggingManager;
    }

    /**
     * Obtener instancia de MigrationManager
     */
    public function getMigrationManager(): MigrationManager
    {
        return $this->migrationManager;
    }

    /**
     * Obtener instancia de OutputManager
     */
    public function getOutputManager(): OutputManager
    {
        return $this->outputManager;
    }

    /**
     * Obtener instancia de TemplateEngine
     */
    public function getTemplateEngine(): TemplateEngine
    {
        return $this->templateEngine;
    }

    /**
     * Obtener instancia de ViewManager
     */
    public function getViewManager(): ViewManager
    {
        return $this->viewManager;
    }

    /**
     * Obtener instancia de LayoutManager
     */
    public function getLayoutManager(): LayoutManager
    {
        return $this->layoutManager;
    }

    /**
     * Obtener instancia de SessionManager
     */
    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }

    /**
     * Obtener instancia de CacheManager
     */
    public function getCacheManager(): CacheManager
    {
        return $this->cacheManager;
    }

    /**
     * Obtener instancia de EventManager
     */
    public function getEventManager(): EventManager
    {
        return $this->eventManager;
    }

    /**
     * Obtener instancia de QueueManager
     */
    public function getQueueManager(): QueueManager
    {
        return $this->queueManager;
    }

    /**
     * Obtener instancia de NotificationManager
     */
    public function getNotificationManager(): NotificationManager
    {
        return $this->notificationManager;
    }

    /**
     * Obtener instancia de StorageManager
     */
    public function getStorageManager(): StorageManager
    {
        return $this->storageManager;
    }
}
