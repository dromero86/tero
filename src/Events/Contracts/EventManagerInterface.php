<?php

namespace Tero\Events\Contracts;

/**
 * EventManagerInterface - Interfaz para el gestor de eventos
 * 
 * @package Tero\Events\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface EventManagerInterface
{
    /**
     * Registrar listener para evento
     */
    public function listen(string $event, callable $listener, int $priority = 0): void;

    /**
     * Disparar evento
     */
    public function fire(string $event, mixed $payload = [], bool $halt = false): mixed;

    /**
     * Encolar evento
     */
    public function queue(string $event, mixed $payload = [], int $delay = 0): void;

    /**
     * Procesar eventos encolados
     */
    public function processQueuedEvents(): int;

    /**
     * Verificar si evento tiene listeners
     */
    public function hasListeners(string $event): bool;

    /**
     * Obtener listeners de evento
     */
    public function getEventListeners(string $event): array;

    /**
     * Obtener todos los listeners
     */
    public function getAllListeners(): array;

    /**
     * Obtener eventos disparados
     */
    public function getFiredEvents(): array;

    /**
     * Obtener eventos encolados
     */
    public function getQueuedEvents(): array;

    /**
     * Limpiar eventos disparados
     */
    public function clearFiredEvents(): void;

    /**
     * Limpiar eventos encolados
     */
    public function clearQueuedEvents(): void;

    /**
     * Remover listener
     */
    public function forget(string $event, callable $listener = null): void;

    /**
     * Remover todos los listeners
     */
    public function flush(): void;
}
