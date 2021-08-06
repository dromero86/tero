<?php  

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
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

require FCPATH."core_helper".EXT;

/**
 * core 
 * 
 * Load all resources from core.json
 * can be "library" or "Helper"
 * - Library: the object is allocate to core instance as property ex: $core->{library_name} 
 * - Helper: included file but not work as class
 * After stack to method "get" in intern array.
 * Analize pattern url and if match, exec the method
 * 
 */

class core {

    /**
     * Current version
     *
     * @var string
     */
    const VERSION = '4.1.0-stable';


    /**
     * Class config file
     *
     * @var string
     */
    private $config_file      = "app/config/core.json";
    

    /**
     * Method extract from url
     *
     * @var string
     */    
    public  $page             = ""      ;
    

    /**
     * Debug array 
     *
     * @var array
     */     
    private $stacklog         = array() ;


    /**
     * User controller array 
     *
     * @var array
     */     
    private $routes           = array() ; 


    /**
     * encoding value, default utf-8
     *
     * @var string
     */    
    private $encoding         = 'UTF-8' ;


    /**
     * default user controller method, default index
     *
     * @var string
     */    
    private $default_method   = 'index' ;


    /**
     * default argument get to analize {url}?{default_key}=..., default action
     *
     * @var string
     */     
    private $default_key      = 'action';


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


    /**
     * debug mode, default off
     *
     * @var string
     */ 
    private $debug            = FALSE   ;


    /**
     * object with config.json items parsed
     *
     * @var object 
     */ 
    private $config           = NULL    ;


    /**
     * Run time object for Singleton Pattern
     *
     * @var object 
     */ 
    private static $instancia = NULL    ;






    /**
     * Get the static core instance 
     *
     * @return object
     */
    public static function getInstance()
    {
        $that = NULL;

        if (!self::$instancia instanceof self)
        {
            if(self::$instancia == NULL)
            {
                $that = new self;
                self::$instancia = $that;
            } 
        }
        else
        {
            $that = self::$instancia;
        }

        if($that == NULL)
        {
            die("[core] >> Raise Error >> Not get instance.");
        }

        return $that;
    }



    /**
     * Constructor store static instance and load config
     * 
     * 
     */
    function __construct() {

        self::$instancia = $this; 

        $this->after_load();
    }

    public function is_error_handling_enable()
    {
        return $this->config->error_handling;
    }

    /**
     * Load config from core.json
     * 
     * 
     */
    private function after_load() 
    { 
        $this->config = file_get_json( BASEPATH.$this->config_file );
 
        if( isset($this->config->debug      ) ){ $this->debug       = $this->config->debug  ; }
        if( isset($this->config->timezone   ) ){ $this->timezone    = $this->config->timezone ; }
        if( isset($this->config->leak       ) ){ $this->leak        = $this->config->leak   ; }
        if( isset($this->config->error      ) ){ $this->error       = $this->config->error  ; } 
        if( isset($this->config->encoding   ) ){ $this->encoding    = $this->config->encoding  ; }     

        date_default_timezone_set( $this->timezone               ); 
        ini_set                  ( "display_errors", $this->error);
        ini_set                  ( "memory_limit"  , $this->leak );

        mb_internal_encoding( $this->encoding );
        mb_http_output      ( $this->encoding ); 


        foreach ($this->config->{"loader"} as $item)
        {
            if( isset($item->{"helper"}) || !isset($item->{"library"}) )
            {
                $this->load_helper($item->{"file"});
            }
            else
            {
                $this->load_library
                ( 
                    TRUE, 
                    $item->{"file"}, 
                    $item->{"library"}->{"class"},  
                    isset($item->{"library"}->{"rename"}) ? $item->{"library"}->{"rename"} : "" 
                );
            }
        } 
    }


