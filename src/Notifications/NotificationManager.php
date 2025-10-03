<?php

namespace Tero\Notifications;

use Tero\Config\ConfigManager;
use Tero\Notifications\Contracts\NotificationManagerInterface;
use Tero\Notifications\Contracts\NotificationInterface;
use Tero\Notifications\Contracts\NotificationChannelInterface;
use Tero\Notifications\Exceptions\NotificationException;

/**
 * NotificationManager - Gestor de notificaciones multi-canal
 * 
 * @package Tero\Notifications
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class NotificationManager implements NotificationManagerInterface
{
    private ConfigManager $config;
    private array $channels = [];
    private array $defaultChannels = [];
    private array $queuedNotifications = [];
    private bool $autoDiscovery = true;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->autoDiscovery = $config->get('NOTIFICATIONS_AUTO_DISCOVERY', true, 'bool');
        $this->defaultChannels = $config->get('NOTIFICATIONS_DEFAULT_CHANNELS', ['mail'], 'array');
        
        $this->initializeChannels();
        
        if ($this->autoDiscovery) {
            $this->discoverChannels();
        }
    }

    /**
     * Inicializar canales por defecto
     */
    private function initializeChannels(): void
    {
        // Canal de email
        $this->channels['mail'] = new Channels\MailChannel($this->config);
        
        // Canal de base de datos
        $this->channels['database'] = new Channels\DatabaseChannel($this->config);
        
        // Canal de archivo (para testing)
        $this->channels['file'] = new Channels\FileChannel($this->config);
        
        // Canal de Slack
        $this->channels['slack'] = new Channels\SlackChannel($this->config);
        
        // Canal de Discord
        $this->channels['discord'] = new Channels\DiscordChannel($this->config);
        
        // Canal de SMS
        $this->channels['sms'] = new Channels\SmsChannel($this->config);
        
        // Canal de Push
        $this->channels['push'] = new Channels\PushChannel($this->config);
    }

    /**
     * Descubrir canales automáticamente
     */
    private function discoverChannels(): void
    {
        $channelsPath = $this->config->get('NOTIFICATION_CHANNELS_PATH', 'app/NotificationChannels');
        
        if (!is_dir($channelsPath)) {
            return;
        }
        
        $files = glob($channelsPath . '/*.php');
        
        foreach ($files as $file) {
            $className = basename($file, '.php');
            $fullClassName = "App\\NotificationChannels\\{$className}";
            
            if (class_exists($fullClassName)) {
                $this->registerChannelClass($fullClassName);
            }
        }
    }

    /**
     * Registrar clase de canal
     */
    private function registerChannelClass(string $className): void
    {
        if (!class_exists($className)) {
            return;
        }
        
        $reflection = new \ReflectionClass($className);
        
        if (!$reflection->implementsInterface(NotificationChannelInterface::class)) {
            return;
        }
        
        $instance = new $className($this->config);
        $channelName = $this->extractChannelName($className);
        
        $this->channels[$channelName] = $instance;
    }

    /**
     * Extraer nombre del canal de la clase
     */
    private function extractChannelName(string $className): string
    {
        // App\NotificationChannels\TelegramChannel -> telegram
        $parts = explode('\\', $className);
        $className = end($parts);
        
        // TelegramChannel -> telegram
        $channelName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className));
        $channelName = str_replace('_channel', '', $channelName);
        
        return $channelName;
    }

    /**
     * Registrar canal personalizado
     */
    public function registerChannel(string $name, NotificationChannelInterface $channel): void
    {
        $this->channels[$name] = $channel;
    }

    /**
     * Obtener canal
     */
    public function channel(string $name): NotificationChannelInterface
    {
        if (!isset($this->channels[$name])) {
            throw new NotificationException("Notification channel '{$name}' not found");
        }
        
        return $this->channels[$name];
    }

    /**
     * Enviar notificación
     */
    public function send(NotificationInterface $notification, array $channels = null): void
    {
        $channels = $channels ?? $this->getDefaultChannels($notification);
        
        foreach ($channels as $channelName) {
            try {
                $channel = $this->channel($channelName);
                $channel->send($notification);
            } catch (\Throwable $e) {
                $this->handleChannelException($e, $channelName, $notification);
            }
        }
    }

    /**
     * Enviar notificación a canal específico
     */
    public function sendToChannel(NotificationInterface $notification, string $channel): void
    {
        $this->send($notification, [$channel]);
    }

    /**
     * Encolar notificación
     */
    public function queue(NotificationInterface $notification, array $channels = null, int $delay = 0): void
    {
        $this->queuedNotifications[] = [
            'notification' => $notification,
            'channels' => $channels ?? $this->getDefaultChannels($notification),
            'delay' => $delay,
            'queued_at' => time()
        ];
    }

    /**
     * Procesar notificaciones encoladas
     */
    public function processQueuedNotifications(): int
    {
        $processed = 0;
        $now = time();
        
        foreach ($this->queuedNotifications as $index => $queuedNotification) {
            if ($now >= $queuedNotification['queued_at'] + $queuedNotification['delay']) {
                $this->send(
                    $queuedNotification['notification'],
                    $queuedNotification['channels']
                );
                
                unset($this->queuedNotifications[$index]);
                $processed++;
            }
        }
        
        // Reindexar array
        $this->queuedNotifications = array_values($this->queuedNotifications);
        
        return $processed;
    }

    /**
     * Obtener canales por defecto para notificación
     */
    private function getDefaultChannels(NotificationInterface $notification): array
    {
        $notificationChannels = $notification->via();
        
        if (!empty($notificationChannels)) {
            return $notificationChannels;
        }
        
        return $this->defaultChannels;
    }

    /**
     * Manejar excepción de canal
     */
    private function handleChannelException(\Throwable $e, string $channelName, NotificationInterface $notification): void
    {
        $notificationName = get_class($notification);
        
        error_log("Notification channel exception in '{$channelName}' for '{$notificationName}': " . $e->getMessage());
        
        // Disparar evento de error
        if (class_exists('Tero\Events\EventManager')) {
            $eventManager = new \Tero\Events\EventManager($this->config);
            $eventManager->fire('notification.channel.error', [
                'channel' => $channelName,
                'notification' => $notificationName,
                'exception' => $e
            ]);
        }
    }

    /**
     * Obtener todos los canales
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Obtener canales disponibles
     */
    public function getAvailableChannels(): array
    {
        return array_keys($this->channels);
    }

    /**
     * Obtener notificaciones encoladas
     */
    public function getQueuedNotifications(): array
    {
        return $this->queuedNotifications;
    }

    /**
     * Limpiar notificaciones encoladas
     */
    public function clearQueuedNotifications(): void
    {
        $this->queuedNotifications = [];
    }

    /**
     * Verificar si canal existe
     */
    public function hasChannel(string $name): bool
    {
        return isset($this->channels[$name]);
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'auto_discovery' => $this->autoDiscovery,
            'channels_count' => count($this->channels),
            'available_channels' => array_keys($this->channels),
            'default_channels' => $this->defaultChannels,
            'queued_notifications_count' => count($this->queuedNotifications)
        ];
    }
}
