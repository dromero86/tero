<?php

namespace Tero\Storage\Drivers;

use Tero\Config\ConfigManager;
use Tero\Storage\Contracts\StorageInterface;
use Tero\Storage\Exceptions\StorageException;

/**
 * FtpDriver - Driver de storage para FTP
 * 
 * @package Tero\Storage\Drivers
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class FtpDriver implements StorageInterface
{
    private ConfigManager $config;
    private $connection = null;
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private bool $passive;
    private bool $ssl;
    private int $timeout;
    private string $root;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->host = $config->get('FTP_HOST', 'localhost');
        $this->port = (int) $config->get('FTP_PORT', '21');
        $this->username = $config->get('FTP_USERNAME', '');
        $this->password = $config->get('FTP_PASSWORD', '');
        $this->passive = $config->get('FTP_PASSIVE', true, 'bool');
        $this->ssl = $config->get('FTP_SSL', false, 'bool');
        $this->timeout = (int) $config->get('FTP_TIMEOUT', '90');
        $this->root = $config->get('FTP_ROOT', '/');
        
        $this->connect();
    }

    /**
     * Conectar a FTP
     */
    private function connect(): void
    {
        if (empty($this->username) || empty($this->password)) {
            return;
        }
        
        try {
            if ($this->ssl) {
                $this->connection = ftp_ssl_connect($this->host, $this->port, $this->timeout);
            } else {
                $this->connection = ftp_connect($this->host, $this->port, $this->timeout);
            }
            
            if ($this->connection === false) {
                throw new StorageException("Failed to connect to FTP server");
            }
            
            $login = ftp_login($this->connection, $this->username, $this->password);
            
            if ($login === false) {
                throw new StorageException("Failed to login to FTP server");
            }
            
            ftp_pasv($this->connection, $this->passive);
            
            if (!empty($this->root)) {
                ftp_chdir($this->connection, $this->root);
            }
        } catch (\Throwable $e) {
            error_log("Failed to connect to FTP: " . $e->getMessage());
            $this->connection = null;
        }
    }

    /**
     * Verificar conexión
     */
    private function ensureConnection(): void
    {
        if (!$this->isAvailable()) {
            throw new StorageException("FTP connection is not available");
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
        
        $size = ftp_size($this->connection, $path);
        return $size !== -1;
    }

    /**
     * Obtener contenido de archivo
     */
    public function get(string $path): string
    {
        $this->ensureConnection();
        
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        
        $result = ftp_get($this->connection, $tempPath, $path, FTP_BINARY);
        
        if ($result === false) {
            fclose($tempFile);
            throw new StorageException("Failed to download file: {$path}");
        }
        
        $content = file_get_contents($tempPath);
        fclose($tempFile);
        
        return $content;
    }

    /**
     * Obtener contenido de archivo como stream
     */
    public function readStream(string $path)
    {
        $this->ensureConnection();
        
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        
        $result = ftp_get($this->connection, $tempPath, $path, FTP_BINARY);
        
        if ($result === false) {
            fclose($tempFile);
            throw new StorageException("Failed to download file: {$path}");
        }
        
        return fopen($tempPath, 'rb');
    }

    /**
     * Escribir contenido a archivo
     */
    public function put(string $path, string $contents, array $options = []): bool
    {
        $this->ensureConnection();
        
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        
        fwrite($tempFile, $contents);
        rewind($tempFile);
        
        $result = ftp_put($this->connection, $path, $tempPath, FTP_BINARY);
        
        fclose($tempFile);
        
        if ($result === false) {
            throw new StorageException("Failed to upload file: {$path}");
        }
        
        return true;
    }

    /**
     * Escribir stream a archivo
     */
    public function writeStream(string $path, $stream, array $options = []): bool
    {
        $this->ensureConnection();
        
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        
        stream_copy_to_stream($stream, $tempFile);
        rewind($tempFile);
        
        $result = ftp_put($this->connection, $path, $tempPath, FTP_BINARY);
        
        fclose($tempFile);
        
        if ($result === false) {
            throw new StorageException("Failed to upload stream: {$path}");
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
        
        $result = ftp_delete($this->connection, $path);
        
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
        
        $result = ftp_mkdir($this->connection, $path);
        
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
        
        $result = ftp_rmdir($this->connection, $path);
        
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
        $list = ftp_nlist($this->connection, $directory);
        
        if ($list === false) {
            return [];
        }
        
        foreach ($list as $item) {
            $size = ftp_size($this->connection, $item);
            
            if ($size !== -1) {
                $files[] = $item;
            } elseif ($recursive) {
                $subFiles = $this->files($item, true);
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
        $list = ftp_nlist($this->connection, $directory);
        
        if ($list === false) {
            return [];
        }
        
        foreach ($list as $item) {
            $size = ftp_size($this->connection, $item);
            
            if ($size === -1) {
                $directories[] = $item;
                
                if ($recursive) {
                    $subDirs = $this->directories($item, true);
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
        $list = ftp_nlist($this->connection, $directory);
        
        if ($list === false) {
            return [];
        }
        
        foreach ($list as $item) {
            $size = ftp_size($this->connection, $item);
            $timestamp = ftp_mdtm($this->connection, $item);
            
            if ($size !== -1) {
                $contents[] = [
                    'type' => 'file',
                    'path' => $item,
                    'size' => $size,
                    'timestamp' => $timestamp,
                    'visibility' => 'private'
                ];
            } else {
                $contents[] = [
                    'type' => 'dir',
                    'path' => $item,
                    'size' => 0,
                    'timestamp' => $timestamp,
                    'visibility' => 'private'
                ];
                
                if ($recursive) {
                    $subContents = $this->listContents($item, true);
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
        
        $size = ftp_size($this->connection, $path);
        
        if ($size === -1) {
            throw new StorageException("File not found: {$path}");
        }
        
        return $size;
    }

    /**
     * Obtener fecha de última modificación
     */
    public function lastModified(string $path): int
    {
        $this->ensureConnection();
        
        $timestamp = ftp_mdtm($this->connection, $path);
        
        if ($timestamp === -1) {
            throw new StorageException("File not found: {$path}");
        }
        
        return $timestamp;
    }

    /**
     * Verificar si el driver está disponible
     */
    public function isAvailable(): bool
    {
        return $this->connection !== null && ftp_pwd($this->connection) !== false;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'driver' => 'ftp',
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'passive' => $this->passive,
            'ssl' => $this->ssl,
            'timeout' => $this->timeout,
            'root' => $this->root,
            'available' => $this->isAvailable(),
            'connected' => $this->connection !== null
        ];
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->connection !== null) {
            ftp_close($this->connection);
        }
    }
}
