<?php

namespace Tero\Config;

use Dotenv\Dotenv;
use Tero\Config\ConfigInterface;

/**
 * ConfigManager - Gestor de configuración con variables de entorno
 * 
 * @package Tero\Config
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class ConfigManager implements ConfigInterface
{
    private static ?ConfigManager $instance = null;
    private array $config = [];
    private array $cache = [];
    private bool $loaded = false;

    private function __construct()
    {
        $this->loadEnvironment();
    }

    public static function getInstance(): ConfigManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Cargar variables de entorno
     */
    private function loadEnvironment(): void
    {
        if ($this->loaded) {
            return;
        }

        $envPath = $this->getEnvPath();
        
        if (file_exists($envPath)) {
            $dotenv = Dotenv::createImmutable(dirname($envPath));
            $dotenv->load();
        }

        $this->config = $_ENV;
        $this->loaded = true;
    }

    /**
     * Obtener ruta del archivo .env
     */
    private function getEnvPath(): string
    {
        $env = $_ENV['APP_ENV'] ?? 'development';
        
        if (file_exists(__DIR__ . '/../../.env.' . $env)) {
            return __DIR__ . '/../../.env.' . $env;
        }
        
        return __DIR__ . '/../../.env';
    }

    /**
     * Obtener valor de configuración
     */
    public function get(string $key, mixed $default = null, ?string $type = null): mixed
    {
        $value = $this->config[$key] ?? $default;

        if ($type !== null) {
            $value = $this->castValue($value, $type);
        }

        return $value;
    }

    /**
     * Establecer valor de configuración
     */
    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
        $this->cache[$key] = $value;
    }

    /**
     * Verificar si existe una configuración
     */
    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }

    /**
     * Obtener todas las configuraciones
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Convertir valor al tipo especificado
     */
    private function castValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int', 'integer' => (int) $value,
            'float' => (float) $value,
            'array' => is_string($value) ? explode(',', $value) : (array) $value,
            'string' => (string) $value,
            default => $value
        };
    }

    /**
     * Obtener configuración de base de datos
     */
    public function getDatabaseConfig(): array
    {
        return [
            'connection' => $this->get('DB_CONNECTION', 'mysql'),
            'host' => $this->get('DB_HOST', 'localhost'),
            'port' => $this->get('DB_PORT', 3306, 'int'),
            'database' => $this->get('DB_DATABASE', 'tero_db'),
            'username' => $this->get('DB_USERNAME', 'root'),
            'password' => $this->get('DB_PASSWORD', ''),
            'charset' => $this->get('DB_CHARSET', 'utf8mb4'),
            'collation' => $this->get('DB_COLLATION', 'utf8mb4_unicode_ci'),
        ];
    }

    /**
     * Obtener configuración de aplicación
     */
    public function getAppConfig(): array
    {
        return [
            'name' => $this->get('APP_NAME', 'Tero'),
            'env' => $this->get('APP_ENV', 'development'),
            'debug' => $this->get('APP_DEBUG', false, 'bool'),
            'url' => $this->get('APP_URL', 'http://localhost'),
            'timezone' => $this->get('APP_TIMEZONE', 'America/Argentina/Buenos_Aires'),
            'locale' => $this->get('APP_LOCALE', 'es'),
        ];
    }

    /**
     * Obtener configuración de cache
     */
    public function getCacheConfig(): array
    {
        return [
            'driver' => $this->get('CACHE_DRIVER', 'file'),
            'path' => $this->get('CACHE_PATH', 'storage/cache'),
            'ttl' => $this->get('CACHE_TTL', 3600, 'int'),
        ];
    }

    /**
     * Limpiar cache de configuración
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Recargar configuración
     */
    public function reload(): void
    {
        $this->loaded = false;
        $this->cache = [];
        $this->loadEnvironment();
    }
}
