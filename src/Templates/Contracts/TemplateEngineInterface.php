<?php

namespace Tero\Templates\Contracts;

/**
 * TemplateEngineInterface - Interfaz para el motor de templates
 * 
 * @package Tero\Templates\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface TemplateEngineInterface
{
    /**
     * Renderizar template
     */
    public function render(string $template, array $variables = []): string;

    /**
     * Obtener variable
     */
    public function getVariable(string $name, bool $escape = true): string;

    /**
     * Llamar helper
     */
    public function callHelper(string $name, array $params = []): string;

    /**
     * Registrar helper
     */
    public function registerHelper(string $name, callable $helper): void;

    /**
     * Incluir template
     */
    public function include(string $template): string;

    /**
     * Extender layout
     */
    public function extends(string $layout): void;

    /**
     * Iniciar sección
     */
    public function startSection(string $name): void;

    /**
     * Finalizar sección
     */
    public function endSection(): void;

    /**
     * Obtener sección
     */
    public function yieldSection(string $name): string;

    /**
     * Limpiar cache
     */
    public function clearCache(): int;
}
