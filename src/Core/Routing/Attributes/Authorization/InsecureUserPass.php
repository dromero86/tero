<?php

namespace Tero\Core\Routing\Attributes\Authorization;

use Attribute;

/**
 * InsecureUserPass - Atributo para autenticación básica (insegura)
 * 
 * @package Tero\Core\Routing\Attributes\Authorization
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class InsecureUserPass
{
    public function __construct(
        public string $credentials = 'admin:admin',
        public ?string $middleware = null,
        public bool $required = true,
        public ?string $realm = null,
        public bool $encrypt = false
    ) {}

    /**
     * Obtener middleware por defecto si no se especifica
     */
    public function getMiddleware(): string
    {
        return $this->middleware ?? 'Tero\\Core\\Security\\Middleware\\BasicAuthMiddleware';
    }

    /**
     * Obtener usuario y contraseña
     */
    public function getCredentials(): array
    {
        [$username, $password] = explode(':', $this->credentials, 2);
        return [
            'username' => $username,
            'password' => $password
        ];
    }

    /**
     * Obtener realm para WWW-Authenticate header
     */
    public function getRealm(): string
    {
        return $this->realm ?? 'Tero API';
    }

    /**
     * Verificar si las credenciales están encriptadas
     */
    public function isEncrypted(): bool
    {
        return $this->encrypt;
    }
}
