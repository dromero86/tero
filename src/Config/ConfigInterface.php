<?php

namespace Tero\Config;

/**
 * ConfigInterface - Interfaz para el gestor de configuración
 * 
 * @package Tero\Config
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface ConfigInterface
{
    /**
     * Obtener valor de configuración
     */
    public function get(string $key, mixed $default = null, ?string $type = null): mixed;

    /**
     * Establecer valor de configuración
     */
    public function set(string $key, mixed $value): void;

    /**
     * Verificar si existe una configuración
     */
    public function has(string $key): bool;

    /**
     * Obtener todas las configuraciones
     */
    public function all(): array;
}
