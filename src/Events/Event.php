<?php

namespace Tero\Events;

use Tero\Events\Contracts\EventInterface;

/**
 * Event - Clase base para eventos
 * 
 * @package Tero\Events
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class Event implements EventInterface
{
    private string $name;
    private mixed $payload;
    private bool $propagationStopped = false;
    private array $metadata = [];

    public function __construct(string $name, mixed $payload = [])
    {
        $this->name = $name;
        $this->payload = $payload;
    }

    /**
     * Obtener nombre del evento
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Obtener payload del evento
     */
    public function getPayload(): mixed
    {
        return $this->payload;
    }

    /**
     * Establecer payload del evento
     */
    public function setPayload(mixed $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * Obtener dato específico del payload
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        if (is_array($this->payload) && isset($this->payload[$key])) {
            return $this->payload[$key];
        }
        
        if (is_object($this->payload) && isset($this->payload->$key)) {
            return $this->payload->$key;
        }
        
        return $default;
    }

    /**
     * Establecer dato específico del payload
     */
    public function setData(string $key, mixed $value): self
    {
        if (is_array($this->payload)) {
            $this->payload[$key] = $value;
        } elseif (is_object($this->payload)) {
            $this->payload->$key = $value;
        } else {
            $this->payload = [$key => $value];
        }
        
        return $this;
    }

    /**
     * Verificar si la propagación está detenida
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Detener la propagación del evento
     */
    public function stopPropagation(): self
    {
        $this->propagationStopped = true;
        return $this;
    }

    /**
     * Obtener metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Establecer metadata
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Obtener dato específico de metadata
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Establecer dato específico de metadata
     */
    public function setMetadataValue(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Convertir a array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'payload' => $this->payload,
            'propagation_stopped' => $this->propagationStopped,
            'metadata' => $this->metadata
        ];
    }

    /**
     * Convertir a JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convertir a string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
