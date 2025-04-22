<?php

namespace Tero;

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2025 Daniel Romero
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
class RequestInput
{

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

}