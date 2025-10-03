<?php

namespace Tero\Data\Exceptions;

/**
 * DatasetException - Excepción del sistema de datos
 * 
 * @package Tero\Data\Exceptions
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class DatasetException extends \Exception
{
    /**
     * Crear excepción de atributo no encontrado
     */
    public static function attributeNotFound(string $attribute): self
    {
        return new self("Attribute '{$attribute}' not found");
    }

    /**
     * Crear excepción de cast inválido
     */
    public static function invalidCast(string $cast): self
    {
        return new self("Invalid cast type: {$cast}");
    }

    /**
     * Crear excepción de clave primaria no encontrada
     */
    public static function primaryKeyNotFound(): self
    {
        return new self("Primary key not found");
    }

    /**
     * Crear excepción de datos inválidos
     */
    public static function invalidData(string $message): self
    {
        return new self("Invalid data: {$message}");
    }

    /**
     * Crear excepción de operación no permitida
     */
    public static function operationNotAllowed(string $operation): self
    {
        return new self("Operation '{$operation}' not allowed");
    }
}
