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
     * Store query exception
     *
     * @var object
     */ 
    private $error = null;

    /**
     * bind query and result
     * 
     * @param   string SQL query
     * @param   object result
     */
    public function set_databind($query, $result)
    {
        $this->query  = $query;
        $this->source = $result;
    }
	
    /**
     * Store exception object error
     *
     * @var exception object
     */ 
    public function set_error($e)
    {
        $this->error = $e;
    }
	
    /**
     * get exception of query
     * 
     */ 
    public function get_error()
    {
        return $this->error;
    }

    /**
     * get both query
     * 
     */ 
    public function get_query()
    {
        return $this->query;
    }

    /**
     * check if has error
     * 
	 * return boolean
     */ 
    public function has_error()
    {
        return ($this->error == null ? FALSE : TRUE );
    }

    /**
     * query escape wrong values
     * 
	 * @var string
	 *
	 * return string
     */ 
    function escape($inp) 
    {
        if(is_array($inp))
            return array_map(__METHOD__, $inp);

        if(!empty($inp) && is_string($inp)) {
            return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
        }

        return $inp;
    }

    /**
     * quit quotes from string
     * 
	 * @var string
	 *
	 * return string
     */ 
    function quit_quotes($str) 
    {
        $str = str_replace('"',"",$str);
        $str = str_replace("'","",$str);

        return $str;
    }

    /**
     * get result data as array object
	 *
	 * @var string filter comma separate values
	 *
     * @return  array
     */
    function result($filter=FALSE)
    {
        $data = array();
        
        if($this->source)
        foreach($this->source as $rs)
        {
            $o = new stdClass;
            
            foreach($rs as $k=>$v)
            {
                if($filter==FALSE)
                { 

                    $o->$k = $v;
                }
                else
                {
                    foreach (explode(",", $filter) as $item) 
                    {
                        switch ($item) 
                        {
                            case 'trim' : $v = trim ($v); break;  
                            case 'upper' : $v = strtoupper ($v); break;  
                            case 'lower' : $v = strtolower ($v); break; 
                            case 'deflate': $v = deflate ($v); break; 
                            case 'entity_decode': $v = html_entity_decode($v); break;  
                            case 'entity_encode': $v = htmlentities($v); break;  
                            case 'quit_quotes': $v = $this->quit_quotes($v); break;  
                            case 'escape': $v = $this->escape($v); break;  
                        }
                    }
 

                    $o->$k = $v;
                }
                
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

    function first() 
    { 
        $rox = FALSE; 
        
        foreach($this->result() as $row) 
        { 
            return $row; 
        } 
        
        return $rox; 
    }

    function count()
    {
        return count($this->result_array());
    }
}
