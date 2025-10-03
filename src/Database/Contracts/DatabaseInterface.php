<?php

namespace Tero\Database\Contracts;

use PDO;

/**
 * DatabaseInterface - Interfaz para el gestor de base de datos
 * 
 * @package Tero\Database\Contracts
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
interface DatabaseInterface
{
    /**
     * Obtener conexión de base de datos
     */
    public function connection(?string $name = null): ?PDO;

    /**
     * Agregar nueva conexión
     */
    public function addConnection(string $name, array $config): void;

    /**
     * Ejecutar consulta SQL
     */
    public function query(string $sql, array $params = []): \PDOStatement;

    /**
     * Ejecutar consulta y obtener todos los resultados
     */
    public function fetchAll(string $sql, array $params = []): array;

    /**
     * Ejecutar consulta y obtener un resultado
     */
    public function fetchOne(string $sql, array $params = []): ?array;

    /**
     * Ejecutar consulta y obtener un valor
     */
    public function fetchValue(string $sql, array $params = []): mixed;

    /**
     * Ejecutar consulta de inserción
     */
    public function insert(string $table, array $data): int;

    /**
     * Ejecutar consulta de actualización
     */
    public function update(string $table, array $data, array $where): int;

    /**
     * Ejecutar consulta de eliminación
     */
    public function delete(string $table, array $where): int;

    /**
     * Iniciar transacción
     */
    public function beginTransaction(): bool;

    /**
     * Confirmar transacción
     */
    public function commit(): bool;

    /**
     * Revertir transacción
     */
    public function rollback(): bool;

    /**
     * Ejecutar callback en transacción
     */
    public function transaction(callable $callback): mixed;

    /**
     * Verificar si la tabla existe
     */
    public function tableExists(string $table): bool;

    /**
     * Obtener información de la tabla
     */
    public function getTableInfo(string $table): array;

    /**
     * Obtener todas las tablas
     */
    public function getTables(): array;
}
