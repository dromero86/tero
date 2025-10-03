<?php

namespace Tero\Notifications\Exceptions;

/**
 * NotificationException - Excepción del sistema de notificaciones
 * 
 * @package Tero\Notifications\Exceptions
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class NotificationException extends \Exception
{
    /**
     * Crear excepción de canal no encontrado
     */
    public static function channelNotFound(string $channel): self
    {
        return new self("Notification channel '{$channel}' not found");
    }

    /**
     * Crear excepción de canal no disponible
     */
    public static function channelNotAvailable(string $channel): self
    {
        return new self("Notification channel '{$channel}' is not available");
    }

    /**
     * Crear excepción de notificación inválida
     */
    public static function invalidNotification(string $notification): self
    {
        return new self("Invalid notification: {$notification}");
    }

    /**
     * Crear excepción de envío fallido
     */
    public static function sendFailed(string $channel, string $message): self
    {
        return new self("Failed to send notification via '{$channel}': {$message}");
    }

    /**
     * Crear excepción de configuración faltante
     */
    public static function missingConfiguration(string $channel, string $config): self
    {
        return new self("Missing configuration for channel '{$channel}': {$config}");
    }
}
