<?php

namespace Tero\Notifications\Channels;

use Tero\Config\ConfigManager;
use Tero\Notifications\Contracts\NotificationChannelInterface;
use Tero\Notifications\Contracts\NotificationInterface;
use Tero\Notifications\Exceptions\NotificationException;

/**
 * MailChannel - Canal de notificaciones por email
 * 
 * @package Tero\Notifications\Channels
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class MailChannel implements NotificationChannelInterface
{
    private ConfigManager $config;
    private string $name = 'mail';
    private ?\Swift_Mailer $mailer = null;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->initializeMailer();
    }

    /**
     * Inicializar mailer
     */
    private function initializeMailer(): void
    {
        try {
            $transport = $this->createTransport();
            $this->mailer = new \Swift_Mailer($transport);
        } catch (\Throwable $e) {
            error_log("Failed to initialize mailer: " . $e->getMessage());
            $this->mailer = null;
        }
    }

    /**
     * Crear transporte
     */
    private function createTransport(): \Swift_Transport
    {
        $driver = $this->config->get('MAIL_DRIVER', 'smtp');
        
        return match ($driver) {
            'smtp' => $this->createSmtpTransport(),
            'sendmail' => new \Swift_SendmailTransport(),
            'mail' => new \Swift_MailTransport(),
            default => throw new NotificationException("Unsupported mail driver: {$driver}")
        };
    }

    /**
     * Crear transporte SMTP
     */
    private function createSmtpTransport(): \Swift_SmtpTransport
    {
        $host = $this->config->get('MAIL_HOST', 'localhost');
        $port = (int) $this->config->get('MAIL_PORT', '587');
        $encryption = $this->config->get('MAIL_ENCRYPTION', 'tls');
        $username = $this->config->get('MAIL_USERNAME');
        $password = $this->config->get('MAIL_PASSWORD');
        
        $transport = new \Swift_SmtpTransport($host, $port);
        
        if ($encryption) {
            $transport->setEncryption($encryption);
        }
        
        if ($username && $password) {
            $transport->setUsername($username);
            $transport->setPassword($password);
        }
        
        return $transport;
    }

    /**
     * Enviar notificación
     */
    public function send(NotificationInterface $notification): void
    {
        if (!$this->isAvailable()) {
            throw new NotificationException("Mail channel is not available");
        }
        
        $message = $this->buildMessage($notification);
        
        try {
            $result = $this->mailer->send($message);
            
            if ($result === 0) {
                throw new NotificationException("No recipients for email notification");
            }
        } catch (\Throwable $e) {
            throw new NotificationException("Failed to send email: " . $e->getMessage());
        }
    }

    /**
     * Construir mensaje
     */
    private function buildMessage(NotificationInterface $notification): \Swift_Message
    {
        $data = $notification->getData();
        
        $fromEmail = $this->config->get('MAIL_FROM_ADDRESS', 'noreply@example.com');
        $fromName = $this->config->get('MAIL_FROM_NAME', 'Tero Framework');
        
        $message = new \Swift_Message();
        $message->setFrom([$fromEmail => $fromName]);
        
        // Destinatario
        if (isset($data['to'])) {
            if (is_string($data['to'])) {
                $message->setTo($data['to']);
            } elseif (is_array($data['to'])) {
                $message->setTo($data['to']);
            }
        }
        
        // Asunto
        if (isset($data['subject'])) {
            $message->setSubject($data['subject']);
        }
        
        // Cuerpo del mensaje
        if (isset($data['body'])) {
            $message->setBody($data['body'], 'text/html');
        } elseif (isset($data['text'])) {
            $message->setBody($data['text'], 'text/plain');
        }
        
        // CC
        if (isset($data['cc'])) {
            $message->setCc($data['cc']);
        }
        
        // BCC
        if (isset($data['bcc'])) {
            $message->setBcc($data['bcc']);
        }
        
        // Adjuntos
        if (isset($data['attachments'])) {
            foreach ($data['attachments'] as $attachment) {
                if (is_string($attachment)) {
                    $message->attach(\Swift_Attachment::fromPath($attachment));
                } elseif (is_array($attachment)) {
                    $message->attach(\Swift_Attachment::fromPath($attachment['path']));
                }
            }
        }
        
        return $message;
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
        return $this->mailer !== null;
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'name' => $this->name,
            'available' => $this->isAvailable(),
            'driver' => $this->config->get('MAIL_DRIVER', 'smtp'),
            'host' => $this->config->get('MAIL_HOST', 'localhost'),
            'port' => $this->config->get('MAIL_PORT', '587'),
            'encryption' => $this->config->get('MAIL_ENCRYPTION', 'tls'),
            'from_address' => $this->config->get('MAIL_FROM_ADDRESS', 'noreply@example.com'),
            'from_name' => $this->config->get('MAIL_FROM_NAME', 'Tero Framework')
        ];
    }
}
