<?php

namespace Tero\Storage\Drivers;

use Tero\Config\ConfigManager;
use Tero\Storage\Contracts\StorageInterface;
use Tero\Storage\Exceptions\StorageException;

/**
 * AzureDriver - Driver de storage para Azure Blob Storage
 * 
 * @package Tero\Storage\Drivers
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class AzureDriver implements StorageInterface
{
    private ConfigManager $config;
    private ?\MicrosoftAzure\Storage\Blob\BlobRestProxy $client = null;
    private string $accountName;
    private string $accountKey;
    private string $container;
    private string $endpoint;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->accountName = $config->get('AZURE_ACCOUNT_NAME', '');
        $this->accountKey = $config->get('AZURE_ACCOUNT_KEY', '');
        $this->container = $config->get('AZURE_CONTAINER', '');
        $this->endpoint = $config->get('AZURE_ENDPOINT', '');
        
        $this->initializeClient();
    }

    /**
     * Inicializar cliente Azure
     */
    private function initializeClient(): void
    {
        if (empty($this->accountName) || empty($this->accountKey) || empty($this->container)) {
            return;
        }
        
        try {
            $connectionString = "DefaultEndpointsProtocol=https;AccountName={$this->accountName};AccountKey={$this->accountKey}";
            
            if (!empty($this->endpoint)) {
                $connectionString .= ";EndpointSuffix={$this->endpoint}";
            }
            
            $this->client = \MicrosoftAzure\Storage\Blob\BlobRestProxy::createBlobService($connectionString);
        } catch (\Throwable $e) {
            error_log("Failed to initialize Azure client: " . $e->getMessage());
            $this->client = null;
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
            $this->client->getBlobMetadata($this->container, $path);
            return true;
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            if ($e->getCode() === 404) {
                return false;
            }
            throw new StorageException("Azure error checking file existence: " . $e->getMessage());
        }
    }

    /**
     * Obtener contenido de archivo
     */
    public function get(string $path): string
    {
        if (!$this->isAvailable()) {
            throw new StorageException("Azure driver is not available");
        }
        
        try {
            $blob = $this->client->getBlob($this->container, $path);
            return stream_get_contents($blob->getContentStream());
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error reading file: " . $e->getMessage());
        }
    }

    /**
     * Obtener contenido de archivo como stream
     */
    public function readStream(string $path)
    {
        if (!$this->isAvailable()) {
            throw new StorageException("Azure driver is not available");
        }
        
        try {
            $blob = $this->client->getBlob($this->container, $path);
            return $blob->getContentStream();
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error reading stream: " . $e->getMessage());
        }
    }

    /**
     * Escribir contenido a archivo
     */
    public function put(string $path, string $contents, array $options = []): bool
    {
        if (!$this->isAvailable()) {
            throw new StorageException("Azure driver is not available");
        }
        
        try {
            $this->client->createBlockBlob($this->container, $path, $contents);
            return true;
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error writing file: " . $e->getMessage());
        }
    }

    /**
     * Escribir stream a archivo
     */
    public function writeStream(string $path, $stream, array $options = []): bool
    {
        if (!$this->isAvailable()) {
            throw new StorageException("Azure driver is not available");
        }
        
        try {
            $this->client->createBlockBlob($this->container, $path, $stream);
            return true;
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error writing stream: " . $e->getMessage());
        }
    }

    /**
     * Copiar archivo
     */
    public function copy(string $from, string $to): bool
    {
        if (!$this->isAvailable()) {
            throw new StorageException("Azure driver is not available");
        }
        
        try {
            $sourceUrl = $this->client->getBlobUrl($this->container, $from);
            $this->client->copyBlob($this->container, $to, $sourceUrl);
            return true;
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error copying file: " . $e->getMessage());
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
            $this->client->deleteBlob($this->container, $path);
            return true;
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error deleting file: " . $e->getMessage());
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
                $this->client->deleteBlob($this->container, $path);
                $deleted[] = $path;
            } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
                // Log error but continue with other files
                error_log("Azure error deleting file {$path}: " . $e->getMessage());
            }
        }
        
        return $deleted;
    }

    /**
     * Crear directorio
     */
    public function makeDirectory(string $path): bool
    {
        // Azure Blob Storage no tiene directorios reales
        // Crear un blob vacío para simular un directorio
        if (!$this->isAvailable()) {
            return false;
        }
        
        $path = rtrim($path, '/') . '/';
        
        try {
            $this->client->createBlockBlob($this->container, $path, '');
            return true;
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error creating directory: " . $e->getMessage());
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
            $blobs = $this->client->listBlobs($this->container, $path);
            
            foreach ($blobs->getBlobs() as $blob) {
                $this->client->deleteBlob($this->container, $blob->getName());
            }
            
            return true;
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error deleting directory: " . $e->getMessage());
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
            $blobs = $this->client->listBlobs($this->container, $directory);
            
            foreach ($blobs->getBlobs() as $blob) {
                if (!$recursive && strpos($blob->getName(), '/', strlen($directory)) !== false) {
                    continue;
                }
                
                $files[] = $blob->getName();
            }
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error listing files: " . $e->getMessage());
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
            $blobs = $this->client->listBlobs($this->container, $directory);
            
            foreach ($blobs->getBlobs() as $blob) {
                $name = $blob->getName();
                
                if (str_ends_with($name, '/')) {
                    $directories[] = rtrim($name, '/');
                }
            }
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error listing directories: " . $e->getMessage());
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
            $blobs = $this->client->listBlobs($this->container, $directory);
            
            foreach ($blobs->getBlobs() as $blob) {
                $name = $blob->getName();
                
                if (str_ends_with($name, '/')) {
                    $contents[] = [
                        'type' => 'dir',
                        'path' => rtrim($name, '/'),
                        'size' => 0,
                        'timestamp' => $blob->getProperties()->getLastModified()->getTimestamp(),
                        'visibility' => 'private'
                    ];
                } else {
                    $contents[] = [
                        'type' => 'file',
                        'path' => $name,
                        'size' => $blob->getProperties()->getContentLength(),
                        'timestamp' => $blob->getProperties()->getLastModified()->getTimestamp(),
                        'visibility' => 'private'
                    ];
                }
            }
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error listing contents: " . $e->getMessage());
        }
        
        return $contents;
    }

    /**
     * Obtener tamaño de archivo
     */
    public function size(string $path): int
    {
        if (!$this->isAvailable()) {
            throw new StorageException("Azure driver is not available");
        }
        
        try {
            $blob = $this->client->getBlobProperties($this->container, $path);
            return $blob->getProperties()->getContentLength();
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error getting file size: " . $e->getMessage());
        }
    }

    /**
     * Obtener fecha de última modificación
     */
    public function lastModified(string $path): int
    {
        if (!$this->isAvailable()) {
            throw new StorageException("Azure driver is not available");
        }
        
        try {
            $blob = $this->client->getBlobProperties($this->container, $path);
            return $blob->getProperties()->getLastModified()->getTimestamp();
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error getting last modified: " . $e->getMessage());
        }
    }

    /**
     * Obtener URL temporal
     */
    public function temporaryUrl(string $path, int $expiration = 3600): string
    {
        if (!$this->isAvailable()) {
            throw new StorageException("Azure driver is not available");
        }
        
        try {
            $sasToken = $this->client->generateBlobServiceSharedAccessSignatureToken(
                $this->container,
                $path,
                'r',
                new \DateTime("+{$expiration} seconds")
            );
            
            return $this->client->getBlobUrl($this->container, $path) . '?' . $sasToken;
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            throw new StorageException("Azure error creating temporary URL: " . $e->getMessage());
        }
    }

    /**
     * Verificar si el driver está disponible
     */
    public function isAvailable(): bool
    {
        return $this->client !== null && !empty($this->container);
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'driver' => 'azure',
            'account_name' => $this->accountName,
            'container' => $this->container,
            'endpoint' => $this->endpoint,
            'available' => $this->isAvailable(),
            'client_initialized' => $this->client !== null,
            'credentials_configured' => !empty($this->accountName) && !empty($this->accountKey)
        ];
    }
}
