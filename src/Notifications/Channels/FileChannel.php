<?php

namespace Tero\Notifications\Channels;

use Tero\Config\ConfigManager;
use Tero\Notifications\Contracts\NotificationChannelInterface;
use Tero\Notifications\Contracts\NotificationInterface;
use Tero\Notifications\Exceptions\NotificationException;

/**
 * FileChannel - Canal de notificaciones por archivo
 * 
 * @package Tero\Notifications\Channels
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class FileChannel implements NotificationChannelInterface
{
    private ConfigManager $config;
    private string $name = 'file';
    private string $path;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->path = $config->get('NOTIFICATIONS_FILE_PATH', 'storage/notifications');
        
        $this->ensureDirectory();
    }

    /**
     * Asegurar que el directorio existe
     */
    private function ensureDirectory(): void
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * Enviar notificación
     */
    public function send(NotificationInterface $notification): void
    {
        $data = $notification->getData();
        $filename = $this->generateFilename($notification);
        $filepath = $this->path . '/' . $filename;
        
        $notificationData = [
            'id' => $notification->getId(),
            'type' => get_class($notification),
            'data' => $data,
            'metadata' => $notification->getMetadata(),
            'sent_at' => date('Y-m-d H:i:s'),
            'timestamp' => time()
        ];
        
        try {
            file_put_contents($filepath, json_encode($notificationData, JSON_PRETTY_PRINT), LOCK_EX);
        } catch (\Throwable $e) {
            throw new NotificationException("Failed to save notification to file: " . $e->getMessage());
        }
    }

    /**
     * Generar nombre de archivo
     */
    private function generateFilename(NotificationInterface $notification): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $id = substr($notification->getId(), -8);
        $type = basename(str_replace('\\', '/', get_class($notification)));
        
        return "{$timestamp}_{$type}_{$id}.json";
    }

    /**
     * Obtener nombre del canal
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Verificar si el canal está disponible
     */
    public function isAvailable(): bool
    {
        return is_dir($this->path) && is_writable($this->path);
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'name' => $this->name,
            'available' => $this->isAvailable(),
            'path' => $this->path,
            'directory_exists' => is_dir($this->path),
            'directory_writable' => is_writable($this->path)
        ];
    }
}
