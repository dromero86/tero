<?php

namespace Tero\Queue\Connections;

use Tero\Config\ConfigManager;
use Tero\Queue\Contracts\QueueConnectionInterface;
use Tero\Queue\Contracts\JobInterface;

/**
 * RedisQueueConnection - Conexión de cola basada en Redis
 * 
 * @package Tero\Queue\Connections
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class RedisQueueConnection implements QueueConnectionInterface
{
    private ConfigManager $config;
    private string $queueName;
    private ?\Redis $redis = null;
    private string $host;
    private int $port;
    private string $password;
    private int $database;

    public function __construct(ConfigManager $config, string $queueName)
    {
        $this->config = $config;
        $this->queueName = $queueName;
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
     * Obtener clave de cola
     */
    private function getQueueKey(): string
    {
        return "queue:{$this->queueName}";
    }

    /**
     * Obtener clave de cola de delay
     */
    private function getDelayedQueueKey(): string
    {
        return "queue:{$this->queueName}:delayed";
    }

    /**
     * Obtener clave de jobs fallidos
     */
    private function getFailedQueueKey(): string
    {
        return "queue:{$this->queueName}:failed";
    }

    /**
     * Encolar job
     */
    public function push(JobInterface $job): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        
        try {
            $jobData = [
                'id' => $job->getId(),
                'queue' => $this->queueName,
                'payload' => $job->getPayload(),
                'attempts' => $job->getAttempts(),
                'max_attempts' => $job->getMaxAttempts(),
                'timeout' => $job->getTimeout(),
                'metadata' => $job->getMetadata(),
                'created_at' => time(),
                'available_at' => time()
            ];
            
            $this->redis->lpush($this->getQueueKey(), json_encode($jobData));
        } catch (\Throwable $e) {
            error_log("Failed to push job to Redis queue: " . $e->getMessage());
        }
    }

    /**
     * Encolar job con delay
     */
    public function later(JobInterface $job, int $delay): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        
        try {
            $jobData = [
                'id' => $job->getId(),
                'queue' => $this->queueName,
                'payload' => $job->getPayload(),
                'attempts' => $job->getAttempts(),
                'max_attempts' => $job->getMaxAttempts(),
                'timeout' => $job->getTimeout(),
                'metadata' => $job->getMetadata(),
                'created_at' => time(),
                'available_at' => time() + $delay
            ];
            
            $this->redis->zadd($this->getDelayedQueueKey(), time() + $delay, json_encode($jobData));
        } catch (\Throwable $e) {
            error_log("Failed to push delayed job to Redis queue: " . $e->getMessage());
        }
    }

    /**
     * Procesar jobs
     */
    public function process(int $maxJobs = 0): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }
        
        $processed = 0;
        $now = time();
        
        try {
            // Procesar jobs con delay
            $this->processDelayedJobs($now);
            
            // Procesar jobs normales
            $jobs = $this->redis->lrange($this->getQueueKey(), 0, $maxJobs > 0 ? $maxJobs - 1 : 99);
            
            foreach ($jobs as $jobJson) {
                if ($maxJobs > 0 && $processed >= $maxJobs) {
                    break;
                }
                
                $jobData = json_decode($jobJson, true);
                
                if (!$jobData) {
                    continue;
                }
                
                try {
                    // Deserializar y ejecutar job
                    $job = $this->deserializeJob($jobData);
                    $job->execute();
                    
                    // Eliminar job de la cola
                    $this->redis->lrem($this->getQueueKey(), 1, $jobJson);
                    $processed++;
                } catch (\Throwable $e) {
                    // Manejar job fallido
                    $this->handleFailedJob($jobData, $e);
                    $this->redis->lrem($this->getQueueKey(), 1, $jobJson);
                }
            }
        } catch (\Throwable $e) {
            error_log("Failed to process Redis queue jobs: " . $e->getMessage());
        }
        
        return $processed;
    }

    /**
     * Procesar jobs con delay
     */
    private function processDelayedJobs(int $now): void
    {
        try {
            $delayedJobs = $this->redis->zrangebyscore($this->getDelayedQueueKey(), 0, $now);
            
            foreach ($delayedJobs as $jobJson) {
                $jobData = json_decode($jobJson, true);
                
                if ($jobData) {
                    // Mover a cola principal
                    $this->redis->lpush($this->getQueueKey(), $jobJson);
                    
                    // Eliminar de cola de delay
                    $this->redis->zrem($this->getDelayedQueueKey(), $jobJson);
                }
            }
        } catch (\Throwable $e) {
            error_log("Failed to process delayed Redis queue jobs: " . $e->getMessage());
        }
    }

    /**
     * Deserializar job
     */
    private function deserializeJob(array $data): JobInterface
    {
        if (!isset($data['class'])) {
            throw new \RuntimeException("Invalid job data");
        }
        
        $className = $data['class'];
        
        if (!class_exists($className)) {
            throw new \RuntimeException("Job class not found: {$className}");
        }
        
        $job = new $className($data['payload'] ?? []);
        
        if (isset($data['id'])) {
            $job->setId($data['id']);
        }
        
        if (isset($data['queue'])) {
            $job->setQueue($data['queue']);
        }
        
        if (isset($data['attempts'])) {
            $job->setAttempts($data['attempts']);
        }
        
        if (isset($data['max_attempts'])) {
            $job->setMaxAttempts($data['max_attempts']);
        }
        
        if (isset($data['timeout'])) {
            $job->setTimeout($data['timeout']);
        }
        
        return $job;
    }

    /**
     * Manejar job fallido
     */
    private function handleFailedJob(array $jobData, \Throwable $exception): void
    {
        try {
            $attempts = $jobData['attempts'] + 1;
            
            // Verificar si puede reintentar
            if ($attempts < $jobData['max_attempts']) {
                // Reintentar
                $jobData['attempts'] = $attempts;
                $jobData['available_at'] = time() + 60; // Retry en 1 minuto
                
                $this->redis->zadd($this->getDelayedQueueKey(), $jobData['available_at'], json_encode($jobData));
            } else {
                // Mover a jobs fallidos
                $failedData = [
                    'id' => $jobData['id'],
                    'queue' => $this->queueName,
                    'payload' => $jobData['payload'],
                    'exception' => $exception->getMessage(),
                    'failed_at' => time()
                ];
                
                $this->redis->lpush($this->getFailedQueueKey(), json_encode($failedData));
            }
        } catch (\Throwable $e) {
            error_log("Failed to handle failed Redis queue job: " . $e->getMessage());
        }
    }

    /**
     * Obtener tamaño de cola
     */
    public function size(): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }
        
        try {
            return $this->redis->llen($this->getQueueKey());
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Limpiar cola
     */
    public function clear(): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }
        
        try {
            $size = $this->redis->llen($this->getQueueKey());
            $this->redis->del($this->getQueueKey());
            $this->redis->del($this->getDelayedQueueKey());
            return $size;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Obtener jobs fallidos
     */
    public function getFailedJobs(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }
        
        try {
            $failedJobs = $this->redis->lrange($this->getFailedQueueKey(), 0, -1);
            $result = [];
            
            foreach ($failedJobs as $jobJson) {
                $data = json_decode($jobJson, true);
                if ($data) {
                    $result[] = $data;
                }
            }
            
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Reintentar job fallido
     */
    public function retryFailedJob(int $id): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            $failedJobs = $this->redis->lrange($this->getFailedQueueKey(), 0, -1);
            
            foreach ($failedJobs as $index => $jobJson) {
                $data = json_decode($jobJson, true);
                
                if ($data && $data['id'] == $id) {
                    // Mover de vuelta a cola principal
                    $jobData = [
                        'id' => $data['id'],
                        'queue' => $this->queueName,
                        'payload' => $data['payload'],
                        'attempts' => 0,
                        'max_attempts' => 3,
                        'timeout' => 60,
                        'metadata' => [],
                        'created_at' => time(),
                        'available_at' => time()
                    ];
                    
                    $this->redis->lpush($this->getQueueKey(), json_encode($jobData));
                    
                    // Eliminar de jobs fallidos
                    $this->redis->lrem($this->getFailedQueueKey(), 1, $jobJson);
                    
                    return true;
                }
            }
            
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Eliminar job fallido
     */
    public function deleteFailedJob(int $id): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            $failedJobs = $this->redis->lrange($this->getFailedQueueKey(), 0, -1);
            
            foreach ($failedJobs as $jobJson) {
                $data = json_decode($jobJson, true);
                
                if ($data && $data['id'] == $id) {
                    $this->redis->lrem($this->getFailedQueueKey(), 1, $jobJson);
                    return true;
                }
            }
            
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Limpiar jobs fallidos
     */
    public function clearFailedJobs(): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }
        
        try {
            $size = $this->redis->llen($this->getFailedQueueKey());
            $this->redis->del($this->getFailedQueueKey());
            return $size;
        } catch (\Throwable $e) {
            return 0;
        }
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
