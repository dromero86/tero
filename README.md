<p align="center">
  <a href="https://github.com/dromero86/tero/" target="_blank" >
    <img alt="Tero" src="https://i.pinimg.com/originals/7e/9e/62/7e9e624d4ba03c5755a83764964a878d.jpg" height="130" /> <br>
	  <h3 align="center">TERO FRAMEWORK</h3> 
  </a>
</p>
<hr>
<p align="center">
Tero is a micro web framework for PHP thought for the simple writing and fast resolution of problems.
</p>

<br>
<br>
<br>
<br>

### About Tero

Tero is written in php 5.3 (which supports the use of anonymous functions with bind), designed to run both that version and server versions 5.6 and 7+

It is optional to use friendly urls but if you want it to work you should use it in apache with mod_rewrite enabled.

For database is intended to integrate almost any database through PDO, Tero was widely used in MySQL 5.5 / Mariadb / SQLite / Mssql Server 2005+

It is supported on both Windows (wamp) and Linux (lamp) base servers

To install tero just go with your console to the web directory and run composer

### Install

```
composer create-project dromero86/tero project_name
```

Tero, by default, has this folder structure:

```
   MyWebsite/
	|--app/
	|  |--config/
	|  |--library/
	|  |--model/
	|  |--schema/
	|  |--third_party/
	|  |--vendor/
	|
	|--ui/
	|  |--images/
	|  |--themes/
	|     |--mytheme
	|
	|--index.php
```

index.php acts as bootstrapper to execute the core, this also has two tasks, the first is to load all the libraries needed for the project and the second is to load all the model-controllers written by the user and finally execute the one required by the url.

The folder app has the structure of the framework that is not public, therefore it is not accessible via web instead the folder ui has all the resources that will be of web use as images, scripts and css files.

### Hello World with tero is:

```php
//1#
<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

//2# 
$App = core::getInstance();  

//3# 
$App->get("index", function()
{    
    //4# 
    echo "Hello world!";
});
```
The first line specifies the environment of tero (similar to codeigniter) and is valid for all files defined by the user. It is a security measure to not access the file directly and exploit a vulnerability.

The second line obtains the core instance, important for defining our controllers and accessing defined libraries / helpers

The third line defines our controller, with the first argument it will interpret the web call and with the function it will resolve the content to be returned

The fourth line is the content printed by the browser. for example, in this case if our project would be at http://localhost/MyWebsite/  el
content to be returned by this driver would be:

```
Hello World!
```

Note that the use of "index" refers to the default controller, therefore there is no need to add parameters to the url.

## Cli Mode

```bash
php /path/to/tero/index.php action="my-command" arg_name1="arg_value1" arg_nameN="arg_valueN"
```

## Setup Apps

To include libraries or helpers, core.json is used, this file contains the load configuration and other options to improve or limit the performance of the app, this file has this syntax:

```json
{
    "loader":
    [
        {"file":"app/vendor/Parser"    , "library":{ "class":"Parser"   , "rename":"parser" } },
        {"file":"app/vendor/Skeleton"  , "library":{ "class":"Skeleton" , "rename":"view"   } },
        {"file":"app/vendor/Dataset"   , "library":{ "class":"Dataset"  , "rename":"data"   } },
        {"file":"app/vendor/input"     , "library":{ "class":"input"    , "rename":"input"  } },
        {"file":"app/model/docs"       , "helper" : true },
        {"file":"app/model/sketch"     , "helper" : true }  
    ],
    "debug"   : false,
    "error"   : "On" ,
    "leak"    : "50M",
    "timezone":"America/Vancouver",
    "encoding": "UTF-8" 
}
```
Within the loader, the elements to be loaded are stacked, the order of loading is from ascending to descending, and as a criterion "from which the smallest dependencies have the most".

The "File" attribute indicates where the file is located and starts from the folder where the file is located to the file without ".php"
 
