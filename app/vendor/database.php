<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    


/**
 * Database
 *
 * @package     Tero
 * @subpackage  Vendor
 * @category    Library
 * @author      Daniel Romero 
 */ 
class database {

    /**
     * Conection dsn list
     *
     * @var array
     */  
    private $dsn = array();

    /**
     * Conection dsn active
     *
     * @var object
     */  
    private $current = null;

    /**
     * Conection instance of database
     *
     * @var object
     */  
    private $link    = array();


    /**
     * conection successfull
     *
     * @var string
     */  
    private $isok    = array();
    

    /**
     * enable debug
     *
     * @var string
     */ 
    private $debug   = FALSE;


    /**
     * Class config file
     *
     * @var string
     */
    private $config_file = "app/config/db.json";


    /**
     * Run time object for Singleton Pattern
     *
     * @var object 
     */ 
    private static $instancia = null;

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
    function __construct() {

        self::$instancia = $this;

        $this->setup_config_file();  
    }


    /**
     * Load configuration and try conect to default database
     * 
     * 
     */
    private function setup_config_file() 
    {

        $config = file_get_json(BASEPATH.$this->config_file);

        $has = FALSE;

        foreach ($config as $name => $cnf) 
        {  
            $has = TRUE;

            if(isset($cnf->default))
                if($cnf->default==true)
                    $this->current = $name; 
 
            $this->connect($name, $cnf);

        }

       
        if($has == FALSE)
            APP_LOG( __CLASS__, "No default database configured" );

        //unset($config);
    }


    /**
     * Establishing conection with database
     *
     * 
     */ 
    private function connect($cId, $config)
    {
		//current dsn is ready conect?
        if(!isset($this->isok[$cId]))
        {
            $this->isok[$cId] = FALSE; 
        }

		
        if(!isset($this->dsn[$cId]))
        {
			//current dsn exist?
            $this->dsn[$cId] = $config;
			
			//current database link offline
			$this->link[$cId] = NULL;
        }
 

        $ret  = FALSE;            

        try
        {
            $this->link[$cId] = new PDO($config->driver.':host='.$config->host.';'.( isset($config->port) ? 'port='.$config->port.';' : '' ).'dbname='.$config->db, $config->user, $config->pass);
            $this->link[$cId]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  
            $this->link[$cId]->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, TRUE);
            $this->isok[$cId] = TRUE;
            $ret  = TRUE;

            $this->setEncoding($config->charset, $config->collate); 
        } 
        catch (PDOException $e) 
        { 
            APP_LOG( __CLASS__, "PDOException: {$e->getMessage()} - ".$config->driver.':host='.$config->host.';'.( isset($config->port) ? 'port='.$config->port.';' : '' ).'dbname='.$config->db);
        }  

