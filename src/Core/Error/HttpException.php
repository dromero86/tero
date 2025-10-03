<?php

namespace Tero\Core\Error;

/**
 * HttpException - Excepción HTTP personalizada
 * 
 * @package Tero\Core\Error
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class HttpException extends \Exception
{
    private int $statusCode;
    private array $headers;

    public function __construct(
        string $message = '',
        int $statusCode = 500,
        array $headers = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Obtener código de estado HTTP
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Obtener headers HTTP
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Crear excepción 400 Bad Request
     */
    public static function badRequest(string $message = 'Bad Request'): self
    {
        return new self($message, 400);
    }

    /**
     * Crear excepción 401 Unauthorized
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self($message, 401);
    }

    /**
     * Crear excepción 403 Forbidden
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self($message, 403);
    }

    /**
     * Crear excepción 404 Not Found
     */
    public static function notFound(string $message = 'Not Found'): self
    {
        return new self($message, 404);
    }

    /**
     * Crear excepción 405 Method Not Allowed
     */
    public static function methodNotAllowed(string $message = 'Method Not Allowed'): self
    {
        return new self($message, 405);
    }

    /**
     * Crear excepción 422 Unprocessable Entity
     */
    public static function unprocessableEntity(string $message = 'Unprocessable Entity'): self
    {
        return new self($message, 422);
    }

    /**
     * Crear excepción 429 Too Many Requests
     */
    public static function tooManyRequests(string $message = 'Too Many Requests'): self
    {
        return new self($message, 429);
    }

    /**
     * Crear excepción 500 Internal Server Error
     */
    public static function internalServerError(string $message = 'Internal Server Error'): self
    {
        return new self($message, 500);
    }
}
