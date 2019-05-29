<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

/**
 * Skeleton
 *
 * @package     Tero
 * @subpackage  Vendor
 * @category    Library
 * @author      Daniel Romero 
 */ 

class Skeleton {


    /**
     * Class config object
     *
     * @var object
     */
	private $config;


    /**
     * page route
     *
     * @var string
     */
	private $route         = "";


    /**
     * object parser
     *
     * @var object
     */
	private $parser;


    /**
     * Config view file
     *
     * @var string
     */
    private $config_file   = "";


    /**
     * Config vars file
     *
     * @var string
     */
    private $vars_file     = "";



    /**
     * theme folder
     *
     * @var string
     */
    private $theme_folder  = "";

    
    /**
     * Class config file
     *
     * @var string
     */
    private $theme_file  = "app/config/theme.json";


    /**
     * enable css, default false
     *
     * @var boolean
     */
    private $create_css  = FALSE;


    /**
     * css array
     *
     * @var array
     */
    private $css         = array();
    

    /**
     * Run time object for Singleton Pattern
     *
     * @var object 
     */     
    private static $instancia= null;


    /**
     * Get the static core instance 
     *
     * @return object
     */
    public static function getInstance()
    {
        $that = null;

        if (!self::$instancia instanceof self)
        {
            if(self::$instancia == null)
            {
                $that = new self;
                self::$instancia = $that;
            } 
        }
        else
        {
            $that = self::$instancia;
        }

        if($that == null)
            die(__CLASS__.": Fallo el singleton");

        return $that;
    }


    /**
     * Constructor store static instance and load config
     * 
     * 
     */
    function __construct() 
    {
        self::$instancia = $this;

        $this->read_theme();
        $this->init();
    }


    /**
     * Extract config from theme.json
     * 
     * 
     */
    function read_theme()
    {
        $config = file_get_json(BASEPATH.$this->theme_file);

        $this->config_file = $config->path.$config->view;
        $this->vars_file   = $config->path.$config->vars;
        $this->theme_folder= $config->path;
    }


    public function get_theme_folder()
    {
        return $this->theme_folder;
    }

    /**
     * load config and parser
     * 
     * 
     */
    function init()
    {
        
		$this->before_connect($this->config_file);
		$this->parser = Parser::getInstance();
	}


    /**
     * before conect load config
     * 
     * 
     */
    private function before_connect($config, $extern=FALSE)
    {
		if($extern==FALSE)
			$this->config = file_get_json(BASEPATH.$config);
		else
			$this->config = file_get_json($config);
		
		 
    }


    /**
     * Set route
     * 
     * @param string route
     */
    private function set_route($route)
    {
    	$this->route = $route;
    }


    /**
     * Extract css from route
     * 
     * @param string file
     */
    private function _extract_css($file)
    {
        $cssName = pathinfo($file, PATHINFO_FILENAME);
        $cssDir  = pathinfo($file, PATHINFO_DIRNAME );
        $cssFile = $cssDir."/".$cssName.".css";

        if(is_file($cssFile))
        {
            $this->css[] = file_get_contents($cssFile);
        }
        else
        { 
            _LOG(core::getInstance(), __CLASS__, "No se puede extraer el css de {$cssFile}");
        }
    }


    /**
     * write css cache
     *  
     */
    private function _write_css()
    {
        $cachedir = BASEPATH."ui/css/cache/";

        $data = implode("", $this->css);
        $path = "{$cachedir}{$this->route}.css";

        if(!is_dir($cachedir))
        {
            if(mkdir($cachedir,0777,TRUE)==FALSE)
            {
                _LOG(core::getInstance(), __CLASS__, "No se puede crear {$cachedir}");
            }
        }

        file_put_contents($path, $data);
    }