    /**
     * Main app exec
     * 
     *
     * Work with url's types
     * 
     * - /?action=index
     * - /index
     * - /index-:id => :id is param
     * - /index?another=param
     * - /index-:id?another=param
     * 
     * Method url: INDEX
     * Param order: left to right
     * 
     * extract url method in this order:
     * - Match Simple: find method in rewrited url without regex url, if not
     * - Match Params: find method in rewrited url with regex expression, if not
     * - Default     : parse GET params
     * 
     * 
     * if method exist and is callable, call it 
     * 
     * 
     */
    public function run() 
    {  
        //obtain method from rewrited url simple (without regex)
        $method = $this->match_simple();

        $PARAM  = array();

        if($method == FALSE)
        { 
            //obtain method from rewrited url simple (with regex)
            $method = $this->match_params();

            if($method == FALSE)
            {
                //if not, default
                $method = $this->default_method; 
            }
            else
            {
                //if work, obtain method and param array
                $PARAM  = $method->param;
                $method = $method->method; 
            }
        }

        if($method == $this->default_method  )
        {
            //if default method work with GET's param
            $method = isset($_GET[ $this->default_key ]) ? $_GET[ $this->default_key ] : FALSE ; if ($method == FALSE) { $method = $this->default_method; }
            $PARAM  = $_GET;
        }
		
		//Version 4.0.2 
        //support console commands
        if( isset($_SERVER["argv"]) )
        {  
            foreach ($_SERVER["argv"] as $k => $item) 
            {  
                if($item != "index.php")
                {  
                    if( strpos($item, "=") !== false )
                    {
                        list( $get_k, $get_v ) = explode("=", $item);
                        $PARAM[$get_k]=$get_v;
                        $method = $get_v;
                    }
                    else
                    {  
                        $method = "index";
                    } 
                }
            }  
        }
 
        $this->page = $method ; 


        //method found?
        if ($method)
        { 
            //method exist?
            if(isset($this->$method))
            {
                //method work ( add as $App->get("...." , ...)  )
                if( $this->$method instanceof Closure )
                {
                    //method has function?
                    if (is_callable($this->$method))
                    {
                        $param = $PARAM;

                        unset($param[ $this->default_key ]);

                        try
                        {

                            // finally call it

                            $this->parameters = $param;
                            $fn               = $this->$method;

                            call_user_func_array($fn, $param);
                        }
                        catch (Exception $e)
                        {
                            _LOG($this, __CLASS__, "{$method} trigger error {$e->getMessage()}");
                        }
                    }
                    else
                    {
                        _LOG($this, __CLASS__, "The method {$method} is not callable");
                    }
                }
                else
                {
                    _LOG($this, __CLASS__, "The method {$method} isn't exists");
                }
            }
            else
            {
                _LOG($this, __CLASS__, "The method {$method} isn't exists");
            }
        }
        else
        {
            _LOG($this, __CLASS__, "Non action");
        }

        $this->after_run();
    }

    /**
     * User controller
     * 
     * 
     * $App->get("index", function([param1, param2, ..., paramn]){ ... });
     * 
     * This method work for   
     * 
     * /?action=index&param1=...&param2=...&paramn=... 
     * 
     * or (if rewrite enable)
     * 
     * /index?param1=...&param2=...&paramn=... 
     * 
     * REWRITE MODE
     * 
     *          [  PATTERN  ]         [ SERIALIZED PARAM ]
     * $App->get("index-:id", function(       $id        ){ ... });
     * 
     * 
     */
    public function get($name, $function) 
    {
        $this->routes[$name]= $name;
        $this->{$name}      = Closure::bind($function, $this, 'core');
    }


    /**
     * add item to log array ( used in library's )
     * 
     * 
     */ 
    public function write_log($string)
    {
        $this->stacklog[]=$string;
    }


    /**
     * Allow load helper runtime inside closure function
     * 
     * Example:
     * 
     * $App->get("...", function(...))
     * {
     *                         [  PATH     ][ FILE WITHOUT .PHP ]
     *      $this->load_helper("app/helpers/my_helper"          );
     * });
     * 
     */
    public function load_helper($module)
    {
        $HelperFile = BASEPATH."{$module}".EXT; 

        if( file_exists($HelperFile) )
            include $HelperFile;
        else 
            _LOG($this, __CLASS__, "Don't load helper {$HelperFile}");
    }


