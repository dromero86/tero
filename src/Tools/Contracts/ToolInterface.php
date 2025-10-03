<?php

namespace Tero\Tools\Contracts;

/**
 * ToolInterface - Interfaz para herramientas del framework
 * 
 * @package Tero\Tools\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface ToolInterface
{
    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array;
}
