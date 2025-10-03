<?php

namespace Tero\Storage\Contracts;

/**
 * StorageInterface - Interfaz para drivers de storage
 * 
 * @package Tero\Storage\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface StorageInterface
{
    /**
     * Verificar si archivo existe
     */
    public function exists(string $path): bool;

    /**
     * Obtener contenido de archivo
     */
    public function get(string $path): string;

    /**
     * Obtener contenido de archivo como stream
     */
    public function readStream(string $path);

    /**
     * Escribir contenido a archivo
     */
    public function put(string $path, string $contents, array $options = []): bool;

    /**
     * Escribir stream a archivo
     */
    public function writeStream(string $path, $stream, array $options = []): bool;

    /**
     * Copiar archivo
     */
    public function copy(string $from, string $to): bool;

    /**
     * Mover archivo
     */
    public function move(string $from, string $to): bool;

    /**
     * Eliminar archivo
     */
    public function delete(string $path): bool;

    /**
     * Eliminar múltiples archivos
     */
    public function deleteMultiple(array $paths): array;

    /**
     * Crear directorio
     */
    public function makeDirectory(string $path): bool;

    /**
     * Eliminar directorio
     */
    public function deleteDirectory(string $path): bool;

    /**
     * Obtener lista de archivos
     */
    public function files(string $directory = '', bool $recursive = false): array;

    /**
     * Obtener lista de directorios
     */
    public function directories(string $directory = '', bool $recursive = false): array;

    /**
     * Obtener lista de archivos y directorios
     */
    public function listContents(string $directory = '', bool $recursive = false): array;

    /**
     * Obtener tamaño de archivo
     */
    public function size(string $path): int;

    /**
     * Obtener fecha de última modificación
     */
    public function lastModified(string $path): int;

    /**
     * Verificar si el driver está disponible
     */
    public function isAvailable(): bool;

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array;
}
