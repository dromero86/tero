<?php

namespace Tero\Cache\Stores;

use Tero\Cache\Contracts\CacheStoreInterface;

/**
 * ArrayCacheStore - Store de cache en memoria
 * 
 * @package Tero\Cache\Stores
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class ArrayCacheStore implements CacheStoreInterface
{
    private array $cache = [];
    private array $expires = [];

    /**
     * Obtener valor del cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }
        
        return $this->cache[$key];
    }

    /**
     * Establecer valor en cache
     */
    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        $this->cache[$key] = $value;
        
        if ($ttl > 0) {
            $this->expires[$key] = time() + $ttl;
        } else {
            unset($this->expires[$key]);
        }
        
        return true;
    }

    /**
     * Verificar si clave existe
     */
    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }
        
        // Verificar si ha expirado
        if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
            unset($this->cache[$key]);
            unset($this->expires[$key]);
            return false;
        }
        
        return true;
    }

    /**
     * Eliminar clave del cache
     */
    public function delete(string $key): bool
    {
        unset($this->cache[$key]);
        unset($this->expires[$key]);
        return true;
    }

    /**
     * Incrementar valor numérico
     */
    public function increment(string $key, int $amount = 1): int
    {
        $value = $this->get($key, 0);
        $newValue = $value + $amount;
        $this->set($key, $newValue);
        return $newValue;
    }

    /**
     * Decrementar valor numérico
     */
    public function decrement(string $key, int $amount = 1): int
    {
        $value = $this->get($key, 0);
        $newValue = $value - $amount;
        $this->set($key, $newValue);
        return $newValue;
    }

    /**
     * Establecer múltiples valores
     */
    public function setMany(array $values, int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        
        return true;
    }

    /**
     * Obtener múltiples valores
     */
    public function getMany(array $keys): array
    {
        $values = [];
        
        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }
        
        return $values;
    }

    /**
     * Eliminar múltiples claves
     */
    public function deleteMany(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        
        return true;
    }

    /**
     * Limpiar todo el cache
     */
    public function flush(): bool
    {
        $this->cache = [];
        $this->expires = [];
        return true;
    }

    /**
     * Limpiar cache expirado
     */
    public function cleanup(): int
    {
        $deleted = 0;
        $now = time();
        
        foreach ($this->expires as $key => $expires) {
            if ($expires < $now) {
                unset($this->cache[$key]);
                unset($this->expires[$key]);
                $deleted++;
            }
        }
        
        return $deleted;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'cache_count' => count($this->cache),
            'expires_count' => count($this->expires),
            'memory_usage' => memory_get_usage(true)
        ];
    }
}
