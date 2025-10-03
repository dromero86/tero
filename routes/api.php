<?php

/**
 * Rutas API - Tero Framework
 * 
 * @package Tero\Routes
 * @author Daniel Romero
 * @version 4.2.2-dev
 */

return [
    // API Health Check
    [
        'pattern' => '/api/health',
        'handler' => function($request) {
            return [
                'status' => 'ok',
                'timestamp' => date('c'),
                'version' => '4.2.2-dev'
            ];
        },
        'options' => [
            'methods' => ['GET'],
            'name' => 'api.health'
        ]
    ],

    // API Users
    [
        'pattern' => '/api/users',
        'handler' => function($request) {
            return [
                'users' => [
                    ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
                    ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com']
                ],
                'total' => 2
            ];
        },
        'options' => [
            'methods' => ['GET'],
            'name' => 'api.users.index',
            'middleware' => ['api', 'auth']
        ]
    ],

    // API User by ID
    [
        'pattern' => '/api/users/{id}',
        'handler' => function($request, $id) {
            return [
                'user' => [
                    'id' => (int)$id,
                    'name' => 'User ' . $id,
                    'email' => "user{$id}@example.com"
                ]
            ];
        },
        'options' => [
            'methods' => ['GET'],
            'name' => 'api.users.show',
            'middleware' => ['api', 'auth']
        ]
    ]
];
