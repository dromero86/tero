<?php

namespace Tero\Tools;

use Tero\Config\ConfigManager;
use Tero\Tools\Contracts\ToolInterface;

/**
 * LoggingManager - Gestor moderno de logging
 * 
 * @package Tero\Tools
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class LoggingManager implements ToolInterface
{
    private ConfigManager $config;
    private string $logPath;
    private string $filename;
    private bool $production;
    private array $channels = [];
    private array $events = ['before' => null, 'after' => null];
    private array $context = [];

    const LOG_FORMAT = "[{date}][{level}][{tag}] {message}\n";
    const DATE_FORMAT = 'Y-m-d H:i:s';
    const LEVELS = ['DEBUG', 'INFO', 'LOG', 'WARNING', 'ERROR', 'CRITICAL'];

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->logPath = $config->get('LOG_PATH', 'storage/logs');
        $this->filename = $config->get('LOG_FILENAME', '{context}.log');
        $this->production = $config->get('APP_ENV', 'development') === 'production';
        
        $this->ensureLogDirectory();
        $this->setupErrorHandler();
    }

    /**
     * Asegurar que el directorio de logs existe
     */
    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Configurar manejador de errores
     */
    private function setupErrorHandler(): void
    {
        set_error_handler([$this, 'handlePhpError']);
        set_exception_handler([$this, 'handlePhpException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Manejar errores PHP
     */
    public function handlePhpError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $level = $this->getErrorLevel($errno);
        $message = $this->formatErrorMessage($errstr, $errfile, $errline);
        
        $this->log($level, 'PHP', $message, [
            'errno' => $errno,
            'file' => $errfile,
            'line' => $errline,
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'sapi' => php_sapi_name()
        ]);

        return true;
    }

    /**
     * Manejar excepciones PHP
     */
    public function handlePhpException(\Throwable $e): void
    {
        $this->error('EXCEPTION', $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    /**
     * Manejar shutdown
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $this->critical('SHUTDOWN', $error['message'], [
                'type' => $error['type'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        }
    }

    /**
     * Obtener nivel de error
     */
    private function getErrorLevel(int $errno): string
    {
        return match ($errno) {
            E_USER_ERROR, E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE => 'ERROR',
            E_USER_WARNING, E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'WARNING',
            E_USER_NOTICE, E_NOTICE, E_DEPRECATED, E_USER_DEPRECATED => 'INFO',
            default => 'LOG'
        };
    }

    /**
     * Formatear mensaje de error
     */
    private function formatErrorMessage(string $errstr, string $errfile, int $errline): string
    {
        $sapi = php_sapi_name();
        $request = $sapi === 'cli' 
            ? (isset($_SERVER['argv']) ? implode(' ', $_SERVER['argv']) : '')
            : ($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? '');

        return sprintf(
            "%s. File: %s (Line: %s) PHP %s (OS: %s) [Input: %s] Request: %s",
            $errstr,
            $errfile,
            $errline,
            PHP_VERSION,
            PHP_OS,
            $sapi,
            $request
        );
    }

    /**
     * Configurar canal de logging
     */
    public function setChannel(string $channel, bool $useDate = false): void
    {
        $this->channels[$channel] = [
            'name' => $channel,
            'use_date' => $useDate,
            'filename' => $useDate 
                ? date('Y-m-d') . "-{$channel}-{context}.log"
                : "{$channel}-{context}.log"
        ];
    }

    /**
     * Establecer contexto global
     */
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    /**
     * Agregar contexto
     */
    public function addContext(string $key, mixed $value): void
    {
        $this->context[$key] = $value;
    }

    /**
     * Limpiar contexto
     */
    public function clearContext(): void
    {
        $this->context = [];
    }

    /**
     * Configurar evento antes del logging
     */
    public function setBeforeEvent(?callable $callback): void
    {
        $this->events['before'] = $callback;
    }

    /**
     * Configurar evento después del logging
     */
    public function setAfterEvent(?callable $callback): void
    {
        $this->events['after'] = $callback;
    }

    /**
     * Log principal
     */
    public function log(string $level, string $tag, string $message, array $context = []): void
    {
        if (!in_array($level, self::LEVELS)) {
            $level = 'LOG';
        }

        $context = array_merge($this->context, $context);
        $formattedMessage = $this->formatMessage($level, $tag, $message, $context);
        
        // Ejecutar evento antes
        if ($this->events['before']) {
            ($this->events['before'])($level, $tag, $message, $context);
        }

        // Escribir log
        $this->writeLog($formattedMessage, $tag);

        // Ejecutar evento después
        if ($this->events['after']) {
            ($this->events['after'])($level, $tag, $message, $context);
        }
    }

    /**
     * Formatear mensaje de log
     */
    private function formatMessage(string $level, string $tag, string $message, array $context): string
    {
        $date = date(self::DATE_FORMAT);
        
        // Agregar contexto al mensaje si existe
        if (!empty($context)) {
            $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE);
            $message .= " | Context: {$contextStr}";
        }

        $line = str_replace('{date}', $date, self::LOG_FORMAT);
        $line = str_replace('{level}', $level, $line);
        $line = str_replace('{tag}', $tag, $line);
        $line = str_replace('{message}', $message, $line);

        return $line;
    }

    /**
     * Escribir log a archivo
     */
    private function writeLog(string $line, string $channel = 'app'): void
    {
        $filename = $this->getLogFilename($channel);
        $filepath = $this->logPath . '/' . $filename;
        
        file_put_contents($filepath, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Obtener nombre de archivo de log
     */
    private function getLogFilename(string $channel): string
    {
        if (isset($this->channels[$channel])) {
            $filename = $this->channels[$channel]['filename'];
        } else {
            $filename = $this->filename;
        }

        $context = $this->production ? 'prod' : 'dev';
        return str_replace('{context}', $context, $filename);
    }

    /**
     * Métodos de conveniencia
     */
    public function debug(string $tag, string $message, array $context = []): void
    {
        $this->log('DEBUG', $tag, $message, $context);
    }

    public function info(string $tag, string $message, array $context = []): void
    {
        $this->log('INFO', $tag, $message, $context);
    }

    public function logMessage(string $tag, string $message, array $context = []): void
    {
        $this->log('LOG', $tag, $message, $context);
    }

    public function warning(string $tag, string $message, array $context = []): void
    {
        $this->log('WARNING', $tag, $message, $context);
    }

    public function error(string $tag, string $message, array $context = []): void
    {
        $this->log('ERROR', $tag, $message, $context);
    }

    public function critical(string $tag, string $message, array $context = []): void
    {
        $this->log('CRITICAL', $tag, $message, $context);
    }

    /**
     * Log con contexto estructurado
     */
    public function logWithContext(string $level, string $tag, string $message, array $context = []): void
    {
        $this->log($level, $tag, $message, $context);
    }

    /**
     * Log de performance
     */
    public function logPerformance(string $tag, string $operation, float $duration, array $context = []): void
    {
        $this->info($tag, "Performance: {$operation} took {$duration}s", array_merge($context, [
            'operation' => $operation,
            'duration' => $duration,
            'type' => 'performance'
        ]));
    }

    /**
     * Log de seguridad
     */
    public function logSecurity(string $tag, string $event, array $context = []): void
    {
        $this->warning($tag, "Security event: {$event}", array_merge($context, [
            'event' => $event,
            'type' => 'security'
        ]));
    }

    /**
     * Log de base de datos
     */
    public function logDatabase(string $tag, string $query, array $context = []): void
    {
        $this->debug($tag, "Database query: {$query}", array_merge($context, [
            'query' => $query,
            'type' => 'database'
        ]));
    }

    /**
     * Rotar logs
     */
    public function rotateLogs(int $maxFiles = 10): int
    {
        $files = glob($this->logPath . '/*.log');
        $rotated = 0;
        
        if (count($files) > $maxFiles) {
            // Ordenar por fecha de modificación
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Eliminar archivos más antiguos
            $filesToDelete = array_slice($files, 0, count($files) - $maxFiles);
            foreach ($filesToDelete as $file) {
                unlink($file);
                $rotated++;
            }
        }
        
        return $rotated;
    }

    /**
     * Limpiar logs antiguos
     */
    public function cleanupOldLogs(int $daysToKeep = 30): int
    {
        $files = glob($this->logPath . '/*.log');
        $deleted = 0;
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }

    /**
     * Obtener estadísticas de logs
     */
    public function getLogStats(): array
    {
        $files = glob($this->logPath . '/*.log');
        $totalSize = 0;
        $fileCount = count($files);
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
        }
        
        return [
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'log_path' => $this->logPath,
            'production' => $this->production,
            'channels' => array_keys($this->channels)
        ];
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'log_path' => $this->logPath,
            'filename' => $this->filename,
            'production' => $this->production,
            'channels' => $this->channels,
            'context' => $this->context,
            'stats' => $this->getLogStats()
        ];
    }
}
