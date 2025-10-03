<?php

namespace Tero\Cache;

use Tero\Config\ConfigManager;
use Tero\Database\DatabaseManager;
use Tero\Cache\Contracts\CacheManagerInterface;
use Tero\Cache\Exceptions\CacheException;
use Tero\Cache\Stores\FileCacheStore;
use Tero\Cache\Stores\DatabaseCacheStore;
use Tero\Cache\Stores\RedisCacheStore;
use Tero\Cache\Stores\ArrayCacheStore;

/**
 * CacheManager - Gestor de cache avanzado
 * 
 * @package Tero\Cache
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class CacheManager implements CacheManagerInterface
{
    private ConfigManager $config;
    private ?DatabaseManager $database;
    private string $driver;
    private string $prefix;
    private int $defaultTtl;
    private array $stores = [];
    private ?\Tero\Cache\Contracts\CacheStoreInterface $defaultStore = null;

    public function __construct(ConfigManager $config, ?DatabaseManager $database = null)
    {
        $this->config = $config;
        $this->database = $database;
        $this->driver = $config->get('CACHE_DRIVER', 'file');
        $this->prefix = $config->get('CACHE_PREFIX', 'tero_cache');
        $this->defaultTtl = (int) $config->get('CACHE_TTL', 3600);
        
        $this->initializeDefaultStore();
    }

    /**
     * Inicializar store por defecto
     */
    private function initializeDefaultStore(): void
    {
        $this->defaultStore = $this->createStore($this->driver);
    }

    /**
     * Crear store
     */
    private function createStore(string $driver): \Tero\Cache\Contracts\CacheStoreInterface
    {
        return match ($driver) {
            'file' => new FileCacheStore($this->config),
            'database' => new DatabaseCacheStore($this->config, $this->database),
            'redis' => new RedisCacheStore($this->config),
            'array' => new ArrayCacheStore(),
            default => throw new CacheException("Unsupported cache driver: {$driver}")
        };
    }

    /**
     * Obtener store por nombre
     */
    public function store(string $name = null): \Tero\Cache\Contracts\CacheStoreInterface
    {
        if ($name === null) {
            return $this->defaultStore;
        }
        
        if (!isset($this->stores[$name])) {
            $driver = $this->config->get("CACHE_STORE_{$name}_DRIVER", $this->driver);
            $this->stores[$name] = $this->createStore($driver);
        }
        
        return $this->stores[$name];
    }

    /**
     * Obtener valor del cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->defaultStore->get($this->getKey($key), $default);
    }

    /**
     * Establecer valor en cache
     */
    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        return $this->defaultStore->set($this->getKey($key), $value, $ttl);
    }

    /**
     * Verificar si clave existe
     */
    public function has(string $key): bool
    {
        return $this->defaultStore->has($this->getKey($key));
    }

    /**
     * Eliminar clave del cache
     */
    public function delete(string $key): bool
    {
        return $this->defaultStore->delete($this->getKey($key));
    }

    /**
     * Obtener y eliminar valor
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->delete($key);
        return $value;
    }

    /**
     * Obtener o establecer valor
     */
    public function remember(string $key, callable $callback, int $ttl = null): mixed
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    /**
     * Obtener o establecer valor permanentemente
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, $callback, 0);
    }

    /**
     * Incrementar valor numérico
     */
    public function increment(string $key, int $amount = 1): int
    {
        return $this->defaultStore->increment($this->getKey($key), $amount);
    }

    /**
     * Decrementar valor numérico
     */
    public function decrement(string $key, int $amount = 1): int
    {
        return $this->defaultStore->decrement($this->getKey($key), $amount);
    }

    /**
     * Establecer múltiples valores
     */
    public function setMany(array $values, int $ttl = null): bool
    {
        $prefixedValues = [];
        
        foreach ($values as $key => $value) {
            $prefixedValues[$this->getKey($key)] = $value;
        }
        
        return $this->defaultStore->setMany($prefixedValues, $ttl);
    }

    /**
     * Obtener múltiples valores
     */
    public function getMany(array $keys): array
    {
        $prefixedKeys = array_map([$this, 'getKey'], $keys);
        $values = $this->defaultStore->getMany($prefixedKeys);
        
        return array_combine($keys, $values);
    }

    /**
     * Eliminar múltiples claves
     */
    public function deleteMany(array $keys): bool
    {
        $prefixedKeys = array_map([$this, 'getKey'], $keys);
        return $this->defaultStore->deleteMany($prefixedKeys);
    }

    /**
     * Limpiar todo el cache
     */
    public function flush(): bool
    {
        return $this->defaultStore->flush();
    }

    /**
     * Obtener prefijo de clave
     */
    private function getKey(string $key): string
    {
        return $this->prefix . ':' . $key;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'driver' => $this->driver,
            'prefix' => $this->prefix,
            'default_ttl' => $this->defaultTtl,
            'stores_count' => count($this->stores),
            'default_store' => get_class($this->defaultStore)
        ];
    }
}
