OBJ-MySQL
=========
[![Latest Stable Version](https://poser.pugx.org/entomb/obj_mysql/v/stable.png)](https://packagist.org/packages/entomb/obj_mysql)
[![Total Downloads](https://poser.pugx.org/entomb/obj_mysql/downloads.png)](https://packagist.org/packages/entomb/obj_mysql)
[![Bitdeli Badge](https://d2weczhvl823v0.cloudfront.net/entomb/obj-mysql/trend.png)](https://bitdeli.com/free "Bitdeli Badge")


OBJ MySQL is a simple MySQL Abstraction Layer for PHP>5.2 that provides a simple and _secure_ interaction with your database using mysqli_* functions at its core.

OBJ-MySQL is perfect for small scale applications such as cron jobs, facebook canvas campaigns or micro frameworks or sites.

_This project is under construction, any feedback would be appreciated_

Author: [Jonathan Tavares](https://github.com/entomb)

**checkout the [changelog](https://github.com/entomb/OBJ-MySQL/wiki/changelog) for info on the latest changes**



##Get OBJ_MySQL
You can download it from here, or require it using [composer](https://packagist.org/packages/entomb/obj_mysql).
```json
{
    "require": {
		"entomb/obj_mysql": "dev-master"
	}
}
```

Or you can require it by cloning this repo

```bash
$ git clone https://github.com/entomb/OBJ-MySQL.git
```

If you are already using GIT on you project you can add it as a submodule

```bash
$ git submodule add https://github.com/entomb/OBJ-MySQL.git libs/db
```


##Starting the driver
To start the db driver you must include the main class file and pass the '$config' array described bellow.
you can have multiple instances of the Class each one with its own $config (one for Reads and one for Writes for example).

```php
    //include de main OBJ_mysql class file
    include("bin/OBJ_mysql.php");

    //configuration array
    $config = array();
    $config["hostname"]  = "YOUR_HOST";
    $config["database"]  = "YOUR_DATABASE_NAME";
    $config["username"]  = "USER_NAME";
    $config["password"]  = "PASSWORD";

    //other configurations
    $config["port"]      = "PORT"; //defaults to 3306
    $config["charset"]    = "CHARSET"; //defaults to UTF-8
    $config["exit_on_error"] = "TRUE|FALSE"; //defaults to true
    $config["allow_logging"] = "TRUE|FALSE"; //defaults to true

    //class instantiation
    $db = new OBJ_mysql($config);

```



##Using OBJ_MySQL

there are numerous ways of using this library, here are some examples of the most common methods

###Selecting and retrieving data from a table

```php
  $Result = $db->query("SELECT * FROM users");
  $Users  = $Result->fetchALL();
```

###Inserting data on a table

To manipulate tables you have the most important methods wrapped,
they all work the same way: parsing arrays of key/value pairs and forming a safe query

the methods are:
```php
  $db->insert( String $Table, Array $Data); //generates an INSERT query
  $db->replace(String $Table, Array $Data); //generates an INSERT OR UPDATE query
  $db->update( String $Table, Array $Data, Array $Where); //generates an UPDATE query
  $db->delete( String $Table, Array $Where); //generates a DELETE query
```

All methods will return the resulting `mysqli_insert_id()` or true/false depending on context.
The correct approach if to always check if they executed as success is always returned

```php
  $ok = $db->delete('users', array( 'user_id' => 9 ) );
  if($ok){
    echo "user deleted!";
  }else{
    echo "can't delete user!";
  }
```

**note**: All parameter values are sanitized before execution, you don't have to escape values beforehand.

```php
  $new_user_id = $db->insert('users', array(
                                'name'  => "jothn",
                                'email' => "johnsmith@email.com",
                                'group' => 1,
                                'active' => true,
                              )
                          );
  if($new_user_id){
    echo "new user inserted with the id $new_user_id";
  }
```


###binding parameters on queries

Binding parameters is a good way of preventing mysql injections as the parameters are sanitized before execution.

```php
  $Result = $db->query("SELECT * FROM users WHERE id_user = ? AND active = ? LIMIT 1",array(11,1));
  if($Result){
    $User = $Result->fetchArray();
    print_r($User);
  }else{
    echo "user not found";
  }
```

###Using the OBJ_mysql_result Class

After executing a `SELECT` query you receive a `OBJ_mysql_result` object that will help you manipulate the resultant data.
There are different ways of accessing this data, check the examples bellow:

####Fetching all data
```php
  $Result = $db->query("SELECT * FROM users");
  $AllUsers = $Result->fetchAll();
```
Fetching all data works as `Object` or `Array` the `fetchAll()` method will return the default based on the `$_default_result_type` config.
Other methods are:

```php
$Row = $Result->fetch();        // Fetch a single result row as defined by the config (Array or Object)
$Row = $Result->fetchArray();   // Fetch a single result row as Array
$Row = $Result->fetchObject();  // Fetch a single result row as Object

$Data = $Result->fetchAll();        // Fetch all result data as defined by the config (Array or Object)
$Data = $Result->fetchAllArray();   // Fetch all result data as Array
$Data = $Result->fetchAllObject();  // Fetch all result data as Object

$Data = $Result->fetchColumn(String $Column);           // Fetch a single column in a 1 dimension Array
$Data = $Result->fetchArrayPair(String $key, String $Value);  // Fetch data as a key/value pair Array.

```
####Aliases
```php
  $db->get()                  // Alias for $db->fetch();
  $db->getAll()               // Alias for $db->fetchAll();
  $db->getObject()            // Alias for $db->fetchAllObject();
  $db->getArray()             // Alias for $db->fetchAllArray();
  $db->getColumn($key)        // Alias for $db->fetchColumn($key);
```

####Iterations
To iterate a resultset, you can use any fetch() method listed above

```php
  $Result = $db->query("SELECT * FROM users");

  //using while
  while( $row = $Result->fetch() ){
    echo $row->name;
    echo $row->email;
  }

  //using foreach
  foreach( $Result->fetchAll() as $row ){
    echo $row->name;
    echo $row->email;
  }

```

####Logging and Errors

Showing the query log. the log comes with the SQL executed, the execution time and the result row count (if any)
```php

  print_r($db->log());

```

To debug mysql errors:

Use `$db->errors()` to fetch all errors (returns false if no errors) or `$db->lastError()` for information on the last error.

```php
  if( $db->errors() ){
      echo $db->lastError();
  }
```




