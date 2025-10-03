<?php

namespace Tero\Notifications\Channels;

use Tero\Config\ConfigManager;
use Tero\Notifications\Contracts\NotificationChannelInterface;
use Tero\Notifications\Contracts\NotificationInterface;
use Tero\Notifications\Exceptions\NotificationException;

/**
 * DiscordChannel - Canal de notificaciones por Discord
 * 
 * @package Tero\Notifications\Channels
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class DiscordChannel implements NotificationChannelInterface
{
    private ConfigManager $config;
    private string $name = 'discord';
    private ?string $webhookUrl = null;
    private ?string $botToken = null;
    private ?string $channelId = null;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->webhookUrl = $config->get('DISCORD_WEBHOOK_URL');
        $this->botToken = $config->get('DISCORD_BOT_TOKEN');
        $this->channelId = $config->get('DISCORD_CHANNEL_ID');
    }

    /**
     * Enviar notificación
     */
    public function send(NotificationInterface $notification): void
    {
        if (!$this->isAvailable()) {
            throw new NotificationException("Discord channel is not available");
        }
        
        $data = $notification->getData();
        $message = $this->buildMessage($data);
        
        try {
            if ($this->webhookUrl) {
                $this->sendViaWebhook($message);
            } elseif ($this->botToken && $this->channelId) {
                $this->sendViaApi($message);
            } else {
                throw new NotificationException("No Discord configuration found");
            }
        } catch (\Throwable $e) {
            throw new NotificationException("Failed to send Discord notification: " . $e->getMessage());
        }
    }

    /**
     * Construir mensaje
     */
    private function buildMessage(array $data): array
    {
        $message = [
            'content' => $data['content'] ?? $data['message'] ?? 'Notification',
            'username' => $this->config->get('DISCORD_USERNAME', 'Tero Bot'),
            'avatar_url' => $this->config->get('DISCORD_AVATAR_URL')
        ];
        
        // Agregar embeds si existen
        if (isset($data['embeds'])) {
            $message['embeds'] = $data['embeds'];
        }
        
        // Agregar archivos si existen
        if (isset($data['files'])) {
            $message['files'] = $data['files'];
        }
        
        return $message;
    }

    /**
     * Enviar vía webhook
     */
    private function sendViaWebhook(array $message): void
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 && $httpCode !== 204) {
            throw new NotificationException("Discord webhook returned HTTP {$httpCode}: {$response}");
        }
    }

    /**
     * Enviar vía API
     */
    private function sendViaApi(array $message): void
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://discord.com/api/v10/channels/{$this->channelId}/messages");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bot ' . $this->botToken
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new NotificationException("Discord API returned HTTP {$httpCode}: {$response}");
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
        return !empty($this->webhookUrl) || (!empty($this->botToken) && !empty($this->channelId));
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'name' => $this->name,
            'available' => $this->isAvailable(),
            'webhook_configured' => !empty($this->webhookUrl),
            'bot_token_configured' => !empty($this->botToken),
            'channel_id_configured' => !empty($this->channelId),
            'username' => $this->config->get('DISCORD_USERNAME', 'Tero Bot'),
            'avatar_url' => $this->config->get('DISCORD_AVATAR_URL')
        ];
    }
}
