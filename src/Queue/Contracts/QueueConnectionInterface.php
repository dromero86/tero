<?php

namespace Tero\Queue\Contracts;

/**
 * QueueConnectionInterface - Interfaz para conexiones de cola
 * 
 * @package Tero\Queue\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface QueueConnectionInterface
{
    /**
     * Encolar job
     */
    public function push(JobInterface $job): void;

    /**
     * Encolar job con delay
     */
    public function later(JobInterface $job, int $delay): void;

    /**
     * Procesar jobs
     */
    public function process(int $maxJobs = 0): int;

    /**
     * Obtener tamaño de cola
     */
    public function size(): int;

    /**
     * Limpiar cola
     */
    public function clear(): int;

    /**
     * Obtener jobs fallidos
     */
    public function getFailedJobs(): array;

    /**
     * Reintentar job fallido
     */
    public function retryFailedJob(int $id): bool;

    /**
     * Eliminar job fallido
     */
    public function deleteFailedJob(int $id): bool;

    /**
     * Limpiar jobs fallidos
     */
    public function clearFailedJobs(): int;
}
