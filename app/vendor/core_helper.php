<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

/**
 * Core Helpers
 *
 * @package     Tero
 * @subpackage  Vendor
 * @category    Helpers
 * @author      Daniel Romero 
 */

if (!function_exists('file_get_json')) 
{
    /**
     * file_get_json
     * 
     * Open file and parse his content as json
     *
     * @param   string  
     * @return  object Return object stdclass with json parsed if success
     */
	function file_get_json($file)
	{
		$object = new stdclass;

		if(is_file($file))
		{
			$string = file_get_contents($file); 
			$object = json_decode($string); 

			$error = json_last_error();

			if( $error != JSON_ERROR_NONE  )
			{
				_LOG(core::getInstance(), $file, "Json error {$error}");
			}
		}
		else
		{
			_LOG(core::getInstance(), $file, "File not found");
		}

		return $object;
	}
}

if (!function_exists('_LOG')) 
{
    /**
     * _LOG
     *
     * stack log with format
     *
     * @param   object Core object
     * @param   string file origin of log
     * @param   string message to log  
     */

	function _LOG($App, $file, $message)
	{
		$hora = date('H:i:s', time());
		$App->write_log('<li class="list-group-item"><span class="label label-primary">'.$file.'</span> '.$message.' <span class="badge">'.$hora.'</span></li>');
	}
}

if (!function_exists('APP_LOG')) 
{
    /**
     * APP_LOG
     *
     * stack log with format with core call
     *
     * @param   object Core object
     * @param   string file origin of log
     * @param   string message to log  
     */

    function APP_LOG($cls, $message)
    {
        $App  = core::getInstance();
        $hora = date('H:i:s', time());
        $App->write_log('<li class="list-group-item"><span class="label label-primary">'.$cls.'</span> '.$message.' <span class="badge">'.$hora.'</span></li>');
    }
}


if (!function_exists('_LOG_WRITE')) 
{
    /**
     * _LOG_WRITE
     *
     * Append message into log file
     *
     * @param   string message to log  
     */    
	function _LOG_WRITE($message)
	{
 		$filename  = date("Y-m-d", time()).".log"; 

 		if(!is_dir("./log")) { mkdir("./log"); }

 		file_put_contents("./log/".$filename, $message, FILE_APPEND); 
	}
}

if (!function_exists('_LOG_TRACE')) 
{

    /**
     * _LOG_TRACE
     *
     * Append message into log file
     *
     * @param   string message to log
     * @param   string route of error  
     */        
	function _LOG_TRACE($message, $route)
	{
		$linea    = replace("{date}[TRACE]: {error} >> {route}\n", array
		(
			"date"  => date("d-m-Y [H:i:s]", time()),
			"route" => $route   ,
			"error" => $message 
		));
	 	  
	 	_LOG_WRITE($linea);
	}
}

if (!function_exists('base_url')) 
{
    /**
     * base_url
     *
     * retorna la url base de origen 
     * 
     * @return  string 
     */            
	function base_url() 
	{
		if (isset($_SERVER['HTTP_HOST'])) 
		{
			$base_url  = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off'?'https':'http';
			$base_url .= '://'.$_SERVER['HTTP_HOST'];
			$base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
		} 
		else 
		{
			$base_url = 'http://localhost/';
		}

		return $base_url;
	}
}

if (!function_exists('redirect')) 
{
    /**
     * redirect
     *
     * Redirige el flujo hacia el destino
     * 
     * @param   string uri [opcional]
     * @param   string method [opcional]
     * @param   string http response code [opcional]
     * 
     * @return  string 
     */    
	function redirect($uri = '', $method = 'location', $http_response_code = 302) 
	{
		if (!preg_match('#^https?://#i', $uri)) 
		{
			$uri = base_url().$uri;
		}

		switch ($method) 
		{
			case 'refresh': header("Refresh:0;url=".$uri 						); break;
			default       : header("Location: ".$uri, TRUE, $http_response_code ); break;
		}

		exit;
	}
}

