<?php

namespace Tero\Queue\Exceptions;

/**
 * QueueException - Excepción del sistema de colas
 * 
 * @package Tero\Queue\Exceptions
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class QueueException extends \Exception
{
    /**
     * Crear excepción de driver no soportado
     */
    public static function unsupportedDriver(string $driver): self
    {
        return new self("Unsupported queue driver: {$driver}");
    }

    /**
     * Crear excepción de conexión fallida
     */
    public static function connectionFailed(string $message): self
    {
        return new self("Queue connection failed: {$message}");
    }

    /**
     * Crear excepción de job no encontrado
     */
    public static function jobNotFound(string $id): self
    {
        return new self("Job not found: {$id}");
    }

    /**
     * Crear excepción de job fallido
     */
    public static function jobFailed(string $id, string $message): self
    {
        return new self("Job '{$id}' failed: {$message}");
    }

    /**
     * Crear excepción de cola no encontrada
     */
    public static function queueNotFound(string $queue): self
    {
        return new self("Queue not found: {$queue}");
    }
}
