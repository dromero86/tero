<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

/**
 * Crunch
 *
 * @package     Tero
 * @subpackage  Vendor
 * @category    Library
 * @author      Daniel Romero 
 */ 

class crunch {

    private $config = null;

    private $config_file = "app/config/crunch.json";

    function __construct() 
    {
        $this->config = file_get_json(BASEPATH.$this->config_file);  
    }

    public function build()
    {
        $css = "";

        foreach($this->config->files as $file)
        {
            $css .= "\n/* BEGIN {$file} */\n\n".file_get_contents(BASEPATH.$file)."\n/* END {$file} */\n";
        }

        foreach($this->config->vars  as $key=>$vars)
        { 
            $css = str_replace("@{$key}", $vars, $css);
        }

        file_put_contents($this->config->output, $css);

        header("Content-type: text/css; charset: UTF-8");
        die($css);
    }

}