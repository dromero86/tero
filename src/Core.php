<?php  

namespace Tero;

use Tero\RequestParser;

use Closure;
use Exception;

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2025 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

class Core {

    /**
     * Current version
     *
     * @var string
     */
    const VERSION = '4.2.2-dev';
    
    /**
     * User controller array 
     *
     * @var array
     */     
    private $routes           = []; 

    /**
     * encoding value, default utf-8
     *
     * @var string
     */    
    private $encoding         = 'UTF-8' ;

    /**
     * Timezone server, default 'America/Argentina/Buenos_Aires' ;)
     *
     * @var string
     */ 
    private $timezone         = 'America/Argentina/Buenos_Aires';

    /**
     * amount memory allow to app, default 10mb, zero for disable
     *
     * @var string
     */ 
    private $leak             = '10M'   ;

    /**
     * show errors, default on
     *
     * @var string
     */ 
    private $error            = 'On'    ; 

    function __construct(private RequestParser $requestParser) {

        header("X-Core: Tero ".self::VERSION); 

        $this->after_load();
    }

    private function after_load() 
    {
        date_default_timezone_set( $this->timezone               ); 
        ini_set                  ( "display_errors", $this->error);
        ini_set                  ( "memory_limit"  , $this->leak );

        mb_internal_encoding( $this->encoding );
        mb_http_output      ( $this->encoding ); 
    }
 
    public function run() 
    { 
        $this->requestParser->setRoutes($this->routes);
        $request = $this->requestParser->getRequest();   

        if( !isset($this->{$request->action}) ) {
            http_response_code(404);
            
            $PATH_INFO = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : "/";

            die("Route {$PATH_INFO} not found ");
        }
        
        if( !($this->{$request->action} instanceof Closure)) throw new Exception("Method {$request->action} is not closure");
        if( !is_callable($this->{$request->action})        ) throw new Exception("Method {$request->action} do not callable");
        
        call_user_func_array($this->{$request->action}, $request->arguments);
    }

    public function get($pattern, $callback) 
    {
        $paramNames = [];
        $regex = $this->patternToRegex($pattern, $paramNames);

        $this->routes[$pattern]= [
            'method'    => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            'pattern'   => $pattern,
            'regex'     => $regex,
            'params'    => $paramNames,
            'callback'  => $callback
        ];
        $this->{$pattern} = Closure::bind($callback, $this, 'core');
    }

    private function patternToRegex($pattern, &$paramNames) {
        $paramNames = [];
        $parts = explode('/', $pattern);
        $regexParts = [];
        
        foreach ($parts as $part) {
            if (strpos($part, ':') === 0) {
                $paramName = substr($part, 1);
                $paramNames[] = $paramName;
                $regexParts[] = '(?<' . $paramName . '>[^\/]+)';
            } else {
                $regexParts[] = preg_quote($part, '/');
            }
        } 
        
        return '/^' . implode('\/', $regexParts) . '$/';
    }
}
