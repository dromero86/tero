<?php

namespace Tero\Session;

use Tero\Config\ConfigManager;
use Tero\Database\DatabaseManager;
use Tero\Session\Contracts\SessionManagerInterface;
use Tero\Session\Exceptions\SessionException;

/**
 * SessionManager - Gestor de sesiones (Telepatia)
 * 
 * @package Tero\Session
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class SessionManager implements SessionManagerInterface
{
    private ConfigManager $config;
    private ?DatabaseManager $database;
    private string $driver;
    private string $lifetime;
    private string $path;
    private string $domain;
    private bool $secure;
    private bool $httpOnly;
    private string $sameSite;
    private bool $started = false;
    private array $data = [];
    private ?string $sessionId = null;
    private string $sessionName;

    public function __construct(ConfigManager $config, ?DatabaseManager $database = null)
    {
        $this->config = $config;
        $this->database = $database;
        $this->driver = $config->get('SESSION_DRIVER', 'file');
        $this->lifetime = $config->get('SESSION_LIFETIME', '120');
        $this->path = $config->get('SESSION_PATH', '/');
        $this->domain = $config->get('SESSION_DOMAIN', '');
        $this->secure = $config->get('SESSION_SECURE', false, 'bool');
        $this->httpOnly = $config->get('SESSION_HTTP_ONLY', true, 'bool');
        $this->sameSite = $config->get('SESSION_SAME_SITE', 'Lax');
        $this->sessionName = $config->get('SESSION_NAME', 'TERO_SESSION');
        
        $this->configureSession();
    }

    /**
     * Configurar sesión
     */
    private function configureSession(): void
    {
        ini_set('session.cookie_lifetime', $this->lifetime);
        ini_set('session.cookie_path', $this->path);
        ini_set('session.cookie_domain', $this->domain);
        ini_set('session.cookie_secure', $this->secure ? '1' : '0');
        ini_set('session.cookie_httponly', $this->httpOnly ? '1' : '0');
        ini_set('session.cookie_samesite', $this->sameSite);
        ini_set('session.name', $this->sessionName);
        
        // Configurar driver de sesión
        match ($this->driver) {
            'file' => $this->configureFileDriver(),
            'database' => $this->configureDatabaseDriver(),
            'redis' => $this->configureRedisDriver(),
            default => throw new SessionException("Unsupported session driver: {$this->driver}")
        };
    }

    /**
     * Configurar driver de archivo
     */
    private function configureFileDriver(): void
    {
        $savePath = $this->config->get('SESSION_SAVE_PATH', 'storage/sessions');
        
        if (!is_dir($savePath)) {
            mkdir($savePath, 0755, true);
        }
        
        ini_set('session.save_handler', 'files');
        ini_set('session.save_path', $savePath);
    }

    /**
     * Configurar driver de base de datos
     */
    private function configureDatabaseDriver(): void
    {
        if (!$this->database) {
            throw new SessionException("Database driver requires database connection");
        }
        
        ini_set('session.save_handler', 'user');
        session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'write'],
            [$this, 'destroySession'],
            [$this, 'gc']
        );
        
        $this->ensureSessionsTable();
    }

    /**
     * Configurar driver de Redis
     */
    private function configureRedisDriver(): void
    {
        $redisHost = $this->config->get('REDIS_HOST', '127.0.0.1');
        $redisPort = $this->config->get('REDIS_PORT', '6379');
        $redisPassword = $this->config->get('REDIS_PASSWORD', '');
        
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', "tcp://{$redisHost}:{$redisPort}");
        
        if ($redisPassword) {
            ini_set('session.save_path', "tcp://{$redisHost}:{$redisPort}?auth={$redisPassword}");
        }
    }

    /**
     * Asegurar que la tabla de sesiones existe
     */
    private function ensureSessionsTable(): void
    {
        if (!$this->database) {
            return;
        }
        
        try {
            if (!$this->database->tableExists('sessions')) {
                $sql = "CREATE TABLE sessions (
                    id VARCHAR(128) NOT NULL PRIMARY KEY,
                    user_id INT NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent TEXT NULL,
                    payload LONGTEXT NOT NULL,
                    last_activity INT NOT NULL,
                    INDEX sessions_user_id_index (user_id),
                    INDEX sessions_last_activity_index (last_activity)
                )";
                
                $this->database->query($sql);
            }
        } catch (\Throwable $e) {
            // Si no se puede crear la tabla, continuar sin base de datos
            error_log("Cannot create sessions table: " . $e->getMessage());
        }
    }

    /**
     * Iniciar sesión
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            $this->sessionId = session_id();
            $this->data = $_SESSION;
            return true;
        }
        
        $result = session_start();
        
        if ($result) {
            $this->started = true;
            $this->sessionId = session_id();
            $this->data = $_SESSION;
        }
        
        return $result;
    }

    /**
     * Verificar si sesión está iniciada
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Obtener ID de sesión
     */
    public function getId(): string
    {
        return $this->sessionId ?? session_id() ?? '';
    }

    /**
     * Regenerar ID de sesión
     */
    public function regenerateId(bool $deleteOldSession = true): bool
    {
        if (!$this->started) {
            $this->start();
        }
        
        $result = session_regenerate_id($deleteOldSession);
        
        if ($result) {
            $this->sessionId = session_id();
        }
        
        return $result;
    }

    /**
     * Obtener valor de sesión
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->started) {
            $this->start();
        }
        
        return $this->data[$key] ?? $default;
    }

    /**
     * Establecer valor de sesión
     */
    public function set(string $key, mixed $value): void
    {
        if (!$this->started) {
            $this->start();
        }
        
        $this->data[$key] = $value;
        $_SESSION[$key] = $value;
    }

    /**
     * Verificar si clave existe
     */
    public function has(string $key): bool
    {
        if (!$this->started) {
            $this->start();
        }
        
        return array_key_exists($key, $this->data);
    }

    /**
     * Eliminar valor de sesión
     */
    public function remove(string $key): void
    {
        if (!$this->started) {
            $this->start();
        }
        
        unset($this->data[$key]);
        unset($_SESSION[$key]);
    }

    /**
     * Obtener todos los datos
     */
    public function all(): array
    {
        if (!$this->started) {
            $this->start();
        }
        
        return $this->data;
    }

    /**
     * Establecer múltiples valores
     */
    public function put(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Obtener y eliminar valor
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    /**
     * Obtener y eliminar múltiples valores
     */
    public function pullMany(array $keys): array
    {
        $values = [];
        
        foreach ($keys as $key) {
            $values[$key] = $this->pull($key);
        }
        
        return $values;
    }

    /**
     * Incrementar valor numérico
     */
    public function increment(string $key, int $amount = 1): int
    {
        $value = $this->get($key, 0);
        $newValue = $value + $amount;
        $this->set($key, $newValue);
        return $newValue;
    }

    /**
     * Decrementar valor numérico
     */
    public function decrement(string $key, int $amount = 1): int
    {
        $value = $this->get($key, 0);
        $newValue = $value - $amount;
        $this->set($key, $newValue);
        return $newValue;
    }

    /**
     * Flash data (disponible solo en la próxima request)
     */
    public function flash(string $key, mixed $value): void
    {
        $this->set("_flash.{$key}", $value);
    }

    /**
     * Obtener flash data
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->get("_flash.{$key}", $default);
    }

    /**
     * Verificar si flash data existe
     */
    public function hasFlash(string $key): bool
    {
        return $this->has("_flash.{$key}");
    }

    /**
     * Obtener todos los flash data
     */
    public function getFlashData(): array
    {
        $flashData = [];
        
        foreach ($this->data as $key => $value) {
            if (str_starts_with($key, '_flash.')) {
                $flashKey = substr($key, 6); // Remove '_flash.' prefix
                $flashData[$flashKey] = $value;
            }
        }
        
        return $flashData;
    }

    /**
     * Limpiar flash data
     */
    public function clearFlash(): void
    {
        foreach ($this->data as $key => $value) {
            if (str_starts_with($key, '_flash.')) {
                $this->remove($key);
            }
        }
    }

    /**
     * Establecer usuario autenticado
     */
    public function setUser(int $userId): void
    {
        $this->set('user_id', $userId);
        $this->set('authenticated', true);
    }

    /**
     * Obtener ID de usuario
     */
    public function getUserId(): ?int
    {
        return $this->get('user_id');
    }

    /**
     * Verificar si usuario está autenticado
     */
    public function isAuthenticated(): bool
    {
        return $this->get('authenticated', false);
    }

    /**
     * Cerrar sesión
     */
    public function logout(): void
    {
        $this->remove('user_id');
        $this->remove('authenticated');
        $this->clearFlash();
    }

    /**
     * Limpiar toda la sesión
     */
    public function clear(): void
    {
        if (!$this->started) {
            $this->start();
        }
        
        $this->data = [];
        $_SESSION = [];
    }

    /**
     * Destruir sesión
     */
    public function destroy(): bool
    {
        if (!$this->started) {
            return true;
        }
        
        $this->clear();
        $result = session_destroy();
        $this->started = false;
        
        return $result;
    }

    /**
     * Guardar sesión
     */
    public function save(): void
    {
        if ($this->started) {
            session_write_close();
        }
    }

    /**
     * Métodos para driver de base de datos
     */
    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string
    {
        if (!$this->database) {
            return '';
        }
        
        try {
            $result = $this->database->fetchOne(
                "SELECT payload FROM sessions WHERE id = ?",
                [$sessionId]
            );
            
            return $result ? base64_decode($result['payload']) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function write(string $sessionId, string $sessionData): bool
    {
        if (!$this->database) {
            return false;
        }
        
        try {
            $payload = base64_encode($sessionData);
            $lastActivity = time();
            $userId = $this->getUserId();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $this->database->query(
                "INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity) 
                 VALUES (?, ?, ?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE 
                 user_id = VALUES(user_id),
                 ip_address = VALUES(ip_address),
                 user_agent = VALUES(user_agent),
                 payload = VALUES(payload),
                 last_activity = VALUES(last_activity)",
                [$sessionId, $userId, $ipAddress, $userAgent, $payload, $lastActivity]
            );
            
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function destroySession(string $sessionId): bool
    {
        if (!$this->database) {
            return false;
        }
        
        try {
            $this->database->query(
                "DELETE FROM sessions WHERE id = ?",
                [$sessionId]
            );
            
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function gc(int $maxLifetime): int
    {
        if (!$this->database) {
            return 0;
        }
        
        try {
            $cutoff = time() - $maxLifetime;
            
            $this->database->query(
                "DELETE FROM sessions WHERE last_activity < ?",
                [$cutoff]
            );
            
            return $this->database->connection()->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Limpiar sesiones expiradas
     */
    public function cleanupExpiredSessions(): int
    {
        if (!$this->database) {
            return 0;
        }
        
        try {
            $cutoff = time() - (int) $this->lifetime;
            
            $this->database->query(
                "DELETE FROM sessions WHERE last_activity < ?",
                [$cutoff]
            );
            
            return $this->database->connection()->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Obtener información de debug
     */
    public function getDebugInfo(): array
    {
        return [
            'driver' => $this->driver,
            'lifetime' => $this->lifetime,
            'path' => $this->path,
            'domain' => $this->domain,
            'secure' => $this->secure,
            'http_only' => $this->httpOnly,
            'same_site' => $this->sameSite,
            'session_name' => $this->sessionName,
            'started' => $this->started,
            'session_id' => $this->sessionId,
            'data_count' => count($this->data),
            'authenticated' => $this->isAuthenticated(),
            'user_id' => $this->getUserId()
        ];
    }
}
