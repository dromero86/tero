<?php

namespace Tero\Database\Migrations;

use Tero\Config\ConfigManager;
use Tero\Database\DatabaseManager;
use Tero\Tools\Contracts\ToolInterface;
use DateTimeImmutable;
use DirectoryIterator;

/**
 * MigrationManager - Gestor moderno de migraciones
 * 
 * @package Tero\Database\Migrations
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class MigrationManager implements ToolInterface
{
    private ConfigManager $config;
    private DatabaseManager $database;
    private string $migrationPath;
    private string $currentMigrationFile;
    private array $migrations = [];
    private bool $autoRun;

    public function __construct(ConfigManager $config, DatabaseManager $database)
    {
        $this->config = $config;
        $this->database = $database;
        $this->migrationPath = $config->get('MIGRATION_PATH', 'storage/migrations');
        $this->currentMigrationFile = $this->migrationPath . '/last.log';
        $this->autoRun = $config->get('MIGRATION_AUTO_RUN', false, 'bool');
        
        $this->ensureMigrationDirectory();
        $this->loadMigrations();
        $this->ensureMigrationsTable();
    }

    /**
     * Asegurar que el directorio de migraciones existe
     */
    private function ensureMigrationDirectory(): void
    {
        if (!is_dir($this->migrationPath)) {
            mkdir($this->migrationPath, 0755, true);
        }
    }

    /**
     * Asegurar que la tabla de migraciones existe
     */
    private function ensureMigrationsTable(): void
    {
        try {
            if (!$this->database->tableExists('migrations')) {
                $sql = "CREATE TABLE migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_migration (migration)
                )";
                
                $this->database->query($sql);
            }
        } catch (\Throwable $e) {
            // Si no se puede crear la tabla, continuar sin base de datos
            error_log("Cannot create migrations table: " . $e->getMessage());
        }
    }

    /**
     * Cargar migraciones desde archivos
     */
    private function loadMigrations(): void
    {
        $this->migrations = [];
        $files = glob($this->migrationPath . '/*-up.sql');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $migrationId = str_replace('-up.sql', '', $filename);
            
            if (strlen($migrationId) === 8 && is_numeric($migrationId)) {
                $this->migrations[$migrationId] = [
                    'id' => $migrationId,
                    'up_file' => $file,
                    'down_file' => str_replace('-up.sql', '-down.sql', $file),
                    'date' => DateTimeImmutable::createFromFormat('Ymd', $migrationId)
                ];
            }
        }
        
        // Ordenar por fecha
        uksort($this->migrations, function($a, $b) {
            return strcmp($a, $b);
        });
    }

    /**
     * Obtener migración actual
     */
    private function getCurrentMigration(): ?string
    {
        if (!file_exists($this->currentMigrationFile)) {
            return null;
        }
        
        $content = file_get_contents($this->currentMigrationFile);
        return trim($content) ?: null;
    }

    /**
     * Guardar migración actual
     */
    private function setCurrentMigration(string $migrationId): void
    {
        file_put_contents($this->currentMigrationFile, $migrationId, LOCK_EX);
    }

    /**
     * Obtener estado de migraciones
     */
    public function getStatus(?string $migrationId = null): array
    {
        if ($migrationId) {
            return $this->getMigrationStatus($migrationId);
        }
        
        return $this->getGeneralStatus();
    }

    /**
     * Obtener estado general
     */
    private function getGeneralStatus(): array
    {
        $current = $this->getCurrentMigration();
        $pending = [];
        $executed = [];
        
        foreach ($this->migrations as $id => $migration) {
            if ($current && $id <= $current) {
                $executed[] = $migration;
            } else {
                $pending[] = $migration;
            }
        }
        
        return [
            'current' => $current,
            'total' => count($this->migrations),
            'executed' => count($executed),
            'pending' => count($pending),
            'pending_migrations' => $pending,
            'executed_migrations' => $executed
        ];
    }

    /**
     * Obtener estado de migración específica
     */
    private function getMigrationStatus(string $migrationId): array
    {
        if (!isset($this->migrations[$migrationId])) {
            throw new \InvalidArgumentException("Migration '{$migrationId}' not found");
        }
        
        $current = $this->getCurrentMigration();
        $migration = $this->migrations[$migrationId];
        
        return [
            'id' => $migrationId,
            'date' => $migration['date']->format('Y-m-d'),
            'status' => $current && $migrationId <= $current ? 'executed' : 'pending',
            'up_file' => $migration['up_file'],
            'down_file' => $migration['down_file'],
            'up_exists' => file_exists($migration['up_file']),
            'down_exists' => file_exists($migration['down_file'])
        ];
    }

    /**
     * Crear nueva migración
     */
    public function makeMigration(string $name = null): string
    {
        $migrationId = date('YmdHis');
        $name = $name ?: 'migration';
        
        $upFile = $this->migrationPath . "/{$migrationId}-{$name}-up.sql";
        $downFile = $this->migrationPath . "/{$migrationId}-{$name}-down.sql";
        
        if (file_exists($upFile)) {
            throw new \RuntimeException("Migration '{$migrationId}' already exists");
        }
        
        // Crear archivos vacíos
        file_put_contents($upFile, "-- Migration UP: {$name}\n-- Created: " . date('Y-m-d H:i:s') . "\n\n");
        file_put_contents($downFile, "-- Migration DOWN: {$name}\n-- Created: " . date('Y-m-d H:i:s') . "\n\n");
        
        // Recargar migraciones
        $this->loadMigrations();
        
        return $migrationId;
    }

    /**
     * Ejecutar migración específica
     */
    public function runMigration(string $migrationId, string $direction = 'up'): bool
    {
        if (!isset($this->migrations[$migrationId])) {
            throw new \InvalidArgumentException("Migration '{$migrationId}' not found");
        }
        
        $migration = $this->migrations[$migrationId];
        $file = $direction === 'up' ? $migration['up_file'] : $migration['down_file'];
        
        if (!file_exists($file)) {
            throw new \RuntimeException("Migration file not found: {$file}");
        }
        
        $sql = file_get_contents($file);
        if (empty(trim($sql))) {
            throw new \RuntimeException("Migration file is empty: {$file}");
        }
        
        try {
            $this->database->beginTransaction();
            
            // Ejecutar SQL
            $this->executeSql($sql);
            
            // Registrar en tabla de migraciones
            if ($direction === 'up') {
                $this->recordMigration($migrationId);
            } else {
                $this->removeMigrationRecord($migrationId);
            }
            
            $this->database->commit();
            
            return true;
            
        } catch (\Throwable $e) {
            try {
                $this->database->rollback();
            } catch (\Throwable $rollbackError) {
                // Ignorar errores de rollback si no hay conexión
            }
            throw new \RuntimeException("Migration failed: " . $e->getMessage());
        }
    }

    /**
     * Ejecutar todas las migraciones pendientes
     */
    public function runPendingMigrations(): array
    {
        $current = $this->getCurrentMigration();
        $results = [];
        
        foreach ($this->migrations as $id => $migration) {
            if (!$current || $id > $current) {
                try {
                    $this->runMigration($id, 'up');
                    $results[$id] = ['status' => 'success', 'message' => 'Migration executed successfully'];
                } catch (\Throwable $e) {
                    $results[$id] = ['status' => 'error', 'message' => $e->getMessage()];
                    break; // Detener en caso de error
                }
            }
        }
        
        return $results;
    }

    /**
     * Revertir migración específica
     */
    public function rollbackMigration(string $migrationId): bool
    {
        return $this->runMigration($migrationId, 'down');
    }

    /**
     * Revertir última migración
     */
    public function rollbackLastMigration(): bool
    {
        $current = $this->getCurrentMigration();
        
        if (!$current) {
            throw new \RuntimeException("No migrations to rollback");
        }
        
        return $this->rollbackMigration($current);
    }

    /**
     * Ejecutar SQL
     */
    private function executeSql(string $sql): void
    {
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $this->database->query($statement);
            }
        }
    }

    /**
     * Registrar migración en tabla
     */
    private function recordMigration(string $migrationId): void
    {
        try {
            $batch = $this->getNextBatchNumber();
            
            $this->database->insert('migrations', [
                'migration' => $migrationId,
                'batch' => $batch
            ]);
        } catch (\Throwable $e) {
            // Ignorar si no hay base de datos
        }
    }

    /**
     * Eliminar registro de migración
     */
    private function removeMigrationRecord(string $migrationId): void
    {
        try {
            $this->database->delete('migrations', ['migration' => $migrationId]);
        } catch (\Throwable $e) {
            // Ignorar si no hay base de datos
        }
    }

    /**
     * Obtener siguiente número de batch
     */
    private function getNextBatchNumber(): int
    {
        try {
            $result = $this->database->fetchValue("SELECT MAX(batch) FROM migrations");
            return ($result ?: 0) + 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * Obtener historial de migraciones
     */
    public function getMigrationHistory(): array
    {
        try {
            return $this->database->fetchAll("SELECT * FROM migrations ORDER BY batch DESC, executed_at DESC");
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Verificar si migración existe
     */
    public function migrationExists(string $migrationId): bool
    {
        return isset($this->migrations[$migrationId]);
    }

    /**
     * Obtener migraciones pendientes
     */
    public function getPendingMigrations(): array
    {
        $current = $this->getCurrentMigration();
        $pending = [];
        
        foreach ($this->migrations as $id => $migration) {
            if (!$current || $id > $current) {
                $pending[] = $migration;
            }
        }
        
        return $pending;
    }

    /**
     * Obtener migraciones ejecutadas
     */
    public function getExecutedMigrations(): array
    {
        $current = $this->getCurrentMigration();
        $executed = [];
        
        foreach ($this->migrations as $id => $migration) {
            if ($current && $id <= $current) {
                $executed[] = $migration;
            }
        }
        
        return $executed;
    }

    /**
     * Validar migración
     */
    public function validateMigration(string $migrationId): array
    {
        $errors = [];
        
        if (!isset($this->migrations[$migrationId])) {
            $errors[] = "Migration '{$migrationId}' not found";
            return $errors;
        }
        
        $migration = $this->migrations[$migrationId];
        
        if (!file_exists($migration['up_file'])) {
            $errors[] = "UP file not found: {$migration['up_file']}";
        }
        
        if (!file_exists($migration['down_file'])) {
            $errors[] = "DOWN file not found: {$migration['down_file']}";
        }
        
        if (file_exists($migration['up_file'])) {
            $upContent = file_get_contents($migration['up_file']);
            if (empty(trim($upContent))) {
                $errors[] = "UP file is empty";
            }
        }
        
        return $errors;
    }

    /**
     * Limpiar migraciones antiguas
     */
    public function cleanupOldMigrations(int $daysToKeep = 90): int
    {
        $files = glob($this->migrationPath . '/*.sql');
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
        $status = $this->getGeneralStatus();
        
        return [
            'total_migrations' => $status['total'],
            'executed_migrations' => $status['executed'],
            'pending_migrations' => $status['pending'],
            'current_migration' => $status['current'],
            'migration_path' => $this->migrationPath,
            'auto_run' => $this->autoRun
        ];
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'migration_path' => $this->migrationPath,
            'current_migration_file' => $this->currentMigrationFile,
            'migrations_count' => count($this->migrations),
            'auto_run' => $this->autoRun,
            'stats' => $this->getStats()
        ];
    }
}
