<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

/**
 * Sendmail
 *
 * @package     Tero
 * @subpackage  Vendor
 * @category    Library
 * @author      Daniel Romero 
 */ 

/**
 * sendmail
 *
 * Automatized send custom emails
 * Flow process
 * - Load post data
 * - save in database
 * - parse mail template wuth data
 * - send customized mail
 */ 
class sendmail {
	 
    /**
     * object with config.json items parsed
     *
     * @var object 
     */ 
	private $config = NULL;
	
    /**
     * Class config file
     *
     * @var string
     */    
    private $config_file = "app/config/sendmail.json";

    /**
     * Load core objects
     * 
     * 
     */
	public function load()
 	{  
 		core::getInstance()->cloneIn($this, array("db", "data", "input", "parser", "email", "upload"));
 	}

    /**
     * Get custom config of senmail.json
     * 
     * 
     */
 	private function setConfig($key)
 	{
 		$this->config = file_get_json( BASEPATH.$this->config_file );

 		return $this->config->{$key};
 	}

    /**
     * Get post valid items from sendmail.json
     * 
     * 
     */
 	private function getPostKeys($config)
 	{
		$post	= new stdClass; 
		 
		foreach($config->field as $item)
		{
			$post->$item	= $this->input->post($item ,TRUE); 
		} 

		return $post;
 	}


    /**
     * Parse email template with post data
     * 
     * 
     */
 	private function getHtmlTemplate($config, $post)
 	{
        $data 	= (array) $post; 

        $data["app"]= $config->app;

 		return $this->parser->parse( $config->template , $data , TRUE ) ;
 	} 


    /**
     * Read post, storage data and send email
     * 
     * 
     */
	public function build($section)
	{    
		if(!count($_POST)) die("POST NOT SEND");

		$config = $this->setConfig($section);

    	$post  = $this->getPostKeys($config); 

    	//INSERT DATA
        $this->db->query( INSERT($config->table, $post) ); 

		//EMAIL PRE-PROCESS
        $this->email->from	 ( $this->email->contacto, $this->email->nombre);
        $this->email->to	 ( $config->to  	);     
        $this->email->subject( $config->subject );
        $this->email->message( $this->getHtmlTemplate($config, $post) );

        //UPLOAD PRE-PROCESS
		$this->upload->setKey($config->upload->key);
		$this->upload->setFolder($config->upload->folder);
		$this->upload->setAllowExtension(upload::{$config->upload->allow});

		$upload = $this->upload->process();

        if($upload->code == upload::FILE_UPLOAD_OK)  $this->email->attach($upload->file); 

        $this->email->send();	   

        redirect($config->redir);  	
	}

 
} 
