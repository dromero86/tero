<?php

namespace Tero\Queue\Contracts;

/**
 * JobInterface - Interfaz para jobs
 * 
 * @package Tero\Queue\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface JobInterface
{
    /**
     * Ejecutar job
     */
    public function handle(): void;

    /**
     * Obtener ID del job
     */
    public function getId(): string;

    /**
     * Obtener cola del job
     */
    public function getQueue(): string;

    /**
     * Obtener intentos
     */
    public function getAttempts(): int;

    /**
     * Obtener máximo de intentos
     */
    public function getMaxAttempts(): int;

    /**
     * Verificar si puede reintentar
     */
    public function canRetry(): bool;

    /**
     * Obtener timeout
     */
    public function getTimeout(): int;

    /**
     * Obtener payload
     */
    public function getPayload(): array;

    /**
     * Obtener metadata
     */
    public function getMetadata(): array;

    /**
     * Ejecutar job con manejo de errores
     */
    public function execute(): void;
}
