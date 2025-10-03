<?php

namespace Tero\Core\Routing;

/**
 * RouteCompiler - Compilador de patrones de rutas
 * 
 * @package Tero\Core\Routing
 * @author Daniel Romero
 * @version 4.2.2-dev
 */
class RouteCompiler
{
    /**
     * Compilar patrón de ruta a regex
     */
    public function compile(string $pattern, array $where = []): array
    {
        $pattern = trim($pattern, '/');
        $params = [];
        $regex = '';

        // Escapar caracteres especiales excepto {param}
        $pattern = preg_replace_callback('/\{([^}]+)\}/', function($matches) use (&$params, $where) {
            $param = $matches[1];
            $params[] = $param;
            
            // Obtener regex personalizada o usar por defecto
            $regex = $where[$param] ?? '[^/]+';
            
            return "($regex)";
        }, $pattern);

        // Construir regex final
        $regex = '#^/' . $pattern . '/?$#';

        return [
            'regex' => $regex,
            'params' => $params,
            'pattern' => $pattern
        ];
    }

    /**
     * Verificar si un patrón tiene parámetros
     */
    public function hasParameters(string $pattern): bool
    {
        return strpos($pattern, '{') !== false;
    }

    /**
     * Extraer parámetros de un patrón
     */
    public function extractParameters(string $pattern): array
    {
        preg_match_all('/\{([^}]+)\}/', $pattern, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Generar URL desde patrón y parámetros
     */
    public function generateUrl(string $pattern, array $params = []): string
    {
        $url = $pattern;
        
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', $value, $url);
        }
        
        return '/' . ltrim($url, '/');
    }

    /**
     * Validar patrón de ruta
     */
    public function validatePattern(string $pattern): bool
    {
        // Verificar que no tenga caracteres inválidos
        if (preg_match('/[<>:"|?*]/', $pattern)) {
            return false;
        }
        
        // Verificar que los parámetros estén bien formados
        if (preg_match('/\{[^}]*\{/', $pattern)) {
            return false;
        }
        
        return true;
    }

    /**
     * Obtener regex por defecto para tipo de parámetro
     */
    public function getDefaultRegex(string $type): string
    {
        return match ($type) {
            'int', 'integer' => '\d+',
            'float' => '\d+\.?\d*',
            'bool', 'boolean' => '(true|false|1|0)',
            'alpha' => '[a-zA-Z]+',
            'alnum' => '[a-zA-Z0-9]+',
            'slug' => '[a-z0-9-]+',
            'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
            'email' => '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}',
            default => '[^/]+'
        };
    }
}
