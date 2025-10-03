<?php

namespace Tero\Cache\Stores;

use Tero\Config\ConfigManager;
use Tero\Database\DatabaseManager;
use Tero\Cache\Contracts\CacheStoreInterface;

/**
 * DatabaseCacheStore - Store de cache basado en base de datos
 * 
 * @package Tero\Cache\Stores
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class DatabaseCacheStore implements CacheStoreInterface
{
    private ConfigManager $config;
    private ?DatabaseManager $database;
    private string $table;

    public function __construct(ConfigManager $config, ?DatabaseManager $database = null)
    {
        $this->config = $config;
        $this->database = $database;
        $this->table = $config->get('CACHE_TABLE', 'cache');
        
        $this->ensureCacheTable();
    }

    /**
     * Asegurar que la tabla de cache existe
     */
    private function ensureCacheTable(): void
    {
        if (!$this->database) {
            return;
        }
        
        try {
            if (!$this->database->tableExists($this->table)) {
                $sql = "CREATE TABLE {$this->table} (
                    key_name VARCHAR(255) NOT NULL PRIMARY KEY,
                    value LONGTEXT NOT NULL,
                    expires_at INT NULL,
                    created_at INT NOT NULL,
                    INDEX cache_expires_at_index (expires_at)
                )";
                
                $this->database->query($sql);
            }
        } catch (\Throwable $e) {
            // Si no se puede crear la tabla, continuar sin base de datos
            error_log("Cannot create cache table: " . $e->getMessage());
        }
    }

    /**
     * Obtener valor del cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->database) {
            return $default;
        }
        
        try {
            $result = $this->database->fetchOne(
                "SELECT value, expires_at FROM {$this->table} WHERE key_name = ?",
                [$key]
            );
            
            if (!$result) {
                return $default;
            }
            
            // Verificar si ha expirado
            if ($result['expires_at'] && $result['expires_at'] < time()) {
                $this->delete($key);
                return $default;
            }
            
            return unserialize($result['value']);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Establecer valor en cache
     */
    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        if (!$this->database) {
            return false;
        }
        
        try {
            $serializedValue = serialize($value);
            $expiresAt = $ttl > 0 ? time() + $ttl : null;
            $createdAt = time();
            
            $this->database->query(
                "INSERT INTO {$this->table} (key_name, value, expires_at, created_at) 
                 VALUES (?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE 
                 value = VALUES(value),
                 expires_at = VALUES(expires_at),
                 created_at = VALUES(created_at)",
                [$key, $serializedValue, $expiresAt, $createdAt]
            );
            
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Verificar si clave existe
     */
    public function has(string $key): bool
    {
        if (!$this->database) {
            return false;
        }
        
        try {
            $result = $this->database->fetchValue(
                "SELECT COUNT(*) FROM {$this->table} WHERE key_name = ? AND (expires_at IS NULL OR expires_at > ?)",
                [$key, time()]
            );
            
            return $result > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Eliminar clave del cache
     */
    public function delete(string $key): bool
    {
        if (!$this->database) {
            return false;
        }
        
        try {
            $this->database->query(
                "DELETE FROM {$this->table} WHERE key_name = ?",
                [$key]
            );
            
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Incrementar valor numérico
     */
    public function increment(string $key, int $amount = 1): int
    {
        $value = $this->get($key, 0);
        $newValue = $value + $amount;
        $this->set($key, $newValue);
        return $newValue;
    }

    /**
     * Decrementar valor numérico
     */
    public function decrement(string $key, int $amount = 1): int
    {
        $value = $this->get($key, 0);
        $newValue = $value - $amount;
        $this->set($key, $newValue);
        return $newValue;
    }

    /**
     * Establecer múltiples valores
     */
    public function setMany(array $values, int $ttl = null): bool
    {
        if (!$this->database) {
            return false;
        }
        
        try {
            $this->database->beginTransaction();
            
            foreach ($values as $key => $value) {
                $this->set($key, $value, $ttl);
            }
            
            $this->database->commit();
            return true;
        } catch (\Throwable $e) {
            $this->database->rollback();
            return false;
        }
    }

    /**
     * Obtener múltiples valores
     */
    public function getMany(array $keys): array
    {
        if (!$this->database || empty($keys)) {
            return array_fill_keys($keys, null);
        }
        
        try {
            $placeholders = str_repeat('?,', count($keys) - 1) . '?';
            $result = $this->database->fetchAll(
                "SELECT key_name, value, expires_at FROM {$this->table} WHERE key_name IN ({$placeholders})",
                $keys
            );
            
            $values = array_fill_keys($keys, null);
            $now = time();
            
            foreach ($result as $row) {
                // Verificar si ha expirado
                if ($row['expires_at'] && $row['expires_at'] < $now) {
                    $this->delete($row['key_name']);
                    continue;
                }
                
                $values[$row['key_name']] = unserialize($row['value']);
            }
            
            return $values;
        } catch (\Throwable $e) {
            return array_fill_keys($keys, null);
        }
    }

    /**
     * Eliminar múltiples claves
     */
    public function deleteMany(array $keys): bool
    {
        if (!$this->database || empty($keys)) {
            return true;
        }
        
        try {
            $placeholders = str_repeat('?,', count($keys) - 1) . '?';
            $this->database->query(
                "DELETE FROM {$this->table} WHERE key_name IN ({$placeholders})",
                $keys
            );
            
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Limpiar todo el cache
     */
    public function flush(): bool
    {
        if (!$this->database) {
            return false;
        }
        
        try {
            $this->database->query("DELETE FROM {$this->table}");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Limpiar cache expirado
     */
    public function cleanup(): int
    {
        if (!$this->database) {
            return 0;
        }
        
        try {
            $this->database->query(
                "DELETE FROM {$this->table} WHERE expires_at IS NOT NULL AND expires_at < ?",
                [time()]
            );
            
            return $this->database->connection()->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        $info = [
            'table' => $this->table,
            'has_database' => $this->database !== null
        ];
        
        if ($this->database) {
            try {
                $count = $this->database->fetchValue("SELECT COUNT(*) FROM {$this->table}");
                $expiredCount = $this->database->fetchValue(
                    "SELECT COUNT(*) FROM {$this->table} WHERE expires_at IS NOT NULL AND expires_at < ?",
                    [time()]
                );
                
                $info['total_entries'] = $count;
                $info['expired_entries'] = $expiredCount;
            } catch (\Throwable $e) {
                $info['error'] = $e->getMessage();
            }
        }
        
        return $info;
    }
}
