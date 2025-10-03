<?php

namespace Tero\Session\Exceptions;

/**
 * SessionException - Excepción del sistema de sesiones
 * 
 * @package Tero\Session\Exceptions
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class SessionException extends \Exception
{
    /**
     * Crear excepción de sesión no iniciada
     */
    public static function sessionNotStarted(): self
    {
        return new self("Session not started");
    }

    /**
     * Crear excepción de driver no soportado
     */
    public static function unsupportedDriver(string $driver): self
    {
        return new self("Unsupported session driver: {$driver}");
    }

    /**
     * Crear excepción de configuración inválida
     */
    public static function invalidConfiguration(string $message): self
    {
        return new self("Invalid session configuration: {$message}");
    }

    /**
     * Crear excepción de sesión expirada
     */
    public static function sessionExpired(): self
    {
        return new self("Session has expired");
    }

    /**
     * Crear excepción de sesión corrupta
     */
    public static function sessionCorrupted(): self
    {
        return new self("Session data is corrupted");
    }
}
