<?php

namespace Tero\Storage;

use Tero\Storage\Contracts\StorageInterface;
use Tero\Storage\Exceptions\StorageException;

/**
 * Disk - Representa un disco de storage
 * 
 * @package Tero\Storage
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class Disk
{
    private string $name;
    private StorageInterface $driver;
    private array $config;
    private array $metadata = [];

    public function __construct(string $name, StorageInterface $driver, array $config = [])
    {
        $this->name = $name;
        $this->driver = $driver;
        $this->config = array_merge([
            'root' => '',
            'url' => '',
            'visibility' => 'private',
            'permissions' => 0755
        ], $config);
    }

    /**
     * Obtener nombre del disco
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Obtener driver
     */
    public function getDriver(): StorageInterface
    {
        return $this->driver;
    }

    /**
     * Obtener configuración
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Obtener valor de configuración
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Establecer valor de configuración
     */
    public function setConfigValue(string $key, mixed $value): self
    {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * Obtener metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Establecer metadata
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Obtener valor específico de metadata
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Establecer valor específico de metadata
     */
    public function setMetadataValue(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Verificar si archivo existe
     */
    public function exists(string $path): bool
    {
        return $this->driver->exists($path);
    }

    /**
     * Obtener contenido de archivo
     */
    public function get(string $path): string
    {
        return $this->driver->get($path);
    }

    /**
     * Obtener contenido de archivo como stream
     */
    public function readStream(string $path)
    {
        return $this->driver->readStream($path);
    }

    /**
     * Escribir contenido a archivo
     */
    public function put(string $path, string $contents, array $options = []): bool
    {
        return $this->driver->put($path, $contents, $options);
    }

    /**
     * Escribir stream a archivo
     */
    public function writeStream(string $path, $stream, array $options = []): bool
    {
        return $this->driver->writeStream($path, $stream, $options);
    }

    /**
     * Copiar archivo
     */
    public function copy(string $from, string $to): bool
    {
        return $this->driver->copy($from, $to);
    }

    /**
     * Mover archivo
     */
    public function move(string $from, string $to): bool
    {
        return $this->driver->move($from, $to);
    }

    /**
     * Eliminar archivo
     */
    public function delete(string $path): bool
    {
        return $this->driver->delete($path);
    }

    /**
     * Eliminar múltiples archivos
     */
    public function deleteMultiple(array $paths): array
    {
        return $this->driver->deleteMultiple($paths);
    }

    /**
     * Crear directorio
     */
    public function makeDirectory(string $path): bool
    {
        return $this->driver->makeDirectory($path);
    }

    /**
     * Eliminar directorio
     */
    public function deleteDirectory(string $path): bool
    {
        return $this->driver->deleteDirectory($path);
    }

    /**
     * Obtener lista de archivos
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        return $this->driver->files($directory, $recursive);
    }

    /**
     * Obtener lista de directorios
     */
    public function directories(string $directory = '', bool $recursive = false): array
    {
        return $this->driver->directories($directory, $recursive);
    }

    /**
     * Obtener lista de archivos y directorios
     */
    public function listContents(string $directory = '', bool $recursive = false): array
    {
        return $this->driver->listContents($directory, $recursive);
    }

    /**
     * Obtener tamaño de archivo
     */
    public function size(string $path): int
    {
        return $this->driver->size($path);
    }

    /**
     * Obtener fecha de última modificación
     */
    public function lastModified(string $path): int
    {
        return $this->driver->lastModified($path);
    }

    /**
     * Obtener URL de archivo
     */
    public function url(string $path): string
    {
        $baseUrl = $this->getConfigValue('url');
        
        if (empty($baseUrl)) {
            return $path;
        }
        
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Obtener URL temporal de archivo
     */
    public function temporaryUrl(string $path, int $expiration = 3600): string
    {
        if (method_exists($this->driver, 'temporaryUrl')) {
            return $this->driver->temporaryUrl($path, $expiration);
        }
        
        // Fallback a URL normal
        return $this->url($path);
    }

    /**
     * Establecer visibilidad
     */
    public function setVisibility(string $path, string $visibility): bool
    {
        if (method_exists($this->driver, 'setVisibility')) {
            return $this->driver->setVisibility($path, $visibility);
        }
        
        return true;
    }

    /**
     * Obtener visibilidad
     */
    public function getVisibility(string $path): string
    {
        if (method_exists($this->driver, 'getVisibility')) {
            return $this->driver->getVisibility($path);
        }
        
        return $this->getConfigValue('visibility', 'private');
    }

    /**
     * Verificar si el disco está disponible
     */
    public function isAvailable(): bool
    {
        return $this->driver->isAvailable();
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'name' => $this->name,
            'driver' => get_class($this->driver),
            'config' => $this->config,
            'metadata' => $this->metadata,
            'available' => $this->driver->isAvailable()
        ];
    }
}