It is important to differentiate a class or library from a helper because core saves the instance of the class as public property (accessible within the user's driver), however, for a helper only loads it.

For libraries I can rename the instance for example:

```php
$App->get("...", function(...))
{
     //data is rename of dataset
     $this->data->set(...);
});
```

## Router URLs

To take the arguments of the url as parameters and to understand how the processes are processed, we will explain how each of them works.

Tero accepts (where "index" is an example method):

- /?action=index
- /index
- /index-:id => :id is param
- /index?another=param
- /index-:id?another=param


Method url: INDEX
Param order: left to right

extract url method in this order:
- Match Simple: find method in rewrited url without regex url, if not
- Match Params: find method in rewrited url with regex expression, if not
- Default     : parse GET params

if method exist and is callable, call it 
if not call default method “index”

To work with friendly urls in apache remember to enable "mod_rewrite" and include .htaccess with:

```
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f

RewriteRule ^ index.php [QSA,L]
```

This will enable type urls
/my-cool-page or parameterized as
/product/:name-:id where ": name" and ": id" are the parameters that will be used in the method:

http://localhost/myterocart/product/cool-glass-red-2345

```php 
$App->get("product/:name-:id", function($name, $id)) //name unnused in the example
{
     //take params
     $id = (int)$id; 

     $this->db->query(“SELECT price FROM products WHERE product_id = {$id}”);
});
```

Later we will see more uses of the parameters.

### Redirections

We often need to jump from one page to another from the server, this can be solved with the native "redirect" function that is implemented like this

```php
$App->get("product/:id", function($id)) 
{
	$category = 36;
	redirect(“products/category/{$category}”);
  ...
```
In the example, it means that you will jump from the products page to the categories page from the server, this function also allows you to indicate headers for when you perform permanent redirects.

# Requesting

Capturing the get variables of the url:

A) the simple native path: the get parameters are serialized and passed to the model function as follows:

```php
//http://localhost/museum_library/?action=download_pdf&file=myfile.pdf

$App->get("download_pdf", function($file)) 
{
...
```
Here the processed variable is file, note that "action" is a reserved word and is used internally by the core of tero.

We must also understand that Tero returns all the parameters in string, it is our responsibility to perform the appropriate cast to avoid security cracks.

B) using friendly urls: Parameters are obtained as a result of using regular expressions on the pattern of the url (the get is not used) the example we saw earlier

```php
//http://localhost/terocart/product/36
$App->get("product/:id", function($id)) 
{
...
```
Here it will take "product/36" and in this case, it will take "36" and pass it as an argument of the method.

C) a bit more complex (and fun): Tero can combine the 2 things, on the one hand, extract variables through a regular expression as well as process the get parameters, leaving a funny monster

```php
//http://localhost/clothestero/tshirt-category/36?color=blue&size=xxl
$App->get("tshirt-category/:category, function($category, $color=””, $size=””)) 
{
...
```

For our Frankenstein to work, we must consider some things:

1. The url pattern goes first
2. The get arguments are not written as if they were regular expressions
3. The get arguments can be placed as optional using php syntax for functions {$ variable = ""}
4. When we use Get we must respect the order of the parameters

## Working with POST

The use of post is not native to the core of tero, but if it is included as a library, therefore we must include it in core.json

```json
{
    "loader":
    [
        ...
        {"file":"app/vendor/input" , "library":{ "class":"input" , "rename":"input"  } }

```
Its use is really simple

```php
$App->get("...”, function(...)) 
{

   $post = $this->input->post();
   ...
```
The post() input method converts the _POST array into an object (with the possibility of treating the elements), as an object it will be useful later when we use database, but even if we do not need it for that, its use is really comfortable

```
	$post = $this->input->post();
	
	$post->name 
	$post->phone
	etc
```

Check if I have post elements

```
$App->get("...”, function(...)) 
{
   if($this->input->has_post())
   {
     $post = $this->input->post();
   }
```

## SETUP DATABASE

To integrate our database we must first enable it from core.json, this is done as follows:

```json
{
    "loader":
    [
      ...
      {"file":"app/vendor/database", "library":{ "class":"database" , "rename":"db"  } 
```

This indicates that we will have access to the database through the core attribute "$this->db" but we still have to configure the access to the database, for this we will have to edit db.json

```json
{
    "database" :
    {
        "driver"    : "mysqli",
        "user"      : "mydbuser"      ,
        "pass"      : "mydbpass"          ,
        "host"      : "myhostname" ,
        "db"        : "mydbname"        ,
        "charset"   : "utf8"      ,
        "collate"   : "utf8_general_ci",
        "debug"     : false,
	"default"   : true
    }
}
```

If everything is ok, we can check if the connection works like this

```php
$App->get("...”, function(...)) 
{
   var_dump($this->db->is_ready());

```

If I return TRUE it means that we connect successfully.

## Support Multibase connection

Set default TRUE for active database

