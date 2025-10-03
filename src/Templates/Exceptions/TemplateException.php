<?php

namespace Tero\Templates\Exceptions;

/**
 * TemplateException - Excepción de templates
 * 
 * @package Tero\Templates\Exceptions
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class TemplateException extends \Exception
{
    /**
     * Crear excepción de template no encontrado
     */
    public static function templateNotFound(string $template): self
    {
        return new self("Template not found: {$template}");
    }

    /**
     * Crear excepción de helper no encontrado
     */
    public static function helperNotFound(string $helper): self
    {
        return new self("Helper '{$helper}' not found");
    }

    /**
     * Crear excepción de compilación fallida
     */
    public static function compilationFailed(string $template, string $error): self
    {
        return new self("Template compilation failed for '{$template}': {$error}");
    }

    /**
     * Crear excepción de ejecución fallida
     */
    public static function executionFailed(string $template, string $error): self
    {
        return new self("Template execution failed for '{$template}': {$error}");
    }

    /**
     * Crear excepción de sintaxis inválida
     */
    public static function invalidSyntax(string $template, string $error): self
    {
        return new self("Invalid template syntax in '{$template}': {$error}");
    }
}
