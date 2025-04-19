<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class request_parser
{
    private static ?self $instance = null;
    private bool $isCli; 
    private array $requestParams;
    //private string $requestUri;
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
            //$this->requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
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

        // Buscar action en GET o POST
        foreach (['requestParams'] as $source) {
            if (isset($this->$source['action'])) {
                $action = $this->$source['action'];
                unset($this->$source['action']);
                $arguments = array_merge($this->requestParams);
                return ['action' => $action, 'arguments' => $arguments];
            }
        }

        // Parsear URL amigable
        $friendly_url = $this->match_params();

        if($friendly_url instanceof stdClass){
            if($friendly_url->method) $action = $friendly_url->method;
            if(!empty($friendly_url->method)) $arguments = $friendly_url->param;
        }

        // Combinar con parámetros GET
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


    /**
     * Pattern for url match params
     * 
     */
    public function pattern_uri_regex($matches) 
    {
        return '([a-zA-Z0-9_\+\-%]+)';
    }

    /**
     * GET url for rewrite method
     * 
     */
    private function get_client_route()
    {
        $uri  = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
        $file = isset($_SERVER["PHP_SELF"]) ? $_SERVER["PHP_SELF"] : "";
        $dir  = pathinfo($file,PATHINFO_DIRNAME);
        $uri  = str_replace($dir."/", "", $uri);
        $uri  = trim($uri,"/");
        $uri  = trim($uri); 

        return $uri;
    }


    /**
     * GET method from rewrite with params 
     * 
     */
    private function match_params()
    {
        $request_uri = $this->get_client_route();
        $found       = FALSE;
        $return      = FALSE;

        $request_uri = trim($request_uri ,"/"); 

        foreach ($this->routes as $key=>$pattern_uri)
        { 
            preg_match_all('/:([0-9a-zA-Z_]+)/', $pattern_uri, $names, PREG_PATTERN_ORDER); 

            $names = $names[0];

            $pattern_uri_regex  = preg_replace_callback('/:[[0-9a-zA-Z_]+/', array($this, 'pattern_uri_regex'), $pattern_uri);
            $pattern_uri_regex .= '/?';

            if(count($names))
            {
                $params = []; 
 
                if (preg_match('@^' . $pattern_uri_regex . '$@', $request_uri, $values))
                {
                    array_shift($values);

                    foreach($names as $index => $value) 
                    {
                        $params[substr($value, 1)] = urldecode($values[$index]); 
                    }
    
                    $return = new stdclass;
                    $return->method = $pattern_uri;
                    $return->param  = $params;
                    return $return;
                }
            } 
        }

        return $return;
    }
}