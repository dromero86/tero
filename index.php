<?php

/**
 * Tero Framework 
 *
 * @link      https://github.com/dromero86/tero
 * @copyright Copyright (c) 2014-2019 Daniel Romero
 * @license   https://github.com/dromero86/tero/blob/master/LICENSE (MIT License)
 */    

require "app/vendor/core.php";

$App->get('test', function(){
    var_dump($_SERVER); 
});


$App->run(); 