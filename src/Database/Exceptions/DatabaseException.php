<?php

namespace Tero\Database\Exceptions;

/**
 * DatabaseException - Excepción de base de datos
 * 
 * @package Tero\Database\Exceptions
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class DatabaseException extends \Exception
{
    /**
     * Crear excepción de conexión fallida
     */
    public static function connectionFailed(string $message): self
    {
        return new self("Database connection failed: $message");
    }

    /**
     * Crear excepción de consulta fallida
     */
    public static function queryFailed(string $message): self
    {
        return new self("Database query failed: $message");
    }

    /**
     * Crear excepción de tabla no encontrada
     */
    public static function tableNotFound(string $table): self
    {
        return new self("Table '$table' not found");
    }

    /**
     * Crear excepción de columna no encontrada
     */
    public static function columnNotFound(string $column, string $table): self
    {
        return new self("Column '$column' not found in table '$table'");
    }

    /**
     * Crear excepción de transacción fallida
     */
    public static function transactionFailed(string $message): self
    {
        return new self("Database transaction failed: $message");
    }
}
