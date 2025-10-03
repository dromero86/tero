<?php

namespace Tero\Data\Contracts;

/**
 * DatasetInterface - Interfaz para el sistema de datos
 * 
 * @package Tero\Data\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface DatasetInterface
{
    /**
     * Obtener atributo
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Establecer atributo
     */
    public function set(string $key, mixed $value): self;

    /**
     * Verificar si atributo existe
     */
    public function has(string $key): bool;

    /**
     * Eliminar atributo
     */
    public function remove(string $key): self;

    /**
     * Obtener todos los datos
     */
    public function all(): array;

    /**
     * Convertir a array
     */
    public function toArray(): array;

    /**
     * Convertir a JSON
     */
    public function toJson(int $options = 0): string;

    /**
     * Verificar si el modelo existe
     */
    public function exists(): bool;

    /**
     * Obtener clave primaria
     */
    public function getKey(): mixed;

    /**
     * Obtener nombre de clave primaria
     */
    public function getKeyName(): string;

    /**
     * Obtener datos originales
     */
    public function getOriginal(string $key = null): mixed;

    /**
     * Verificar si ha cambiado
     */
    public function isDirty(string $key = null): bool;

    /**
     * Obtener cambios
     */
    public function getChanges(): array;

    /**
     * Sincronizar con original
     */
    public function syncOriginal(): self;

    /**
     * Filtrar datos
     */
    public function filter(callable $callback): self;

    /**
     * Mapear datos
     */
    public function map(callable $callback): self;

    /**
     * Obtener solo campos específicos
     */
    public function only(array $keys): self;

    /**
     * Obtener todos excepto campos específicos
     */
    public function except(array $keys): self;

    /**
     * Merge con otros datos
     */
    public function merge(array $data): self;
}
