<?php

namespace Tero\Queue\Connections;

use Tero\Config\ConfigManager;
use Tero\Database\DatabaseManager;
use Tero\Queue\Contracts\QueueConnectionInterface;
use Tero\Queue\Contracts\JobInterface;
use Tero\Queue\Job;

/**
 * DatabaseQueueConnection - Conexión de cola basada en base de datos
 * 
 * @package Tero\Queue\Connections
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class DatabaseQueueConnection implements QueueConnectionInterface
{
    private ConfigManager $config;
    private ?DatabaseManager $database;
    private string $queueName;
    private string $table;
    private string $failedTable;

    public function __construct(ConfigManager $config, ?DatabaseManager $database, string $queueName)
    {
        $this->config = $config;
        $this->database = $database;
        $this->queueName = $queueName;
        $this->table = $config->get('QUEUE_TABLE', 'jobs');
        $this->failedTable = $config->get('QUEUE_FAILED_TABLE', 'failed_jobs');
        
        $this->ensureTables();
    }

    /**
     * Asegurar que las tablas existen
     */
    private function ensureTables(): void
    {
        if (!$this->database) {
            return;
        }
        
        try {
            // Tabla de jobs
            if (!$this->database->tableExists($this->table)) {
                $sql = "CREATE TABLE {$this->table} (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    queue VARCHAR(255) NOT NULL,
                    payload LONGTEXT NOT NULL,
                    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    reserved_at INT UNSIGNED NULL,
                    available_at INT UNSIGNED NOT NULL,
                    created_at INT UNSIGNED NOT NULL,
                    INDEX queue_index (queue),
                    INDEX reserved_at_index (reserved_at),
                    INDEX available_at_index (available_at)
                )";
                
                $this->database->query($sql);
            }
            
            // Tabla de jobs fallidos
            if (!$this->database->tableExists($this->failedTable)) {
                $sql = "CREATE TABLE {$this->failedTable} (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    queue VARCHAR(255) NOT NULL,
                    payload LONGTEXT NOT NULL,
                    exception LONGTEXT NOT NULL,
                    failed_at INT UNSIGNED NOT NULL,
                    INDEX queue_index (queue),
                    INDEX failed_at_index (failed_at)
                )";
                
                $this->database->query($sql);
            }
        } catch (\Throwable $e) {
            error_log("Cannot create queue tables: " . $e->getMessage());
        }
    }

    /**
     * Encolar job
     */
    public function push(JobInterface $job): void
    {
        if (!$this->database) {
            return;
        }
        
        try {
            $jobData = [
                'class' => get_class($job),
                'id' => $job->getId(),
                'queue' => $job->getQueue(),
                'payload' => $job->getPayload(),
                'attempts' => $job->getAttempts(),
                'max_attempts' => $job->getMaxAttempts(),
                'timeout' => $job->getTimeout(),
                'metadata' => $job->getMetadata()
            ];
            
            $this->database->query(
                "INSERT INTO {$this->table} (queue, payload, attempts, available_at, created_at) VALUES (?, ?, ?, ?, ?)",
                [
                    $this->queueName,
                    json_encode($jobData),
                    $job->getAttempts(),
                    time(),
                    time()
                ]
            );
        } catch (\Throwable $e) {
            error_log("Failed to push job to queue: " . $e->getMessage());
        }
    }

    /**
     * Encolar job con delay
     */
    public function later(JobInterface $job, int $delay): void
    {
        if (!$this->database) {
            return;
        }
        
        try {
            $jobData = [
                'class' => get_class($job),
                'id' => $job->getId(),
                'queue' => $job->getQueue(),
                'payload' => $job->getPayload(),
                'attempts' => $job->getAttempts(),
                'max_attempts' => $job->getMaxAttempts(),
                'timeout' => $job->getTimeout(),
                'metadata' => $job->getMetadata()
            ];
            
            $this->database->query(
                "INSERT INTO {$this->table} (queue, payload, attempts, available_at, created_at) VALUES (?, ?, ?, ?, ?)",
                [
                    $this->queueName,
                    json_encode($jobData),
                    $job->getAttempts(),
                    time() + $delay,
                    time()
                ]
            );
        } catch (\Throwable $e) {
            error_log("Failed to push delayed job to queue: " . $e->getMessage());
        }
    }

    /**
     * Procesar jobs
     */
    public function process(int $maxJobs = 0): int
    {
        if (!$this->database) {
            return 0;
        }
        
        $processed = 0;
        $now = time();
        
        try {
            $this->database->beginTransaction();
            
            // Obtener jobs disponibles
            $jobs = $this->database->fetchAll(
                "SELECT * FROM {$this->table} 
                 WHERE queue = ? AND available_at <= ? AND reserved_at IS NULL 
                 ORDER BY created_at ASC 
                 LIMIT ?",
                [$this->queueName, $now, $maxJobs > 0 ? $maxJobs : 100]
            );
            
            foreach ($jobs as $jobData) {
                // Marcar como reservado
                $this->database->query(
                    "UPDATE {$this->table} SET reserved_at = ? WHERE id = ?",
                    [$now, $jobData['id']]
                );
                
                try {
                    // Deserializar y ejecutar job
                    $job = $this->deserializeJob($jobData['payload']);
                    $job->execute();
                    
                    // Eliminar job completado
                    $this->database->query(
                        "DELETE FROM {$this->table} WHERE id = ?",
                        [$jobData['id']]
                    );
                    
                    $processed++;
                } catch (\Throwable $e) {
                    // Manejar job fallido
                    $this->handleFailedJob($jobData, $e);
                }
            }
            
            $this->database->commit();
        } catch (\Throwable $e) {
            $this->database->rollback();
            error_log("Failed to process jobs: " . $e->getMessage());
        }
        
        return $processed;
    }

    /**
     * Deserializar job
     */
    private function deserializeJob(string $payload): JobInterface
    {
        $data = json_decode($payload, true);
        
        if (!$data) {
            throw new \RuntimeException("Invalid job payload");
        }
        
        // Si no hay clase, usar TestJob por defecto
        $className = $data['class'] ?? 'TestJob';
        
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
            // Incrementar intentos
            $attempts = $jobData['attempts'] + 1;
            
            // Verificar si puede reintentar
            $job = $this->deserializeJob($jobData['payload']);
            
            if ($job->canRetry()) {
                // Reintentar
                $this->database->query(
                    "UPDATE {$this->table} SET attempts = ?, reserved_at = NULL, available_at = ? WHERE id = ?",
                    [$attempts, time() + 60, $jobData['id']] // Retry en 1 minuto
                );
            } else {
                // Mover a jobs fallidos
                $this->database->query(
                    "INSERT INTO {$this->failedTable} (queue, payload, exception, failed_at) VALUES (?, ?, ?, ?)",
                    [
                        $this->queueName,
                        $jobData['payload'],
                        $exception->getMessage(),
                        time()
                    ]
                );
                
                // Eliminar de cola principal
                $this->database->query(
                    "DELETE FROM {$this->table} WHERE id = ?",
                    [$jobData['id']]
                );
            }
        } catch (\Throwable $e) {
            error_log("Failed to handle failed job: " . $e->getMessage());
        }
    }

    /**
     * Obtener tamaño de cola
     */
    public function size(): int
    {
        if (!$this->database) {
            return 0;
        }
        
        try {
            $result = $this->database->fetchValue(
                "SELECT COUNT(*) FROM {$this->table} WHERE queue = ?",
                [$this->queueName]
            );
            
            return (int) $result;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Limpiar cola
     */
    public function clear(): int
    {
        if (!$this->database) {
            return 0;
        }
        
        try {
            $this->database->query(
                "DELETE FROM {$this->table} WHERE queue = ?",
                [$this->queueName]
            );
            
            return $this->database->connection()->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Obtener jobs fallidos
     */
    public function getFailedJobs(): array
    {
        if (!$this->database) {
            return [];
        }
        
        try {
            return $this->database->fetchAll(
                "SELECT * FROM {$this->failedTable} WHERE queue = ? ORDER BY failed_at DESC",
                [$this->queueName]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Reintentar job fallido
     */
    public function retryFailedJob(int $id): bool
    {
        if (!$this->database) {
            return false;
        }
        
        try {
            $failedJob = $this->database->fetchOne(
                "SELECT * FROM {$this->failedTable} WHERE id = ?",
                [$id]
            );
            
            if (!$failedJob) {
                return false;
            }
            
            // Mover de vuelta a cola principal
            $this->database->query(
                "INSERT INTO {$this->table} (queue, payload, attempts, available_at, created_at) VALUES (?, ?, ?, ?, ?)",
                [
                    $this->queueName,
                    $failedJob['payload'],
                    0,
                    time(),
                    time()
                ]
            );
            
            // Eliminar de jobs fallidos
            $this->database->query(
                "DELETE FROM {$this->failedTable} WHERE id = ?",
                [$id]
            );
            
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Eliminar job fallido
     */
    public function deleteFailedJob(int $id): bool
    {
        if (!$this->database) {
            return false;
        }
        
        try {
            $this->database->query(
                "DELETE FROM {$this->failedTable} WHERE id = ?",
                [$id]
            );
            
            return $this->database->connection()->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Limpiar jobs fallidos
     */
    public function clearFailedJobs(): int
    {
        if (!$this->database) {
            return 0;
        }
        
        try {
            $this->database->query(
                "DELETE FROM {$this->failedTable} WHERE queue = ?",
                [$this->queueName]
            );
            
            return $this->database->connection()->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
