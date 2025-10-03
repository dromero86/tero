<?php

namespace Tero\Notifications\Channels;

use Tero\Config\ConfigManager;
use Tero\Notifications\Contracts\NotificationChannelInterface;
use Tero\Notifications\Contracts\NotificationInterface;
use Tero\Notifications\Exceptions\NotificationException;

/**
 * PushChannel - Canal de notificaciones push
 * 
 * @package Tero\Notifications\Channels
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class PushChannel implements NotificationChannelInterface
{
    private ConfigManager $config;
    private string $name = 'push';
    private ?string $fcmServerKey = null;
    private ?string $apnsCertificate = null;
    private ?string $apnsPrivateKey = null;
    private string $provider;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->fcmServerKey = $config->get('FCM_SERVER_KEY');
        $this->apnsCertificate = $config->get('APNS_CERTIFICATE');
        $this->apnsPrivateKey = $config->get('APNS_PRIVATE_KEY');
        $this->provider = $config->get('PUSH_PROVIDER', 'fcm');
    }

    /**
     * Enviar notificación
     */
    public function send(NotificationInterface $notification): void
    {
        if (!$this->isAvailable()) {
            throw new NotificationException("Push channel is not available");
        }
        
        $data = $notification->getData();
        
        if (!isset($data['tokens']) && !isset($data['token'])) {
            throw new NotificationException("Push notification requires 'tokens' or 'token' field");
        }
        
        $tokens = $data['tokens'] ?? [$data['token']];
        $title = $data['title'] ?? 'Notification';
        $body = $data['body'] ?? $data['message'] ?? 'You have a new notification';
        
        try {
            match ($this->provider) {
                'fcm' => $this->sendViaFcm($tokens, $title, $body, $data),
                'apns' => $this->sendViaApns($tokens, $title, $body, $data),
                default => throw new NotificationException("Unsupported push provider: {$this->provider}")
            };
        } catch (\Throwable $e) {
            throw new NotificationException("Failed to send push notification: " . $e->getMessage());
        }
    }

    /**
     * Enviar vía FCM (Firebase Cloud Messaging)
     */
    private function sendViaFcm(array $tokens, string $title, string $body, array $data): void
    {
        if (!$this->fcmServerKey) {
            throw new NotificationException("FCM requires FCM_SERVER_KEY");
        }
        
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default'
            ],
            'data' => $data['data'] ?? []
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: key=' . $this->fcmServerKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new NotificationException("FCM API returned HTTP {$httpCode}: {$response}");
        }
        
        $result = json_decode($response, true);
        if ($result['failure_count'] > 0) {
            throw new NotificationException("FCM failed to send to some devices: " . json_encode($result['results']));
        }
    }

    /**
     * Enviar vía APNS (Apple Push Notification Service)
     */
    private function sendViaApns(array $tokens, string $title, string $body, array $data): void
    {
        if (!$this->apnsCertificate || !$this->apnsPrivateKey) {
            throw new NotificationException("APNS requires APNS_CERTIFICATE and APNS_PRIVATE_KEY");
        }
        
        // Implementación básica de APNS
        // En producción, usar una librería especializada como pushok/pushok
        $url = $this->config->get('APNS_URL', 'https://api.push.apple.com/3/device/');
        
        $payload = [
            'aps' => [
                'alert' => [
                    'title' => $title,
                    'body' => $body
                ],
                'sound' => 'default',
                'badge' => $data['badge'] ?? 1
            ],
            'data' => $data['data'] ?? []
        ];
        
        // Aquí iría la implementación completa de APNS
        // Por simplicidad, solo lanzamos una excepción
        throw new NotificationException("APNS implementation not complete");
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
        return match ($this->provider) {
            'fcm' => !empty($this->fcmServerKey),
            'apns' => !empty($this->apnsCertificate) && !empty($this->apnsPrivateKey),
            default => false
        };
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'name' => $this->name,
            'available' => $this->isAvailable(),
            'provider' => $this->provider,
            'fcm_server_key_configured' => !empty($this->fcmServerKey),
            'apns_certificate_configured' => !empty($this->apnsCertificate),
            'apns_private_key_configured' => !empty($this->apnsPrivateKey)
        ];
    }
}
