<?php

namespace Tero\Events\Exceptions;

/**
 * EventException - Excepción del sistema de eventos
 * 
 * @package Tero\Events\Exceptions
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class EventException extends \Exception
{
    /**
     * Crear excepción de listener no encontrado
     */
    public static function listenerNotFound(string $event, string $listener): self
    {
        return new self("Listener '{$listener}' not found for event '{$event}'");
    }

    /**
     * Crear excepción de evento no encontrado
     */
    public static function eventNotFound(string $event): self
    {
        return new self("Event '{$event}' not found");
    }

    /**
     * Crear excepción de listener inválido
     */
    public static function invalidListener(string $listener): self
    {
        return new self("Invalid listener: {$listener}");
    }

    /**
     * Crear excepción de evento inválido
     */
    public static function invalidEvent(string $event): self
    {
        return new self("Invalid event: {$event}");
    }

    /**
     * Crear excepción de propagación detenida
     */
    public static function propagationStopped(string $event): self
    {
        return new self("Event propagation stopped for '{$event}'");
    }
}
