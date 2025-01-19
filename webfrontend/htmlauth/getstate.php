<?php

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";

$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging - getstate.php");

LOGINF("getstate.php: -------------------- start of getstate.php -------------------- ");

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
LOGINF("getstate: Getting state information via BLE for VIN: $vin.");

$blefullcmd = str_replace(COMMAND_TAG, BODYCONTROLLERSTATE, $apidata->baseblecmd);
$blefullcmd = str_replace(VEHICLE_TAG, $vin, $blefullcmd);
$blefullcmd = str_replace(DEBUG_OPTION, "", $blefullcmd);

LOGDEB("getstate: executing command: ".$blefullcmd);

$result_code = tesla_shell_exec($blefullcmd, $output, $apidata->ble_retries, $apidata->lock_timeout, true);
// raw output with full debugging (if enabled)
LOGDEB("getstate: -------------------------------------------------------------------------------------");
foreach($output as $key => $line) {
    LOGDEB("$line");
}
LOGDEB("getstate: -------------------------------------------------------------------------------------");
$output = end($output);

// command was successful
if ($result_code == 0) {
    $sleepStatus = "";
    $rssi = 0;
    // check if vehicle is asleep
    if (strpos($output, '"vehicleSleepStatus":2') > 0) {
        $sleepStatus = "asleep";
    } else if (strpos($output, '"vehicleSleepStatus":1') > 0) {
        $sleepStatus = "awake";
    } 
    $col = strpos($output, '"rssi":');
    if ($col > 0) {
        $rssi = substr($output,$col+7,strpos($line, ',', $col+7)-$col-7);
    }
    if (empty($sleepStatus)) {
        $sleepStatus = "unknown";
    } 
    // return HTTP response code 200 = O.K.
    $return = array(
        'status' => 200,
        'vin' => $vin,
        'sleepStatus' => $sleepStatus,
        'rssi' => $rssi
    );
    http_response_code(200);
    LOGOK("getstate: Success! RSSI: $rssi, sleep status: $sleepStatus");
} else {
    // return HTTP response code 409 = Conflict (I didn't found a better code)
    $return = array(
        'status' => 409,
        'vin' => $vin,
        'sleepStatus' => 'unknown',
        'rssi' => 'unknown'
    );
    http_response_code(409);
    LOGERR("getstate: Failed! The command to get state information has failed! The result code was: $result_code");
}
print_r(json_encode($return));

LOGINF("getstate.php: ==================== end of getstate.php ==================== ");

?>