        return $ret;
    }


    /**
     * Get instance of database 
     *
     * @return database
     */ 
    public function use($db_instance)
    {
        $this->current = $db_instance;
        return clone $this;
    }

    /**
     * return ready state
     *
     * @return boolean
     */
    public function is_ready()
    {
        $cId = $this->getConectionIdentity();
        return $this->isok[$cId];
    }

    /**
     * Get current dsn active
     *
     * @return string or FALSE
     */ 
    public function getConectionIdentity()
    { 
        return $this->current ? $this->current : FALSE; //("Current identity is NULL") ;
    }   
 
    /**
     * Set character_set conection 
     * 
     * @param   string charset
     * @param   string collate
     */
    private function setEncoding($charset, $collate) {

        $this->rawExec("SET NAMES {$charset}");
        $this->rawExec("SET CHARACTER SET {$charset}");
        $this->rawExec("
            SET
                character_set_results    = '{$charset}',
                character_set_client     = '{$charset}',
                character_set_connection = '{$charset}',
                character_set_database   = '{$charset}',
                character_set_server     = '{$charset}',
                collation_connection     = '{$collate}';
        "); 
    }


    /**
     * Exec query and get raw results
     * 
     * @param   string SQL query
	 
     * @return  PDOResult Object
     */
    public function rawExec($str)
    {
        $cId = $this->getConectionIdentity();

        if(!isset($this->link[$cId])) 
        {  
            $is_connect = $this->connect($cId, $this->dsn[$cId]);

            if($is_connect==FALSE)
            { 
                APP_LOG( __CLASS__, "Dont allow conect {$cId}" ); 
            }
        }

        if(!isset($this->link[$cId])) 
        {
            APP_LOG( __CLASS__, "{cId}: DONT CONNECT" );

            return FALSE;
        }

 
        $result = $this->link[$cId]->query($str, PDO::FETCH_ASSOC);
 
        if(!$result)
        {
            APP_LOG( __CLASS__, "SQL Error: {$str}" ); 
        }
        else
        {
            if($this->debug == TRUE) APP_LOG( __CLASS__, "SQL: {$str}" );
        }

        return $result;
    }


    /**
     * Exec query and get results in object mode
     * 
     * @param   string SQL query
     * @return  object
     */
    public function query($str)
    {
        $cId = $this->getConectionIdentity();

        $is_connect = $this->connect($cId, $this->dsn[$cId]);

        if($is_connect==FALSE)
        { 
            //_LOG
            APP_LOG(__CLASS__, "Dont allow conect {$cId}"); 
        }

        $result = new database_result();

        try
        {
            $result->set_databind($str, $this->rawExec($str, PDO::FETCH_ASSOC));
        }
        catch (Exception $e)
        { 
            APP_LOG(__CLASS__, "Query Databind error: ".$e->getMessage());

            $result->set_error($e);

            mail_core_error("Query Error", "Estimado:<br> La query <pre>{$str}</pre> di el siguiente error:<br><code>".$e->getMessage()."</code><br>Saludos cordiales");


        }
 
        return $result;
    }


    /**
     * Exec store procedure and get results in object mode
     * 
     * @param   string SQL query
     * @return  object
     */
    public function procedure($str)
    {
        $cId = $this->getConectionIdentity();

        if  (!$this->link[$cId])
        {
            $this->isok[$cId] = FALSE;
            $this->connect();
            $this->after_connect();
        }
 
        $sql_query = $this->link[$cId]->prepare($str);

        $sql_query->execute(); 

        $result = new database_result();
 
        try
        {
            $result->set_databind($str, $sql_query->fetchAll(PDO::FETCH_CLASS) );
        }
        catch (Exception $e)
        { 
            APP_LOG(__CLASS__, "Procedure Databind error: ".$e->getMessage());
			
            $result->set_error($e);

            mail_core_error("Query Error", "Estimado:<br> La query <pre>{$str}</pre> di el siguiente error:<br><code>".$e->getMessage()."</code><br>Saludos cordiales");
        }

        $sql_query->closeCursor();
 
        return $result;
    }


    /**
     * Get last_id of table insert
     * 
     * @return  int
     */
    public function last_id()
    {

        $rs = $this->query("SELECT LAST_INSERT_ID() AS 'id'");

        $id = 0; 

        foreach ($rs->result() as $row)  
        { 
            $id = (int)$row->id; 
        }   
        
        return $id;
    }


    /**
     * Get last_id of table insert
     * 
     * @param   string TableName
     * @return  object datatable
     */
    public function table($name)
    { 
        $QB = new datatable();
        $QB->connect($this);
        $QB->set($name);

        return $QB;
    }


    /**
     * check if exist table
     *
     * @param   string TableName
     * @return boolean
     */
    public function exist($table)
    {
        if($this->current)
            $db = $this->dsn[$this->current]->db;
        else
            return FALSE;


        $rs = $this->query("SELECT * FROM information_schema.TABLES WHERE table_schema = '{$db}'  AND table_name = '{$table}' LIMIT 1");

        $ret = FALSE; foreach ($rs->result() as $row) { $ret = TRUE;  }

        return $ret;
    }


    /**
     * get object list of tables
     *
     * @return object
     */
    public function show_tables()
    {
        if($this->current)
            $db = $this->dsn[$this->current]->db;
        else
            return FALSE;

        return $this->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE table_schema = '{$db}'");
    }


    /**
     * get object list with columns of table 
     *
     * @param   string TableName
     * @return object
     */
    public function show_column($table)
    {
        if($this->current)
            $db = $this->dsn[$this->current]->db;
        else
            return FALSE;

        return $this->query("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE table_schema = '{$db}'  AND table_name = '{$table}'");
    }

    /**
     * get object list with columns of table 
     *
     * @param   string TableName
     * @param   string ColumnName
     * @return boolean
     */
    public function has_column($table, $column)
    {
        if($this->current)
            $db = $this->dsn[$this->current]->db;
        else
            return FALSE;

        $rs = $this->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE table_schema = '{$db}'  AND table_name = '{$table}' AND COLUMN_NAME='{$column}' LIMIT 1");

        $ret = FALSE; foreach ($rs->result() as $row) { $ret = TRUE;  }

        return $ret;
    }

    /**
     * get object list with columns configuration of table 
     *
     * @param   string TableName
     * @return object
     */
    public function show_full_column($table)
    {
        if($this->current)
            $db = $this->dsn[$this->current]->db;
        else
            return FALSE;

        return $this->query("SELECT ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE, COLUMN_KEY  FROM information_schema.COLUMNS WHERE table_schema = '{$db}'  AND table_name = '{$table}'");
    }

    /**
     * get true if table has primary key 
     *
     * @param   string TableName
     * @return boolean
     */
    public function is_primary_key($table)
    { 
        if($this->current)
            $db = $this->dsn[$this->current]->db;
        else
            return FALSE;

        $rs = $this->query
        ("
            SELECT EXISTS
            (
              SELECT 1
              FROM information_schema.columns
              WHERE table_schema = '{$db}'
                 and table_name  ='{$table}'
                 and column_key = 'PRI'
            ) As has");


        $ret = FALSE; 

        foreach ($rs->result() as $row) 
        {
            $row->has = (int)$row->has;

            if( $row->has == 1 ) 
                $ret = TRUE;  
        }

        return $ret;
    }

    /**
     * get true if schema exist
     *
     * @param   string dsn
     * @return boolean
     */
    public function schema_exist($cId)
    {
        return isset($this->dsn[$cId]) ? true : false ;
    }



    /**
     * Close conection
     * 
     */
    public function close() {
        $cId = $this->getConectionIdentity();

        if($this->link[$cId]) $this->link[$cId] = NULL;
    }
}



class datatable
{
    private $table = "";
    private $db    = NULL;

    public function connect($database)
    {
        $this->db = $database;
    }

    public function run($query)
    { 
        $result = $this->db->query($query);
        
        echo "{$query};";

        return $result;
    }

    public function set($name)
    { 
        $this->table = $name; 
    }

    public function all()
    {
        $query = " SELECT * FROM {$this->table} ";
        
        return $this->run($query);
    }

    public function id($id)
    {
        $query = " SELECT * FROM {$this->table} WHERE id ='{$id}' ";
        
        return $this->run($query);
    }

    public function where($where)
    {
        $query = " SELECT * FROM {$this->table} WHERE {$where} ";
        
        return $this->run($query);
    }

    public function compose($select, $where, $order ="", $limit="")
    {
        $order = $order ? "ORDER BY {$order}" : "";
        $limit = $limit ? "LIMIT {$limit}" : "";
        

        $query = " SELECT {$select} FROM {$this->table} WHERE {$where} {$order} {$limit}";

        return $this->run($query);
    }
}
