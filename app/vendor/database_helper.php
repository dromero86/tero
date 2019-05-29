<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

/**
 * Database Helpers
 *
 * @package     Tero
 * @subpackage  Vendor
 * @category    Helpers
 * @author      Daniel Romero 
 */

if (!function_exists("INSERT")) 
{
    /**
     * INSERT
     *
     * build insert SQL string with table name and object data 
     *
     * @param   string 
     * @param   string  
     * @return  string
     */    
    function INSERT( $table , $object )
    {

    	$db = database::getInstance();
     
    	$SQL = " INSERT {$table} SET ";

    	$item = array();

    	foreach ($object as $key => $value) 
    	{
    		switch ($value) 
    		{
    			case 'CURRENT_DATE' : $value = "CURRENT_DATE"	; break; 
    			case 'NOW' 			: $value = "now()"			; break; 
    			default     		: $value = "'".addslashes($value)."'"  	; break;
    		}

    		if($key =='pass')  $value = 'MD5(\''.$value.'\')' ;

    		if( $db->has_column($table, $key)==TRUE )
    		{
    			$E     = "{$key} = {$value}"; 
    			$item[]=$E;	
    		}

    	}

    	$SQL .= implode(", ", $item);

    	return $SQL;
    }
}

if (!function_exists("UPDATE")) 
{

    /**
     * UPDATE
     *
     * build update SQL string with table name, object data and int id 
     *
     * @param   string 
     * @param   string  
     * @return  string
     */        
    function UPDATE( $table , $object , $id)
    {
    	$db = database::getInstance();

    	$SQL = " UPDATE {$table} SET ";

    	$item = array();

    	foreach ($object as $key => $value) 
    	{
    		switch ($value) 
    		{
    			case 'CURRENT_DATE' : $value = "CURRENT_DATE"	; break; 
    			case 'NOW' 			: $value = "now()"			; break; 
    			default     		: $value = "'".addslashes($value)."'"  	; break;
    		}

    		if($key =='pass')  $value = 'MD5('.$value.')' ;

    		if( $db->has_column($table, $key)==TRUE )
    		{
    			$E     = "{$key} = {$value}"; 
    			$item[]=$E;	
    		} 
    	}

    	$SQL .= implode(", ", $item);
    	$SQL .= " WHERE id = '{$id}'";
    	return $SQL;
    }
}

if (!function_exists("DELETE"))
{ 
    /**
     * DELETE
     *
     * build delete SQL string with table name and int id 
     *
     * @param   string 
     * @param   int  
     * @return  string
     */   
    function DELETE( $table, $id )
    {
    	$SQL = "DELETE FROM {$table} WHERE id = '{$id}'";

    	return $SQL;
    }
}

if (!function_exists("SELECT"))
{ 
    /**
     * SELECT
     *
     * build select SQL string with table name 
     *
     * @param   string 
     * @param   int  
     * @return  string
     */ 
    function SELECT( $table )
    {
    	$SQL = "SELECT * FROM {$table}";

    	return $SQL;
    }
}

if (!function_exists("QUERIFY"))
{ 
    /**
     * QUERIFY
     *
     * Add sql result in dataset list
     *
     * @param   object  Core Instance 
     * @param   string  key string
     * @param   string  SQL String
     * @param   boolean [optional] Set key   
     */ 
    function QUERIFY($that, $key, $sql, $autoset=FALSE, $optJson = NULL )
    {
    	if($autoset==FALSE) $that->data->set($key); 

    	$options = FALSE;

    	if($optJson!=NULL)
    	{
    		$options = json_decode($optJson);
    	} 

    	$rs = $that->db->query ($sql); 
    	
    	foreach ($rs->result() as $row) 
    	{ 
    		//add link custom behavior 
    		if(isset($options->tolink))
    			foreach ($options->tolink as $item) 
    			{
    				$row->{$item->name} = TOLINK($row->{$item->field});
    			}

    		//add link custom behavior 
    		if(isset($options->topost))
    			foreach ($options->topost as $item) 
    			{
    				$row->{$item->field} = to_post($row->{$item->field},$item->trim) ;
    			}

    		//add active custom behavior 
    		if(isset($options->active))
    		{
    			$options->active->equal         = (int)$options->active->equal; 
    			$row->{$options->active->field} = (int)$row->{$options->active->field};
    			$row->active                    = ( $row->{$options->active->field} == $options->active->equal ? "active" : "" ); 
    		}


    		$that->data->map($key , $row ); 
    	}
    }
}

