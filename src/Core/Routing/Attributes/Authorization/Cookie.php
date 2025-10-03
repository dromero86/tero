<?php

namespace Tero\Core\Routing\Attributes\Authorization;

use Attribute;

/**
 * Cookie - Atributo para autenticación por Cookie
 * 
 * @package Tero\Core\Routing\Attributes\Authorization
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class Cookie
{
    public function __construct(
        public string $cookieName = 'tero_token',
        public ?string $middleware = null,
        public bool $required = true,
        public bool $httpOnly = true,
        public bool $secure = false,
        public ?string $domain = null,
        public ?string $path = null,
        public ?string $sameSite = 'Lax'
    ) {}

    /**
     * Obtener middleware por defecto si no se especifica
     */
    public function getMiddleware(): string
    {
        return $this->middleware ?? 'Tero\\Core\\Security\\Middleware\\CookieAuthMiddleware';
    }

    /**
     * Obtener opciones de cookie
     */
    public function getCookieOptions(): array
    {
        return [
            'httpOnly' => $this->httpOnly,
            'secure' => $this->secure,
            'domain' => $this->domain,
            'path' => $this->path,
            'sameSite' => $this->sameSite
        ];
    }
}
