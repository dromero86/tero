<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

/**
 * Schema
 *
 * @package     Tero
 * @subpackage  Vendor
 * @category    Library
 * @author      Daniel Romero 
 */ 
class schema {

    /**
     * object with config.json items parsed
     *
     * @var object 
     */ 
    private $config      = null; 

    /**
     * cmd json parsed object
     *
     * @var object 
     */ 
    private $cmd         = null;

    /**
     * string path schema.json
     *
     * @var string 
     */ 
    private $config_file = "app/config/schema.json";

    /**
     * array filter
     *
     * @var array 
     */ 
    private $filter      = []; 

    /**
     * array select
     *
     * @var array 
     */ 
    private $_select     = [];

    /**
     * parcial select
     *
     * @var string 
     */ 
    private $current     = "";

    /**
     * active table
     *
     * @var string 
     */ 
    private $active      = "";

    /**
     * final sql query
     *
     * @var string 
     */ 
    private $SQL         = "";

    /**
     * Date format field
     *
     * @var string 
     */ 
    private $fn_date     = "DATE_FORMAT({field},'%Y-%m-%d') AS '{rename}'";

    /**
     * Concat value field
     *
     * @var string 
     */     
    private $fn_concat   = "CONCAT({value}) AS '{rename}'";

    /**
     * Inner join sql template
     *
     * @var string 
     */  
    private $ql_join     = "INNER JOIN {table_a} {alias_a} ON( {alias_a}.id = {alias_b}.{id_b} )";

    /**
     * Left join sql template
     *
     * @var string 
     */  
    private $ll_join     = "LEFT JOIN {table_a} {alias_a} ON( {alias_a}.id = {alias_b}.{id_b} )";


    /**
     * Select SQL template
     *
     * @var string 
     */  
    private $ql_select   = "SELECT \n\t{field} \nFROM {table} {alias}";
    
    /**
     * Limit sql
     *
     * @var string 
     */  
    private $ql_limit    = "\nLIMIT ";
    
    /**
     * Group by sql
     *
     * @var string 
     */  
    private $ql_group    = "\nGROUP BY ";
    
    /**
     * Order by sql
     *
     * @var string 
     */ 
    private $ql_order    = "\nORDER BY ";


    private $_order_ql = "";
    private $_group_ql = "";
    private $_limit_ql = "";

    /**
     * string procedure
     *
     * @var string 
     */ 
    private $_procedure = "";

    
    /**
     * Load objects
     *
     *
     */ 
    public function load()
    { 
        core::getInstance()->cloneIn($this, array("db", "input"));

        $this->config = file_get_json(BASEPATH.$this->config_file);  
    } 


    public function procedure($query)
    {
        $this->_procedure = "CALL {$query}";
    }

    /**
     * Join
     *
     * Build join query
     *
     * @param   string  
     * @return  object
     */   
    public function join($extra)
    { 
        $join = array();

        if( !isset($this->config->{$this->active}->join ) ) return $this; 

        foreach ($this->config->{$this->active}->join as $field => $relation) 
        {
            if(isset($relation->fields))
            {
                foreach ($relation->fields as $join_with) 
                {
                    $this->_select[]=$this->config->{$relation->table}->alias.".".$join_with." AS '{$relation->table}_{$join_with}'";
                }
            }

            
            $join[]= replace($this->ql_join, array
            (
                'table_a'=> $relation->table,
                'alias_a'=> $this->config->{$relation->table}->alias,
                'alias_b'=> $this->config->{$this->active}->alias,
                'id_b'   => $field
            )); 
        }

        if(is_array($extra))
            foreach ($extra as $item) 
            {
                $pieces = explode(".", $item->union);
                $relation_table  = $pieces[0];
                $relation_key    = $pieces[1]; 
                
                $tpl = ( $item->type == "left" ? $this->ll_join : $this->ql_join );
                
                $join[]= replace( $tpl, array
                (
                    'table_a'=> $item->with,
                    'alias_a'=> $this->alias($item->with),
                    'alias_b'=> $this->alias($relation_table),
                    'id_b'   => $relation_key
                ));
            }


        $this->current .= "\n".implode("\n", $join); 

        return $this;
    }

    /**
     * Alias
     *
     * Alias query
     *
     * @param   string table
     * @return  object
     */   
    public function alias($table)
    {
        if( !isset($this->config->{$table}->alias) )  throw new Exception("{$table} alias not found in schema");

        return $this->config->{$table}->alias;
    }

    /**
     * GET
     *
     * Select query
     *
     * @param   string table
     * @param   string field 
     * @return  object
     */ 
    public function get($table, $field="*")
    {
        $this->active = $table;  

        if( !isset($this->config->{$table}) ) throw new Exception("{$table} not found in schema");

        $this->current = replace($this->ql_select, array( 'table'=> $table, 'alias'=> $this->alias($table) ));
     
        if($field == "*")
        {
            $this->_select[]=$this->alias($table).".*"; 
        }
        else
        { 
            if(is_array($field))
            {
                foreach ($field as $item)
                {
                    if(is_object($item))
                    {
                        foreach ($item as $key=>$sub) {
                            $this->_select[]=str_replace("@",$this->alias($table).".",$sub)." AS '{$key}'";
                        }
                        
                    }
                    else
                    {
                        $this->_select[]=$this->alias($table).".".$item;
                    }
                }
            }
            else
            {
                foreach (explode(",", $field) as $item) 
                { 
                    if(strpos($item, "="))
                    {
                        $piece        = explode("=", $item);
                        $field_concat = explode("@", $piece[0]);
                        $fn           = array();

                        foreach ( $field_concat as $field_item)  
                            $fn []= $this->alias($table).".".$field_item; 


                        $item_add = replace($this->fn_concat, array
                        ( 
                            'value'=> implode( ",' ',", $fn ), 
                            'rename'=> $piece[1]
                        ));

                    }
                    else
                    {
                        $item_add = $this->config->{$table}->alias.".".$item;

                        if(isset($this->config->{$table}->cast->date))
                        foreach ($this->config->{$table}->cast->date as $fls) {
                            if($fls == $item)
                            {
                                $item_add = replace($this->fn_date, array
                                ( 
                                    'field'=> $this->config->{$table}->alias.".".$item, 
                                    'rename'=> $item
                                ));
                            }
                        }
                    }

                    $this->_select[]=$item_add;
                }
            }
        }

        return $this;
    }