```json
{
    "server1" :
    {
        "driver"    : "mysqli",
        "user"      : "mydbuser"      ,
        "pass"      : "mydbpass"          ,
        "host"      : "myhostname" ,
        "db"        : "mydbname"        ,
        "charset"   : "utf8"      ,
        "collate"   : "utf8_general_ci",
        "debug"     : false,
        "default"   : true
    },
    "server2" :
    {
        "driver"    : "mysql",
        "user"      : "mydbuser"      ,
        "pass"      : "mydbpass"          ,
        "host"      : "myhostname" ,
        "db"        : "mydbname"        ,
        "charset"   : "utf8"      ,
        "collate"   : "utf8_general_ci",
        "debug"     : false
    },
    "server3" :
    {
        "driver"    : "mssql",
        "user"      : "mydbuser"      ,
        "pass"      : "mydbpass"          ,
        "host"      : "myhostname" ,
        "db"        : "mydbname"        ,
        "charset"   : "utf8"      ,
        "collate"   : "utf8_general_ci",
        "debug"     : false
    }
}
```

Just call

```php
$server2 = $this->db->use("server2");
$server2->query("...");

$server3 = $this->db->use("server3");
$server3->query("...");
```


## QUERY AND RESULTS

To run a query, just put

```php
$rs = $this->db->query(“SELECT … FROM … ”);
```

where $ rs will have information of the results encapsulated in an object to access the necessary times in the following way

```php
$rs = $this->db->query(“SELECT id FROM … ”);

foreach($rs->result() as $row)
{
	//$row->id;
	//$row->{“id”}
}
```
If you want to run a stored procedure, you can execute it as:

```php
$rs = $this->db->procedure(“mysp(1,’2’)”);

foreach($rs->result() as $row)
{
   ...
}
```
*IMPORTANT: to work with stored procedures in mysql you must configure in db.json that the driver is mysqli*


## ACTIVE SESSIONS

We can work with sessions using the Telepatia library. To load it we must edit core.json in the following way.

```json
{
    "loader":
    [
      …
      {"file":"app/vendor/database", "library":{ "class":"database" , "rename":"db"  } 
      {"file":"app/vendor/Telepatia", "library":{ "class":"Telepatia" , "rename":"sesion"  } 

```

For sessions to work we must understand that these are stored in database, therefore, it is required to load the database library before Telepatia. Once this is done we must configure sesion.json


```json
{
    "Telepatia": 
    {
        “app”:”name_of_cookie_ref_app”,
        “table”:”db_session_table”,
        “timeout”: 60,
    }
}

```

To store a variable in the session we must place:

```php
//$variable ( string | id | float )
$this->session->send( $variable);
```

To recover the stored data:

```php
$value = $this->session->recv() ;
```

If the session is inactive $value will be equal to FALSE

To close the session we must place:

```php
$this->session->close();
```

## WEB SKELETONS

Web skeletons are pages composed of templates that are joined together to then be rendered together with the data.

To use it we must make the following adjustments:

In core.json

```json
{
    "loader":
    [
     {"file":"app/vendor/Parser", "library":{ "class":"Parser", "rename":"parser" } },
     {"file":"app/vendor/Skeleton", "library":{ "class":"Skeleton","rename":"view”} }, 

```

Parser, at low level, to replace values in variables "{my_variable}".

Then we must define our theme.json

```json
{
    "path": "ui/themes/my_theme/",
    "vars": "vars.json",
    "view": "view.json"
}
```
Inside the folder my_theme we must create 2 files vars.json for static variables and view.json that will contain the structure of the pages
The proposed structure of the my_theme folder can be:


```
   MyTheme/
	|--css/
	|--fonts/
	|--img/
	|--js/
	|--css/ 
	|
	|--views/
	|  |--blocks/
	|     |--_home.php
	|     |--_product.php
	|     |--_contact.php
	|  |--emails/
	|  |--global/
	|     |--_layout.php
	|     |--_header.php
	|     |--_foorer.php
	|
	|--vars.json
	|--view.json
```
You can see that we created 3 global files, layout, header, footer and 3 files that correspond to parts that can (or can not) be repeated.

For now vars.json we'll leave it with {} and focus on view.json

Create a page in view.json

```
{
	"index" :
	{ 
        "layout" : "global/_layout.php",
        "header" : "global/_header.php",
        "content":  
        [
            { "file": "block/home.php" }, 
        ],  
        "footer" : "global/_footer.php"
	},
```
we invoke this from the controller like this:

