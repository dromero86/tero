<?php  

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2025 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

// report all errors
error_reporting(E_ALL);

// display all errors
ini_set('display_errors', '1');

// internal encoding
mb_internal_encoding( 'UTF-8' );
mb_http_output      ( 'UTF-8' ); 

// config system path
$system_path = "./"; if (realpath($system_path) !== FALSE)  $system_path = realpath($system_path).'/'; 

// ensure there's a trailing slash
$system_path = rtrim($system_path, '/').'/';

// Is the system path correct?
if (!is_dir($system_path)) exit("Your system folder path does not appear to be set correctly. Please open the following file and correct this: ".pathinfo(__FILE__, PATHINFO_BASENAME));

// define global paths

define('EXT'        , '.php');
define('SELF'       , pathinfo(__FILE__, PATHINFO_BASENAME)); 
define('BASEPATH'   , str_replace("\\", "/", $system_path)); 
define('FCPATH'     , str_replace(SELF, '' , __FILE__    ));
define('SYSDIR'     , trim(strrchr(trim(BASEPATH, '/'), '/'), '/'));

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

    function __construct() {

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
        $requestParser = new RequestParser();
        $requestParser->setRoutes($this->routes);
        $request        = $requestParser->getRequest();   

        if( !isset($this->{$request->action}) ) {
            http_response_code(404);
            die("Route {$_SERVER['PATH_INFO']} not found ");
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
