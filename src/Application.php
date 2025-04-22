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

    public static function Run($directory, $class){

        $framework = new Framework();
        $framework->dotEnvLoad($directory);
        $framework->setEnvVars(["BASEPATH"=> $directory]);
        $container = $framework->dependencyInyection();
        $entrypointService = $container->get($class);
        $entrypointService->run(); 
    }
}