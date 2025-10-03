<?php

namespace Tero\Cache\Stores;

use Tero\Config\ConfigManager;
use Tero\Cache\Contracts\CacheStoreInterface;

/**
 * FileCacheStore - Store de cache basado en archivos
 * 
 * @package Tero\Cache\Stores
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class FileCacheStore implements CacheStoreInterface
{
    private ConfigManager $config;
    private string $cachePath;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->cachePath = $config->get('CACHE_PATH', 'storage/cache');
        
        $this->ensureCacheDirectory();
    }

    /**
     * Asegurar que el directorio de cache existe
     */
    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * Obtener ruta del archivo de cache
     */
    private function getCacheFile(string $key): string
    {
        $hash = md5($key);
        $directory = $this->cachePath . '/' . substr($hash, 0, 2);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        return $directory . '/' . $hash . '.cache';
    }

    /**
     * Obtener valor del cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $data = file_get_contents($file);
        $cache = unserialize($data);
        
        if ($cache === false || !is_array($cache)) {
            return $default;
        }
        
        // Verificar si ha expirado
        if ($cache['expires'] > 0 && $cache['expires'] < time()) {
            unlink($file);
            return $default;
        }
        
        return $cache['value'];
    }

    /**
     * Establecer valor en cache
     */
    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        $file = $this->getCacheFile($key);
        $expires = $ttl > 0 ? time() + $ttl : 0;
        
        $cache = [
            'value' => $value,
            'expires' => $expires,
            'created' => time()
        ];
        
        $data = serialize($cache);
        return file_put_contents($file, $data, LOCK_EX) !== false;
    }

    /**
     * Verificar si clave existe
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Eliminar clave del cache
     */
    public function delete(string $key): bool
    {
        $file = $this->getCacheFile($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }

    /**
     * Incrementar valor numérico
     */
    public function increment(string $key, int $amount = 1): int
    {
        $value = $this->get($key, 0);
        $newValue = $value + $amount;
        $this->set($key, $newValue);
        return $newValue;
    }

    /**
     * Decrementar valor numérico
     */
    public function decrement(string $key, int $amount = 1): int
    {
        $value = $this->get($key, 0);
        $newValue = $value - $amount;
        $this->set($key, $newValue);
        return $newValue;
    }

    /**
     * Establecer múltiples valores
     */
    public function setMany(array $values, int $ttl = null): bool
    {
        $success = true;
        
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Obtener múltiples valores
     */
    public function getMany(array $keys): array
    {
        $values = [];
        
        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }
        
        return $values;
    }

    /**
     * Eliminar múltiples claves
     */
    public function deleteMany(array $keys): bool
    {
        $success = true;
        
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Limpiar todo el cache
     */
    public function flush(): bool
    {
        return $this->deleteDirectory($this->cachePath);
    }

    /**
     * Eliminar directorio recursivamente
     */
    private function deleteDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return true;
        }
        
        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($directory);
    }

    /**
     * Limpiar cache expirado
     */
    public function cleanup(): int
    {
        return $this->cleanupDirectory($this->cachePath);
    }

    /**
     * Limpiar directorio recursivamente
     */
    private function cleanupDirectory(string $directory): int
    {
        $deleted = 0;
        
        if (!is_dir($directory)) {
            return $deleted;
        }
        
        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            
            if (is_dir($path)) {
                $deleted += $this->cleanupDirectory($path);
                
                // Eliminar directorio vacío
                if (count(scandir($path)) === 2) {
                    rmdir($path);
                }
            } else {
                // Verificar si archivo ha expirado
                $data = file_get_contents($path);
                $cache = unserialize($data);
                
                if ($cache && is_array($cache) && $cache['expires'] > 0 && $cache['expires'] < time()) {
                    unlink($path);
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'cache_path' => $this->cachePath,
            'cache_size' => $this->getCacheSize(),
            'files_count' => $this->getFilesCount()
        ];
    }

    /**
     * Obtener tamaño del cache
     */
    private function getCacheSize(): int
    {
        return $this->getDirectorySize($this->cachePath);
    }

    /**
     * Obtener tamaño de directorio
     */
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        
        if (!is_dir($directory)) {
            return $size;
        }
        
        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            
            if (is_dir($path)) {
                $size += $this->getDirectorySize($path);
            } else {
                $size += filesize($path);
            }
        }
        
        return $size;
    }

    /**
     * Obtener conteo de archivos
     */
    private function getFilesCount(): int
    {
        return $this->getFilesCountInDirectory($this->cachePath);
    }

    /**
     * Obtener conteo de archivos en directorio
     */
    private function getFilesCountInDirectory(string $directory): int
    {
        $count = 0;
        
        if (!is_dir($directory)) {
            return $count;
        }
        
        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            
            if (is_dir($path)) {
                $count += $this->getFilesCountInDirectory($path);
            } else {
                $count++;
            }
        }
        
        return $count;
    }
}
