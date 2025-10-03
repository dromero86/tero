<?php

namespace Tero\Storage\Contracts;

/**
 * StorageManagerInterface - Interfaz para el gestor de storage
 * 
 * @package Tero\Storage\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface StorageManagerInterface
{
    /**
     * Registrar driver personalizado
     */
    public function registerDriver(string $name, StorageInterface $driver): void;

    /**
     * Registrar disco personalizado
     */
    public function registerDisk(string $name, string $driver, array $config = []): void;

    /**
     * Obtener disco
     */
    public function disk(string $name = null): \Tero\Storage\Disk;

    /**
     * Obtener driver
     */
    public function driver(string $name): StorageInterface;

    /**
     * Obtener todos los discos
     */
    public function getDisks(): array;

    /**
     * Obtener todos los drivers
     */
    public function getDrivers(): array;

    /**
     * Obtener discos disponibles
     */
    public function getAvailableDisks(): array;

    /**
     * Obtener drivers disponibles
     */
    public function getAvailableDrivers(): array;

    /**
     * Verificar si disco existe
     */
    public function hasDisk(string $name): bool;

    /**
     * Verificar si driver existe
     */
    public function hasDriver(string $name): bool;
}
