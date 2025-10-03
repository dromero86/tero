<?php

namespace Tero\Core\Routing\Attributes\Authorization;

use Attribute;

/**
 * Bearer - Atributo para autenticación Bearer Token
 * 
 * @package Tero\Core\Routing\Attributes\Authorization
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class Bearer
{
    public function __construct(
        public ?string $middleware = null,
        public bool $required = true,
        public ?string $realm = null,
        public array $scopes = [],
        public ?string $issuer = null,
        public ?string $audience = null
    ) {}

    /**
     * Obtener middleware por defecto si no se especifica
     */
    public function getMiddleware(): string
    {
        return $this->middleware ?? 'Tero\\Core\\Security\\Middleware\\BearerAuthMiddleware';
    }

    /**
     * Verificar si tiene scopes específicos
     */
    public function hasScopes(): bool
    {
        return !empty($this->scopes);
    }

    /**
     * Obtener realm para WWW-Authenticate header
     */
    public function getRealm(): string
    {
        return $this->realm ?? 'Tero API';
    }
}
