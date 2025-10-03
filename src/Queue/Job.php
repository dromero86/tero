<?php

namespace Tero\Queue;

use Tero\Queue\Contracts\JobInterface;
use Tero\Queue\Exceptions\JobException;

/**
 * Job - Clase base para jobs
 * 
 * @package Tero\Queue
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
abstract class Job implements JobInterface
{
    private string $id;
    private string $queue = 'default';
    private int $attempts = 0;
    private int $maxAttempts = 3;
    private int $timeout = 60;
    private array $payload = [];
    private array $metadata = [];
    private ?\DateTime $createdAt = null;
    private ?\DateTime $scheduledAt = null;
    private ?\DateTime $startedAt = null;
    private ?\DateTime $completedAt = null;
    private ?\Exception $lastException = null;

    public function __construct(array $payload = [])
    {
        $this->id = uniqid('job_', true);
        $this->payload = $payload;
        $this->createdAt = new \DateTime();
    }

    /**
     * Ejecutar job
     */
    abstract public function handle(): void;

    /**
     * Obtener ID del job
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Establecer ID del job
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Obtener cola del job
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * Establecer cola del job
     */
    public function setQueue(string $queue): self
    {
        $this->queue = $queue;
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
     * Verificar si puede reintentar
     */
    public function canRetry(): bool
    {
        return $this->attempts < $this->maxAttempts;
    }

    /**
     * Obtener timeout
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Establecer timeout
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Obtener payload
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Establecer payload
     */
    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * Obtener dato específico del payload
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    /**
     * Establecer dato específico del payload
     */
    public function setData(string $key, mixed $value): self
    {
        $this->payload[$key] = $value;
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
     * Obtener fecha de inicio
     */
    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    /**
     * Establecer fecha de inicio
     */
    public function setStartedAt(?\DateTime $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    /**
     * Obtener fecha de finalización
     */
    public function getCompletedAt(): ?\DateTime
    {
        return $this->completedAt;
    }

    /**
     * Establecer fecha de finalización
     */
    public function setCompletedAt(?\DateTime $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    /**
     * Obtener última excepción
     */
    public function getLastException(): ?\Exception
    {
        return $this->lastException;
    }

    /**
     * Establecer última excepción
     */
    public function setLastException(?\Exception $exception): self
    {
        $this->lastException = $exception;
        return $this;
    }

    /**
     * Ejecutar job con manejo de errores
     */
    public function execute(): void
    {
        $this->startedAt = new \DateTime();
        
        try {
            $this->handle();
            $this->completedAt = new \DateTime();
        } catch (\Throwable $e) {
            $this->lastException = $e;
            $this->incrementAttempts();
            
            if ($this->canRetry()) {
                throw new JobException("Job failed, will retry: " . $e->getMessage(), 0, $e);
            } else {
                throw new JobException("Job failed after {$this->maxAttempts} attempts: " . $e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * Convertir a array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'queue' => $this->queue,
            'attempts' => $this->attempts,
            'max_attempts' => $this->maxAttempts,
            'timeout' => $this->timeout,
            'payload' => $this->payload,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'scheduled_at' => $this->scheduledAt?->format('Y-m-d H:i:s'),
            'started_at' => $this->startedAt?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s'),
            'last_exception' => $this->lastException?->getMessage()
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
