<?php

namespace Tero\Storage;

use Tero\Config\ConfigManager;
use Tero\Storage\Contracts\StorageManagerInterface;
use Tero\Storage\Contracts\StorageInterface;
use Tero\Storage\Exceptions\StorageException;

/**
 * StorageManager - Gestor de storage multi-driver
 * 
 * @package Tero\Storage
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class StorageManager implements StorageManagerInterface
{
    private ConfigManager $config;
    private array $disks = [];
    private array $drivers = [];
    private string $defaultDisk;
    private bool $autoDiscovery = true;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->autoDiscovery = $config->get('STORAGE_AUTO_DISCOVERY', true, 'bool');
        $this->defaultDisk = $config->get('STORAGE_DEFAULT_DISK', 'local');
        
        $this->initializeDrivers();
        $this->initializeDisks();
        
        if ($this->autoDiscovery) {
            $this->discoverDrivers();
        }
    }

    /**
     * Inicializar drivers por defecto
     */
    private function initializeDrivers(): void
    {
        // Driver local
        $this->drivers['local'] = new Drivers\LocalDriver($this->config);
        
        // Driver S3
        $this->drivers['s3'] = new Drivers\S3Driver($this->config);
        
        // Driver FTP
        $this->drivers['ftp'] = new Drivers\FtpDriver($this->config);
        
        // Driver SFTP
        $this->drivers['sftp'] = new Drivers\SftpDriver($this->config);
        
        // Driver Azure
        $this->drivers['azure'] = new Drivers\AzureDriver($this->config);
        
        // Driver Google Cloud
        $this->drivers['gcs'] = new Drivers\GcsDriver($this->config);
    }

    /**
     * Inicializar discos
     */
    private function initializeDisks(): void
    {
        // Disco local
        $this->disks['local'] = new Disk('local', $this->drivers['local'], [
            'root' => $this->config->get('STORAGE_LOCAL_ROOT', 'storage/app'),
            'url' => $this->config->get('STORAGE_LOCAL_URL', '/storage'),
            'visibility' => 'public'
        ]);
        
        // Disco público
        $this->disks['public'] = new Disk('public', $this->drivers['local'], [
            'root' => $this->config->get('STORAGE_PUBLIC_ROOT', 'storage/app/public'),
            'url' => $this->config->get('STORAGE_PUBLIC_URL', '/storage'),
            'visibility' => 'public'
        ]);
        
        // Disco temporal
        $this->disks['temp'] = new Disk('temp', $this->drivers['local'], [
            'root' => $this->config->get('STORAGE_TEMP_ROOT', 'storage/temp'),
            'url' => $this->config->get('STORAGE_TEMP_URL', '/temp'),
            'visibility' => 'private'
        ]);
    }

    /**
     * Descubrir drivers automáticamente
     */
    private function discoverDrivers(): void
    {
        $driversPath = $this->config->get('STORAGE_DRIVERS_PATH', 'app/StorageDrivers');
        
        if (!is_dir($driversPath)) {
            return;
        }
        
        $files = glob($driversPath . '/*.php');
        
        foreach ($files as $file) {
            $className = basename($file, '.php');
            $fullClassName = "App\\StorageDrivers\\{$className}";
            
            if (class_exists($fullClassName)) {
                $this->registerDriverClass($fullClassName);
            }
        }
    }

    /**
     * Registrar clase de driver
     */
    private function registerDriverClass(string $className): void
    {
        if (!class_exists($className)) {
            return;
        }
        
        $reflection = new \ReflectionClass($className);
        
        if (!$reflection->implementsInterface(StorageInterface::class)) {
            return;
        }
        
        $instance = new $className($this->config);
        $driverName = $this->extractDriverName($className);
        
        $this->drivers[$driverName] = $instance;
    }

    /**
     * Extraer nombre del driver de la clase
     */
    private function extractDriverName(string $className): string
    {
        // App\StorageDrivers\DropboxDriver -> dropbox
        $parts = explode('\\', $className);
        $className = end($parts);
        
        // DropboxDriver -> dropbox
        $driverName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className));
        $driverName = str_replace('_driver', '', $driverName);
        
        return $driverName;
    }

    /**
     * Registrar driver personalizado
     */
    public function registerDriver(string $name, StorageInterface $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    /**
     * Registrar disco personalizado
     */
    public function registerDisk(string $name, string $driver, array $config = []): void
    {
        if (!isset($this->drivers[$driver])) {
            throw new StorageException("Storage driver '{$driver}' not found");
        }
        
        $this->disks[$name] = new Disk($name, $this->drivers[$driver], $config);
    }

    /**
     * Obtener disco
     */
    public function disk(string $name = null): Disk
    {
        $name = $name ?? $this->defaultDisk;
        
        if (!isset($this->disks[$name])) {
            throw new StorageException("Storage disk '{$name}' not found");
        }
        
        return $this->disks[$name];
    }

    /**
     * Obtener driver
     */
    public function driver(string $name): StorageInterface
    {
        if (!isset($this->drivers[$name])) {
            throw new StorageException("Storage driver '{$name}' not found");
        }
        
        return $this->drivers[$name];
    }

    /**
     * Obtener todos los discos
     */
    public function getDisks(): array
    {
        return $this->disks;
    }

    /**
     * Obtener todos los drivers
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Obtener discos disponibles
     */
    public function getAvailableDisks(): array
    {
        return array_keys($this->disks);
    }

    /**
     * Obtener drivers disponibles
     */
    public function getAvailableDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Verificar si disco existe
     */
    public function hasDisk(string $name): bool
    {
        return isset($this->disks[$name]);
    }

    /**
     * Verificar si driver existe
     */
    public function hasDriver(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'auto_discovery' => $this->autoDiscovery,
            'default_disk' => $this->defaultDisk,
            'disks_count' => count($this->disks),
            'drivers_count' => count($this->drivers),
            'available_disks' => array_keys($this->disks),
            'available_drivers' => array_keys($this->drivers)
        ];
    }
}
