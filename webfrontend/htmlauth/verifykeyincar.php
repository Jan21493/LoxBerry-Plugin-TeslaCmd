<?php

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";


$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging - verifykeyincar.php");

LOGINF("verifykeyincar.php: -------------------- start of verifykeyincar.php -------------------- ");

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
LOGINF("verifykeyincar: Sending public key to car with VIN: $vin.");

$blefullcmd = str_replace(COMMAND_TAG, LIST_KEYS, $apidata->baseblecmd);
$blefullcmd = str_replace(VEHICLE_TAG, $vin, $blefullcmd);
LOGDEB("verifykeyincar: executing command: ".$blefullcmd);

$result_code = tesla_shell_exec( "$blefullcmd", $output, $apidata->ble_retries, $apidata->lock_timeout, true);
// raw output with full debugging (if enabled)
LOGDEB("verifykeyincar: -------------------------------------------------------------------------------------");
foreach($output as $key => $line) {
    LOGDEB("$line");
}
LOGDEB("verifykeyincar: -------------------------------------------------------------------------------------");

// command to retrieve key list was successful
if ($result_code == 0) {
    $output = end($output);
    $response = json_decode($output);
    $keylist = $response->keylist;
    getPublicKeyHex($vin, $hexKey);
    LOGDEB("verifykeyincar: locally stored public key in raw format: ".$hexKey);
    $found = false;
    foreach ($keylist as $index => &$keylistEntry) {
        //LOGDEB("verifykeyincar: hex key no ".$index." from car: ".$keylistEntry->publicKey);
        if ($hexKey == $keylistEntry->publicKey) {
            // return HTTP response code 200 = O.K. and sucess with message
            $return = array(
                'status' => 200,
                'success' => 1, 
                'message' => "Key was installed in the vehicle!"
            );
            http_response_code(200);
            $found = true;
            LOGOK("verifykeyincar: Success! The last key matched, so it was found in the keylist of the vehicle!");
            break;
        }
    }
    if (!$found) {
        // return HTTP response code 200 = O.K. and no sucess with message
        $return = array(
            'status' => 200,
            'success' => 0, 
            'message' => "Key was not found in key list of the vehicle."
        );
        http_response_code(200);
        LOGERR("verifykeyincar: Failed! The key was NOT found in the keylist of the vehicle.");
    }
} else {
    // return HTTP response code 200 = O.K. and no sucess with message
    $return = array(
        'status' => 200,
        'success' => 0, 
        'message' => $output
    );
    http_response_code(200);
    LOGERR("verifykeyincar: Sending the command to verify if the key was install on the vehicle has failed! The result code was: $result_code");
}
print_r(json_encode($return));

LOGINF("verifykeyincar.php: ==================== end of verifykeyincar.php ==================== ");

?>
