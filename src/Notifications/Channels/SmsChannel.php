<?php

namespace Tero\Notifications\Channels;

use Tero\Config\ConfigManager;
use Tero\Notifications\Contracts\NotificationChannelInterface;
use Tero\Notifications\Contracts\NotificationInterface;
use Tero\Notifications\Exceptions\NotificationException;

/**
 * SmsChannel - Canal de notificaciones por SMS
 * 
 * @package Tero\Notifications\Channels
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class SmsChannel implements NotificationChannelInterface
{
    private ConfigManager $config;
    private string $name = 'sms';
    private ?string $apiKey = null;
    private ?string $apiSecret = null;
    private ?string $fromNumber = null;
    private string $provider;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->apiKey = $config->get('SMS_API_KEY');
        $this->apiSecret = $config->get('SMS_API_SECRET');
        $this->fromNumber = $config->get('SMS_FROM_NUMBER');
        $this->provider = $config->get('SMS_PROVIDER', 'twilio');
    }

    /**
     * Enviar notificación
     */
    public function send(NotificationInterface $notification): void
    {
        if (!$this->isAvailable()) {
            throw new NotificationException("SMS channel is not available");
        }
        
        $data = $notification->getData();
        
        if (!isset($data['to'])) {
            throw new NotificationException("SMS notification requires 'to' field");
        }
        
        $message = $data['message'] ?? $data['text'] ?? 'Notification';
        
        try {
            match ($this->provider) {
                'twilio' => $this->sendViaTwilio($data['to'], $message),
                'nexmo' => $this->sendViaNexmo($data['to'], $message),
                'aws' => $this->sendViaAws($data['to'], $message),
                default => throw new NotificationException("Unsupported SMS provider: {$this->provider}")
            };
        } catch (\Throwable $e) {
            throw new NotificationException("Failed to send SMS: " . $e->getMessage());
        }
    }

    /**
     * Enviar vía Twilio
     */
    private function sendViaTwilio(string $to, string $message): void
    {
        $accountSid = $this->apiKey;
        $authToken = $this->apiSecret;
        
        if (!$accountSid || !$authToken) {
            throw new NotificationException("Twilio requires SMS_API_KEY and SMS_API_SECRET");
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
        
        $data = [
            'From' => $this->fromNumber,
            'To' => $to,
            'Body' => $message
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "{$accountSid}:{$authToken}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new NotificationException("Twilio API returned HTTP {$httpCode}: {$response}");
        }
    }

    /**
     * Enviar vía Nexmo (Vonage)
     */
    private function sendViaNexmo(string $to, string $message): void
    {
        $apiKey = $this->apiKey;
        $apiSecret = $this->apiSecret;
        
        if (!$apiKey || !$apiSecret) {
            throw new NotificationException("Nexmo requires SMS_API_KEY and SMS_API_SECRET");
        }
        
        $url = 'https://rest.nexmo.com/sms/json';
        
        $data = [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'to' => $to,
            'from' => $this->fromNumber,
            'text' => $message
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new NotificationException("Nexmo API returned HTTP {$httpCode}: {$response}");
        }
        
        $result = json_decode($response, true);
        if ($result['messages'][0]['status'] !== '0') {
            throw new NotificationException("Nexmo API error: " . $result['messages'][0]['error-text']);
        }
    }

    /**
     * Enviar vía AWS SNS
     */
    private function sendViaAws(string $to, string $message): void
    {
        $accessKey = $this->apiKey;
        $secretKey = $this->apiSecret;
        
        if (!$accessKey || !$secretKey) {
            throw new NotificationException("AWS SNS requires SMS_API_KEY and SMS_API_SECRET");
        }
        
        // Implementación básica de AWS SNS
        // En producción, usar AWS SDK
        $url = 'https://sns.us-east-1.amazonaws.com/';
        
        $data = [
            'Action' => 'Publish',
            'TopicArn' => $this->config->get('SMS_TOPIC_ARN'),
            'Message' => $message,
            'PhoneNumber' => $to
        ];
        
        // Aquí iría la implementación completa de AWS SNS
        // Por simplicidad, solo lanzamos una excepción
        throw new NotificationException("AWS SNS implementation not complete");
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
        return !empty($this->apiKey) && !empty($this->apiSecret) && !empty($this->fromNumber);
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
            'api_key_configured' => !empty($this->apiKey),
            'api_secret_configured' => !empty($this->apiSecret),
            'from_number_configured' => !empty($this->fromNumber)
        ];
    }
}
