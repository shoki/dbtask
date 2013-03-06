#!/usr/bin/env php
<?php

ini_set('include_path', dirname(__FILE__).':.');

function __autoload($class_name) {
    require_once $class_name . '.php';
}

/* install custom Error handler */
require_once 'ErrorHandler.php';
$old_error_handler = set_error_handler("my_error_handler");

$ts = new DB_Task();
$ts->run(array_slice($argv,1));	/* skip argv[0] */


?>
