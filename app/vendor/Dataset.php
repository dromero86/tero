<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

/**
 * Dataset
 *
 * @package     Tero
 * @subpackage  Vendor
 * @category    Library
 * @author      Daniel Romero 
 */ 
class Dataset
{

    /**
     * Store data
     *
     * @var array
     */
	private $store = array(); 


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
     * set key, default value array
     *
     * @param string
     * @param mixed 
     */
	public function set($key, $value = array() ) 
	{
		$this->store[ $key ] = $value ;
	} 


    /**
     * remove key 
     *
     * @param string 
     */ 
    public function rem($key) 
    {
        unset($this->store[ $key ]);
    } 


    /**
     * add key, value
     *
     * @param string
     * @param mixed 
     */ 
	public function add($key, $value)
	{
		$this->store[ $key ][] = $value ;
	}

    /**
     * get array
     *
     * @return array 
     */ 
	public function get()
	{
		return $this->store;
	}  

	/* MULTIDEFS  - TRICKS */

    /**
     * set blank array of values
     *
     * @param array 
     * @param string  
     */ 
	public function init( $list , $value="")
	{
		foreach ($list as $key) 
		{
			$this->set($key, $value);
		}
	}


    /**
     * map object array with new key
     *
     * @param string
     * @param object   
     */ 
	public function map($key, $object)
	{
		$item  = array();

		foreach ($object as $name => $value) 
		{
			$item [ "{$key}_{$name}" ]= $value;
		}

		$this->store[$key][]=$item;		
	}


    /**
     * map object with new prefix
     *
     * @param object 
     * @param string 
     */ 
	public function automap($object, $prefix="")
	{
		foreach ($object as $name => $value) 
		{
			$this->store [ $prefix.$name ]= $value;
		}
	}
}