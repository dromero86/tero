<?php

namespace Tero\Queue\Connections;

use Tero\Config\ConfigManager;
use Tero\Queue\Contracts\QueueConnectionInterface;
use Tero\Queue\Contracts\JobInterface;

/**
 * SyncQueueConnection - Conexión de cola síncrona
 * 
 * @package Tero\Queue\Connections
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class SyncQueueConnection implements QueueConnectionInterface
{
    private ConfigManager $config;
    private string $queueName;

    public function __construct(ConfigManager $config, string $queueName)
    {
        $this->config = $config;
        $this->queueName = $queueName;
    }

    /**
     * Encolar job
     */
    public function push(JobInterface $job): void
    {
        // Ejecutar inmediatamente
        $job->execute();
    }

    /**
     * Encolar job con delay
     */
    public function later(JobInterface $job, int $delay): void
    {
        // Ejecutar después del delay
        sleep($delay);
        $job->execute();
    }

    /**
     * Procesar jobs
     */
    public function process(int $maxJobs = 0): int
    {
        // No hay jobs en cola síncrona
        return 0;
    }

    /**
     * Obtener tamaño de cola
     */
    public function size(): int
    {
        // No hay jobs en cola síncrona
        return 0;
    }

    /**
     * Limpiar cola
     */
    public function clear(): int
    {
        // No hay jobs en cola síncrona
        return 0;
    }

    /**
     * Obtener jobs fallidos
     */
    public function getFailedJobs(): array
    {
        // No hay jobs fallidos en cola síncrona
        return [];
    }

    /**
     * Reintentar job fallido
     */
    public function retryFailedJob(int $id): bool
    {
        // No hay jobs fallidos en cola síncrona
        return false;
    }

    /**
     * Eliminar job fallido
     */
    public function deleteFailedJob(int $id): bool
    {
        // No hay jobs fallidos en cola síncrona
        return false;
    }

    /**
     * Limpiar jobs fallidos
     */
    public function clearFailedJobs(): int
    {
        // No hay jobs fallidos en cola síncrona
        return 0;
    }
}
