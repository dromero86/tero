<?php

namespace Tero\Tools;

use Tero\Config\ConfigManager;
use Tero\Database\DatabaseManager;
use Tero\Tools\Contracts\ToolInterface;

/**
 * CronManager - Gestor moderno de cron jobs
 * 
 * @package Tero\Tools
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class CronManager implements ToolInterface
{
    private ConfigManager $config;
    private DatabaseManager $database;
    private string $cronPath;
    private array $jobs = [];
    private bool $autoCleanup;

    public function __construct(ConfigManager $config, DatabaseManager $database)
    {
        $this->config = $config;
        $this->database = $database;
        $this->cronPath = $config->get('CRON_PATH', 'storage/cronjobs');
        $this->autoCleanup = $config->get('CRON_AUTO_CLEANUP', true, 'bool');
        
        $this->ensureCronDirectory();
        $this->loadJobs();
    }

    /**
     * Asegurar que el directorio de cron jobs existe
     */
    private function ensureCronDirectory(): void
    {
        if (!is_dir($this->cronPath)) {
            mkdir($this->cronPath, 0755, true);
        }
    }

    /**
     * Cargar jobs desde archivos
     */
    private function loadJobs(): void
    {
        $this->jobs = [];
        $files = glob($this->cronPath . '/*.json');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content) {
                $jobs = json_decode($content, true);
                if (is_array($jobs)) {
                    $this->jobs = array_merge($this->jobs, $jobs);
                }
            }
        }
    }

    /**
     * Guardar jobs en archivo
     */
    private function saveJobs(): void
    {
        $date = date('Ymd');
        $filename = $this->cronPath . "/{$date}.json";
        
        $content = json_encode($this->jobs, JSON_PRETTY_PRINT);
        file_put_contents($filename, $content);
    }

    /**
     * Listar todos los jobs
     */
    public function listJobs(): array
    {
        return $this->jobs;
    }

    /**
     * Mostrar jobs en formato cron
     */
    public function showJobs(?string $jobId = null): string
    {
        $output = "\n";
        $output .= "##############\n";
        $output .= "# -CRONJOBS- #\n";
        $output .= "##############\n";
        $output .= "\n";

        if ($jobId) {
            if (isset($this->jobs[$jobId])) {
                $output .= $this->formatJob($jobId, $this->jobs[$jobId]);
            } else {
                $output .= "JobId [{$jobId}] not found!\n";
            }
        } else {
            foreach ($this->jobs as $id => $job) {
                $output .= $this->formatJob($id, $job);
            }
        }

        return $output;
    }

    /**
     * Formatear job para mostrar
     */
    private function formatJob(string $id, array $job): string
    {
        $disabled = $job['disable'] ?? false;
        $name = $job['name'] ?? 'Unnamed';
        $when = $job['when'] ?? 'Unknown';
        $expression = $job['expression'] ?? '';
        $command = $job['command'] ?? '';

        $line = "# " . ($disabled ? '[DISABLED]' : '') . " {$name} - {$when}\n";
        $line .= ($disabled ? '#' : '') . "{$expression} {$command}\n\n";
        
        return $line;
    }

    /**
     * Crear nuevo job
     */
    public function createJob(array $jobData): bool
    {
        $required = ['id', 'name', 'when', 'expression', 'command'];
        
        foreach ($required as $field) {
            if (!isset($jobData[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing");
            }
        }

        $jobId = $jobData['id'];
        
        $this->jobs[$jobId] = [
            'name' => $jobData['name'],
            'when' => $jobData['when'],
            'expression' => $jobData['expression'],
            'command' => $jobData['command'],
            'disable' => $jobData['disable'] ?? false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->saveJobs();
        return true;
    }

    /**
     * Editar job existente
     */
    public function editJob(string $jobId, array $updates): bool
    {
        if (!isset($this->jobs[$jobId])) {
            throw new \InvalidArgumentException("Job '{$jobId}' not found");
        }

        $allowedFields = ['name', 'when', 'expression', 'command', 'disable'];
        
        foreach ($updates as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $this->jobs[$jobId][$field] = $value;
            }
        }

        $this->jobs[$jobId]['updated_at'] = date('Y-m-d H:i:s');
        $this->saveJobs();
        
        return true;
    }

    /**
     * Deshabilitar job
     */
    public function disableJob(string $jobId): bool
    {
        return $this->editJob($jobId, ['disable' => true]);
    }

    /**
     * Habilitar job
     */
    public function enableJob(string $jobId): bool
    {
        return $this->editJob($jobId, ['disable' => false]);
    }

    /**
     * Eliminar job
     */
    public function deleteJob(string $jobId): bool
    {
        if (!isset($this->jobs[$jobId])) {
            throw new \InvalidArgumentException("Job '{$jobId}' not found");
        }

        unset($this->jobs[$jobId]);
        $this->saveJobs();
        
        return true;
    }

    /**
     * Ejecutar jobs programados
     */
    public function runScheduledJobs(): array
    {
        $results = [];
        $currentTime = time();
        
        foreach ($this->jobs as $id => $job) {
            if ($job['disable'] ?? false) {
                continue;
            }

            if ($this->shouldRunJob($job, $currentTime)) {
                $results[$id] = $this->executeJob($id, $job);
            }
        }

        return $results;
    }

    /**
     * Verificar si un job debe ejecutarse
     */
    private function shouldRunJob(array $job, int $currentTime): bool
    {
        $expression = $job['expression'] ?? '';
        
        // Implementación simplificada de cron expression
        // En una implementación real, usar una librería como mtdowling/cron-expression
        return $this->parseCronExpression($expression, $currentTime);
    }

    /**
     * Parsear expresión cron (implementación simplificada)
     */
    private function parseCronExpression(string $expression, int $currentTime): bool
    {
        $parts = explode(' ', trim($expression));
        
        if (count($parts) !== 5) {
            return false;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;
        
        $now = getdate($currentTime);
        
        return $this->matchesCronPart($minute, $now['minutes']) &&
               $this->matchesCronPart($hour, $now['hours']) &&
               $this->matchesCronPart($day, $now['mday']) &&
               $this->matchesCronPart($month, $now['mon']) &&
               $this->matchesCronPart($weekday, $now['wday']);
    }

    /**
     * Verificar si un valor coincide con una parte de cron
     */
    private function matchesCronPart(string $part, int $value): bool
    {
        if ($part === '*') {
            return true;
        }
        
        if (is_numeric($part)) {
            return (int)$part === $value;
        }
        
        if (strpos($part, ',') !== false) {
            $values = explode(',', $part);
            return in_array($value, array_map('intval', $values));
        }
        
        if (strpos($part, '-') !== false) {
            [$start, $end] = explode('-', $part, 2);
            return $value >= (int)$start && $value <= (int)$end;
        }
        
        return false;
    }

    /**
     * Ejecutar job
     */
    private function executeJob(string $id, array $job): array
    {
        $command = $job['command'];
        $startTime = microtime(true);
        
        try {
            // Ejecutar comando
            $output = [];
            $returnCode = 0;
            
            exec($command . ' 2>&1', $output, $returnCode);
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            
            $result = [
                'success' => $returnCode === 0,
                'output' => implode("\n", $output),
                'return_code' => $returnCode,
                'duration' => $duration,
                'executed_at' => date('Y-m-d H:i:s')
            ];
            
            // Log del resultado
            $this->logJobExecution($id, $result);
            
            return $result;
            
        } catch (\Throwable $e) {
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            
            $result = [
                'success' => false,
                'output' => $e->getMessage(),
                'return_code' => 1,
                'duration' => $duration,
                'executed_at' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ];
            
            $this->logJobExecution($id, $result);
            
            return $result;
        }
    }

    /**
     * Log de ejecución de job
     */
    private function logJobExecution(string $jobId, array $result): void
    {
        $logData = [
            'job_id' => $jobId,
            'result' => $result,
            'timestamp' => date('c')
        ];
        
        $logFile = $this->cronPath . '/execution.log';
        $logLine = date('Y-m-d H:i:s') . ' - ' . json_encode($logData) . "\n";
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Limpiar archivos antiguos
     */
    public function cleanupOldFiles(int $daysToKeep = 30): int
    {
        if (!$this->autoCleanup) {
            return 0;
        }

        $files = glob($this->cronPath . '/*.json');
        $deleted = 0;
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }

    /**
     * Obtener estadísticas
     */
    public function getStats(): array
    {
        $total = count($this->jobs);
        $enabled = 0;
        $disabled = 0;
        
        foreach ($this->jobs as $job) {
            if ($job['disable'] ?? false) {
                $disabled++;
            } else {
                $enabled++;
            }
        }
        
        return [
            'total_jobs' => $total,
            'enabled_jobs' => $enabled,
            'disabled_jobs' => $disabled,
            'cron_path' => $this->cronPath,
            'auto_cleanup' => $this->autoCleanup
        ];
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'cron_path' => $this->cronPath,
            'jobs_count' => count($this->jobs),
            'auto_cleanup' => $this->autoCleanup,
            'stats' => $this->getStats()
        ];
    }

    /**
     * Validar expresión cron
     */
    public function validateCronExpression(string $expression): bool
    {
        $parts = explode(' ', trim($expression));
        
        if (count($parts) !== 5) {
            return false;
        }
        
        // Validación básica de cada parte
        foreach ($parts as $part) {
            if (!$this->isValidCronPart($part)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validar parte de expresión cron
     */
    private function isValidCronPart(string $part): bool
    {
        if ($part === '*') {
            return true;
        }
        
        if (is_numeric($part)) {
            return true;
        }
        
        if (strpos($part, ',') !== false) {
            $values = explode(',', $part);
            foreach ($values as $value) {
                if (!is_numeric(trim($value))) {
                    return false;
                }
            }
            return true;
        }
        
        if (strpos($part, '-') !== false) {
            $range = explode('-', $part, 2);
            return count($range) === 2 && is_numeric($range[0]) && is_numeric($range[1]);
        }
        
        return false;
    }
}
