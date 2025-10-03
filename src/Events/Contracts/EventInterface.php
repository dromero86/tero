<?php

namespace Tero\Events\Contracts;

/**
 * EventInterface - Interfaz para eventos
 * 
 * @package Tero\Events\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface EventInterface
{
    /**
     * Obtener nombre del evento
     */
    public function getName(): string;

    /**
     * Obtener payload del evento
     */
    public function getPayload(): mixed;

    /**
     * Establecer payload del evento
     */
    public function setPayload(mixed $payload): self;

    /**
     * Obtener dato específico del payload
     */
    public function getData(string $key, mixed $default = null): mixed;

    /**
     * Establecer dato específico del payload
     */
    public function setData(string $key, mixed $value): self;

    /**
     * Verificar si la propagación está detenida
     */
    public function isPropagationStopped(): bool;

    /**
     * Detener la propagación del evento
     */
    public function stopPropagation(): self;

    /**
     * Obtener metadata
     */
    public function getMetadata(): array;

    /**
     * Establecer metadata
     */
    public function setMetadata(array $metadata): self;

    /**
     * Obtener dato específico de metadata
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed;

    /**
     * Establecer dato específico de metadata
     */
    public function setMetadataValue(string $key, mixed $value): self;
}
