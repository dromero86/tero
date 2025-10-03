<?php

namespace Tero\Notifications\Channels;

use Tero\Config\ConfigManager;
use Tero\Notifications\Contracts\NotificationChannelInterface;
use Tero\Notifications\Contracts\NotificationInterface;
use Tero\Notifications\Exceptions\NotificationException;

/**
 * SlackChannel - Canal de notificaciones por Slack
 * 
 * @package Tero\Notifications\Channels
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class SlackChannel implements NotificationChannelInterface
{
    private ConfigManager $config;
    private string $name = 'slack';
    private ?string $webhookUrl = null;
    private ?string $token = null;
    private ?string $channel = null;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->webhookUrl = $config->get('SLACK_WEBHOOK_URL');
        $this->token = $config->get('SLACK_TOKEN');
        $this->channel = $config->get('SLACK_CHANNEL', '#general');
    }

    /**
     * Enviar notificación
     */
    public function send(NotificationInterface $notification): void
    {
        if (!$this->isAvailable()) {
            throw new NotificationException("Slack channel is not available");
        }
        
        $data = $notification->getData();
        $message = $this->buildMessage($data);
        
        try {
            if ($this->webhookUrl) {
                $this->sendViaWebhook($message);
            } elseif ($this->token) {
                $this->sendViaApi($message);
            } else {
                throw new NotificationException("No Slack configuration found");
            }
        } catch (\Throwable $e) {
            throw new NotificationException("Failed to send Slack notification: " . $e->getMessage());
        }
    }

    /**
     * Construir mensaje
     */
    private function buildMessage(array $data): array
    {
        $message = [
            'channel' => $this->channel,
            'text' => $data['text'] ?? $data['message'] ?? 'Notification',
            'username' => $this->config->get('SLACK_USERNAME', 'Tero Bot'),
            'icon_emoji' => $this->config->get('SLACK_ICON', ':robot_face:')
        ];
        
        // Agregar attachments si existen
        if (isset($data['attachments'])) {
            $message['attachments'] = $data['attachments'];
        }
        
        // Agregar blocks si existen
        if (isset($data['blocks'])) {
            $message['blocks'] = $data['blocks'];
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
        
        if ($httpCode !== 200) {
            throw new NotificationException("Slack webhook returned HTTP {$httpCode}: {$response}");
        }
    }

    /**
     * Enviar vía API
     */
    private function sendViaApi(array $message): void
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://slack.com/api/chat.postMessage');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new NotificationException("Slack API returned HTTP {$httpCode}: {$response}");
        }
        
        $result = json_decode($response, true);
        if (!$result['ok']) {
            throw new NotificationException("Slack API error: " . ($result['error'] ?? 'Unknown error'));
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
        return !empty($this->webhookUrl) || !empty($this->token);
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
            'token_configured' => !empty($this->token),
            'channel' => $this->channel,
            'username' => $this->config->get('SLACK_USERNAME', 'Tero Bot'),
            'icon' => $this->config->get('SLACK_ICON', ':robot_face:')
        ];
    }
}
