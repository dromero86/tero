<?php

/**
 * Rutas Web - Tero Framework
 * 
 * @package Tero\Routes
 * @author Daniel Romero
 * @version 4.2.2-dev
 */

return [
    // Ruta de ejemplo
    [
        'pattern' => '/',
        'handler' => function($request) {
            return [
                'message' => 'Welcome to Tero Framework!',
                'version' => '4.2.2-dev',
                'timestamp' => date('c')
            ];
        },
        'options' => [
            'methods' => ['GET'],
            'name' => 'home'
        ]
    ],

    // Ruta de ejemplo con parámetros
    [
        'pattern' => '/hello/{name}',
        'handler' => function($request, $name) {
            return [
                'message' => "Hello, $name!",
                'timestamp' => date('c')
            ];
        },
        'options' => [
            'methods' => ['GET'],
            'name' => 'hello'
        ]
    ],

    // Ruta de ejemplo POST
    [
        'pattern' => '/api/data',
        'handler' => function($request) {
            $data = json_decode($request->body ?? '{}', true);
            return [
                'received' => $data,
                'timestamp' => date('c')
            ];
        },
        'options' => [
            'methods' => ['POST'],
            'name' => 'api.data',
            'middleware' => ['api']
        ]
    ]
];
