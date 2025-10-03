<?php

namespace Tero\Notifications\Channels;

use Tero\Config\ConfigManager;
use Tero\Database\DatabaseManager;
use Tero\Notifications\Contracts\NotificationChannelInterface;
use Tero\Notifications\Contracts\NotificationInterface;
use Tero\Notifications\Exceptions\NotificationException;

/**
 * DatabaseChannel - Canal de notificaciones por base de datos
 * 
 * @package Tero\Notifications\Channels
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class DatabaseChannel implements NotificationChannelInterface
{
    private ConfigManager $config;
    private ?DatabaseManager $database;
    private string $name = 'database';
    private string $table;

    public function __construct(ConfigManager $config, ?DatabaseManager $database = null)
    {
        $this->config = $config;
        $this->database = $database;
        $this->table = $config->get('NOTIFICATIONS_DATABASE_TABLE', 'notifications');
        
        $this->ensureTable();
    }

    /**
     * Asegurar que la tabla existe
     */
    private function ensureTable(): void
    {
        if (!$this->database) {
            return;
        }
        
        try {
            if (!$this->database->tableExists($this->table)) {
                $sql = "CREATE TABLE {$this->table} (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    type VARCHAR(255) NOT NULL,
                    notifiable_type VARCHAR(255) NOT NULL,
                    notifiable_id BIGINT NOT NULL,
                    data LONGTEXT NOT NULL,
                    read_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX notifiable_index (notifiable_type, notifiable_id),
                    INDEX type_index (type),
                    INDEX read_at_index (read_at)
                )";
                
                $this->database->query($sql);
            }
        } catch (\Throwable $e) {
            error_log("Cannot create notifications table: " . $e->getMessage());
        }
    }

    /**
     * Enviar notificación
     */
    public function send(NotificationInterface $notification): void
    {
        if (!$this->isAvailable()) {
            throw new NotificationException("Database channel is not available");
        }
        
        $data = $notification->getData();
        
        if (!isset($data['notifiable_type']) || !isset($data['notifiable_id'])) {
            throw new NotificationException("Database notification requires notifiable_type and notifiable_id");
        }
        
        try {
            $this->database->query(
                "INSERT INTO {$this->table} (type, notifiable_type, notifiable_id, data) VALUES (?, ?, ?, ?)",
                [
                    get_class($notification),
                    $data['notifiable_type'],
                    $data['notifiable_id'],
                    json_encode($data)
                ]
            );
        } catch (\Throwable $e) {
            throw new NotificationException("Failed to save notification to database: " . $e->getMessage());
        }
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
        return $this->database !== null;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'name' => $this->name,
            'available' => $this->isAvailable(),
            'table' => $this->table,
            'database_connected' => $this->database !== null
        ];
    }
}
