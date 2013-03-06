<?php

/* include this file to use custom error handler which will give you
   an backtrace on PHP errors */

function my_error_handler($errno, $errstr, $errfile, $errline){
    $errno = $errno & error_reporting();
    if ($errno == 0)
    	return true;
    if (!defined('E_STRICT'))
    	define('E_STRICT', 2048);
    if (!defined('E_RECOVERABLE_ERROR'))
    	define('E_RECOVERABLE_ERROR', 4096);

    switch($errno) {
        case E_ERROR:               $str = "Error";                  break;
        case E_WARNING:             $str = "Warning";                break;
        case E_PARSE:               $str = "Parse Error";            break;
        case E_NOTICE:              $str = "Notice";                 break;
        case E_CORE_ERROR:          $str = "Core Error";             break;
        case E_CORE_WARNING:        $str = "Core Warning";           break;
        case E_COMPILE_ERROR:       $str = "Compile Error";          break;
        case E_COMPILE_WARNING:     $str = "Compile Warning";        break;
        case E_USER_ERROR:          $str = "User Error";             break;
        case E_USER_WARNING:        $str = "User Warning";           break;
        case E_USER_NOTICE:         $str = "User Notice";            break;
        case E_STRICT:              $str = "Strict Notice";          break;
        case E_RECOVERABLE_ERROR:   $str = "Recoverable Error";      break;
        default:                    $str = "Unknown error ($errno)"; break;
    }
    $str .= ": $errstr in $errfile on line $errline\n";
    if (function_exists('debug_backtrace')) {
        $str .= "backtrace:\n";
        $backtrace = debug_backtrace();
        array_shift($backtrace);
        $newlineFlag = count($backtrace) - 1;
        foreach ($backtrace as $i => $l) {
            $str .= "\t[$i] {$l['class']}{$l['type']}{$l['function']}";
            if ($l['file'])
            	$str .= " in {$l['file']}";
            if ($l['line'])
            	$str .= " on line {$l['line']}";

            if ($i < $newlineFlag)
            	$str .= "\n";
        }
    }

    // in CLI mode we just echo the errors
    if (php_sapi_name() == 'cli')
		echo($str."\n");
    else {
		$errarr = explode("\n", $str);
		foreach ($errarr as $line)
		    error_log($line);
    }

    if (isset($GLOBALS['error_fatal']))
        if ($GLOBALS['error_fatal'] & $errno)
        	die('fatal');
    return true;
}

function error_fatal($mask = NULL) {
    if (!is_null($mask))
        $GLOBALS['error_fatal'] = $mask;
    elseif (!isset($GLOBALS['die_on']))
        $GLOBALS['error_fatal'] = 0;
    return $GLOBALS['error_fatal'];
}

?>
