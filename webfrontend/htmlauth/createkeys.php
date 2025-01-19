<?php

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";

$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging - createkeys.php");

LOGINF("createkeys.php: -------------------- start of createkeys.php -------------------- ");

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

LOGINF("createkeys: Creating a public private key pair for VIN: $vin.");
$keygencmd = str_replace(VEHICLE_TAG, $vin, TESLA_KEYGEN_CMD);
$result_code = tesla_shell_exec( $keygencmd, $output, 0, 0, false);
	
// raw output with full debugging (if enabled)
LOGDEB("createkeys: output -------------------------------------------------------------------------------------");
foreach($output as $key => $line) {
    LOGDEB("$line");
}
LOGINF("createkeys.php: ==================== end of createkeys.php ==================== ");

?>