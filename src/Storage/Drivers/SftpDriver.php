<?php

namespace Tero\Storage\Drivers;

use Tero\Config\ConfigManager;
use Tero\Storage\Contracts\StorageInterface;
use Tero\Storage\Exceptions\StorageException;

/**
 * SftpDriver - Driver de storage para SFTP
 * 
 * @package Tero\Storage\Drivers
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class SftpDriver implements StorageInterface
{
    private ConfigManager $config;
    private ?\phpseclib3\Net\SFTP $connection = null;
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $privateKey;
    private string $publicKey;
    private int $timeout;
    private string $root;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->host = $config->get('SFTP_HOST', 'localhost');
        $this->port = (int) $config->get('SFTP_PORT', '22');
        $this->username = $config->get('SFTP_USERNAME', '');
        $this->password = $config->get('SFTP_PASSWORD', '');
        $this->privateKey = $config->get('SFTP_PRIVATE_KEY', '');
        $this->publicKey = $config->get('SFTP_PUBLIC_KEY', '');
        $this->timeout = (int) $config->get('SFTP_TIMEOUT', '90');
        $this->root = $config->get('SFTP_ROOT', '/');
        
        $this->connect();
    }

    /**
     * Conectar a SFTP
     */
    private function connect(): void
    {
        if (empty($this->username)) {
            return;
        }
        
        try {
            $this->connection = new \phpseclib3\Net\SFTP($this->host, $this->port, $this->timeout);
            
            if (!empty($this->privateKey)) {
                $key = \phpseclib3\Crypt\PublicKeyLoader::load(file_get_contents($this->privateKey));
                $login = $this->connection->login($this->username, $key);
            } else {
                $login = $this->connection->login($this->username, $this->password);
            }
            
            if (!$login) {
                throw new StorageException("Failed to login to SFTP server");
            }
            
            if (!empty($this->root)) {
                $this->connection->chdir($this->root);
            }
        } catch (\Throwable $e) {
            error_log("Failed to connect to SFTP: " . $e->getMessage());
            $this->connection = null;
        }
    }

    /**
     * Verificar conexión
     */
    private function ensureConnection(): void
    {
        if (!$this->isAvailable()) {
            throw new StorageException("SFTP connection is not available");
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
        
        $this->ensureConnection();
        
        return $this->connection->file_exists($path);
    }

    /**
     * Obtener contenido de archivo
     */
    public function get(string $path): string
    {
        $this->ensureConnection();
        
        $content = $this->connection->get($path);
        
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
        $this->ensureConnection();
        
        $content = $this->connection->get($path);
        
        if ($content === false) {
            throw new StorageException("Failed to read file: {$path}");
        }
        
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        
        return $stream;
    }

    /**
     * Escribir contenido a archivo
     */
    public function put(string $path, string $contents, array $options = []): bool
    {
        $this->ensureConnection();
        
        $result = $this->connection->put($path, $contents);
        
        if ($result === false) {
            throw new StorageException("Failed to write file: {$path}");
        }
        
        return true;
    }

    /**
     * Escribir stream a archivo
     */
    public function writeStream(string $path, $stream, array $options = []): bool
    {
        $this->ensureConnection();
        
        $content = stream_get_contents($stream);
        rewind($stream);
        
        $result = $this->connection->put($path, $content);
        
        if ($result === false) {
            throw new StorageException("Failed to write stream: {$path}");
        }
        
        return true;
    }

    /**
     * Copiar archivo
     */
    public function copy(string $from, string $to): bool
    {
        $content = $this->get($from);
        return $this->put($to, $content);
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
        $this->ensureConnection();
        
        $result = $this->connection->delete($path);
        
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
        $this->ensureConnection();
        
        $result = $this->connection->mkdir($path);
        
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
        $this->ensureConnection();
        
        $result = $this->connection->rmdir($path);
        
        if ($result === false) {
            throw new StorageException("Failed to delete directory: {$path}");
        }
        
        return true;
    }

    /**
     * Obtener lista de archivos
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $this->ensureConnection();
        
        $files = [];
        $list = $this->connection->nlist($directory);
        
        if ($list === false) {
            return [];
        }
        
        foreach ($list as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $fullPath = $directory ? $directory . '/' . $item : $item;
            
            if ($this->connection->is_file($fullPath)) {
                $files[] = $fullPath;
            } elseif ($recursive && $this->connection->is_dir($fullPath)) {
                $subFiles = $this->files($fullPath, true);
                $files = array_merge($files, $subFiles);
            }
        }
        
        return $files;
    }

    /**
     * Obtener lista de directorios
     */
    public function directories(string $directory = '', bool $recursive = false): array
    {
        $this->ensureConnection();
        
        $directories = [];
        $list = $this->connection->nlist($directory);
        
        if ($list === false) {
            return [];
        }
        
        foreach ($list as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $fullPath = $directory ? $directory . '/' . $item : $item;
            
            if ($this->connection->is_dir($fullPath)) {
                $directories[] = $fullPath;
                
                if ($recursive) {
                    $subDirs = $this->directories($fullPath, true);
                    $directories = array_merge($directories, $subDirs);
                }
            }
        }
        
        return $directories;
    }

    /**
     * Obtener lista de archivos y directorios
     */
    public function listContents(string $directory = '', bool $recursive = false): array
    {
        $this->ensureConnection();
        
        $contents = [];
        $list = $this->connection->nlist($directory);
        
        if ($list === false) {
            return [];
        }
        
        foreach ($list as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $fullPath = $directory ? $directory . '/' . $item : $item;
            
            if ($this->connection->is_file($fullPath)) {
                $stat = $this->connection->stat($fullPath);
                
                $contents[] = [
                    'type' => 'file',
                    'path' => $fullPath,
                    'size' => $stat['size'] ?? 0,
                    'timestamp' => $stat['mtime'] ?? 0,
                    'visibility' => 'private'
                ];
            } elseif ($this->connection->is_dir($fullPath)) {
                $stat = $this->connection->stat($fullPath);
                
                $contents[] = [
                    'type' => 'dir',
                    'path' => $fullPath,
                    'size' => 0,
                    'timestamp' => $stat['mtime'] ?? 0,
                    'visibility' => 'private'
                ];
                
                if ($recursive) {
                    $subContents = $this->listContents($fullPath, true);
                    $contents = array_merge($contents, $subContents);
                }
            }
        }
        
        return $contents;
    }

    /**
     * Obtener tamaño de archivo
     */
    public function size(string $path): int
    {
        $this->ensureConnection();
        
        $stat = $this->connection->stat($path);
        
        if ($stat === false) {
            throw new StorageException("File not found: {$path}");
        }
        
        return $stat['size'] ?? 0;
    }

    /**
     * Obtener fecha de última modificación
     */
    public function lastModified(string $path): int
    {
        $this->ensureConnection();
        
        $stat = $this->connection->stat($path);
        
        if ($stat === false) {
            throw new StorageException("File not found: {$path}");
        }
        
        return $stat['mtime'] ?? 0;
    }

    /**
     * Verificar si el driver está disponible
     */
    public function isAvailable(): bool
    {
        return $this->connection !== null && $this->connection->isConnected();
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'driver' => 'sftp',
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'timeout' => $this->timeout,
            'root' => $this->root,
            'available' => $this->isAvailable(),
            'connected' => $this->connection !== null && $this->connection->isConnected(),
            'private_key_configured' => !empty($this->privateKey),
            'public_key_configured' => !empty($this->publicKey)
        ];
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->connection !== null) {
            $this->connection->disconnect();
        }
    }
}