    /**
     * LIMIT
     *
     * Limit query
     *
     * @param   int limit value 
     * @return  object
     */ 
    public function limit($k)
    {
        $this->_limit_ql = "{$this->ql_limit} {$k}"; 

        //return $this;
    }

    /**
     * ORDER
     *
     * Order query
     *
     * @param   object 
     * @return  object
     */ 
    public function order($object)
    {
        $ord=[];

        foreach ($object as $key => $value) 
        {
            $key = $this->alias($this->active).".".$key;
            $ord[]="{$key} {$value}";
        }
        $this->_order_ql = "{$this->ql_order} ".implode(",",$ord); 

        return $this;
    }    

    /**
     * BUILD
     *
     * Order query
     *
     * @param   array 
     * @return  object
     */ 
    public function group($arg)
    {
        foreach ($arg as $key=>$value) {
            $arg[$key]=$this->alias($this->active).".".$value;
        }

        $this->_group_ql = "{$this->ql_group} ".implode(",",$arg); 

        return $this;
    }    


    /**
     * FIELD
     *
     * Order query
     *
     * @param   string field
     * @param   string compare
     * @param   string value
     * @return  object
     */ 
    public function filter($field, $compare, $value)
    {
        if(strpos(".", $field))
        {     
            $pieces = explode(".", $field);
            $table  = $pieces[0];
            $key    = $pieces[1];

            if( !isset($this->config->{$table}) ) throw new Exception("key not found");  

            $alias  = $this->config->{$table}->alias; 
            
            $this->filter [] = "{$alias}.{$key} {$compare} ".($value!="null" ? "'{$value}'" : "" ) ;
        }
        else
        {
            $this->filter [] = $this->config->{$this->active}->alias.".{$field} {$compare} ".($value!="null" ? "'{$value}'" : "" ) ;
        }
        

        return $this;
    }

    /**
     * WRITE
     *
     * Generate result
     *
     * @param   string json 
     */ 
    public function write($json_output)
    {
        header("Access-Control-Allow-Origin: *");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS');
        header("Access-Control-Allow-Headers: X-Requested-With");
        header('Content-Type: text/html; charset=utf-8');
        header('P3P: CP="IDC DSP COR CURa ADMa OUR IND PHY ONL COM STA"');


        die( $json_output );
    }

    /**
     * EXEC
     *
     * Execute query
     *
     * @param   string mode 
     */ 
    public function exec($expect)
    {
        if( $this->db == null ) throw new Exception("Database not found");

        if(count($this->_select)>0)
        {
            $this->current = replace($this->current, array( 'field'=> implode(",\n\t", $this->_select) ));

            $this->SQL = $this->current.( count($this->filter)>0 ? " WHERE ".implode(" AND ", $this->filter) : "" );

            if($this->_group_ql) $this->SQL .= $this->_group_ql."\n";

            if($this->_order_ql) $this->SQL .= $this->_order_ql."\n";

            if($this->_limit_ql) $this->SQL .= $this->_limit_ql."\n";     
            
            $rs = $this->db->query( $this->SQL );        
        }
        else
        {
            if($this->_procedure)
            {
                $this->SQL = $this->_procedure;

                $rs = $this->db->procedure( $this->SQL ); 
            }
        }
  
        if($this->SQL)
        { 
            $output_object           = new stdclass;
            $output_object->request  = $this->cmd;
            $output_object->data     = ($expect == "list" ? $rs->result() : $rs->first());
            $output_object->sql      = $this->SQL ;

            $this->write( json_encode( $output_object ) ); 
        }
        else
        {   
			throw new Exception("SQL data not found");
        }
    }

    /**
     * START SERVER
     *
     *  
     * 
     */ 
    public function start_server()
    { 
        $this->cmd = $this->input->payload(); 

        if(!isset($this->cmd->select)) 
        {
            if(!isset($this->cmd->procedure))
            { 
				throw new Exception("select/procedure required");
            } 
            else
            {
                $this->procedure($this->cmd->procedure); 

                $this->exec( isset($this->cmd->expect) ? $this->cmd->expect : "list" );
            }
        }
        else
        {
            if(is_object($this->cmd->select))
                $this->get($this->cmd->select->from, $this->cmd->select->field);
            else
                $this->get($this->cmd->select);

            if(isset($this->cmd->join)) $this->join($this->cmd->join);

            if(isset($this->cmd->filter)) 
                foreach ($this->cmd->filter as $item) {
                     $this->filter($item->field, $item->is, $item->to); 
                 } 

            if(isset($this->cmd->group)) 
                $this->group($this->cmd->group);

            if(isset($this->cmd->order)) 
                $this->order($this->cmd->order);

            if(isset($this->cmd->limit)) $this->limit($this->cmd->limit);

            $this->exec( isset($this->cmd->expect) ? $this->cmd->expect : "list" );
        } 
    }
}