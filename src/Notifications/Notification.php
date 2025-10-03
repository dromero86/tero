<?php

namespace Tero\Notifications;

use Tero\Notifications\Contracts\NotificationInterface;

/**
 * Notification - Clase base para notificaciones
 * 
 * @package Tero\Notifications
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
abstract class Notification implements NotificationInterface
{
    private array $data = [];
    private array $metadata = [];
    private ?string $id = null;
    private ?\DateTime $createdAt = null;
    private ?\DateTime $scheduledAt = null;
    private ?\DateTime $sentAt = null;
    private array $channels = [];
    private int $priority = 0;
    private int $maxAttempts = 3;
    private int $attempts = 0;

    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->id = uniqid('notification_', true);
        $this->createdAt = new \DateTime();
    }

    /**
     * Obtener canales por defecto
     */
    public function via(): array
    {
        return ['mail'];
    }

    /**
     * Obtener ID de la notificación
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Establecer ID de la notificación
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Obtener datos de la notificación
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Establecer datos de la notificación
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Obtener dato específico
     */
    public function getDataValue(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Establecer dato específico
     */
    public function setDataValue(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
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
     * Obtener valor específico de metadata
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Establecer valor específico de metadata
     */
    public function setMetadataValue(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Obtener fecha de creación
     */
    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    /**
     * Establecer fecha de creación
     */
    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Obtener fecha programada
     */
    public function getScheduledAt(): ?\DateTime
    {
        return $this->scheduledAt;
    }

    /**
     * Establecer fecha programada
     */
    public function setScheduledAt(?\DateTime $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    /**
     * Obtener fecha de envío
     */
    public function getSentAt(): ?\DateTime
    {
        return $this->sentAt;
    }

    /**
     * Establecer fecha de envío
     */
    public function setSentAt(?\DateTime $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    /**
     * Obtener canales
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Establecer canales
     */
    public function setChannels(array $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    /**
     * Obtener prioridad
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Establecer prioridad
     */
    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Obtener máximo de intentos
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Establecer máximo de intentos
     */
    public function setMaxAttempts(int $maxAttempts): self
    {
        $this->maxAttempts = $maxAttempts;
        return $this;
    }

    /**
     * Obtener intentos
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Establecer intentos
     */
    public function setAttempts(int $attempts): self
    {
        $this->attempts = $attempts;
        return $this;
    }

    /**
     * Incrementar intentos
     */
    public function incrementAttempts(): self
    {
        $this->attempts++;
        return $this;
    }

    /**
     * Verificar si puede reintentar
     */
    public function canRetry(): bool
    {
        return $this->attempts < $this->maxAttempts;
    }

    /**
     * Convertir a array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'class' => get_class($this),
            'data' => $this->data,
            'metadata' => $this->metadata,
            'channels' => $this->channels,
            'priority' => $this->priority,
            'max_attempts' => $this->maxAttempts,
            'attempts' => $this->attempts,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'scheduled_at' => $this->scheduledAt?->format('Y-m-d H:i:s'),
            'sent_at' => $this->sentAt?->format('Y-m-d H:i:s')
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
