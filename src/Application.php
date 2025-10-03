<?php 

namespace Tero;

use Tero\Core\Core;
use Tero\Config\ConfigManager;
use Tero\Framework\Framework;

/**
 * Tero Framework - Application Entry Point
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2025 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

class Application
{
    private static ?Core $core = null;
    private static ?ConfigManager $config = null;
    private static ?Framework $framework = null;

    /**
     * Obtener instancia del Core
     */
    public static function Get(string $class = Core::class): mixed
    {
        if ($class === Core::class) {
            if (self::$core === null) {
                self::$core = new Core();
            }
            return self::$core;
        }

        // Usar el sistema de DI para otras clases
        $framework = self::getFramework();
        $container = $framework->dependencyInyection();
        return $container->get($class);
    }

    /**
     * Obtener instancia de configuración
     */
    public static function getConfig(): ConfigManager
    {
        if (self::$config === null) {
            self::$config = new ConfigManager(__DIR__);
        }
        return self::$config;
    }

    /**
     * Obtener instancia del Framework
     */
    public static function getFramework(): Framework
    {
        if (self::$framework === null) {
            self::$framework = new Framework(__DIR__);
        }
        return self::$framework;
    }

    /**
     * Obtener instancia del Core (alias)
     */
    public static function getCore(): Core
    {
        return self::Get(Core::class);
    }

    /**
     * Crear nueva instancia de la aplicación
     */
    public static function create(): Core
    {
        return new Core();
    }

    /**
     * Verificar si la aplicación está inicializada
     */
    public static function isInitialized(): bool
    {
        return self::$core !== null;
    }

    /**
     * Obtener información de debug de la aplicación
     */
    public static function getDebugInfo(): array
    {
        $info = [
            'initialized' => self::isInitialized(),
            'version' => '4.2.2-dev',
            'framework' => 'Tero Framework',
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];

        if (self::$core) {
            $info['core'] = self::$core->getDebugInfo();
        }

        if (self::$config) {
            $info['config'] = self::$config->getDebugInfo();
        }

        return $info;
    }

    /**
     * Inicializar la aplicación
     */
    public static function initialize(): Core
    {
        if (self::$core === null) {
            self::$core = new Core();
        }
        return self::$core;
    }

    /**
     * Reiniciar la aplicación
     */
    public static function reset(): void
    {
        self::$core = null;
        self::$config = null;
        self::$framework = null;
    }
}