    /**
     * build all
     *  
     */
    private function build()
    {
        $skeleton = "";

        if(isset($this->config->{$this->route}))
        {
            $skeleton = $this->config->{$this->route};
        }
        else
        {
            _LOG(core::getInstance(), __CLASS__, "Error al procesar la ruta [{$this->route}]");
            return "";
        }

    	$content  = array();

        if(isset($skeleton->content))
            if(is_array($skeleton->content))
            {
            	foreach( $skeleton->content as $pieces)
            	{
                    if(is_file($this->theme_folder."views/".$pieces->file))
                    {
                        $content[] = $this->parser->parse( $this->theme_folder."views/".$pieces->file , array() , TRUE);
                        $this->_extract_css($this->theme_folder."views/".$pieces->file);
                    }
                    else
                    {
                        _LOG(core::getInstance(), __CLASS__, " Content - {$pieces->file} no existe");
                    }
            	}
            }
            else
            {
                if(is_file($this->theme_folder."views/".$skeleton->content))
                {
                    $content[] = $this->parser->parse( $this->theme_folder."views/".$skeleton->content , array() , TRUE);
                    $this->_extract_css($this->theme_folder."views/".$skeleton->content);
                }
                else
                {
                    _LOG(core::getInstance(), __CLASS__, "Content - {$skeleton->content} no existe");
                }
            }

        $header = "";

        if(isset($skeleton->header))
            if(is_array($skeleton->header))
            {
                foreach( $skeleton->header as $pieces)
                {
                    if(is_file($this->theme_folder."views/".$pieces->file))
                    {
                        $content[] = $this->parser->parse( $this->theme_folder."views/".$pieces->file , array() , TRUE);
                        $this->_extract_css($this->theme_folder."views/".$pieces->file);
                    }
					else
					{
						_LOG(core::getInstance(), __CLASS__, "header - {$pieces->file} no existe");
					}
                }
            }
            else
            {
                if(is_file($this->theme_folder."views/".$skeleton->header))
                {
                    $header = $this->parser->parse( $this->theme_folder."views/".$skeleton->header , array()  , TRUE);
                    $this->_extract_css($this->theme_folder."views/".$skeleton->header);
                }
				else
				{
					_LOG(core::getInstance(), __CLASS__, "header - {$skeleton->header} no existe");
				}
            }

        $footer = "";

        if(isset($skeleton->footer))
            if(is_array($skeleton->footer))
            {
                foreach( $skeleton->footer as $pieces)
                {
                    if(is_file($this->theme_folder."views/".$pieces->file))
                    {
                        $content[] = $this->parser->parse( $this->theme_folder."views/".$pieces->file , array() , TRUE);
                        $this->_extract_css($this->theme_folder."views/".$pieces->file);
                    }
					else
					{
						_LOG(core::getInstance(), __CLASS__, "footer - {$pieces->file} no existe");
					}
                }
            }
            else
            {
                if(is_file($this->theme_folder."views/".$skeleton->footer))
                {
                    $footer = $this->parser->parse( $this->theme_folder."views/".$skeleton->footer , array()  , TRUE);
                    $this->_extract_css($this->theme_folder."views/".$skeleton->footer);
                }
				else
				{
					_LOG(core::getInstance(), __CLASS__, "footer - {$skeleton->footer} no existe");
				}
            }

		$layout  = array
		(
			'header'  => $header,
			'content' => count($content) ? implode("",$content) : "",
			'footer'  => $footer
		);

        $output ="";

        if(isset($skeleton->layout))
        {
             if(is_file($this->theme_folder."views/".$skeleton->layout))
             {
                $output = $this->parser->parse( $this->theme_folder."views/".$skeleton->layout , $layout, TRUE );
                $this->_extract_css($this->theme_folder."views/".$skeleton->layout);
             }
             else
             {
                _LOG(core::getInstance(), __CLASS__, "Layout - {$skeleton->layout} no existe ");
             }
        }

    	return $output;
    }

    /**
     * merge vars.json from theme
     * 
     * @param string route
     * @param array  data
     */
    private function attach_vars($route, &$data)
    {
        $vararray = file_get_json(BASEPATH.$this->vars_file);

        if( isset($vararray->{$route}))
        {
            $item = $vararray->{$route};

            foreach ($item as $valueobject)
            {
                if(isset($valueobject->name) && isset($valueobject->value))
                {
                    $key = $valueobject->name;
                    $val = "";

                    if(is_string($valueobject->value))
                    {
                        $val = $valueobject->value;
                    }
                    else
                    {
                        if(is_array($valueobject->value))
                        {
                            $val = array();

                            foreach ($valueobject->value as $itemvar)
                            {
                                $cell = array();
                                foreach($itemvar as $k=>$v)
                                {
                                    $cell [$k]=$v;
                                }
                                $val[]=$cell;
                            }
                        }
                    }

                    $data[$key]=$val;
                }
            }
        }
    }

    /**
     * public write > merge route with data
     * 
     * @param string route
     * @param array  data
     * @param boolean output
     */
    public function write($route, $data=array(), $return=FALSE)
    {
	    $this->set_route($route);

        $data["csscache"]=$route; 

        $html = $this->build();

        if( $this->create_css ==TRUE )$this->_write_css();

        $this->attach_vars("_global", $data);
        $this->attach_vars($route   , $data);
        
        for ($i=0; $i<3; $i++) 
        { 
            $html = $this->parser->parse_string(  $html , $data , TRUE);
        }
 
        if($return == TRUE)
        {
            return $html;
        }
        else
        {
            header('Content-type: text/html; charset=utf8');
            echo ($html);
        }
    }
 

}