```php
$App->get("index", function())  
{ 
     $this->view->write(“index”);
});
```

This will return the web page.

Now, how does this work? What the hell is it doing inside?

It all starts in the Parser library, a brilliant idea of codeigniter to work with templates, to see its use see:https://www.codeigniter.com/userguide3/libraries/parser.html 
The idea is very simple, it is to replace this "{variable_en_plantilla_html}" by this "$ variable_php_usually_string_o_entero"

Tero takes this concept to a next level and transforms it into a principle of layout of views, this new law, so to speak, will be expressed in the following way.

```
> "The HTML view will not under any circumstances have PHP code, its use will be penalized with imprisonment in maximum security jail (nah !, lie;))".
```

With this we create a problem for our PHP programmer friend, but do not panic, we solve it in this way.

1. Understanding the power of Parser rendering that allows us to connect simple variables as an array, later this will allow us to connect our data resultset.
2. Understanding that list rendering allows us to write selective code eg:

```html
<div>
{si_tiene_session_activa}
	<h1>Session id: {si_tiene_session_activa_username}</h1>
{/si_tiene_session_activa}
</div>
```

While my server code could be:

```php
$data[“si_tiene_session_activa”]= array();

$sesion = $this->session->recv() ;

if(!$sesion) // != FALSE
{
$data[“si_tiene_session_activa”][]=array
(
“si_tiene_session_activa_username”=>$session
);
}

$this->view->write(“myview”, $data);
```

Later, we can do this only in one line.

The benefits then, are in sight;)

1. Segmentation of templates, everything that can be done in blocks is reused.
2. Simplification of the layout with simple variables to place and use
3. Easy to debug, if you see a variable "{sin_renderizar}" it's because something is wrong.
4. Without php errors, if you do not embed php code there is no possibility of error.
5. Friendly with javascript, friend, this makes the difference.
6. Easy to think for designers and layout designers (Proven)

## DATASET

The pipeline between the data and the view.

To minimize the excess code produced by the array that Parser receives, to solve the complexity produced by connecting data with the view and to reflect a readable code that can be maintained over time, Dataset exists.

Let's include it to core.json

```
{
    "loader":
    [
      …
      {"file":"app/vendor/Dataset", "library":{ "class":"Dataset" , "rename":"data"  } }
```

How is it used?

Set a variable

```php
$this->data->set(“username”, “mynickname”);
```

or

```php
$this->data->set(“username”, $variable);
```

if we want to define a list with this structure:

```html
{news}
<h1>{news_title}</h1>
<p>{news_details}</p>
{/news}
```

we must start a list like this:

```php
$this->data->set(“news”);
```

to fill it we can map an object

```php
$news = new stdclass;
$news->title = “A example  news”;
$news->details = “this is a example news row”;

$this->data->map(“news”, $news);

$this->view->write(“myview”, $this->data->get());
```

This should produce

```html
<h1>A example  news</h1>
<p>this is a example news row</p>
```

Now, this is not meant for the use of stdclass, then ..., what is it for?

Database!

Look how

```php
$this->data->set(“news”);

$rs = $this->db->query(“SELECT title,details FROM news_table”);

foreach($rs->result() as $row)
	$this->data->map(“news”, $row);

$this->view->write(“myview”, $this->data->get());
```

In just 5 lines you did the following:

1. You filled a template with data.
2. If there is no data, the variables will not be visible
3. If your query has more fields, they are automatically rendered as news_xxxx
4. You can easily change the name of the variables to make a more readable code

What happens if I do not want a list, or do I have a single record to render?

Well, then you can use "automap", whose principle is to take an object and for each property to create an individual variable.

Going back to the news example ...

```php
$news = new stdclass;
$news->title = “A example  news”;
$news->details = “this is a example news row”;

$this->data->automap($news, “news_”);

$this->view->write(“myview”, $this->data->get());
```

this should help us to fill this view

```html
<h1>{news_title}</h1>
<p>{news_details}</p>
```

Note that there is no list that involves the template, this is because they are individual variables.

## PRINCIPLES 

- [x] Tero must be easy to learn.
- [x] Tero must be easy to apply.
- [x] Tero must be easy to teach.
- [x] Tero must know how to adapt to the future.
- [x] Tero must be ridiculously intuitive.
- [x] Tero must be the developer's tool
- [x] Tero must be a framework to earn money through the solution made in the shortest possible time
