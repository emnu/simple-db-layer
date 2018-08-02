<?php

function ErrorHandler($errno, $errmsg, $filename, $linenum, $vars) 
{
    // timestamp for the error entry
    $dt = date("Y-m-d H:i:s (T)");

    // define an assoc array of error string
    // in reality the only entries we should
    // consider are E_WARNING, E_NOTICE, E_USER_ERROR,
    // E_USER_WARNING and E_USER_NOTICE
    $errortype = array (
                E_ERROR              => 'Error',
                E_WARNING            => 'Warning',
                E_PARSE              => 'Parsing Error',
                E_NOTICE             => 'Notice',
                E_CORE_ERROR         => 'Core Error',
                E_CORE_WARNING       => 'Core Warning',
                E_COMPILE_ERROR      => 'Compile Error',
                E_COMPILE_WARNING    => 'Compile Warning',
                E_USER_ERROR         => 'User Error',
                E_USER_WARNING       => 'User Warning',
                E_USER_NOTICE        => 'User Notice',
                E_STRICT             => 'Runtime Notice',
                // E_RECOVERABLE_ERROR  => 'Catchable Fatal Error' // not compatible in php ver 5.1.*
                );
    // set of errors for which a var trace will be saved
    $user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);
    
    $err = array();
    $err['processid'] = Task::getProcessId();
    $err['datetime'] = $dt;
    $err['errornum'] = $errno;
    $err['errortype'] = $errortype[$errno];
    $err['errormsg'] =  $errmsg;
    $err['scriptname'] = $filename;
    $err['scriptlinenum'] = $linenum;

    if (in_array($errno, $user_errors)) {
        $err['vartrace'] = wddx_serialize_value($vars, "Variables");
    }
    
    if(CONFIG::$debug > 0) {
        echo "[" . $err['datetime'] . "] " . $err['errortype'] . "[" . $err['errornum'] . "]: " . $err['errormsg'] . " in " . $err['scriptname'] . " on line " . $err['scriptlinenum'] . "\n";
    }

    // save to the error log, and e-mail me if there is a critical user error
    error_log(json_encode($err) . "\n", 3, APP_PATH."logs".DIRECTORY_SEPARATOR."error.log");
    // if ($errno == E_USER_ERROR) {
    //     mail("phpdev@example.com", "Critical User Error", $err);
    // }
}
set_error_handler("ErrorHandler");