<?php

namespace Tero\Storage\Drivers;

use Tero\Config\ConfigManager;
use Tero\Storage\Contracts\StorageInterface;
use Tero\Storage\Exceptions\StorageException;

/**
 * LocalDriver - Driver de storage local
 * 
 * @package Tero\Storage\Drivers
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class LocalDriver implements StorageInterface
{
    private ConfigManager $config;
    private string $root;
    private int $permissions;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->root = $config->get('STORAGE_LOCAL_ROOT', 'storage/app');
        $this->permissions = (int) $config->get('STORAGE_LOCAL_PERMISSIONS', '0755', 'octal');
        
        $this->ensureRootDirectory();
    }

    /**
     * Asegurar que el directorio raíz existe
     */
    private function ensureRootDirectory(): void
    {
        if (!is_dir($this->root)) {
            mkdir($this->root, $this->permissions, true);
        }
    }

    /**
     * Obtener ruta completa
     */
    private function getFullPath(string $path): string
    {
        $fullPath = $this->root . '/' . ltrim($path, '/');
        
        // Normalizar la ruta
        $fullPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $fullPath);
        
        // Prevenir directory traversal básico
        if (str_contains($path, '..') || str_contains($path, '~')) {
            throw new StorageException("Path outside root directory: {$path}");
        }
        
        return $fullPath;
    }

    /**
     * Verificar si archivo existe
     */
    public function exists(string $path): bool
    {
        return file_exists($this->getFullPath($path));
    }

    /**
     * Obtener contenido de archivo
     */
    public function get(string $path): string
    {
        $fullPath = $this->getFullPath($path);
        
        if (!file_exists($fullPath)) {
            throw new StorageException("File not found: {$path}");
        }
        
        $content = file_get_contents($fullPath);
        
        if ($content === false) {
            throw new StorageException("Failed to read file: {$path}");
        }
        
        return $content;
    }

    /**
     * Obtener contenido de archivo como stream
     */
    public function readStream(string $path)
    {
        $fullPath = $this->getFullPath($path);
        
        if (!file_exists($fullPath)) {
            throw new StorageException("File not found: {$path}");
        }
        
        $stream = fopen($fullPath, 'rb');
        
        if ($stream === false) {
            throw new StorageException("Failed to open file for reading: {$path}");
        }
        
        return $stream;
    }

    /**
     * Escribir contenido a archivo
     */
    public function put(string $path, string $contents, array $options = []): bool
    {
        $fullPath = $this->getFullPath($path);
        $directory = dirname($fullPath);
        
        if (!is_dir($directory)) {
            mkdir($directory, $this->permissions, true);
        }
        
        $result = file_put_contents($fullPath, $contents, LOCK_EX);
        
        if ($result === false) {
            throw new StorageException("Failed to write file: {$path}");
        }
        
        // Establecer permisos
        $permissions = $options['permissions'] ?? $this->permissions;
        chmod($fullPath, $permissions);
        
        return true;
    }

    /**
     * Escribir stream a archivo
     */
    public function writeStream(string $path, $stream, array $options = []): bool
    {
        $fullPath = $this->getFullPath($path);
        $directory = dirname($fullPath);
        
        if (!is_dir($directory)) {
            mkdir($directory, $this->permissions, true);
        }
        
        $destStream = fopen($fullPath, 'wb');
        
        if ($destStream === false) {
            throw new StorageException("Failed to open file for writing: {$path}");
        }
        
        $result = stream_copy_to_stream($stream, $destStream);
        fclose($destStream);
        
        if ($result === false) {
            throw new StorageException("Failed to write stream to file: {$path}");
        }
        
        // Establecer permisos
        $permissions = $options['permissions'] ?? $this->permissions;
        chmod($fullPath, $permissions);
        
        return true;
    }

    /**
     * Copiar archivo
     */
    public function copy(string $from, string $to): bool
    {
        $fromPath = $this->getFullPath($from);
        $toPath = $this->getFullPath($to);
        
        if (!file_exists($fromPath)) {
            throw new StorageException("Source file not found: {$from}");
        }
        
        $directory = dirname($toPath);
        
        if (!is_dir($directory)) {
            mkdir($directory, $this->permissions, true);
        }
        
        $result = copy($fromPath, $toPath);
        
        if ($result === false) {
            throw new StorageException("Failed to copy file from {$from} to {$to}");
        }
        
        return true;
    }

    /**
     * Mover archivo
     */
    public function move(string $from, string $to): bool
    {
        $fromPath = $this->getFullPath($from);
        $toPath = $this->getFullPath($to);
        
        if (!file_exists($fromPath)) {
            throw new StorageException("Source file not found: {$from}");
        }
        
        $directory = dirname($toPath);
        
        if (!is_dir($directory)) {
            mkdir($directory, $this->permissions, true);
        }
        
        $result = rename($fromPath, $toPath);
        
        if ($result === false) {
            throw new StorageException("Failed to move file from {$from} to {$to}");
        }
        
        return true;
    }

    /**
     * Eliminar archivo
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->getFullPath($path);
        
        if (!file_exists($fullPath)) {
            return false;
        }
        
        $result = unlink($fullPath);
        
        if ($result === false) {
            throw new StorageException("Failed to delete file: {$path}");
        }
        
        return true;
    }

    /**
     * Eliminar múltiples archivos
     */
    public function deleteMultiple(array $paths): array
    {
        $deleted = [];
        
        foreach ($paths as $path) {
            if ($this->delete($path)) {
                $deleted[] = $path;
            }
        }
        
        return $deleted;
    }

    /**
     * Crear directorio
     */
    public function makeDirectory(string $path): bool
    {
        $fullPath = $this->getFullPath($path);
        
        if (is_dir($fullPath)) {
            return true;
        }
        
        $result = mkdir($fullPath, $this->permissions, true);
        
        if ($result === false) {
            throw new StorageException("Failed to create directory: {$path}");
        }
        
        return true;
    }

    /**
     * Eliminar directorio
     */
    public function deleteDirectory(string $path): bool
    {
        $fullPath = $this->getFullPath($path);
        
        if (!is_dir($fullPath)) {
            return false;
        }
        
        $result = $this->removeDirectory($fullPath);
        
        if ($result === false) {
            throw new StorageException("Failed to delete directory: {$path}");
        }
        
        return true;
    }

    /**
     * Eliminar directorio recursivamente
     */
    private function removeDirectory(string $directory): bool
    {
        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $filePath = $directory . '/' . $file;
            
            if (is_dir($filePath)) {
                $this->removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        
        return rmdir($directory);
    }

    /**
     * Obtener lista de archivos
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $fullPath = $this->getFullPath($directory);
        
        if (!is_dir($fullPath)) {
            return [];
        }
        
        $files = [];
        $iterator = $recursive ? 
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fullPath)) :
            new \DirectoryIterator($fullPath);
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($this->root . '/', '', $file->getPathname());
                $files[] = $relativePath;
            }
        }
        
        return $files;
    }

    /**
     * Obtener lista de directorios
     */
    public function directories(string $directory = '', bool $recursive = false): array
    {
        $fullPath = $this->getFullPath($directory);
        
        if (!is_dir($fullPath)) {
            return [];
        }
        
        $directories = [];
        $iterator = $recursive ? 
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fullPath)) :
            new \DirectoryIterator($fullPath);
        
        foreach ($iterator as $file) {
            if ($file->isDir() && !$file->isDot()) {
                $relativePath = str_replace($this->root . '/', '', $file->getPathname());
                $directories[] = $relativePath;
            }
        }
        
        return $directories;
    }

    /**
     * Obtener lista de archivos y directorios
     */
    public function listContents(string $directory = '', bool $recursive = false): array
    {
        $fullPath = $this->getFullPath($directory);
        
        if (!is_dir($fullPath)) {
            return [];
        }
        
        $contents = [];
        $iterator = $recursive ? 
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fullPath)) :
            new \DirectoryIterator($fullPath);
        
        foreach ($iterator as $file) {
            if ($file->isDot()) {
                continue;
            }
            
            $relativePath = str_replace($this->root . '/', '', $file->getPathname());
            
            $contents[] = [
                'type' => $file->isDir() ? 'dir' : 'file',
                'path' => $relativePath,
                'size' => $file->isFile() ? $file->getSize() : 0,
                'timestamp' => $file->getMTime(),
                'visibility' => $this->getVisibility($relativePath)
            ];
        }
        
        return $contents;
    }

    /**
     * Obtener tamaño de archivo
     */
    public function size(string $path): int
    {
        $fullPath = $this->getFullPath($path);
        
        if (!file_exists($fullPath)) {
            throw new StorageException("File not found: {$path}");
        }
        
        return filesize($fullPath);
    }

    /**
     * Obtener fecha de última modificación
     */
    public function lastModified(string $path): int
    {
        $fullPath = $this->getFullPath($path);
        
        if (!file_exists($fullPath)) {
            throw new StorageException("File not found: {$path}");
        }
        
        return filemtime($fullPath);
    }

    /**
     * Establecer visibilidad
     */
    public function setVisibility(string $path, string $visibility): bool
    {
        $fullPath = $this->getFullPath($path);
        
        if (!file_exists($fullPath)) {
            throw new StorageException("File not found: {$path}");
        }
        
        $permissions = $visibility === 'public' ? 0644 : 0600;
        
        return chmod($fullPath, $permissions);
    }

    /**
     * Obtener visibilidad
     */
    public function getVisibility(string $path): string
    {
        $fullPath = $this->getFullPath($path);
        
        if (!file_exists($fullPath)) {
            throw new StorageException("File not found: {$path}");
        }
        
        $permissions = fileperms($fullPath) & 0777;
        
        return ($permissions & 0004) ? 'public' : 'private';
    }

    /**
     * Verificar si el driver está disponible
     */
    public function isAvailable(): bool
    {
        return is_dir($this->root) && is_writable($this->root);
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'driver' => 'local',
            'root' => $this->root,
            'permissions' => $this->permissions,
            'available' => $this->isAvailable(),
            'root_exists' => is_dir($this->root),
            'root_writable' => is_writable($this->root)
        ];
    }
}
