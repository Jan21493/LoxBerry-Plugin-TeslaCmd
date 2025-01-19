<?php

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";

$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging - deletekeys.php");

LOGINF("deletekeys.php: -------------------- start of deletekeys.php -------------------- ");

require_once "defines.php";
require_once "tesla_inc.php";

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
LOGINF("deletekeys: Deleting both keys (public and private) for VIN: $vin.");
keyDelete($vin, PRIVATE_KEY);
keyDelete($vin, PUBLIC_KEY);

LOGINF("deletekeys.php: ==================== end of deletekeys.php ==================== ");

?>