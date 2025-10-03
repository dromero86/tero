<?php

namespace Tero\Core\Security;

use Tero\Config\ConfigManager;

/**
 * SecurityManager - Gestor de seguridad del framework
 * 
 * @package Tero\Core\Security
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class SecurityManager
{
    private ConfigManager $config;
    private array $securityHeaders = [];
    private array $corsConfig = [];
    private bool $csrfEnabled = false;
    private bool $xssProtection = false;
    private bool $rateLimitEnabled = false;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->initializeSecurity();
    }

    /**
     * Inicializar configuración de seguridad
     */
    private function initializeSecurity(): void
    {
        $this->csrfEnabled = $this->config->get('SECURITY_CSRF', true, 'bool');
        $this->xssProtection = $this->config->get('SECURITY_XSS', true, 'bool');
        $this->rateLimitEnabled = $this->config->get('SECURITY_RATE_LIMIT', 100, 'int') > 0;
        
        $this->setupSecurityHeaders();
        $this->setupCorsConfig();
    }

    /**
     * Configurar headers de seguridad
     */
    private function setupSecurityHeaders(): void
    {
        $this->securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
        ];

        if ($this->config->get('WEB_HTTPS', false, 'bool')) {
            $this->securityHeaders['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
    }

    /**
     * Configurar CORS
     */
    private function setupCorsConfig(): void
    {
        $this->corsConfig = [
            'allowed_origins' => $this->config->get('CORS_ALLOWED_ORIGINS', ['*'], 'array'),
            'allowed_methods' => $this->config->get('CORS_ALLOWED_METHODS', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], 'array'),
            'allowed_headers' => $this->config->get('CORS_ALLOWED_HEADERS', ['Content-Type', 'Authorization'], 'array'),
            'exposed_headers' => $this->config->get('CORS_EXPOSED_HEADERS', [], 'array'),
            'max_age' => $this->config->get('CORS_MAX_AGE', 86400, 'int'),
            'allow_credentials' => $this->config->get('CORS_ALLOW_CREDENTIALS', false, 'bool')
        ];
    }

    /**
     * Aplicar headers de seguridad
     */
    public function applySecurityHeaders(): void
    {
        if (!headers_sent()) {
            foreach ($this->securityHeaders as $header => $value) {
                header("$header: $value");
            }
        }
    }

    /**
     * Aplicar headers CORS
     */
    public function applyCorsHeaders(?string $origin = null): void
    {
        if (!headers_sent()) {
            // Access-Control-Allow-Origin
            if (in_array('*', $this->corsConfig['allowed_origins']) || 
                ($origin && in_array($origin, $this->corsConfig['allowed_origins']))) {
                header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
            }

            // Access-Control-Allow-Methods
            header('Access-Control-Allow-Methods: ' . implode(', ', $this->corsConfig['allowed_methods']));

            // Access-Control-Allow-Headers
            header('Access-Control-Allow-Headers: ' . implode(', ', $this->corsConfig['allowed_headers']));

            // Access-Control-Expose-Headers
            if (!empty($this->corsConfig['exposed_headers'])) {
                header('Access-Control-Expose-Headers: ' . implode(', ', $this->corsConfig['exposed_headers']));
            }

            // Access-Control-Max-Age
            header('Access-Control-Max-Age: ' . $this->corsConfig['max_age']);

            // Access-Control-Allow-Credentials
            if ($this->corsConfig['allow_credentials']) {
                header('Access-Control-Allow-Credentials: true');
            }
        }
    }

    /**
     * Verificar CSRF token
     */
    public function verifyCsrfToken(string $token): bool
    {
        if (!$this->csrfEnabled) {
            return true;
        }

        $sessionToken = $_SESSION['_csrf_token'] ?? null;
        return $token && $sessionToken && hash_equals($sessionToken, $token);
    }

    /**
     * Generar CSRF token
     */
    public function generateCsrfToken(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['_csrf_token'];
    }

    /**
     * Sanitizar entrada para prevenir XSS
     */
    public function sanitizeInput(string $input): string
    {
        if (!$this->xssProtection) {
            return $input;
        }

        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Verificar rate limit
     */
    public function checkRateLimit(string $identifier, ?int $limit = null): bool
    {
        if (!$this->rateLimitEnabled) {
            return true;
        }

        $limit = $limit ?? $this->config->get('SECURITY_RATE_LIMIT', 100, 'int');
        $window = $this->config->get('SECURITY_RATE_LIMIT_WINDOW', 3600, 'int'); // 1 hora por defecto

        $key = "rate_limit_$identifier";
        $current = $_SESSION[$key] ?? 0;
        $lastReset = $_SESSION[$key . '_reset'] ?? time();

        // Resetear contador si ha pasado la ventana de tiempo
        if (time() - $lastReset > $window) {
            $current = 0;
            $_SESSION[$key . '_reset'] = time();
        }

        // Incrementar contador
        $current++;
        $_SESSION[$key] = $current;

        return $current <= $limit;
    }

    /**
     * Validar entrada
     */
    public function validateInput(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            if (is_string($rule)) {
                $rule = explode('|', $rule);
            }

            foreach ($rule as $validation) {
                $error = $this->validateField($field, $value, $validation);
                if ($error) {
                    $errors[$field][] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * Validar campo individual
     */
    private function validateField(string $field, mixed $value, string $rule): ?string
    {
        if (strpos($rule, ':') !== false) {
            [$rule, $param] = explode(':', $rule, 2);
        }

        return match ($rule) {
            'required' => empty($value) ? "The $field field is required." : null,
            'email' => !filter_var($value, FILTER_VALIDATE_EMAIL) ? "The $field field must be a valid email." : null,
            'numeric' => !is_numeric($value) ? "The $field field must be numeric." : null,
            'min' => strlen($value) < $param ? "The $field field must be at least $param characters." : null,
            'max' => strlen($value) > $param ? "The $field field must not exceed $param characters." : null,
            'alpha' => !ctype_alpha($value) ? "The $field field must contain only letters." : null,
            'alnum' => !ctype_alnum($value) ? "The $field field must contain only letters and numbers." : null,
            default => null
        };
    }

    /**
     * Generar hash seguro
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verificar hash de contraseña
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Generar token JWT (simplificado)
     */
    public function generateJwtToken(array $payload): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $headerEncoded = $this->base64UrlEncode($header);
        $payloadEncoded = $this->base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $this->getJwtSecret(), true);
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * Verificar token JWT (simplificado)
     */
    public function verifyJwtToken(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        
        $signature = $this->base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $this->getJwtSecret(), true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return null;
        }
        
        return json_decode($this->base64UrlDecode($payloadEncoded), true);
    }

    /**
     * Codificar base64 URL
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodificar base64 URL
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * Obtener secreto JWT
     */
    private function getJwtSecret(): string
    {
        return $this->config->get('AUTH_JWT_SECRET', 'default-secret-key');
    }

    /**
     * Obtener configuración de seguridad
     */
    public function getSecurityConfig(): array
    {
        return [
            'csrf_enabled' => $this->csrfEnabled,
            'xss_protection' => $this->xssProtection,
            'rate_limit_enabled' => $this->rateLimitEnabled,
            'security_headers' => $this->securityHeaders,
            'cors_config' => $this->corsConfig
        ];
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'csrf_enabled' => $this->csrfEnabled,
            'xss_protection' => $this->xssProtection,
            'rate_limit_enabled' => $this->rateLimitEnabled,
            'security_headers_count' => count($this->securityHeaders),
            'cors_origins' => $this->corsConfig['allowed_origins'],
            'cors_methods' => $this->corsConfig['allowed_methods']
        ];
    }
}
