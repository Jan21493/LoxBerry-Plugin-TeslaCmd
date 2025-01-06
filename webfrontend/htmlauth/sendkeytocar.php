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
LOGINF("sendkeytocar: Sending public key to car with VIN: $vin.");

$baseblecmd = str_replace(VEHICLE_TAG, $vin, $baseblecmd);
$sendkeyscmd = str_replace(VEHICLE_TAG, $vin, $sendkeyscmd);
$blefullcmd = str_replace("{command}", $sendkeyscmd, $baseblecmd);

$result_code = tesla_shell_exec( "$blefullcmd", $output, $ble_repeat, true);
// raw output with full debugging (if enabled)
LOGDEB("sendkeytocar: -------------------------------------------------------------------------------------");
foreach($output as $key => $line) {
    LOGDEB("$line");
}
LOGDEB("sendkeytocar: -------------------------------------------------------------------------------------");
$output = end($output);
// command was successful
if ($result_code == 0 && strpos($output, "Confirm by tapping NFC card on center console.") > 0) {
    // return HTTP response code 200 = O.K.
    $return = array(
        'status' => 200,
        'message' => "Tap NFC card!"
    );
    http_response_code(200);
} else {
    // return HTTP response code 409 = Conflict (I didn't found a better code)
    $return = array(
        'status' => 409,
        'message' => $output
    );
    http_response_code(409);
}
print_r(json_encode($return));
?>
