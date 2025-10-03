<?php

namespace Tero\Core\Routing\Attributes;

use Attribute;

/**
 * HttpPayload - Atributo para definir payload HTTP
 * 
 * @package Tero\Core\Routing\Attributes
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class HttpPayload
{
    public function __construct(
        public ?string $contentType = null,
        public ?int $maxSize = null,
        public array $allowedTypes = ['application/json', 'application/x-www-form-urlencoded'],
        public bool $validate = true,
        public ?string $schema = null
    ) {}

    /**
     * Verificar si el content type está permitido
     */
    public function isContentTypeAllowed(string $contentType): bool
    {
        return in_array($contentType, $this->allowedTypes);
    }

    /**
     * Obtener tamaño máximo en bytes
     */
    public function getMaxSizeInBytes(): int
    {
        if ($this->maxSize === null) {
            return 1024 * 1024; // 1MB por defecto
        }
        
        return $this->maxSize;
    }
}
