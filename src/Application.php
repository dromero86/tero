<?php 

namespace Tero;

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2025 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    


class Application{

    public static function Get($class){

        $framework = new Framework(__DIR__);
        $container = $framework->dependencyInyection();
        return $container->get($class);
    } 
}