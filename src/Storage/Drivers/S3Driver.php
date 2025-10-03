<?php

namespace Tero\Storage\Drivers;

use Tero\Config\ConfigManager;
use Tero\Storage\Contracts\StorageInterface;
use Tero\Storage\Exceptions\StorageException;

/**
 * S3Driver - Driver de storage para Amazon S3
 * 
 * @package Tero\Storage\Drivers
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class S3Driver implements StorageInterface
{
    private ConfigManager $config;
    private ?\Aws\S3\S3Client $client = null;
    private string $bucket;
    private string $region;
    private string $key;
    private string $secret;
    private string $endpoint;
    private bool $usePathStyle;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->bucket = $config->get('S3_BUCKET', '');
        $this->region = $config->get('S3_REGION', 'us-east-1');
        $this->key = $config->get('S3_KEY', '');
        $this->secret = $config->get('S3_SECRET', '');
        $this->endpoint = $config->get('S3_ENDPOINT', '');
        $this->usePathStyle = $config->get('S3_USE_PATH_STYLE', false, 'bool');
        
        $this->initializeClient();
    }

    /**
     * Inicializar cliente S3
     */
    private function initializeClient(): void
    {
        if (empty($this->key) || empty($this->secret) || empty($this->bucket)) {
            return;
        }
        
        try {
            $config = [
                'version' => 'latest',
                'region' => $this->region,
                'credentials' => [
                    'key' => $this->key,
                    'secret' => $this->secret
                ]
            ];
            
            if (!empty($this->endpoint)) {
                $config['endpoint'] = $this->endpoint;
                $config['use_path_style_endpoint'] = $this->usePathStyle;
            }
            
            $this->client = new \Aws\S3\S3Client($config);
        } catch (\Throwable $e) {
            error_log("Failed to initialize S3 client: " . $e->getMessage());
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
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $path
            ]);
            return true;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($e->getAwsErrorCode() === 'NotFound') {
                return false;
            }
            throw new StorageException("S3 error checking file existence: " . $e->getMessage());
        }
    }

    /**
     * Obtener contenido de archivo
     */
    public function get(string $path): string
    {
        if (!$this->isAvailable()) {
            throw new StorageException("S3 driver is not available");
        }
        
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $path
            ]);
            
            return $result['Body']->getContents();
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error reading file: " . $e->getMessage());
        }
    }

    /**
     * Obtener contenido de archivo como stream
     */
    public function readStream(string $path)
    {
        if (!$this->isAvailable()) {
            throw new StorageException("S3 driver is not available");
        }
        
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $path
            ]);
            
            return $result['Body']->detach();
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error reading stream: " . $e->getMessage());
        }
    }

    /**
     * Escribir contenido a archivo
     */
    public function put(string $path, string $contents, array $options = []): bool
    {
        if (!$this->isAvailable()) {
            throw new StorageException("S3 driver is not available");
        }
        
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => $contents,
                'ContentType' => $options['content_type'] ?? 'application/octet-stream'
            ];
            
            if (isset($options['visibility'])) {
                $params['ACL'] = $options['visibility'] === 'public' ? 'public-read' : 'private';
            }
            
            $this->client->putObject($params);
            return true;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error writing file: " . $e->getMessage());
        }
    }

    /**
     * Escribir stream a archivo
     */
    public function writeStream(string $path, $stream, array $options = []): bool
    {
        if (!$this->isAvailable()) {
            throw new StorageException("S3 driver is not available");
        }
        
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => $stream,
                'ContentType' => $options['content_type'] ?? 'application/octet-stream'
            ];
            
            if (isset($options['visibility'])) {
                $params['ACL'] = $options['visibility'] === 'public' ? 'public-read' : 'private';
            }
            
            $this->client->putObject($params);
            return true;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error writing stream: " . $e->getMessage());
        }
    }

    /**
     * Copiar archivo
     */
    public function copy(string $from, string $to): bool
    {
        if (!$this->isAvailable()) {
            throw new StorageException("S3 driver is not available");
        }
        
        try {
            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $to,
                'CopySource' => $this->bucket . '/' . $from
            ]);
            return true;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error copying file: " . $e->getMessage());
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
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $path
            ]);
            return true;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error deleting file: " . $e->getMessage());
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
        
        try {
            $objects = array_map(function($path) {
                return ['Key' => $path];
            }, $paths);
            
            $result = $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => [
                    'Objects' => $objects
                ]
            ]);
            
            foreach ($result['Deleted'] as $deletedObject) {
                $deleted[] = $deletedObject['Key'];
            }
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error deleting multiple files: " . $e->getMessage());
        }
        
        return $deleted;
    }

    /**
     * Crear directorio
     */
    public function makeDirectory(string $path): bool
    {
        // S3 no tiene directorios reales, solo objetos con "/" en el nombre
        // Crear un objeto vacío para simular un directorio
        if (!$this->isAvailable()) {
            return false;
        }
        
        $path = rtrim($path, '/') . '/';
        
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => ''
            ]);
            return true;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error creating directory: " . $e->getMessage());
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
            $objects = $this->client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $path
            ]);
            
            if (empty($objects['Contents'])) {
                return true;
            }
            
            $keys = array_map(function($object) {
                return ['Key' => $object['Key']];
            }, $objects['Contents']);
            
            $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => [
                    'Objects' => $keys
                ]
            ]);
            
            return true;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error deleting directory: " . $e->getMessage());
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
        $prefix = $directory ? rtrim($directory, '/') . '/' : '';
        
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Prefix' => $prefix
            ];
            
            if (!$recursive) {
                $params['Delimiter'] = '/';
            }
            
            $result = $this->client->listObjectsV2($params);
            
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    if ($object['Key'] !== $prefix) {
                        $files[] = $object['Key'];
                    }
                }
            }
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error listing files: " . $e->getMessage());
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
        $prefix = $directory ? rtrim($directory, '/') . '/' : '';
        
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
                'Delimiter' => '/'
            ];
            
            $result = $this->client->listObjectsV2($params);
            
            if (isset($result['CommonPrefixes'])) {
                foreach ($result['CommonPrefixes'] as $prefix) {
                    $directories[] = rtrim($prefix['Prefix'], '/');
                }
            }
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error listing directories: " . $e->getMessage());
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
        $prefix = $directory ? rtrim($directory, '/') . '/' : '';
        
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Prefix' => $prefix
            ];
            
            if (!$recursive) {
                $params['Delimiter'] = '/';
            }
            
            $result = $this->client->listObjectsV2($params);
            
            // Agregar archivos
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    if ($object['Key'] !== $prefix) {
                        $contents[] = [
                            'type' => 'file',
                            'path' => $object['Key'],
                            'size' => $object['Size'],
                            'timestamp' => $object['LastModified']->getTimestamp(),
                            'visibility' => $this->getVisibility($object['Key'])
                        ];
                    }
                }
            }
            
            // Agregar directorios
            if (isset($result['CommonPrefixes'])) {
                foreach ($result['CommonPrefixes'] as $prefix) {
                    $contents[] = [
                        'type' => 'dir',
                        'path' => rtrim($prefix['Prefix'], '/'),
                        'size' => 0,
                        'timestamp' => 0,
                        'visibility' => 'private'
                    ];
                }
            }
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error listing contents: " . $e->getMessage());
        }
        
        return $contents;
    }

    /**
     * Obtener tamaño de archivo
     */
    public function size(string $path): int
    {
        if (!$this->isAvailable()) {
            throw new StorageException("S3 driver is not available");
        }
        
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $path
            ]);
            
            return (int) $result['ContentLength'];
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error getting file size: " . $e->getMessage());
        }
    }

    /**
     * Obtener fecha de última modificación
     */
    public function lastModified(string $path): int
    {
        if (!$this->isAvailable()) {
            throw new StorageException("S3 driver is not available");
        }
        
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $path
            ]);
            
            return $result['LastModified']->getTimestamp();
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error getting last modified: " . $e->getMessage());
        }
    }

    /**
     * Obtener URL temporal
     */
    public function temporaryUrl(string $path, int $expiration = 3600): string
    {
        if (!$this->isAvailable()) {
            throw new StorageException("S3 driver is not available");
        }
        
        try {
            $cmd = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $path
            ]);
            
            $request = $this->client->createPresignedRequest($cmd, "+{$expiration} seconds");
            
            return (string) $request->getUri();
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error creating temporary URL: " . $e->getMessage());
        }
    }

    /**
     * Establecer visibilidad
     */
    public function setVisibility(string $path, string $visibility): bool
    {
        if (!$this->isAvailable()) {
            throw new StorageException("S3 driver is not available");
        }
        
        try {
            $this->client->putObjectAcl([
                'Bucket' => $this->bucket,
                'Key' => $path,
                'ACL' => $visibility === 'public' ? 'public-read' : 'private'
            ]);
            return true;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error setting visibility: " . $e->getMessage());
        }
    }

    /**
     * Obtener visibilidad
     */
    public function getVisibility(string $path): string
    {
        if (!$this->isAvailable()) {
            throw new StorageException("S3 driver is not available");
        }
        
        try {
            $result = $this->client->getObjectAcl([
                'Bucket' => $this->bucket,
                'Key' => $path
            ]);
            
            foreach ($result['Grants'] as $grant) {
                if ($grant['Grantee']['Type'] === 'Group' && 
                    $grant['Grantee']['URI'] === 'http://acs.amazonaws.com/groups/global/AllUsers' &&
                    $grant['Permission'] === 'READ') {
                    return 'public';
                }
            }
            
            return 'private';
        } catch (\Aws\S3\Exception\S3Exception $e) {
            throw new StorageException("S3 error getting visibility: " . $e->getMessage());
        }
    }

    /**
     * Verificar si el driver está disponible
     */
    public function isAvailable(): bool
    {
        return $this->client !== null && !empty($this->bucket);
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'driver' => 's3',
            'bucket' => $this->bucket,
            'region' => $this->region,
            'endpoint' => $this->endpoint,
            'use_path_style' => $this->usePathStyle,
            'available' => $this->isAvailable(),
            'client_initialized' => $this->client !== null,
            'credentials_configured' => !empty($this->key) && !empty($this->secret)
        ];
    }
}
