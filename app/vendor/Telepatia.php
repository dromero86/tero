<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

/**
 * Telepatia
 *
 * @package     Tero
 * @subpackage  Vendor
 * @category    Library
 * @author      Daniel Romero 
 */ 
class Telepatia
{
    /**
     * Tiempo de la session
     *
     * @var int
     */
    private $session_time 	= 1    	;

    /**
     * Identificacion de la session
     *
     * @var string
     */
	public  $appname 		= ""	;

    /**
     * db table where allocate session
     *
     * @var string
     */
	private $table			= ""	;

    /**
     * Object database instance
     *
     * @var object
     */
	private $db 			= NULL 	;

    /**
     * Connection database ready, default off
     *
     * @var boolean
     */
	private $isok 			= FALSE ;


    /**
     * Class config file
     *
     * @var string
     */
	private $config_file = "app/config/session.json";


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

		$this->db = database::getInstance();

		$this->init();

		$this->after_connect();

		if(	!$this->db->is_ready() )
		{
			_LOG(core::getInstance(), __CLASS__, "[Database] is off");
		}
		else
		{
			// check table exist
			if(!$this->db->exist($this->table))
			{
				_LOG(core::getInstance(), __CLASS__, "Table [{$this->table}] don't exist");
			}
			else
			{
				$this->isok = TRUE;
			}
		}
	}


    /**
     * Load config from session.json
     * 
     * 
     */
	private function after_connect() {
		$config   = file_get_json(BASEPATH.$this->config_file);

		if(isset($config->Telepatia))
		{
			$this->table		= $config->Telepatia->table;
			$this->appname		= $config->Telepatia->app;
			$this->session_time	= $config->Telepatia->timeout;
		}
		else
		{
			_LOG(core::getInstance(), __CLASS__, "No se hallo la sección [Telepatia]");
		}

	}

    /**
     * Conection is ready
     *  
     * @return  boolean
     */
	public function is_ready()
	{
		return $this->isok;
	}


    /**
     * set config session
     *   
     */
	public function open($db,$table,$appname,$session_time)
	{
		$this->db           = $db;
		$this->table        = $table;
		$this->appname      = $appname;
		$this->session_time = $session_time;
	}


    /**
     * set database name
     *   
     */
	public function set_database($db)
	{
		$this->db = $db;
	}


    /**
     * set table name
     *   
     */
	public function set_table($table)
	{
		$this->table = $table;
	}


    /**
     * set ap session
     *   
     */
	public function set_appname($appname)
	{
		$this->appname = $appname;
	}


    /**
     * set session time
     *   
     */
	public function set_session_time($session_time)
	{
		$this->session_time = $session_time;
	}



    /**
     * start session
     *   
     */
	public function init()
	{
		if( session_id() == '' )
		{
			@session_start();
		}
	}


    /**
     * get ip address
     * 
     * @return string ip address  
     */
	private function ip_address()
	{
		$ip = '';

		if (!empty($_SERVER['HTTP_CLIENT_IP']))
		{
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		else
		{
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return $ip;
	}


    /**
     * get user id stored
     * 
     * @return mixed (int or boolean)
     */
    public function  get()
    {
        $cookie = isset( $_COOKIE[ $this->appname ] ) ? $_COOKIE[ $this->appname ] : "";

        $rs     = $this->db->query(" SELECT id_user FROM {$this->table} WHERE cookie = '{$cookie}' LIMIT 1 ");

        $has    = FALSE;

        foreach( $rs->result() as $row )
        {
            $has = (int) $row->id_user > 0 ? $row->id_user : FALSE ;
        }

        return $has;
    }



    /**
     * check if user online
     * 
     * @return boolean
     */
    public function  has_session()
    {

        $cookie = isset( $_COOKIE[ $this->appname ] ) ? $_COOKIE[ $this->appname ] : "";

        $rs     = $this->db->query(" SELECT TIMESTAMPDIFF( HOUR , last_time, NOW( ) ) AS 'curr_time', expire  FROM  {$this->table} WHERE cookie = '{$cookie}'  LIMIT 1 ");

        $has    = FALSE;

		foreach( $rs->result() as $row )
        {
			$row->expire = (int)$row->expire;

			if( $row->expire > 0 ) $this->session_time = $row->expire;

            $has = TRUE;

            if( (int) $row->curr_time > $this->session_time ) $has = FALSE;

			if( $has == FALSE )
			{
				$this->rem();
			}
			else
			{
				$this->db->query(" UPDATE {$this->table} SET  last_time=now()  WHERE  cookie = '{$cookie}' LIMIT 1");
			}

        }

        return $has;
    }


    /**
     * add user id
     * 
     * @param int
     */
    private function add($id)
    {
		$cookie =  uniqid();

		setcookie( $this->appname , $cookie , time() + ( 3600 * $this->session_time ) , '/');

        $sIp    = $this->ip_address();

        $this->db->query(" INSERT {$this->table} SET ip = '{$sIp}', id_user = '{$id}', type = '{$this->appname}', cookie = '{$cookie}', expire ='{$this->session_time}' ");
    }


    /**
     * set user id
     * 
     * @param int
     */
    private function set($id)
    {
        $sIp    = $this->ip_address();

		$cookie =  uniqid();

		setcookie( $this->appname , $cookie , time() + ( 3600 * $this->session_time ) , '/');

        $this->db->query(" UPDATE {$this->table} SET id_user = '{$id}', type = '{$this->appname}', expire ='{$this->session_time}'  WHERE ip = '{$sIp}', cookie = '{$cookie}' LIMIT 1");
    }


    /**
     * rem user id
     * 
     * @param int
     */
    private function rem($id = FALSE)
    {
		$cookie = isset( $_COOKIE[ $this->appname ] ) ? $_COOKIE[ $this->appname ] : FALSE;

		if( $id == FALSE )
		{
			if($cookie == FALSE )
			{
				$sIp    = $this->ip_address();

				$this->db->query(" DELETE FROM {$this->table} WHERE ip = '{$sIp}' ");
			}
			else
			{
				$this->db->query(" DELETE FROM {$this->table} WHERE cookie = '{$cookie}' ");

				unset($_COOKIE[$this->appname]);

				setcookie($this->appname, NULL, -1, '/');
			}
		}
		else
		{
			$this->db->query(" DELETE FROM {$this->table} WHERE id_user = '{$id}'");
		}
    }


    /**
     * close session user id
     * 
     * @param int
     */
    public function  close($id = FALSE)
    {
        $this->rem($id); 
    }


    /**
     * send session user id
     * 
     * @param int
     */
    public function  send($id)
    {
		$this->rem( $id );
        $this->add( $id );
    }


    /**
     * receive session user id if online
     * 
     * @return int
     */
    public function  recv()
    {
        $ret = FALSE;

        if( $this->has_session() )
        {
            $ret = $this->get();
        }

        return $ret;
    }
}
