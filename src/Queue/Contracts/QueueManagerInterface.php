<?php

namespace Tero\Queue\Contracts;

/**
 * QueueManagerInterface - Interfaz para el gestor de colas
 * 
 * @package Tero\Queue\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface QueueManagerInterface
{
    /**
     * Obtener conexión
     */
    public function connection(string $name = null): QueueConnectionInterface;

    /**
     * Encolar job
     */
    public function push(JobInterface $job, string $queue = null): void;

    /**
     * Encolar job con delay
     */
    public function later(JobInterface $job, int $delay, string $queue = null): void;

    /**
     * Procesar jobs
     */
    public function process(string $queue = null, int $maxJobs = 0): int;

    /**
     * Obtener tamaño de cola
     */
    public function size(string $queue = null): int;

    /**
     * Limpiar cola
     */
    public function clear(string $queue = null): int;

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
