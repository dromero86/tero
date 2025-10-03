<?php

namespace Tero\Queue;

use Tero\Config\ConfigManager;
use Tero\Database\DatabaseManager;
use Tero\Queue\Contracts\QueueManagerInterface;
use Tero\Queue\Contracts\QueueConnectionInterface;
use Tero\Queue\Contracts\JobInterface;
use Tero\Queue\Exceptions\QueueException;
use Tero\Queue\Connections\DatabaseQueueConnection;
use Tero\Queue\Connections\FileQueueConnection;
use Tero\Queue\Connections\RedisQueueConnection;
use Tero\Queue\Connections\SyncQueueConnection;

/**
 * QueueManager - Gestor de colas y jobs
 * 
 * @package Tero\Queue
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class QueueManager implements QueueManagerInterface
{
    private ConfigManager $config;
    private ?DatabaseManager $database;
    private string $defaultQueue;
    private array $queues = [];
    private array $connections = [];
    private ?\Tero\Queue\Contracts\QueueConnectionInterface $defaultConnection = null;

    public function __construct(ConfigManager $config, ?DatabaseManager $database = null)
    {
        $this->config = $config;
        $this->database = $database;
        $this->defaultQueue = $config->get('QUEUE_DEFAULT', 'default');
        
        $this->initializeConnections();
    }

    /**
     * Inicializar conexiones
     */
    private function initializeConnections(): void
    {
        // Usar conexión de archivos por defecto si no hay base de datos
        $defaultDriver = $this->database ? 'database' : 'file';
        $this->defaultConnection = $this->createConnection($defaultDriver);
    }

    /**
     * Crear conexión
     */
    private function createConnection(string $name): \Tero\Queue\Contracts\QueueConnectionInterface
    {
        // Si el nombre es un driver, usarlo directamente
        if (in_array($name, ['database', 'file', 'redis', 'sync'])) {
            $driver = $name;
        } else {
            $driver = $this->config->get("QUEUE_CONNECTION_{$name}_DRIVER", 'database');
        }
        
        return match ($driver) {
            'database' => new DatabaseQueueConnection($this->config, $this->database, $name),
            'file' => new FileQueueConnection($this->config, $name),
            'redis' => new RedisQueueConnection($this->config, $name),
            'sync' => new SyncQueueConnection($this->config, $name),
            default => throw new QueueException("Unsupported queue driver: {$driver}")
        };
    }

    /**
     * Obtener conexión
     */
    public function connection(string $name = null): \Tero\Queue\Contracts\QueueConnectionInterface
    {
        $name = $name ?? $this->defaultQueue;
        
        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->createConnection($name);
        }
        
        return $this->connections[$name];
    }

    /**
     * Encolar job
     */
    public function push(JobInterface $job, string $queue = null): void
    {
        $queue = $queue ?? $this->defaultQueue;
        $connection = $this->connection($queue);
        $connection->push($job);
    }

    /**
     * Encolar job con delay
     */
    public function later(JobInterface $job, int $delay, string $queue = null): void
    {
        $queue = $queue ?? $this->defaultQueue;
        $connection = $this->connection($queue);
        $connection->later($job, $delay);
    }

    /**
     * Procesar jobs
     */
    public function process(string $queue = null, int $maxJobs = 0): int
    {
        $queue = $queue ?? $this->defaultQueue;
        $connection = $this->connection($queue);
        
        return $connection->process($maxJobs);
    }

    /**
     * Obtener tamaño de cola
     */
    public function size(string $queue = null): int
    {
        $queue = $queue ?? $this->defaultQueue;
        $connection = $this->connection($queue);
        
        return $connection->size();
    }

    /**
     * Limpiar cola
     */
    public function clear(string $queue = null): int
    {
        $queue = $queue ?? $this->defaultQueue;
        $connection = $this->connection($queue);
        
        return $connection->clear();
    }

    /**
     * Obtener jobs fallidos
     */
    public function getFailedJobs(): array
    {
        $failedJobs = [];
        
        foreach ($this->connections as $connection) {
            $failedJobs = array_merge($failedJobs, $connection->getFailedJobs());
        }
        
        return $failedJobs;
    }

    /**
     * Reintentar job fallido
     */
    public function retryFailedJob(int $id): bool
    {
        foreach ($this->connections as $connection) {
            if ($connection->retryFailedJob($id)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Eliminar job fallido
     */
    public function deleteFailedJob(int $id): bool
    {
        foreach ($this->connections as $connection) {
            if ($connection->deleteFailedJob($id)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Limpiar jobs fallidos
     */
    public function clearFailedJobs(): int
    {
        $cleared = 0;
        
        foreach ($this->connections as $connection) {
            $cleared += $connection->clearFailedJobs();
        }
        
        return $cleared;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'default_queue' => $this->defaultQueue,
            'connections_count' => count($this->connections),
            'queues' => array_keys($this->connections),
            'failed_jobs_count' => count($this->getFailedJobs())
        ];
    }
}
