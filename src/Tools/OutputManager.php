<?php

namespace Tero\Tools;

use Tero\Config\ConfigManager;
use Tero\Tools\Contracts\ToolInterface;

/**
 * OutputManager - Gestor moderno de output
 * 
 * @package Tero\Tools
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class OutputManager implements ToolInterface
{
    private ConfigManager $config;
    private string $charset;
    private string $defaultFormat;
    private bool $debugMode;

    const CHARSET = 'utf-8';
    const MIME_JSON = 'application/json';
    const MIME_TEXT = 'text/plain';
    const MIME_HTML = 'text/html';
    const MIME_XML = 'application/xml';
    const MIME_CSV = 'text/csv';

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->charset = $config->get('APP_ENCODING', 'UTF-8');
        $this->defaultFormat = $config->get('OUTPUT_DEFAULT_FORMAT', 'json');
        $this->debugMode = $config->get('OUTPUT_DEBUG_MODE', false, 'bool');
    }

    /**
     * Output JSON
     */
    public function json(mixed $data, int $status = 200, array $headers = []): void
    {
        $this->setHeaders($status, self::MIME_JSON, $headers);
        
        if ($data instanceof \Exception) {
            $data = $this->formatException($data);
        }
        
        $options = $this->debugMode ? JSON_PRETTY_PRINT : 0;
        $json = json_encode($data, $options | JSON_UNESCAPED_UNICODE);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->json(['error' => 'JSON encoding failed: ' . json_last_error_msg()], 500);
            return;
        }
        
        echo $json;
        exit;
    }

    /**
     * Output texto plano
     */
    public function text(string $text, int $status = 200, array $headers = []): void
    {
        $this->setHeaders($status, self::MIME_TEXT, $headers);
        echo $text;
        exit;
    }

    /**
     * Output HTML
     */
    public function html(string $html, int $status = 200, array $headers = []): void
    {
        $this->setHeaders($status, self::MIME_HTML, $headers);
        echo $html;
        exit;
    }

    /**
     * Output XML
     */
    public function xml(string $xml, int $status = 200, array $headers = []): void
    {
        $this->setHeaders($status, self::MIME_XML, $headers);
        echo $xml;
        exit;
    }

    /**
     * Output CSV
     */
    public function csv(array $data, string $filename = 'export.csv', int $status = 200): void
    {
        $this->setHeaders($status, self::MIME_CSV, [
            'Content-Disposition' => "attachment; filename=\"{$filename}\""
        ]);
        
        if (empty($data)) {
            echo '';
            exit;
        }
        
        $output = fopen('php://output', 'w');
        
        // Escribir headers si es array asociativo
        if (isset($data[0]) && is_array($data[0])) {
            fputcsv($output, array_keys($data[0]));
        }
        
        // Escribir datos
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Output con formato automático
     */
    public function output(mixed $data, int $status = 200, ?string $format = null): void
    {
        $format = $format ?? $this->defaultFormat;
        
        match ($format) {
            'json' => $this->json($data, $status),
            'text' => $this->text((string)$data, $status),
            'html' => $this->html((string)$data, $status),
            'xml' => $this->xml((string)$data, $status),
            default => $this->json($data, $status)
        };
    }

    /**
     * Escribir texto (sin exit)
     */
    public function write(string $text, array $args = []): void
    {
        if (!empty($args)) {
            printf($text, ...$args);
        } else {
            echo $text;
        }
    }

    /**
     * Escribir línea
     */
    public function writeln(string $text = '', array $args = []): void
    {
        $this->write($text . "\n", $args);
    }

    /**
     * Obtener URL base
     */
    public function baseUrl(): string
    {
        $appUrl = $this->config->get('APP_URL', 'http://localhost');
        
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            $path = str_replace(basename($script), '', $script);
            
            return $protocol . '://' . $host . $path;
        }
        
        return $appUrl;
    }

    /**
     * Generar URL completa
     */
    public function url(string $path = '', array $params = []): string
    {
        $url = $this->baseUrl() . ltrim($path, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }

    /**
     * Redireccionar
     */
    public function redirect(string $uri = '', string $method = 'location', int $httpResponseCode = 302): void
    {
        if (!preg_match('#^https?://#i', $uri)) {
            $uri = $this->baseUrl() . ltrim($uri, '/');
        }
        
        switch ($method) {
            case 'refresh':
                header("Refresh:0; url={$uri}");
                break;
            case 'javascript':
                echo "<script>window.location.href = '{$uri}';</script>";
                break;
            case 'meta':
                echo "<meta http-equiv='refresh' content='0;url={$uri}'>";
                break;
            default:
                header("Location: {$uri}", true, $httpResponseCode);
                break;
        }
        
        exit;
    }

    /**
     * Redireccionar con mensaje flash
     */
    public function redirectWithMessage(string $uri, string $message, string $type = 'info'): void
    {
        $this->setFlashMessage($message, $type);
        $this->redirect($uri);
    }

    /**
     * Establecer mensaje flash
     */
    public function setFlashMessage(string $message, string $type = 'info'): void
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $_SESSION['flash_messages'][] = [
            'message' => $message,
            'type' => $type,
            'timestamp' => time()
        ];
    }

    /**
     * Obtener mensajes flash
     */
    public function getFlashMessages(): array
    {
        if (!isset($_SESSION)) {
            return [];
        }
        
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        
        return $messages;
    }

    /**
     * Establecer headers
     */
    private function setHeaders(int $status, string $contentType, array $additionalHeaders = []): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header("Content-Type: {$contentType}; charset={$this->charset}");
            
            foreach ($additionalHeaders as $name => $value) {
                header("{$name}: {$value}");
            }
        }
    }

    /**
     * Formatear excepción para JSON
     */
    private function formatException(\Exception $exception): array
    {
        $data = [
            'result' => false,
            'exception' => true,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ];
        
        if ($this->debugMode) {
            $data['trace'] = $exception->getTraceAsString();
        }
        
        return $data;
    }

    /**
     * Output de respuesta API estándar
     */
    public function apiResponse(mixed $data = null, string $message = 'Success', int $status = 200, array $meta = []): void
    {
        $response = [
            'success' => $status >= 200 && $status < 300,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ];
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        $this->json($response, $status);
    }

    /**
     * Output de error API
     */
    public function apiError(string $message = 'Error', int $status = 400, array $errors = []): void
    {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('c')
        ];
        
        $this->json($response, $status);
    }

    /**
     * Output de respuesta paginada
     */
    public function paginatedResponse(array $data, int $page, int $perPage, int $total, array $meta = []): void
    {
        $totalPages = ceil($total / $perPage);
        
        $response = [
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ],
            'timestamp' => date('c')
        ];
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        $this->json($response);
    }

    /**
     * Output de archivo
     */
    public function file(string $filepath, string $filename = null, string $contentType = null): void
    {
        if (!file_exists($filepath)) {
            $this->apiError('File not found', 404);
            return;
        }
        
        $filename = $filename ?? basename($filepath);
        $contentType = $contentType ?? mime_content_type($filepath) ?? 'application/octet-stream';
        
        $this->setHeaders(200, $contentType, [
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => filesize($filepath)
        ]);
        
        readfile($filepath);
        exit;
    }

    /**
     * Output de imagen
     */
    public function image(string $filepath, string $contentType = null): void
    {
        if (!file_exists($filepath)) {
            $this->apiError('Image not found', 404);
            return;
        }
        
        $contentType = $contentType ?? mime_content_type($filepath) ?? 'image/jpeg';
        
        $this->setHeaders(200, $contentType, [
            'Content-Length' => filesize($filepath),
            'Cache-Control' => 'public, max-age=3600'
        ]);
        
        readfile($filepath);
        exit;
    }

    /**
     * Output de descarga
     */
    public function download(string $content, string $filename, string $contentType = 'application/octet-stream'): void
    {
        $this->setHeaders(200, $contentType, [
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($content)
        ]);
        
        echo $content;
        exit;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'charset' => $this->charset,
            'default_format' => $this->defaultFormat,
            'debug_mode' => $this->debugMode,
            'base_url' => $this->baseUrl(),
            'headers_sent' => headers_sent()
        ];
    }
}
