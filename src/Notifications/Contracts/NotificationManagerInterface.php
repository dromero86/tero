<?php

namespace Tero\Notifications\Contracts;

/**
 * NotificationManagerInterface - Interfaz para el gestor de notificaciones
 * 
 * @package Tero\Notifications\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface NotificationManagerInterface
{
    /**
     * Registrar canal personalizado
     */
    public function registerChannel(string $name, NotificationChannelInterface $channel): void;

    /**
     * Obtener canal
     */
    public function channel(string $name): NotificationChannelInterface;

    /**
     * Enviar notificación
     */
    public function send(NotificationInterface $notification, array $channels = null): void;

    /**
     * Enviar notificación a canal específico
     */
    public function sendToChannel(NotificationInterface $notification, string $channel): void;

    /**
     * Encolar notificación
     */
    public function queue(NotificationInterface $notification, array $channels = null, int $delay = 0): void;

    /**
     * Procesar notificaciones encoladas
     */
    public function processQueuedNotifications(): int;

    /**
     * Obtener todos los canales
     */
    public function getChannels(): array;

    /**
     * Obtener canales disponibles
     */
    public function getAvailableChannels(): array;

    /**
     * Obtener notificaciones encoladas
     */
    public function getQueuedNotifications(): array;

    /**
     * Limpiar notificaciones encoladas
     */
    public function clearQueuedNotifications(): void;

    /**
     * Verificar si canal existe
     */
    public function hasChannel(string $name): bool;
}
