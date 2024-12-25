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

read_api_data($baseblecmd, $ble_repeat);
LOGINF("deletekeys: Deleting both keys for VIN: $vin.");
keyDelete($vin, $baseblecmd, PRIVATE_KEY);
keyDelete($vin, $baseblecmd, PUBLIC_KEY);

?>