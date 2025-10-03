<?php

namespace Tero\Cache\Contracts;

/**
 * CacheStoreInterface - Interfaz para stores de cache
 * 
 * @package Tero\Cache\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface CacheStoreInterface
{
    /**
     * Obtener valor del cache
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Establecer valor en cache
     */
    public function set(string $key, mixed $value, int $ttl = null): bool;

    /**
     * Verificar si clave existe
     */
    public function has(string $key): bool;

    /**
     * Eliminar clave del cache
     */
    public function delete(string $key): bool;

    /**
     * Incrementar valor numérico
     */
    public function increment(string $key, int $amount = 1): int;

    /**
     * Decrementar valor numérico
     */
    public function decrement(string $key, int $amount = 1): int;

    /**
     * Establecer múltiples valores
     */
    public function setMany(array $values, int $ttl = null): bool;

    /**
     * Obtener múltiples valores
     */
    public function getMany(array $keys): array;

    /**
     * Eliminar múltiples claves
     */
    public function deleteMany(array $keys): bool;

    /**
     * Limpiar todo el cache
     */
    public function flush(): bool;
}
