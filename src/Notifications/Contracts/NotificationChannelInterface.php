<?php

namespace Tero\Notifications\Contracts;

/**
 * NotificationChannelInterface - Interfaz para canales de notificación
 * 
 * @package Tero\Notifications\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface NotificationChannelInterface
{
    /**
     * Enviar notificación
     */
    public function send(NotificationInterface $notification): void;

    /**
     * Obtener nombre del canal
     */
    public function getName(): string;

    /**
     * Verificar si el canal está disponible
     */
    public function isAvailable(): bool;

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array;
}
