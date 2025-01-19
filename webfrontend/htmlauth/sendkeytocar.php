<?php

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";


$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging - sendkeytocar.php");

LOGINF("sendkeytocar.php: -------------------- start of sendkeytocar.php -------------------- ");

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
$apidata = read_api_data();
LOGINF("sendkeytocar: Sending public key to car with VIN: $vin.");
$blecmd = str_replace(VEHICLE_TAG, $vin, ADD_KEY_REQUEST);
$baseblecmd = str_replace(VEHICLE_TAG, $vin, $apidata->baseblecmd);

// preparing BLE command with wakeup if necessary
LOGDEB("sendkeytocar: preparing BLE command: ".$baseblecmd.", command: ".$blecmd.", retries: ".$apidata->ble_retries.", force=true.");
// don't force a wakeup, add-key-request works when vehicle is asleep (we don't have a key yet that has been installed in the vehicle already)
$output = tesla_ble_query( $vin, "ADD_KEY_REQUEST", $baseblecmd, $blecmd, $apidata->ble_retries, $apidata->lock_timeout, false );

// separate json output from debug (no JSON)
$return = strpos($output, "\n");
if ($return)
    $jsonoutput = substr($line, 0, $return);
else
    $jsonoutput = $output;
$jsonoutput = json_decode($jsonoutput);
$result_code = 1;
$result_code = $jsonoutput ->{"result_code"};
$output_msg = $jsonoutput ->{"output_msg"};
$error_msg = $jsonoutput ->{"error_msg"};

LOGDEB("sendkeytocar: received result_code=$result_code, output_msg=\"$output_msg\", error_msg=\"$error_msg\"");

if ($result_code == 0 && strpos($output_msg, "Confirm by tapping NFC card on center console.") > 0) {
    // return HTTP response code 200 = O.K., with success and message
    $return = array(
        'status' => 200,
        'success' => 1, 
        'message' => "Tap NFC card!"
    );
    http_response_code(200);
    LOGOK("sendkeytocar: sending public key was successful!");
} else {
    // return HTTP response code 200 = O.K. witout success and error message
    $return = array(
        'status' => 200,
        'success' => 0,
        'message' => "$error_msg"
    );
    http_response_code(200);
    LOGERR("sendkeytocar: failed! returned message: $error_msg");
}
print_r(json_encode($return));

LOGINF("sendkeytocar.php: ==================== end of sendkeytocar.php ==================== ");

?>
