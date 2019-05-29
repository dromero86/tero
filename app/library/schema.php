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

    private $config      = null; 
    private $cmd         = null;

    private $config_file = "app/config/schema.json";

    private $filter      = []; 
    private $_select     = [];

    private $current     = "";
    private $active      = "";
    private $SQL         = "";

    private $fn_date     = "DATE_FORMAT({field},'%Y-%m-%d') AS '{rename}'";
    private $fn_concat   = "CONCAT({value}) AS '{rename}'";
    private $ql_join     = "INNER JOIN {table_a} {alias_a} ON( {alias_a}.id = {alias_b}.{id_b} )";
    private $ql_select   = "SELECT \n\t{field} \nFROM {table} {alias}";
    private $ql_limit    = "\nLIMIT ";
    private $ql_group    = "\nGROUP BY ";
    private $ql_order    = "\nORDER BY ";



    public function load()
    { 
        core::getInstance()->cloneIn($this, array("db", "input"));

        $this->config = file_get_json(BASEPATH.$this->config_file);  
    } 

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
            


            $join[]= replace($this->ql_join, array
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

    public function alias($table)
    {
        if( !isset($this->config->{$table}->alias) ) die(); //throw new Exception("{$table} alias not found in schema");

        return $this->config->{$table}->alias;
    }

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

    public function limit($k)
    {
        $this->current .= "{$this->ql_limit} {$k}"; 

        return $this;
    }

    public function order($object)
    {
        $ord=[];

        foreach ($object as $key => $value) 
        {
            $key = $this->alias($this->active).".".$key;
            $ord[]="{$key} {$value}";
        }
        $this->current .= "{$this->ql_order} ".implode(",",$ord); 

        return $this;
    }    

    public function group($arg)
    {
        foreach ($arg as $key=>$value) {
            $arg[$key]=$this->alias($this->active).".".$value;
        }


        $this->current .= "{$this->ql_group} ".implode(",",$arg); 

        return $this;
    }    


    public function filter($field, $compare, $value)
    {
        if(strpos(".", $field))
        {     
            $pieces = explode(".", $field);
            $table  = $pieces[0];
            $key    = $pieces[1];

            if( !isset($this->config->{$table}) ) die("key not found");

            $alias  = $this->config->{$table}->alias; 
            
            $this->filter [] = "{$alias}.{$key} {$compare} ".($value!="null" ? "'{$value}'" : "" ) ;
        }
        else
        {
            $this->filter [] = $this->config->{$this->active}->alias.".{$field} {$compare} ".($value!="null" ? "'{$value}'" : "" ) ;
        }
        

        return $this;
    }

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

    public function exec($expect)
    {
        if( $this->db == null ) throw new Exception("Database not found");

        $this->current = replace($this->current, array( 'field'=> implode(",\n\t", $this->_select) ));

        $this->SQL = $this->current.( count($this->filter)>0 ? " WHERE ".implode(" AND ", $this->filter) : "" );

        $rs = $this->db->query( $this->SQL ); 

        $output_object           = new stdclass;
        $output_object->request  = $this->cmd;
        $output_object->data     = ($expect == "list" ? $rs->result() : $rs->first());
        $output_object->sql      = $this->SQL ;

        $this->write( json_encode( $output_object ) );
    }

    public function start_server()
    { 
        $this->cmd = $this->input->payload(); 



        if(!isset($this->cmd->select)) die("select required");  

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