if (!function_exists("QUERYMAP"))
{ 
    /**
     * QUERYMAP
     *
     * Add sql result in dataset item
     *
     * @param   object  Core Instance 
     * @param   string  key string
     * @param   string  SQL String
     * @param   boolean [optional] Set key   
     */ 
    function QUERYMAP($that, $sql, $key="", $optJson = NULL)
    {
    	$options = FALSE;

    	if($optJson!=NULL)
    	{
    		$options = json_decode($optJson);
    	}

    	$rs = $that->db->query ($sql); 
      
    	foreach ($rs->result() as $row) 
    	{ 
    		//add link custom behavior 
    		if(isset($options->tolink))
    			foreach ($options->tolink as $item) 
    			{
    				$row->{$item->name} = TOLINK($row->{$item->field});
    			}

    		//add active custom behavior 
    		if(isset($options->active))
    		{
    			$options->active->equal         = (int)$options->active->equal; 
    			$row->{$options->active->field} = (int)$row->{$options->active->field};
    			$row->active                    = ( $row->{$options->active->field} == $options->active->equal ? "active" : "" ); 
    		}


    		$that->data->automap( $row, $key ); 

    		//var_dump($that->data->get());
    	}
    }
}

if (!function_exists("QUERYJS"))
{ 
    /**
     * QUERYJS
     *
     * Add sql result in json
     *
     * @param   object  Core Instance  
     * @param   string  SQL String  
     */ 
    function QUERYJS($that, $sql )
    {
    	$data = array();

    	$rs = $that->db->query($sql);

    	foreach ($rs->result() as $row) 
    	{
    		foreach ($row as $key => $value) {
    			$row->{$key}=$value;
    		}

    		$data[]= json_encode($row);
    	}

    	$str = "[".implode(",", $data)."]";

    	return $str;
    }
}

if (!function_exists("QUERYCOMBO"))
{ 
    /**
     * QUERYCOMBO
     *
     * Create html combobox
     *
     * @param   object  Core Instance  
     * @param   string  SQL String  
     * @param   string  combo name  
     */ 
    function QUERYCOMBO($that, $sql, $message)
    {
    	$rs = $that->db->query($sql);

    	$result = $rs->result();

    	if(count($result)) 
    	{
    		foreach ($result as $row) 
    		{
    			echo '<option value="'.$row->id.'">'.$row->nombre.'</option>';
    		}
    	}
    	else
    	{
    		echo '<option>'.$message.'</option>';
    	}
    }
}

if (!function_exists("QUERYSON"))
{ 
    /**
     * QUERYSON
     *
     * Add sql result in json with autoprint
     *
     * @param   object  Core Instance  
     * @param   string  SQL String  
     * @param   boolean print enable  
     */ 
    function QUERYSON($that, $sql, $print =TRUE)
    { 
    	$rs   = $that->db->query( $sql );

    	$data = $rs->result();  $output = "{}";

    	if(count($data)) foreach ($data as $row) { $output = json_encode($row); } 

    	if($print ==TRUE)
    		die($output); 
    	else
    		return $output;
    }
}

if (!function_exists("TOLINK"))
{ 
    /**
     * TOLINK
     *
     * Replace string with array
     * 
     * @param   string    
     */ 
    function TOLINK($str)
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

if (!function_exists("SPLITMAP"))
{ 
    /**
     * SPLITMAP
     *
     * Split item value comma separated 
     * in dataset list
     *
     * @param   object  Core Instance 
     * @param   string  
     * @param   string  
     * @param   string  
     * @param   string 
     */ 
    function SPLITMAP($that, $field, $key_parent, $key_child,  $char=",")
    {
    	$that->data->set($key_parent); 
    	
    	if(!is_array($field))return;
    	
    	$rs = explode($char, $field);

    	foreach ($rs as $item) 
    	{
    		$row = new stdclass;

    		$row->{$key_child}=$item;

    		$that->data->map($key_parent , $row ); 
    	}
    }
}

if (!function_exists("IFMAP"))
{ 
    /**
     * IFMAP
     *
     * create dataset if value content add item
     *
     * @param   object  Core Instance 
     * @param   string  
     * @param   string  
     * @param   string   
     */ 
    function IFMAP($that,  $key_parent, $key_child, $item)
    {
        $that->data->set($key_parent);  
     
        if($item)
        {
            $row = new stdclass;

            $row->{$key_child}=$item;

            $that->data->map($key_parent , $row ); 
        }
    }
}