    /**
     * Allow load library runtime inside closure function
     * 
     * Example:
     * 
     * $App->get("...", function(...))
     * {
     *                        [ CALL IF NOT LOADED ] [       PATH           ] [ CLASS NAME ] [ INSTANCE NAME ]
     *      $this->load_helper( TRUE               , "app/library/my_library", "my_library", "cool_library"  );
     *            
     *      $this->cool_library->my_method_to_exec(...);
     * });
     * 
     */
    public function load_library($is_library, $path, $module, $name)
    {
        if( $is_library ) include BASEPATH."{$path}".EXT; 

        $this->load($module, $name ? $name : ""); 
    }


    /**
     * Pass objects from core to my library
     * 
     * class my_library
     * {
     *    
     *    public function load()
     *    {                             [  MY LIBRARY INSTANCE  ] [ ARRAY CORE OBJECTS TO PASS ]
     *       core::getInstance()->cloneIn($this                 , array("db", "data","parser"));
     * 
     *       //my library now get access to core methods
     * 
     *       // $this->db, $this->data, $this->parser
     * 
     *    }
     * }
     * 
     */
    public function cloneIn($object, $array)
    {
        foreach ($array as $item) 
        {
            $object->{$item} = $this->{$item};
        }
    }


    /**
     * Load library or helper from core.json
     * 
     */
    private function load($module, $as = '') {


        if(!class_exists($module))
            include BASEPATH."{$module}".EXT;
        else
            _LOG( $this, __CLASS__ , "{$module} ready load" );

        if ($as)
            if( !isset($this->{$as}) )
                $this->{$as} = new $module();
            else
                _LOG( $this, __CLASS__ , "{$module} [{$as}] ready defined" );
        else
            if( !isset($this->{$module}) )
                $this->{$module} = new $module();
            else
                _LOG( $this, __CLASS__ , "{$module} ready defined" );
    }

     /**
     * After run check for debug show
     * 
     */
    private function after_run()
    {
        if( $this->debug == TRUE )
        {
            $lines = implode("", $this->stacklog);

            echo
            "
            <div style='position:fixed; bottom:0; left:0; right:0; height:400px; overflow-y:auto; background:#F3F2F2; box-shadow:0px 1px 37px #000; z-index: 9999999999999999; '>
                <div class='panel panel-default'>
                    <div class='panel-heading'>Debug</div>

                    <ul class='list-group'>
                    {$lines}
                    </ul>
                </div>
            </div>
            ";
        }
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
     * GET method from rewrite simple 
     * 
     */
    private function match_simple()
    {
        $request = $this->get_client_route();
        $found   = FALSE;
        
        foreach ($this->routes as $value) 
        {
            if($request == $value)
            {
                $found = $request;
            }
        }

        return $found;
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
                $params = array(); 
 
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


function __TERO_ERROR_HANDLING_CORE($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        // Este código de error no está incluido en error_reporting
        return;
    }

    $txt = "";

    switch ($errno) {
    case E_USER_ERROR:
        $txt.= "<b>Mi ERROR</b> [$errno] $errstr<br />\n";
        $txt.= "  Error fatal en la línea $errline en el archivo $errfile";
        $txt.= ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
        $txt.= "Abortando...<br />\n";
        exit(1);
        break;

    case E_USER_WARNING:
        $txt.= "<b>Mi WARNING</b> [$errno] $errstr<br />\n";
        $txt.= "  warning en la línea $errline en el archivo $errfile";
        $txt.= ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";

        break;

    case E_USER_NOTICE:
        $txt.= "<b>Mi NOTICE</b> [$errno] $errstr<br />\n"; 
        $txt.= "  notice en la línea $errline en el archivo $errfile";
        $txt.= ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
        break;

    default:
        $txt.= "Tipo de error desconocido: [$errno] $errstr<br />\n";
        $txt.= "  error en la línea $errline en el archivo $errfile";
        $txt.= ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
        break;
    }


    mail_core_error("PHP ERROR", $txt); 
    /* No ejecutar el gestor de errores interno de PHP */
    return true;
}
 

// establecer el gestro de errores definido por el usuario

// Launch core instance as $App 
// used before for user method's
$App = new core(); 

if($App->is_error_handling_enable())
{
    set_error_handler("__TERO_ERROR_HANDLING_CORE");
}
