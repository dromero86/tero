<?php

namespace Tero\Core\Console;

use Tero\Config\ConfigManager;

/**
 * ConsoleManager - Gestor de comandos de consola
 * 
 * @package Tero\Core\Console
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class ConsoleManager
{
    private ConfigManager $config;
    private array $systemCommands = [];
    private array $customCommands = [];

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->registerDefaultCommands();
    }

    /**
     * Registrar comando de sistema
     */
    public function registerSystemCommand(string $command, callable $handler, array $options = []): void
    {
        $this->systemCommands[$command] = [
            'command' => $command,
            'handler' => $handler,
            'description' => $options['description'] ?? '',
            'arguments' => $options['arguments'] ?? [],
            'options' => $options['options'] ?? [],
            'type' => 'system'
        ];
    }

    /**
     * Registrar comando personalizado
     */
    public function registerCustomCommand(string $command, callable $handler, array $options = []): void
    {
        $this->customCommands[$command] = [
            'command' => $command,
            'handler' => $handler,
            'description' => $options['description'] ?? '',
            'arguments' => $options['arguments'] ?? [],
            'options' => $options['options'] ?? [],
            'type' => 'custom'
        ];
    }

    /**
     * Ejecutar comando
     */
    public function executeCommand(string $command, array $args = []): int
    {
        // Buscar en comandos de sistema
        if (isset($this->systemCommands[$command])) {
            return $this->executeSystemCommand($command, $args);
        }

        // Buscar en comandos personalizados
        if (isset($this->customCommands[$command])) {
            return $this->executeCustomCommand($command, $args);
        }

        // Comando no encontrado
        $this->showCommandNotFound($command);
        return 1;
    }

    /**
     * Ejecutar comando de sistema
     */
    private function executeSystemCommand(string $command, array $args): int
    {
        $commandData = $this->systemCommands[$command];
        $handler = $commandData['handler'];

        try {
            if (is_callable($handler)) {
                return $handler($args);
            }
        } catch (\Throwable $e) {
            echo "Error executing system command '$command': " . $e->getMessage() . "\n";
            return 1;
        }

        return 0;
    }

    /**
     * Ejecutar comando personalizado
     */
    private function executeCustomCommand(string $command, array $args): int
    {
        $commandData = $this->customCommands[$command];
        $handler = $commandData['handler'];

        try {
            if (is_callable($handler)) {
                return $handler($args);
            }
        } catch (\Throwable $e) {
            echo "Error executing custom command '$command': " . $e->getMessage() . "\n";
            return 1;
        }

        return 0;
    }

    /**
     * Mostrar comando no encontrado
     */
    private function showCommandNotFound(string $command): void
    {
        echo "Command '$command' not found.\n\n";
        echo "Available commands:\n";
        $this->showAvailableCommands();
    }

    /**
     * Mostrar comandos disponibles
     */
    public function showAvailableCommands(): void
    {
        echo "System Commands:\n";
        foreach ($this->systemCommands as $command => $data) {
            echo "  tero $command" . ($data['description'] ? " - {$data['description']}" : '') . "\n";
        }

        if (!empty($this->customCommands)) {
            echo "\nCustom Commands:\n";
            foreach ($this->customCommands as $command => $data) {
                echo "  tero $command" . ($data['description'] ? " - {$data['description']}" : '') . "\n";
            }
        }
    }

    /**
     * Registrar comandos por defecto
     */
    private function registerDefaultCommands(): void
    {
        // Comando help
        $this->registerSystemCommand('help', function($args) {
            $this->showHelp();
            return 0;
        }, [
            'description' => 'Show help information'
        ]);

        // Comando serve
        $this->registerSystemCommand('serve', function($args) {
            return $this->serveCommand($args);
        }, [
            'description' => 'Start development server',
            'arguments' => ['host', 'port']
        ]);

        // Comando migrate
        $this->registerSystemCommand('migrate', function($args) {
            return $this->migrateCommand($args);
        }, [
            'description' => 'Database migrations',
            'arguments' => ['action']
        ]);

        // Comando cron
        $this->registerSystemCommand('cron', function($args) {
            return $this->cronCommand($args);
        }, [
            'description' => 'Cron jobs management',
            'arguments' => ['action']
        ]);

        // Comando cache:clear
        $this->registerSystemCommand('cache:clear', function($args) {
            return $this->cacheClearCommand($args);
        }, [
            'description' => 'Clear application cache'
        ]);

        // Comando route:list
        $this->registerSystemCommand('route:list', function($args) {
            return $this->routeListCommand($args);
        }, [
            'description' => 'List all registered routes'
        ]);
    }

    /**
     * Comando help
     */
    private function showHelp(): void
    {
        echo "Tero Framework Console\n";
        echo "Version: " . \Tero\Core\Core::VERSION . "\n\n";
        echo "Usage: tero <command> [arguments] [options]\n\n";
        $this->showAvailableCommands();
    }

    /**
     * Comando serve
     */
    private function serveCommand(array $args): int
    {
        $host = $args[0] ?? '127.0.0.1';
        $port = $args[1] ?? '8000';
        
        echo "Starting Tero development server...\n";
        echo "Server running at http://$host:$port\n";
        echo "Press Ctrl+C to stop the server.\n\n";
        
        $command = "php -S $host:$port -t public/";
        passthru($command);
        
        return 0;
    }

    /**
     * Comando migrate
     */
    private function migrateCommand(array $args): int
    {
        $action = $args[0] ?? 'help';
        
        switch ($action) {
            case 'make':
                $name = $args[1] ?? 'migration';
                return $this->makeMigration($name);
                
            case 'run':
                return $this->runMigrations();
                
            case 'rollback':
                return $this->rollbackMigrations();
                
            case 'status':
                return $this->showMigrationStatus();
                
            default:
                echo "Usage: tero migrate {make|run|rollback|status}\n";
                return 1;
        }
    }

    /**
     * Crear migración
     */
    private function makeMigration(string $name): int
    {
        $timestamp = date('YmdHis');
        $className = 'Migration_' . $timestamp . '_' . $name;
        $filename = $timestamp . '_' . $name . '.php';
        $path = $this->config->get('MIGRATION_PATH', 'storage/migrations');
        
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        
        $content = "<?php

namespace Tero\\Database\\Migrations;

use Tero\\Database\\Migration;

class $className extends Migration
{
    public function up()
    {
        // Migration code here
    }
    
    public function down()
    {
        // Rollback code here
    }
}";
        
        file_put_contents("$path/$filename", $content);
        echo "Migration created: $filename\n";
        
        return 0;
    }

    /**
     * Ejecutar migraciones
     */
    private function runMigrations(): int
    {
        echo "Running migrations...\n";
        // Implementar lógica de migraciones
        return 0;
    }

    /**
     * Revertir migraciones
     */
    private function rollbackMigrations(): int
    {
        echo "Rolling back migrations...\n";
        // Implementar lógica de rollback
        return 0;
    }

    /**
     * Mostrar estado de migraciones
     */
    private function showMigrationStatus(): int
    {
        echo "Migration Status:\n";
        // Implementar lógica de estado
        return 0;
    }

    /**
     * Comando cron
     */
    private function cronCommand(array $args): int
    {
        $action = $args[0] ?? 'help';
        
        switch ($action) {
            case 'list':
                return $this->listCronJobs();
                
            case 'create':
                return $this->createCronJob($args);
                
            case 'run':
                return $this->runCronJobs();
                
            default:
                echo "Usage: tero cron {list|create|run}\n";
                return 1;
        }
    }

    /**
     * Listar cron jobs
     */
    private function listCronJobs(): int
    {
        echo "Cron Jobs:\n";
        // Implementar lógica de listado
        return 0;
    }

    /**
     * Crear cron job
     */
    private function createCronJob(array $args): int
    {
        echo "Creating cron job...\n";
        // Implementar lógica de creación
        return 0;
    }

    /**
     * Ejecutar cron jobs
     */
    private function runCronJobs(): int
    {
        echo "Running cron jobs...\n";
        // Implementar lógica de ejecución
        return 0;
    }

    /**
     * Comando cache:clear
     */
    private function cacheClearCommand(array $args): int
    {
        echo "Clearing cache...\n";
        
        $cachePath = $this->config->get('CACHE_PATH', 'storage/cache');
        
        if (is_dir($cachePath)) {
            $this->clearDirectory($cachePath);
            echo "Cache cleared successfully.\n";
        } else {
            echo "Cache directory not found.\n";
        }
        
        return 0;
    }

    /**
     * Limpiar directorio
     */
    private function clearDirectory(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
    }

    /**
     * Comando route:list
     */
    private function routeListCommand(array $args): int
    {
        echo "Registered Routes:\n";
        // Implementar lógica de listado de rutas
        return 0;
    }

    /**
     * Obtener comandos de sistema
     */
    public function getSystemCommands(): array
    {
        return $this->systemCommands;
    }

    /**
     * Obtener comandos personalizados
     */
    public function getCustomCommands(): array
    {
        return $this->customCommands;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'system_commands' => array_keys($this->systemCommands),
            'custom_commands' => array_keys($this->customCommands),
            'total_commands' => count($this->systemCommands) + count($this->customCommands)
        ];
    }
}