if (!function_exists('replace')) 
{

    /**
     * replace
     *
     * Reemplaza el contenido del string con array
     * 
     * @param   string 
     * @param   array  
     * @return  string 
     */  
	function replace($str, $arr) 
	{ 
	 
		foreach ($arr as $k => $v) 
		{
			$str = str_replace('{'.$k.'}', $v, $str);
		} 
		return $str;
	}
}

if (!function_exists('deflate')) 
{

    /**
     * deflate
     *
     * desinfla del contenido del string 
     * quitando espacios y caracteres especiales
     * 
     * 
     * @param   string 
     * @return  string 
     */      
	function deflate($resource) {
		$resource = string_deflate($resource);

		$resource = quit_spaces($resource);

		return $resource;
	}
}

if (!function_exists('string_deflate')) 
{
    /**
     * string_deflate
     *
     * quita caracteres especiales del string
     * 
     * @param   string 
     * @return  string 
     */         
	function string_deflate($string) 
	{
		$is_deflate = array("\n", "\t", "\r");

		foreach ($is_deflate as $quit) 
		{
			$string = str_replace($quit, " ", $string);
		}

		return $string;
	}
}

if (!function_exists('quit_spaces')) 
{

    /**
     * quit_spaces
     *
     * quita los espacios del string "deja uno solo"
     *
     * @param   string  
     * @return  string 
     */             
	function quit_spaces($string) 
	{
		$ISOK = FALSE;

		while ($ISOK == FALSE) 
		{
			$aesp = explode("  ", $string);

			if (count($aesp) > 1) {
				$string = str_replace("  ", " ", $string);

				$ISOK = FALSE;
			} else {
				$ISOK = TRUE;
			}

		}

		return $string;
	}
}

if (!function_exists('to_link')) 
{

    /**
     * to_link
     *
     * - quita los acentos 
     * - string to lowercase
     * - reemplaza otro caracter non alpha por "-"
     * - reemplaza espacios por "-"
     * 
     * @param   string 
     * @return  string 
     */      
	function to_link($str)
	{
	    $str = str_replace(" ","-",$str);
	    $str = str_replace("Á","a",$str);
	    $str = str_replace("É","e",$str);
	    $str = str_replace("Í","i",$str);
	    $str = str_replace("Ó","o",$str);
	    $str = str_replace("Ú","u",$str);
	    $str = str_replace(" ","-",$str);
	    $str = str_replace("á","a",$str);
	    $str = str_replace("é","e",$str);
	    $str = str_replace("í","i",$str);
	    $str = str_replace("ó","o",$str);
	    $str = str_replace("ú","u",$str);
	    $str = preg_replace("/\W+/",'-',$str);
	    $str = strtolower($str); 
	    return $str;
	}
}

if (!function_exists('to_post')) 
{

    /**
     * to_post
     *
     * - quita los acentos 
     * - string to lowercase
     * - reemplaza otro caracter non alpha por "-"
     * - reemplaza espacios por "-"
     * 
     * @param   string 
     * @param   int     max lenght to cut
     * @return  string 
     */     
	function to_post($str,$max=400)
	{ 
		$str = strip_tags($str);
		$sz  = strlen    ($str);
		
		if( $sz > $max  )
		{
			$str  = substr($str, 0, $max);
			$str2 = substr($str, 0, strripos($str, " ") );	
			$sz2  = strlen($str2); 
			
			if( ($sz-10)>$sz2 ) $str = $str2."..."; 
		}
		 
		return $str  ;  
	}
}


if (!function_exists('mail_core_error')) 
{
	function mail_core_error($subject, $message)
	{
		$core = core::getInstance();

		if( !isset($core->email) ) return;

		$subject = "{$core->email->on_error_project} {$subject}";
	 
		try
		{ 
			$request = ( isset($_SERVER["REQUEST_URI"]) ? $core->email->on_error_url.$_SERVER["REQUEST_URI"] : "");
 
			$core->email->from   ( $core->email->contacto, $core->email->nombre);
			$core->email->to     ( $core->email->on_error_addr ); 
			$core->email->subject( $subject );
			$core->email->message
			("
			<div>{$message}</div>
			<blockquote>{$core->email->on_error_text}<br>{$request}</blockquote>
			");

			$core->email->send();
		}
		catch(Exception $e)
		{  
	        
		} 
	}
}