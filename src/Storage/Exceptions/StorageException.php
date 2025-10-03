<?php

namespace Tero\Storage\Exceptions;

/**
 * StorageException - Excepción del sistema de storage
 * 
 * @package Tero\Storage\Exceptions
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class StorageException extends \Exception
{
    /**
     * Crear excepción de driver no encontrado
     */
    public static function driverNotFound(string $driver): self
    {
        return new self("Storage driver '{$driver}' not found");
    }

    /**
     * Crear excepción de disco no encontrado
     */
    public static function diskNotFound(string $disk): self
    {
        return new self("Storage disk '{$disk}' not found");
    }

    /**
     * Crear excepción de archivo no encontrado
     */
    public static function fileNotFound(string $path): self
    {
        return new self("File not found: {$path}");
    }

    /**
     * Crear excepción de directorio no encontrado
     */
    public static function directoryNotFound(string $path): self
    {
        return new self("Directory not found: {$path}");
    }

    /**
     * Crear excepción de operación fallida
     */
    public static function operationFailed(string $operation, string $path, string $message = ''): self
    {
        $error = $message ? ": {$message}" : '';
        return new self("Storage operation '{$operation}' failed for '{$path}'{$error}");
    }

    /**
     * Crear excepción de configuración faltante
     */
    public static function missingConfiguration(string $driver, string $config): self
    {
        return new self("Missing configuration for storage driver '{$driver}': {$config}");
    }

    /**
     * Crear excepción de permisos insuficientes
     */
    public static function insufficientPermissions(string $path): self
    {
        return new self("Insufficient permissions for: {$path}");
    }

    /**
     * Crear excepción de archivo demasiado grande
     */
    public static function fileTooLarge(string $path, int $size, int $maxSize): self
    {
        return new self("File '{$path}' is too large ({$size} bytes, max: {$maxSize} bytes)");
    }

    /**
     * Crear excepción de tipo de archivo no permitido
     */
    public static function fileTypeNotAllowed(string $path, string $type): self
    {
        return new self("File type '{$type}' is not allowed for: {$path}");
    }
}
