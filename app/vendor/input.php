<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    


/**
 * input
 *
 * @package     Tero
 * @subpackage  Vendor
 * @category    Library
 * @author      Daniel Romero 
 */ 
class input
{

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
     * Constructor store static instance
     * 
     * 
     */
	function __construct() {

		self::$instancia = $this;
	}


    /**
     * Check if has server variables
     *
     * @return boolean 
     */
	public function has_server()
	{
		return count($_SERVER)>0 ? TRUE : FALSE;
	}	


    /**
     * Check if has server variables item
     *
     * @param string optional
     * @return mixed 
     */
	public function server($key='')
	{ 

		if($key)
		{
			$ret = isset($_SERVER[$key]) ? $_SERVER[$key] : FALSE;
		}
		else
		{
			$ret = new stdclass;

			foreach ($_SERVER as $key => $value) 
			{
				$ret->{$key} = $value;
			}
		}

		return $ret;
	}
	

    /**
     * Check if has post variables
     *
     * @return boolean 
     */
	public function has_post()
	{
		return count($_POST)>0 ? TRUE : FALSE;
	}

    public function has_put()
    {
        $_SERVER['REQUEST_METHOD']==="PUT" ? parse_str(file_get_contents('php://input', false , null, -1 , $_SERVER['CONTENT_LENGTH'] ), $_PUT): $_PUT=array();

        return count($_PUT)>0 ? TRUE : FALSE;
    }

    public function has_options()
    {
        $_SERVER['REQUEST_METHOD']==="OPTIONS" ? parse_str(file_get_contents('php://input', false , null, -1 , $_SERVER['CONTENT_LENGTH'] ), $_OPTIONS): $_OPTIONS=array();

        return count($_OPTIONS)>0 ? TRUE : FALSE;
    }

    public function has_payload()
    {
        $request_body = file_get_contents('php://input');

        $_PAYLOAD = json_decode($request_body);

        return count($_PAYLOAD)>0 ? TRUE : FALSE;
    }
    /**
     * Check if has post variables item
     *
     * @param string optional
     * @return mixed 
     */
	public function post($key='')
	{ 
		if($key)
		{
			$ret = isset($_POST[$key]) ? $_POST[$key] : FALSE;
		}
		else
		{
			$ret = new stdclass;

			foreach ($_POST as $key => $value) {
				$ret->{$key} = $value;
			}
		}

		return $ret;
	}

    public function put($key='')
    {
        $_SERVER['REQUEST_METHOD']==="PUT" ? parse_str(file_get_contents('php://input', false , null, -1 , $_SERVER['CONTENT_LENGTH'] ), $_PUT): $_PUT=array();

        if($key)
        {
            $ret = isset($_PUT[$key]) ? $_PUT[$key] : FALSE;
        }
        else
        {
            $ret = new stdclass;

            foreach ($_PUT as $key => $value) {
                $ret->{$key} = $value;
            }
        }

        return $ret;
    }


    public function options($key='')
    {
        $_SERVER['REQUEST_METHOD']==="OPTIONS" ? parse_str(file_get_contents('php://input', false , null, -1 , $_SERVER['CONTENT_LENGTH'] ), $_OPTIONS): $_OPTIONS=array();

        if($key)
        {
            $ret = isset($_OPTIONS[$key]) ? $_OPTIONS[$key] : FALSE;
        }
        else
        {
            $ret = new stdclass;

            foreach ($_OPTIONS as $key => $value) {
                $ret->{$key} = $value;
            }
        }

        return $ret;
    }


    public function payload($key='')
    {
        $request_body = file_get_contents('php://input');

        $_PAYLOAD = json_decode($request_body);

        if($key)
        {
            $ret = isset($_PAYLOAD->{$key}) ? $_PAYLOAD->{$key} : FALSE;
        }
        else
        {
            $ret = $_PAYLOAD;
        } 

        return $ret;
    }

    /**
     * Convert array in json
     *
     * @param array
     * @return string 
     */
	public function post2json($array)
	{ 

		$obj = new stdclass;

		foreach ($array as $value) {
			$obj->{$value} = $this->post($value);
		}

		return json_encode($obj);
	}

}