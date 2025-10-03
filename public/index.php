<?php

/**
 * Tero Framework - Public Entry Point
 * 
 * @package Tero
 * @author Daniel Romero
 * @version 4.2.2-dev
 * @link https://github.com/dromero86/tero
 */

// Cargar autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Obtener instancia de la aplicación
$App = Tero\Application::Get();

// Configurar rutas de ejemplo
$App->get('index', function() {
    return [
        'message' => 'Welcome to Tero Framework!',
        'version' => '4.2.2-dev',
        'timestamp' => date('c'),
        'note' => 'This is the public entry point'
    ];
});

$App->get('hello/{name}', function($request, $name) {
    return [
        'message' => "Hello, $name!",
        'timestamp' => date('c')
    ];
});

// Ejemplo de ruta API simple
$App->get('api/status', function() {
    return [
        'status' => 'ok',
        'framework' => 'Tero',
        'version' => '4.2.2-dev',
        'timestamp' => date('c'),
        'memory_usage' => memory_get_usage(true),
        'note' => 'This route uses the modern framework'
    ];
});

// Ejemplo de ruta protegida
$App->get('api/protected', function() {
    return [
        'message' => 'This is a protected route',
        'timestamp' => date('c')
    ];
});

// La aplicación se ejecutará automáticamente
