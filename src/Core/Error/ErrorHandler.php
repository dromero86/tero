<?php

namespace Tero\Core\Error;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tero\Config\ConfigManager;

/**
 * ErrorHandler - Manejador de errores centralizado
 * 
 * @package Tero\Core\Error
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class ErrorHandler
{
    private ConfigManager $config;
    private array $handlers = [];
    private bool $debugMode;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->debugMode = $config->get('APP_DEBUG', false, 'bool');
        $this->registerDefaultHandlers();
    }

    /**
     * Manejar excepción general
     */
    public function handleException(ServerRequestInterface $request, \Throwable $e): ResponseInterface
    {
        $this->logException($e);

        if ($this->isApiRequest($request)) {
            return $this->handleApiException($request, $e);
        }

        return $this->handleWebException($request, $e);
    }

    /**
     * Manejar error 404
     */
    public function handleNotFound(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->isApiRequest($request)) {
            return $this->createApiResponse([
                'error' => 'Not Found',
                'message' => 'The requested resource was not found',
                'path' => $request->getUri()->getPath()
            ], 404);
        }

        return $this->createWebResponse('404 - Not Found', 404);
    }

    /**
     * Manejar excepción de middleware
     */
    public function handleMiddlewareException(ServerRequestInterface $request, \Throwable $e, string $middleware): ResponseInterface
    {
        $this->logException($e, ['middleware' => $middleware]);

        if ($this->isApiRequest($request)) {
            return $this->createApiResponse([
                'error' => 'Middleware Error',
                'message' => 'An error occurred in middleware: ' . $middleware,
                'details' => $this->debugMode ? $e->getMessage() : 'Internal server error'
            ], 500);
        }

        return $this->createWebResponse('500 - Internal Server Error', 500);
    }

    /**
     * Manejar excepción de controlador
     */
    public function handleControllerException(ServerRequestInterface $request, \Throwable $e, array $route): ResponseInterface
    {
        $this->logException($e, ['route' => $route]);

        if ($this->isApiRequest($request)) {
            return $this->createApiResponse([
                'error' => 'Controller Error',
                'message' => 'An error occurred in the controller',
                'details' => $this->debugMode ? $e->getMessage() : 'Internal server error'
            ], 500);
        }

        return $this->createWebResponse('500 - Internal Server Error', 500);
    }

    /**
     * Manejar error CLI no encontrado
     */
    public function handleCliNotFound(string $command, ?string $subCommand = null): void
    {
        $fullCommand = $subCommand ? "$command:$subCommand" : $command;
        
        echo "Command '$fullCommand' not found.\n\n";
        echo "Available commands:\n";
        echo "  tero help          Show help\n";
        echo "  tero serve         Start development server\n";
        echo "  tero migrate       Database migrations\n";
        echo "  tero cron          Cron jobs management\n";
        echo "  tero cache:clear   Clear cache\n";
    }

    /**
     * Manejar excepción CLI
     */
    public function handleCliException(\Throwable $e): void
    {
        echo "Error: " . $e->getMessage() . "\n";
        
        if ($this->debugMode) {
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
            echo "Trace:\n" . $e->getTraceAsString() . "\n";
        }
    }

    /**
     * Manejar excepción de API
     */
    private function handleApiException(ServerRequestInterface $request, \Throwable $e): ResponseInterface
    {
        $statusCode = $this->getStatusCodeFromException($e);
        
        $response = [
            'error' => $this->getErrorName($e),
            'message' => $e->getMessage(),
            'path' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
            'timestamp' => date('c')
        ];

        if ($this->debugMode) {
            $response['file'] = $e->getFile();
            $response['line'] = $e->getLine();
            $response['trace'] = $e->getTraceAsString();
        }

        return $this->createApiResponse($response, $statusCode);
    }

    /**
     * Manejar excepción web
     */
    private function handleWebException(ServerRequestInterface $request, \Throwable $e): ResponseInterface
    {
        $statusCode = $this->getStatusCodeFromException($e);
        
        if ($this->debugMode) {
            $content = $this->createDebugPage($e, $request);
        } else {
            $content = $this->createErrorPage($statusCode);
        }

        return $this->createWebResponse($content, $statusCode);
    }

    /**
     * Crear respuesta API
     */
    private function createApiResponse(array $data, int $statusCode): ResponseInterface
    {
        // Crear respuesta PSR-7
        $body = json_encode($data);
        $headers = [
            'Content-Type' => 'application/json',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block'
        ];
        
        return new \GuzzleHttp\Psr7\Response(
            $statusCode,
            $headers,
            $body
        );
    }

    /**
     * Crear respuesta web
     */
    private function createWebResponse(string $content, int $statusCode): ResponseInterface
    {
        // Crear respuesta PSR-7
        $headers = [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block'
        ];
        
        return new \GuzzleHttp\Psr7\Response(
            $statusCode,
            $headers,
            $content
        );
    }

    /**
     * Crear página de debug
     */
    private function createDebugPage(\Throwable $e, ServerRequestInterface $request): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Debug - Tero Framework</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; }
        .error { background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .error h1 { color: #d32f2f; margin-top: 0; }
        .error h2 { color: #1976d2; }
        .code { background: #f5f5f5; padding: 10px; border-radius: 3px; margin: 10px 0; }
        .trace { background: #fff; padding: 10px; border-radius: 3px; margin: 10px 0; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <div class="error">
        <h1>Debug Mode - Tero Framework</h1>
        
        <h2>Exception</h2>
        <div class="code">
            <strong>' . get_class($e) . '</strong>: ' . htmlspecialchars($e->getMessage()) . '
        </div>
        
        <h2>File & Line</h2>
        <div class="code">
            ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '
        </div>
        
        <h2>Request</h2>
        <div class="code">
            <strong>Method:</strong> ' . $request->getMethod() . '<br>
            <strong>Path:</strong> ' . $request->getUri()->getPath() . '<br>
            <strong>Query:</strong> ' . $request->getUri()->getQuery() . '
        </div>
        
        <h2>Stack Trace</h2>
        <div class="trace">
            <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Crear página de error
     */
    private function createErrorPage(int $statusCode): string
    {
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable'
        ];

        $message = $messages[$statusCode] ?? 'Unknown Error';

        return "<!DOCTYPE html>
<html>
<head>
    <title>$statusCode - $message</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #d32f2f; }
        p { color: #666; }
    </style>
</head>
<body>
    <h1>$statusCode - $message</h1>
    <p>An error occurred while processing your request.</p>
</body>
</html>";
    }

    /**
     * Verificar si es petición API
     */
    private function isApiRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        $accept = $request->getHeaderLine('Accept');
        
        return strpos($path, '/api/') === 0 || 
               strpos($accept, 'application/json') !== false;
    }

    /**
     * Obtener código de estado desde excepción
     */
    private function getStatusCodeFromException(\Throwable $e): int
    {
        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        return 500;
    }

    /**
     * Obtener nombre del error
     */
    private function getErrorName(\Throwable $e): string
    {
        $class = get_class($e);
        $parts = explode('\\', $class);
        return end($parts);
    }

    /**
     * Registrar manejadores por defecto
     */
    private function registerDefaultHandlers(): void
    {
        // Registrar manejador de errores PHP
        set_error_handler([$this, 'handlePhpError']);
        set_exception_handler([$this, 'handlePhpException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Manejar errores PHP
     */
    public function handlePhpError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $exception = new \ErrorException($message, 0, $severity, $file, $line);
        $this->logException($exception);

        return true;
    }

    /**
     * Manejar excepciones PHP
     */
    public function handlePhpException(\Throwable $e): void
    {
        $this->logException($e);
        
        if ($this->debugMode) {
            echo $this->createDebugPage($e, $this->createMockRequest());
        } else {
            echo $this->createErrorPage(500);
        }
    }

    /**
     * Manejar shutdown
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            
            $this->logException($exception);
        }
    }

    /**
     * Crear request mock para errores PHP
     */
    private function createMockRequest(): ServerRequestInterface
    {
        // Implementación simplificada
        $request = new \stdClass();
        $request->method = 'GET';
        $request->path = '/';
        $request->query = '';
        
        return $request;
    }

    /**
     * Registrar excepción en log
     */
    private function logException(\Throwable $e, array $context = []): void
    {
        $logData = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'timestamp' => date('c'),
            'context' => $context
        ];

        error_log('Tero Exception: ' . json_encode($logData));
    }
}
