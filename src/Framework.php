<?php

namespace Tero;

use Dotenv\Dotenv;
use DI\ContainerBuilder;

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2025 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

class Framework {

    private $basepath = FALSE;

    function __construct($basepath = FALSE){
        $this->basepath = $basepath ? $basepath : dirname( __DIR__ );
    }

    public function getBasepath(){
        return $this->basepath;
    }

    public function setEnvVars($data){
        foreach($data as $key=>$value){
            $_ENV[$key]=$value;
        }
    }

    public function dotEnvLoad(){
        $envFile = "{$this->basepath}/.env";

        if(file_exists($envFile)){
            $dotenv = Dotenv::createImmutable($this->basepath);
            $dotenv->load();
        }
    }

    public function dependencyInyection(){

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->useAutowiring(true); 
        $containerBuilder->addDefinitions([ 'directories' => [ $this->basepath ] ]);
        
        return $containerBuilder->build();
    }
}