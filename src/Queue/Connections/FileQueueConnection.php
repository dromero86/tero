<?php

namespace Tero\Queue\Connections;

use Tero\Config\ConfigManager;
use Tero\Queue\Contracts\QueueConnectionInterface;
use Tero\Queue\Contracts\JobInterface;

/**
 * FileQueueConnection - Conexión de cola basada en archivos
 * 
 * @package Tero\Queue\Connections
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class FileQueueConnection implements QueueConnectionInterface
{
    private ConfigManager $config;
    private string $queueName;
    private string $queuePath;
    private string $failedPath;

    public function __construct(ConfigManager $config, string $queueName)
    {
        $this->config = $config;
        $this->queueName = $queueName;
        $this->queuePath = $config->get('QUEUE_PATH', 'storage/queue');
        $this->failedPath = $config->get('QUEUE_FAILED_PATH', 'storage/failed_jobs');
        
        $this->ensureDirectories();
    }

    /**
     * Asegurar que los directorios existen
     */
    private function ensureDirectories(): void
    {
        if (!is_dir($this->queuePath)) {
            mkdir($this->queuePath, 0755, true);
        }
        
        if (!is_dir($this->failedPath)) {
            mkdir($this->failedPath, 0755, true);
        }
    }

    /**
     * Obtener ruta del archivo de job
     */
    private function getJobFile(string $id): string
    {
        return $this->queuePath . '/' . $this->queueName . '_' . $id . '.job';
    }

    /**
     * Obtener ruta del archivo de job fallido
     */
    private function getFailedJobFile(string $id): string
    {
        return $this->failedPath . '/' . $this->queueName . '_' . $id . '.failed';
    }

    /**
     * Encolar job
     */
    public function push(JobInterface $job): void
    {
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
        
        $file = $this->getJobFile($job->getId());
        file_put_contents($file, json_encode($jobData), LOCK_EX);
    }

    /**
     * Encolar job con delay
     */
    public function later(JobInterface $job, int $delay): void
    {
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
        
        $file = $this->getJobFile($job->getId());
        file_put_contents($file, json_encode($jobData), LOCK_EX);
    }

    /**
     * Procesar jobs
     */
    public function process(int $maxJobs = 0): int
    {
        $processed = 0;
        $now = time();
        $pattern = $this->queuePath . '/' . $this->queueName . '_*.job';
        $files = glob($pattern);
        
        if (empty($files)) {
            return 0;
        }
        
        foreach ($files as $file) {
            if ($maxJobs > 0 && $processed >= $maxJobs) {
                break;
            }
            
            $jobData = json_decode(file_get_contents($file), true);
            
            if (!$jobData || $jobData['available_at'] > $now) {
                continue;
            }
            
            try {
                // Deserializar y ejecutar job
                $job = $this->deserializeJob($jobData);
                $job->execute();
                
                // Eliminar archivo de job
                unlink($file);
                $processed++;
            } catch (\Throwable $e) {
                // Manejar job fallido
                $this->handleFailedJob($jobData, $e);
                unlink($file);
            }
        }
        
        return $processed;
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
        $attempts = $jobData['attempts'] + 1;
        
        // Verificar si puede reintentar
        if ($attempts < $jobData['max_attempts']) {
            // Reintentar
            $jobData['attempts'] = $attempts;
            $jobData['available_at'] = time() + 60; // Retry en 1 minuto
            
            $file = $this->getJobFile($jobData['id']);
            file_put_contents($file, json_encode($jobData), LOCK_EX);
        } else {
            // Mover a jobs fallidos
            $failedData = [
                'id' => $jobData['id'],
                'queue' => $this->queueName,
                'payload' => $jobData['payload'],
                'exception' => $exception->getMessage(),
                'failed_at' => time()
            ];
            
            $file = $this->getFailedJobFile($jobData['id']);
            file_put_contents($file, json_encode($failedData), LOCK_EX);
        }
    }

    /**
     * Obtener tamaño de cola
     */
    public function size(): int
    {
        $pattern = $this->queuePath . '/' . $this->queueName . '_*.job';
        $files = glob($pattern);
        
        return count($files);
    }

    /**
     * Limpiar cola
     */
    public function clear(): int
    {
        $pattern = $this->queuePath . '/' . $this->queueName . '_*.job';
        $files = glob($pattern);
        $deleted = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }

    /**
     * Obtener jobs fallidos
     */
    public function getFailedJobs(): array
    {
        $pattern = $this->failedPath . '/' . $this->queueName . '_*.failed';
        $files = glob($pattern);
        $failedJobs = [];
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $failedJobs[] = $data;
            }
        }
        
        // Ordenar por fecha de fallo
        usort($failedJobs, function($a, $b) {
            return $b['failed_at'] <=> $a['failed_at'];
        });
        
        return $failedJobs;
    }

    /**
     * Reintentar job fallido
     */
    public function retryFailedJob(int $id): bool
    {
        $file = $this->getFailedJobFile($id);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $failedData = json_decode(file_get_contents($file), true);
        
        if (!$failedData) {
            return false;
        }
        
        // Mover de vuelta a cola principal
        $jobData = [
            'id' => $failedData['id'],
            'queue' => $this->queueName,
            'payload' => $failedData['payload'],
            'attempts' => 0,
            'max_attempts' => 3,
            'timeout' => 60,
            'metadata' => [],
            'created_at' => time(),
            'available_at' => time()
        ];
        
        $jobFile = $this->getJobFile($failedData['id']);
        file_put_contents($jobFile, json_encode($jobData), LOCK_EX);
        
        // Eliminar archivo de job fallido
        unlink($file);
        
        return true;
    }

    /**
     * Eliminar job fallido
     */
    public function deleteFailedJob(int $id): bool
    {
        $file = $this->getFailedJobFile($id);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return false;
    }

    /**
     * Limpiar jobs fallidos
     */
    public function clearFailedJobs(): int
    {
        $pattern = $this->failedPath . '/' . $this->queueName . '_*.failed';
        $files = glob($pattern);
        $deleted = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
}
