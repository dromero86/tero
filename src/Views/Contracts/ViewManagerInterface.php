<?php

namespace Tero\Views\Contracts;

/**
 * ViewManagerInterface - Interfaz para el gestor de vistas
 * 
 * @package Tero\Views\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface ViewManagerInterface
{
    /**
     * Renderizar vista
     */
    public function render(string $view, array $data = []): string;

    /**
     * Crear vista
     */
    public function make(string $view, array $data = []): \Tero\Views\View;

    /**
     * Compartir datos con todas las vistas
     */
    public function share(string $key, mixed $value): void;

    /**
     * Compartir múltiples datos
     */
    public function shareArray(array $data): void;

    /**
     * Registrar composer
     */
    public function composer(string $view, callable $callback): void;

    /**
     * Registrar creator
     */
    public function creator(string $view, callable $callback): void;

    /**
     * Verificar si vista existe
     */
    public function exists(string $view): bool;

    /**
     * Obtener ruta de vista
     */
    public function getViewPath(string $view): string;

    /**
     * Obtener todas las vistas disponibles
     */
    public function getAvailableViews(): array;

    /**
     * Limpiar cache de vistas
     */
    public function clearCache(): int;
}
