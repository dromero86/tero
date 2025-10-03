<?php

namespace Tero\Database;

use Tero\Config\ConfigManager;
use Tero\Database\Contracts\DatabaseInterface;
use Tero\Database\Exceptions\DatabaseException;
use PDO;
use PDOException;

/**
 * DatabaseManager - Gestor principal de base de datos
 * 
 * @package Tero\Database
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class DatabaseManager implements DatabaseInterface
{
    private ConfigManager $config;
    private array $connections = [];
    private ?PDO $defaultConnection = null;
    private string $defaultConnectionName = 'default';

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->initializeDefaultConnection();
    }

    /**
     * Inicializar conexión por defecto
     */
    private function initializeDefaultConnection(): void
    {
        try {
            $dbConfig = $this->config->getDatabaseConfig();
            $this->defaultConnection = $this->createConnection($dbConfig);
            $this->connections[$this->defaultConnectionName] = $this->defaultConnection;
        } catch (\Throwable $e) {
            // Si no se puede conectar a la base de datos, continuar sin conexión
            // Esto permite que el framework funcione sin base de datos
            error_log("Database connection failed: " . $e->getMessage());
            $this->defaultConnection = null;
        }
    }

    /**
     * Crear nueva conexión PDO
     */
    private function createConnection(array $config): PDO
    {
        $dsn = $this->buildDsn($config);
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        // Agregar opción específica de MySQL si está disponible
        if ($config['connection'] === 'mysql' && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$config['charset']}";
        }

        try {
            return new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            throw new DatabaseException("Failed to connect to database: " . $e->getMessage());
        }
    }

    /**
     * Construir DSN para la conexión
     */
    private function buildDsn(array $config): string
    {
        $driver = $config['connection'];
        
        return match ($driver) {
            'mysql' => "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}",
            'pgsql' => "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
            'sqlite' => "sqlite:{$config['database']}",
            'sqlsrv' => "sqlsrv:Server={$config['host']},{$config['port']};Database={$config['database']}",
            default => throw new DatabaseException("Unsupported database driver: $driver")
        };
    }

    /**
     * Obtener conexión por defecto
     */
    public function connection(?string $name = null): ?PDO
    {
        $name = $name ?? $this->defaultConnectionName;
        
        if (!isset($this->connections[$name])) {
            if ($this->defaultConnection === null) {
                throw new DatabaseException("No database connection available. Please check your database configuration.");
            }
            throw new DatabaseException("Database connection '$name' not found");
        }
        
        return $this->connections[$name];
    }

    /**
     * Agregar nueva conexión
     */
    public function addConnection(string $name, array $config): void
    {
        $this->connections[$name] = $this->createConnection($config);
    }

    /**
     * Ejecutar consulta SQL
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $connection = $this->connection();
        
        try {
            $stmt = $connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new DatabaseException("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Ejecutar consulta y obtener todos los resultados
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Ejecutar consulta y obtener un resultado
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Ejecutar consulta y obtener un valor
     */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $result = $this->query($sql, $params)->fetchColumn();
        return $result ?: null;
    }

    /**
     * Ejecutar consulta de inserción
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->query($sql, $data);
        
        return (int) $this->connection()->lastInsertId();
    }

    /**
     * Ejecutar consulta de actualización
     */
    public function update(string $table, array $data, array $where): int
    {
        $setClause = [];
        foreach (array_keys($data) as $column) {
            $setClause[] = "$column = :$column";
        }
        
        $whereClause = [];
        foreach (array_keys($where) as $column) {
            $whereClause[] = "$column = :where_$column";
        }
        
        $sql = "UPDATE $table SET " . implode(', ', $setClause) . " WHERE " . implode(' AND ', $whereClause);
        
        $params = array_merge($data, array_combine(
            array_map(fn($key) => "where_$key", array_keys($where)),
            array_values($where)
        ));
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Ejecutar consulta de eliminación
     */
    public function delete(string $table, array $where): int
    {
        $whereClause = [];
        foreach (array_keys($where) as $column) {
            $whereClause[] = "$column = :$column";
        }
        
        $sql = "DELETE FROM $table WHERE " . implode(' AND ', $whereClause);
        $stmt = $this->query($sql, $where);
        
        return $stmt->rowCount();
    }

    /**
     * Iniciar transacción
     */
    public function beginTransaction(): bool
    {
        return $this->connection()->beginTransaction();
    }

    /**
     * Confirmar transacción
     */
    public function commit(): bool
    {
        return $this->connection()->commit();
    }

    /**
     * Revertir transacción
     */
    public function rollback(): bool
    {
        return $this->connection()->rollBack();
    }

    /**
     * Ejecutar callback en transacción
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Verificar si la tabla existe
     */
    public function tableExists(string $table): bool
    {
        $driver = $this->config->getDatabaseConfig()['connection'];
        
        $sql = match ($driver) {
            'mysql' => "SHOW TABLES LIKE :table",
            'pgsql' => "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = :table)",
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name = :table",
            'sqlsrv' => "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = :table",
            default => throw new DatabaseException("Unsupported database driver: $driver")
        };
        
        $result = $this->fetchValue($sql, ['table' => $table]);
        
        return match ($driver) {
            'pgsql' => (bool) $result,
            default => $result !== null
        };
    }

    /**
     * Obtener información de la tabla
     */
    public function getTableInfo(string $table): array
    {
        $driver = $this->config->getDatabaseConfig()['connection'];
        
        return match ($driver) {
            'mysql' => $this->fetchAll("DESCRIBE $table"),
            'pgsql' => $this->fetchAll("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = :table", ['table' => $table]),
            'sqlite' => $this->fetchAll("PRAGMA table_info($table)"),
            'sqlsrv' => $this->fetchAll("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table", ['table' => $table]),
            default => throw new DatabaseException("Unsupported database driver: $driver")
        };
    }

    /**
     * Obtener todas las tablas
     */
    public function getTables(): array
    {
        $driver = $this->config->getDatabaseConfig()['connection'];
        
        $sql = match ($driver) {
            'mysql' => "SHOW TABLES",
            'pgsql' => "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'",
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table'",
            'sqlsrv' => "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES",
            default => throw new DatabaseException("Unsupported database driver: $driver")
        };
        
        $result = $this->fetchAll($sql);
        
        return match ($driver) {
            'mysql' => array_column($result, array_keys($result[0] ?? [])[0] ?? 0),
            'pgsql', 'sqlsrv' => array_column($result, 'table_name'),
            'sqlite' => array_column($result, 'name'),
            default => []
        };
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        $debugInfo = [
            'default_connection' => $this->defaultConnectionName,
            'active_connections' => array_keys($this->connections),
            'database_config' => $this->config->getDatabaseConfig(),
            'connection_count' => count($this->connections),
            'has_connection' => $this->defaultConnection !== null
        ];

        try {
            $debugInfo['tables'] = $this->getTables();
        } catch (\Throwable $e) {
            $debugInfo['tables'] = [];
            $debugInfo['tables_error'] = $e->getMessage();
        }

        return $debugInfo;
    }

    /**
     * Cerrar todas las conexiones
     */
    public function closeConnections(): void
    {
        $this->connections = [];
        $this->defaultConnection = null;
    }
}
