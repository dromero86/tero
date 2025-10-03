<?php

namespace Tero\Core\Routing\Attributes;

use Attribute;

/**
 * Route - Atributo para definir rutas con PHP 8+
 * 
 * @package Tero\Core\Routing\Attributes
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Route
{
    public function __construct(
        public string $path,
        public string $name,
        public array $methods = ['GET'],
        public array $middleware = [],
        public ?string $controller = null,
        public ?string $action = null,
        public array $parameters = [],
        public ?string $domain = null,
        public ?string $prefix = null,
        public ?string $where = null
    ) {}

    /**
     * Obtener métodos HTTP como string
     */
    public function getMethodsAsString(): string
    {
        return implode('|', $this->methods);
    }

    /**
     * Verificar si acepta un método HTTP específico
     */
    public function acceptsMethod(string $method): bool
    {
        return in_array(strtoupper($method), array_map('strtoupper', $this->methods));
    }

    /**
     * Obtener ruta completa con prefijo
     */
    public function getFullPath(): string
    {
        $path = $this->path;
        
        if ($this->prefix) {
            $path = rtrim($this->prefix, '/') . '/' . ltrim($path, '/');
        }
        
        return '/' . ltrim($path, '/');
    }
}
