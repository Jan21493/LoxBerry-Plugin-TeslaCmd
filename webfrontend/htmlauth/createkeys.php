<?php

require_once "loxberry_web.php";
require_once "tesla_inc.php";
require_once "defines.php";

//
// Query parameter 
//

// Convert commandline parameters to query parameter
foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_REQUEST[$e[0]]=$e[1];
    else    
        $_REQUEST[$e[0]]=0;
}

if(!empty($_REQUEST["keysID"])) { 
	$vin = $_REQUEST["keysID"];
} 

LOGINF("createkeys: Creating a public private key pair for VIN: $vin.");
$keygencmd = str_replace(VEHICLE_TAG, $vin, $keygencmd);
$result_code = tesla_shell_exec( $keygencmd, $output);
	
// raw output with full debugging (if enabled)
LOGDEB("createkeys: -------------------------------------------------------------------------------------");
foreach($output as $key => $line) {
    LOGDEB("$line");
}
LOGDEB("createkeys: -------------------------------------------------------------------------------------");

?>