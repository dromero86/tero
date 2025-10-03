<?php

namespace Tero\Notifications\Contracts;

/**
 * NotificationInterface - Interfaz para notificaciones
 * 
 * @package Tero\Notifications\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface NotificationInterface
{
    /**
     * Obtener canales por defecto
     */
    public function via(): array;

    /**
     * Obtener ID de la notificación
     */
    public function getId(): string;

    /**
     * Obtener datos de la notificación
     */
    public function getData(): array;

    /**
     * Obtener metadata
     */
    public function getMetadata(): array;

    /**
     * Obtener prioridad
     */
    public function getPriority(): int;

    /**
     * Obtener máximo de intentos
     */
    public function getMaxAttempts(): int;

    /**
     * Obtener intentos
     */
    public function getAttempts(): int;

    /**
     * Verificar si puede reintentar
     */
    public function canRetry(): bool;
}
