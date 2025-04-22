<?php

namespace Tero;

use stdClass;
/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2025 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

/**
 * Request Parser
 *
 * @package     Tero
 * @subpackage  Vendor
 * @category    Library
 * @author      Daniel Romero 
 */

class RequestParser
{
    private static ?self $instance = null;
    private bool $isCli; 
    private array $requestParams; 
    private array $cliArgs;
    private $routes;

    function __construct()
    {
        $this->isCli = PHP_SAPI === 'cli';

        $argv = isset($_SERVER['argv']) ? $_SERVER['argv'] : [];
        
        if ($this->isCli) {
            $this->cliArgs = array_slice($argv, 1);
        } else {
            $this->requestParams = $_REQUEST; 
        }
    }

    public function setRoutes($routes){
        $this->routes = $routes;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function ingress(): array
    {
        return $this->isCli ? $this->parseCli() : $this->parseWeb();
    }

    private function parseWeb(): array
    {
        $action = '';
        $arguments = [];
 
        foreach (['requestParams'] as $source) {
            if (isset($this->$source['action'])) {
                $action = $this->$source['action'];
                unset($this->$source['action']);
                $arguments = array_merge($this->requestParams);
                return ['action' => $action, 'arguments' => $arguments];
            }
        }

        $friendly_url = $this->match_params();

        if($friendly_url instanceof stdClass){
            if($friendly_url->method) $action = $friendly_url->method;
            if(!empty($friendly_url->method)) $arguments = $friendly_url->param;
        }

        return [
            'action' => $action,
            'arguments' => array_merge($arguments, $this->requestParams)
        ];
    }

    private function parseCli(): array
    {
        $action = '';
        $arguments = [];

        foreach ($this->cliArgs as $arg) {
            if (str_contains($arg, '=')) {
                [$key, $value] = explode('=', $arg, 2);
                $arguments[$key] = $value;
                if ($key === 'action') {
                    $action = $value;
                }
            } else {
                $arguments[] = $arg;
            }
        }

        // Eliminar action de los argumentos si existe
        if (isset($arguments['action'])) {
            unset($arguments['action']);
        }

        // Obtener action de argumentos posicionales si no se encontró
        if (empty($action) && !empty($arguments)) {
            $action = array_shift($arguments);
        }

        return ['action' => $action, 'arguments' => $arguments];
    }

    public function getRequest(){

        $ingress            = $this->ingress();
        $object             = new stdClass;
        $object->action     = $ingress['action'] ? $ingress['action'] : 'index';
        $object->arguments  = $ingress['arguments'];

        return $object;
    }

    public function match_params() {
        $path = $this->getCurrentPath(); 
        
        foreach ($this->routes as $route) {   
            if (preg_match($route['regex'], $path, $matches)) {
                $params = [];
                foreach ($route['params'] as $param) {
                    $params[] = $matches[$param];
                }
                
                $object = new stdClass;
                $object->method = $route['pattern'];
                $object->param  = $params;
                
                return $object;
            }
        }
        
        return FALSE;
    }
      
    private function getCurrentPath() {
        if (!empty($_SERVER['PATH_INFO'])) {
            $path = $_SERVER['PATH_INFO'];
            $path = trim($path, "/");

            return $path;
        }
        
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = trim($path, "/");

        return $path;
    }
}