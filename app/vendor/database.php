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
     * Motor database, default mysql
     *
     * @var string
     */
	private $driver  = NULL;


    /**
     * nombre de usuario
     *
     * @var string
     */    
	private $user    = NULL;


    /**
     * contraseña
     *
     * @var string
     */  
	private $pass    = NULL;


    /**
     * database name
     *
     * @var string
     */  
	private $db      = NULL; 


    /**
     * encoding
     *
     * @var string
     */  
	private $charset = NULL;


    /**
     * charater collation
     *
     * @var string
     */  
	private $collate = NULL;


    /**
     * Conection instance of database
     *
     * @var object
     */  
	private $link    = NULL;


    /**
     * conection successfull
     *
     * @var string
     */  
	private $isok 	 = FALSE;
    

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
     * Constructor store static instance and load config
     * 
     * 
     */
	function __construct() {

		self::$instancia = $this;

		$this->before_connect();

		$this->connect();

		$this->after_connect();
	}

    /**
     * return ready state
     *
     * @return boolean
     */
	public function is_ready()
	{
		return $this->isok;
	}


    /**
     * check if exist table
     *
     * @param   string TableName
     * @return boolean
     */
	public function exist($table)
	{
		$rs = $this->query("SELECT * FROM information_schema.TABLES WHERE table_schema = '{$this->db}'  AND table_name = '{$table}' LIMIT 1");

		$ret = FALSE; foreach ($rs->result() as $row) { $ret = TRUE;  }

		return $ret;
	}


    /**
     * obtain object list of tables
     *
     * @return object
     */
	public function show_tables()
	{
		return $this->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE table_schema = '{$this->db}'");
	}


    /**
     * obtain object list with columns of table 
     *
     * @param   string TableName
     * @return object
     */
	public function show_column($table)
	{
		return $this->query("SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE table_schema = '{$this->db}'  AND table_name = '{$table}'");
	}

    /**
     * obtain object list with columns of table 
     *
     * @param   string TableName
     * @param   string ColumnName
     * @return boolean
     */
	public function has_column($table, $column)
	{
		$rs = $this->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE table_schema = '{$this->db}'  AND table_name = '{$table}' AND COLUMN_NAME='{$column}' LIMIT 1");

		$ret = FALSE; foreach ($rs->result() as $row) { $ret = TRUE;  }

		return $ret;
	}

    /**
     * obtain object list with columns configuration of table 
     *
     * @param   string TableName
     * @return object
     */
	public function show_full_column($table)
	{
		return $this->query("SELECT ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE, COLUMN_KEY  FROM information_schema.COLUMNS WHERE table_schema = '{$this->db}'  AND table_name = '{$table}'");
	}
 

    /**
     * Establishing conection with database
     *
     * 
     */ 
	private function connect()
	{
		if($this->isok == FALSE)
		{ 
		    try
		    {
			    $this->link = new PDO($this->driver.':host='.$this->host.';dbname='.$this->db, $this->user, $this->pass);
			    $this->link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  
			    $this->link->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, TRUE);
			    $this->isok = TRUE;
		    } 
		    catch (PDOException $e) 
		    {
			    var_dump($e);
		    }

		}
	}


    /**
     * Load config from db.json
     * 
     * 
     */
	private function before_connect() 
	{

		$config = file_get_json(BASEPATH.$this->config_file);

		if( isset($config->database->debug) )
		{
			$this->debug 	= $config->database->debug;
		}

		if(isset($config->database))
		{
			$this->user 	 = $config->database->user   ;
			$this->pass 	 = $config->database->pass   ;
			$this->host 	 = $config->database->host   ;
			$this->db   	 = $config->database->db     ;
			$this->charset   = $config->database->charset;
			$this->collate   = $config->database->collate;
			$this->driver    = $config->database->driver ;
		}
		else
		{
			_LOG(core::getInstance(), __CLASS__, "No se hallo la sección [database]");
		}

		unset($config);
	}


    /**
     * Set character_set conection 
     * 
     * 
     */
	private function after_connect() {

		$this->rawExec("SET NAMES {$this->charset}");
		$this->rawExec("SET CHARACTER SET {$this->charset}");
		$this->rawExec("
			SET
				character_set_results 	 = '{$this->charset}',
				character_set_client 	 = '{$this->charset}',
				character_set_connection = '{$this->charset}',
				character_set_database 	 = '{$this->charset}',
				character_set_server 	 = '{$this->charset}',
				collation_connection 	 = '{$this->collate}';
		"); 
	}


    /**
     * Exec query and get raw results
     * 
     * @param   string SQL query
     * @return  object
     */
	public function rawExec($str)
	{

		$result = $this->link->query($str, PDO::FETCH_ASSOC);
 
		if(!$result)
		{
		    _LOG(core::getInstance(), __CLASS__, "SQL Error: {$str}");
		}
		else
		{
		    if($this->debug == TRUE) _LOG(core::getInstance(), __CLASS__, "SQL: {$str}");
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
		if  (!$this->link)
		{
		    $this->isok = FALSE;
		    $this->connect();
		    $this->after_connect();
		}

		$result = new database_result();

		try
		{
		    $result->set_databind($str, $this->rawExec($str, PDO::FETCH_ASSOC));
		}
		catch (Exception $e)
		{
		    echo $e->getMessage();
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
		if  (!$this->link)
		{
		    $this->isok = FALSE;
		    $this->connect();
		    $this->after_connect();
		}
 
		$sql_query = $this->link->prepare($str);

		$sql_query->execute(); 

		$result = new database_result();
 
		try
		{
		    $result->set_databind($str, $sql_query->fetchAll() );
		}
		catch (Exception $e)
		{
		    echo $e->getMessage();
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
     * Close conection
     * 
     */
	public function close() {
	    if($this->link) $this->link = NULL;
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
