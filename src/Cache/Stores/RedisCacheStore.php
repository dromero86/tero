<?php

namespace Tero\Cache\Stores;

use Tero\Config\ConfigManager;
use Tero\Cache\Contracts\CacheStoreInterface;

/**
 * RedisCacheStore - Store de cache basado en Redis
 * 
 * @package Tero\Cache\Stores
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class RedisCacheStore implements CacheStoreInterface
{
    private ConfigManager $config;
    private ?\Redis $redis = null;
    private string $host;
    private int $port;
    private string $password;
    private int $database;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->host = $config->get('REDIS_HOST', '127.0.0.1');
        $this->port = (int) $config->get('REDIS_PORT', '6379');
        $this->password = $config->get('REDIS_PASSWORD', '');
        $this->database = (int) $config->get('REDIS_DATABASE', '0');
        
        $this->connect();
    }

    /**
     * Conectar a Redis
     */
    private function connect(): void
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension not loaded');
        }
        
        try {
            $this->redis = new \Redis();
            $this->redis->connect($this->host, $this->port);
            
            if ($this->password) {
                $this->redis->auth($this->password);
            }
            
            $this->redis->select($this->database);
        } catch (\Throwable $e) {
            $this->redis = null;
            error_log("Redis connection failed: " . $e->getMessage());
        }
    }

    /**
     * Verificar si Redis está disponible
     */
    private function isAvailable(): bool
    {
        return $this->redis !== null;
    }

    /**
     * Obtener valor del cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->isAvailable()) {
            return $default;
        }
        
        try {
            $value = $this->redis->get($key);
            
            if ($value === false) {
                return $default;
            }
            
            return unserialize($value);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Establecer valor en cache
     */
    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            $serializedValue = serialize($value);
            
            if ($ttl > 0) {
                return $this->redis->setex($key, $ttl, $serializedValue);
            } else {
                return $this->redis->set($key, $serializedValue);
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Verificar si clave existe
     */
    public function has(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            return $this->redis->exists($key) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Eliminar clave del cache
     */
    public function delete(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            return $this->redis->del($key) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Incrementar valor numérico
     */
    public function increment(string $key, int $amount = 1): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }
        
        try {
            return $this->redis->incrBy($key, $amount);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Decrementar valor numérico
     */
    public function decrement(string $key, int $amount = 1): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }
        
        try {
            return $this->redis->decrBy($key, $amount);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Establecer múltiples valores
     */
    public function setMany(array $values, int $ttl = null): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            $pipeline = $this->redis->multi();
            
            foreach ($values as $key => $value) {
                $serializedValue = serialize($value);
                
                if ($ttl > 0) {
                    $pipeline->setex($key, $ttl, $serializedValue);
                } else {
                    $pipeline->set($key, $serializedValue);
                }
            }
            
            $pipeline->exec();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Obtener múltiples valores
     */
    public function getMany(array $keys): array
    {
        if (!$this->isAvailable() || empty($keys)) {
            return array_fill_keys($keys, null);
        }
        
        try {
            $values = $this->redis->mget($keys);
            $result = [];
            
            foreach ($keys as $index => $key) {
                $value = $values[$index];
                $result[$key] = $value === false ? null : unserialize($value);
            }
            
            return $result;
        } catch (\Throwable $e) {
            return array_fill_keys($keys, null);
        }
    }

    /**
     * Eliminar múltiples claves
     */
    public function deleteMany(array $keys): bool
    {
        if (!$this->isAvailable() || empty($keys)) {
            return true;
        }
        
        try {
            return $this->redis->del($keys) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Limpiar todo el cache
     */
    public function flush(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            return $this->redis->flushDB();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Limpiar cache expirado (Redis maneja esto automáticamente)
     */
    public function cleanup(): int
    {
        return 0; // Redis maneja la expiración automáticamente
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        $info = [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'has_password' => !empty($this->password),
            'is_available' => $this->isAvailable()
        ];
        
        if ($this->isAvailable()) {
            try {
                $info['info'] = $this->redis->info();
                $info['db_size'] = $this->redis->dbSize();
            } catch (\Throwable $e) {
                $info['error'] = $e->getMessage();
            }
        }
        
        return $info;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->redis) {
            $this->redis->close();
        }
    }
}
