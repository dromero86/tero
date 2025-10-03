<?php

namespace Tero\Storage\Drivers;

use Tero\Config\ConfigManager;
use Tero\Storage\Contracts\StorageInterface;
use Tero\Storage\Exceptions\StorageException;

/**
 * GcsDriver - Driver de storage para Google Cloud Storage
 * 
 * @package Tero\Storage\Drivers
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class GcsDriver implements StorageInterface
{
    private ConfigManager $config;
    private ?\Google\Cloud\Storage\StorageClient $client = null;
    private ?\Google\Cloud\Storage\Bucket $bucket = null;
    private string $projectId;
    private string $keyFile;
    private string $bucketName;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->projectId = $config->get('GCS_PROJECT_ID', '');
        $this->keyFile = $config->get('GCS_KEY_FILE', '');
        $this->bucketName = $config->get('GCS_BUCKET', '');
        
        $this->initializeClient();
    }

    /**
     * Inicializar cliente GCS
     */
    private function initializeClient(): void
    {
        if (empty($this->projectId) || empty($this->bucketName)) {
            return;
        }
        
        try {
            $config = [
                'projectId' => $this->projectId
            ];
            
            if (!empty($this->keyFile)) {
                $config['keyFile'] = $this->keyFile;
            }
            
            $this->client = new \Google\Cloud\Storage\StorageClient($config);
            $this->bucket = $this->client->bucket($this->bucketName);
        } catch (\Throwable $e) {
            error_log("Failed to initialize GCS client: " . $e->getMessage());
            $this->client = null;
            $this->bucket = null;
        }
    }

    /**
     * Verificar si archivo existe
     */
    public function exists(string $path): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            $object = $this->bucket->object($path);
            return $object->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Obtener contenido de archivo
     */
    public function get(string $path): string
    {
        if (!$this->isAvailable()) {
            throw new StorageException("GCS driver is not available");
        }
        
        try {
            $object = $this->bucket->object($path);
            return $object->downloadAsString();
        } catch (\Throwable $e) {
            throw new StorageException("GCS error reading file: " . $e->getMessage());
        }
    }

    /**
     * Obtener contenido de archivo como stream
     */
    public function readStream(string $path)
    {
        if (!$this->isAvailable()) {
            throw new StorageException("GCS driver is not available");
        }
        
        try {
            $object = $this->bucket->object($path);
            return $object->downloadAsStream();
        } catch (\Throwable $e) {
            throw new StorageException("GCS error reading stream: " . $e->getMessage());
        }
    }

    /**
     * Escribir contenido a archivo
     */
    public function put(string $path, string $contents, array $options = []): bool
    {
        if (!$this->isAvailable()) {
            throw new StorageException("GCS driver is not available");
        }
        
        try {
            $object = $this->bucket->upload($contents, [
                'name' => $path,
                'metadata' => $options['metadata'] ?? []
            ]);
            
            return $object !== null;
        } catch (\Throwable $e) {
            throw new StorageException("GCS error writing file: " . $e->getMessage());
        }
    }

    /**
     * Escribir stream a archivo
     */
    public function writeStream(string $path, $stream, array $options = []): bool
    {
        if (!$this->isAvailable()) {
            throw new StorageException("GCS driver is not available");
        }
        
        try {
            $object = $this->bucket->upload($stream, [
                'name' => $path,
                'metadata' => $options['metadata'] ?? []
            ]);
            
            return $object !== null;
        } catch (\Throwable $e) {
            throw new StorageException("GCS error writing stream: " . $e->getMessage());
        }
    }

    /**
     * Copiar archivo
     */
    public function copy(string $from, string $to): bool
    {
        if (!$this->isAvailable()) {
            throw new StorageException("GCS driver is not available");
        }
        
        try {
            $sourceObject = $this->bucket->object($from);
            $sourceObject->copy($this->bucket, ['name' => $to]);
            return true;
        } catch (\Throwable $e) {
            throw new StorageException("GCS error copying file: " . $e->getMessage());
        }
    }

    /**
     * Mover archivo
     */
    public function move(string $from, string $to): bool
    {
        if ($this->copy($from, $to)) {
            return $this->delete($from);
        }
        return false;
    }

    /**
     * Eliminar archivo
     */
    public function delete(string $path): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            $object = $this->bucket->object($path);
            $object->delete();
            return true;
        } catch (\Throwable $e) {
            throw new StorageException("GCS error deleting file: " . $e->getMessage());
        }
    }

    /**
     * Eliminar múltiples archivos
     */
    public function deleteMultiple(array $paths): array
    {
        if (!$this->isAvailable()) {
            return [];
        }
        
        $deleted = [];
        
        foreach ($paths as $path) {
            try {
                $object = $this->bucket->object($path);
                $object->delete();
                $deleted[] = $path;
            } catch (\Throwable $e) {
                // Log error but continue with other files
                error_log("GCS error deleting file {$path}: " . $e->getMessage());
            }
        }
        
        return $deleted;
    }

    /**
     * Crear directorio
     */
    public function makeDirectory(string $path): bool
    {
        // GCS no tiene directorios reales
        // Crear un objeto vacío para simular un directorio
        if (!$this->isAvailable()) {
            return false;
        }
        
        $path = rtrim($path, '/') . '/';
        
        try {
            $this->bucket->upload('', [
                'name' => $path,
                'metadata' => ['is_directory' => 'true']
            ]);
            return true;
        } catch (\Throwable $e) {
            throw new StorageException("GCS error creating directory: " . $e->getMessage());
        }
    }

    /**
     * Eliminar directorio
     */
    public function deleteDirectory(string $path): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        $path = rtrim($path, '/') . '/';
        
        try {
            $objects = $this->bucket->objects(['prefix' => $path]);
            
            foreach ($objects as $object) {
                $object->delete();
            }
            
            return true;
        } catch (\Throwable $e) {
            throw new StorageException("GCS error deleting directory: " . $e->getMessage());
        }
    }

    /**
     * Obtener lista de archivos
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        if (!$this->isAvailable()) {
            return [];
        }
        
        $files = [];
        
        try {
            $options = ['prefix' => $directory];
            
            if (!$recursive) {
                $options['delimiter'] = '/';
            }
            
            $objects = $this->bucket->objects($options);
            
            foreach ($objects as $object) {
                if (!$recursive && strpos($object->name(), '/', strlen($directory)) !== false) {
                    continue;
                }
                
                $files[] = $object->name();
            }
        } catch (\Throwable $e) {
            throw new StorageException("GCS error listing files: " . $e->getMessage());
        }
        
        return $files;
    }

    /**
     * Obtener lista de directorios
     */
    public function directories(string $directory = '', bool $recursive = false): array
    {
        if (!$this->isAvailable()) {
            return [];
        }
        
        $directories = [];
        
        try {
            $options = [
                'prefix' => $directory,
                'delimiter' => '/'
            ];
            
            $objects = $this->bucket->objects($options);
            
            foreach ($objects as $object) {
                $name = $object->name();
                
                if (str_ends_with($name, '/')) {
                    $directories[] = rtrim($name, '/');
                }
            }
        } catch (\Throwable $e) {
            throw new StorageException("GCS error listing directories: " . $e->getMessage());
        }
        
        return $directories;
    }

    /**
     * Obtener lista de archivos y directorios
     */
    public function listContents(string $directory = '', bool $recursive = false): array
    {
        if (!$this->isAvailable()) {
            return [];
        }
        
        $contents = [];
        
        try {
            $options = ['prefix' => $directory];
            
            if (!$recursive) {
                $options['delimiter'] = '/';
            }
            
            $objects = $this->bucket->objects($options);
            
            foreach ($objects as $object) {
                $name = $object->name();
                
                if (str_ends_with($name, '/')) {
                    $contents[] = [
                        'type' => 'dir',
                        'path' => rtrim($name, '/'),
                        'size' => 0,
                        'timestamp' => $object->info()['timeCreated'] ?? 0,
                        'visibility' => 'private'
                    ];
                } else {
                    $contents[] = [
                        'type' => 'file',
                        'path' => $name,
                        'size' => $object->info()['size'] ?? 0,
                        'timestamp' => $object->info()['timeCreated'] ?? 0,
                        'visibility' => 'private'
                    ];
                }
            }
        } catch (\Throwable $e) {
            throw new StorageException("GCS error listing contents: " . $e->getMessage());
        }
        
        return $contents;
    }

    /**
     * Obtener tamaño de archivo
     */
    public function size(string $path): int
    {
        if (!$this->isAvailable()) {
            throw new StorageException("GCS driver is not available");
        }
        
        try {
            $object = $this->bucket->object($path);
            return $object->info()['size'] ?? 0;
        } catch (\Throwable $e) {
            throw new StorageException("GCS error getting file size: " . $e->getMessage());
        }
    }

    /**
     * Obtener fecha de última modificación
     */
    public function lastModified(string $path): int
    {
        if (!$this->isAvailable()) {
            throw new StorageException("GCS driver is not available");
        }
        
        try {
            $object = $this->bucket->object($path);
            $info = $object->info();
            return strtotime($info['timeCreated'] ?? 'now');
        } catch (\Throwable $e) {
            throw new StorageException("GCS error getting last modified: " . $e->getMessage());
        }
    }

    /**
     * Obtener URL temporal
     */
    public function temporaryUrl(string $path, int $expiration = 3600): string
    {
        if (!$this->isAvailable()) {
            throw new StorageException("GCS driver is not available");
        }
        
        try {
            $object = $this->bucket->object($path);
            return $object->signedUrl(new \DateTime("+{$expiration} seconds"));
        } catch (\Throwable $e) {
            throw new StorageException("GCS error creating temporary URL: " . $e->getMessage());
        }
    }

    /**
     * Verificar si el driver está disponible
     */
    public function isAvailable(): bool
    {
        return $this->client !== null && $this->bucket !== null;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'driver' => 'gcs',
            'project_id' => $this->projectId,
            'bucket' => $this->bucketName,
            'key_file' => $this->keyFile,
            'available' => $this->isAvailable(),
            'client_initialized' => $this->client !== null,
            'bucket_initialized' => $this->bucket !== null,
            'credentials_configured' => !empty($this->projectId)
        ];
    }
}
