<?php

namespace Tero\Cache\Exceptions;

/**
 * CacheException - Excepción del sistema de cache
 * 
 * @package Tero\Cache\Exceptions
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class CacheException extends \Exception
{
    /**
     * Crear excepción de driver no soportado
     */
    public static function unsupportedDriver(string $driver): self
    {
        return new self("Unsupported cache driver: {$driver}");
    }

    /**
     * Crear excepción de conexión fallida
     */
    public static function connectionFailed(string $message): self
    {
        return new self("Cache connection failed: {$message}");
    }

    /**
     * Crear excepción de operación fallida
     */
    public static function operationFailed(string $operation, string $message): self
    {
        return new self("Cache operation '{$operation}' failed: {$message}");
    }

    /**
     * Crear excepción de serialización fallida
     */
    public static function serializationFailed(string $message): self
    {
        return new self("Cache serialization failed: {$message}");
    }
}
