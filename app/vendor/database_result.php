<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

/**
 * Database Result
 *
 * @package     Tero
 * @subpackage  Vendor
 * @category    Library
 * @author      Daniel Romero 
 */ 
class database_result {


    /**
     * String SQL Query
     *
     * @var string
     */
	private $query 		= "";


    /**
     * Store object data
     *
     * @var array
     */
	private $source 	= array();


    /**
     * Store result data
     *
     * @var array
     */ 
	private $dataassoc 	= array(); 
    

    /**
     * bind query and result
     * 
     * @param   string SQL query
     * @param   object result
     */
	public function set_databind($query, $result)
	{
		$this->query = $query;
		$this->source = $result;
	}


    /**
     * get result data as array object
     * 
     * @return  array
     */
	function result()
	{
	    $data = array();
	    
	    if($this->source)
		foreach($this->source as $rs)
		{
		    $o = new stdClass;
		    
		    foreach($rs as $k=>$v)
		    {
			$o->$k = $v;
		    }
		    
		    $data[] = $o;
		}


	    return $data;
	} 

    /**
     * get result data as array array
     * 
     * @return  array
     */
	function result_array()
	{
	    $this->dataassoc= array();

	    if($this->source)
	    {
		    foreach($this->source as $rs)
		    {
			    $this->dataassoc[] = $rs;
		    }
	    }

	    return $this->dataassoc;
	}

    function first() { $rox = FALSE; foreach($this->result() as $row) { return $row; } return $rox; }
}
