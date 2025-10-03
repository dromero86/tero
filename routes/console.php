<?php

/**
 * Rutas Console - Tero Framework
 * 
 * @package Tero\Routes
 * @author Daniel Romero
 * @version 4.2.2-dev
 */

return [
    // Comando de ejemplo
    [
        'command' => 'example',
        'handler' => function($args) {
            echo "This is an example console command!\n";
            echo "Arguments: " . implode(', ', $args) . "\n";
            return 0;
        },
        'options' => [
            'description' => 'Example console command',
            'arguments' => ['name']
        ]
    ],

    // Comando de información del sistema
    [
        'command' => 'info',
        'handler' => function($args) {
            echo "Tero Framework Information:\n";
            echo "Version: 4.2.2-dev\n";
            echo "PHP Version: " . PHP_VERSION . "\n";
            echo "Memory Usage: " . memory_get_usage(true) . " bytes\n";
            echo "Peak Memory: " . memory_get_peak_usage(true) . " bytes\n";
            return 0;
        },
        'options' => [
            'description' => 'Show framework information'
        ]
    ]
];
