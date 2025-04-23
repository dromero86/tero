<?php //php -S localhost:8000

require "../vendor/autoload.php"; 

$App = Tero\Application::Get( Tero\Core::class );

$App->get('demo', function(){

    die("Hello Tero!");
});

$App->run();