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
LOGINF("verifykeyincar: Sending public key to car with VIN: $vin.");

$baseblecmd = str_replace(VEHICLE_TAG, $vin, $baseblecmd);
$verifykeyscmd = str_replace(VEHICLE_TAG, $vin, $verifykeyscmd);
$blefullcmd = str_replace("{command}", $verifykeyscmd, $baseblecmd);

$result_code = tesla_shell_exec( "$blefullcmd", $output);
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
    LOGDEB("verifykeyincar: locally stored public key in raw format: ".$hexKey."##");
    $found = false;
    foreach ($keylist as $index => &$keylistEntry) {
        LOGDEB("verifykeyincar: hex key no ".$index."from car: ".$keylistEntry->publicKey."##");
        if ($hexKey == $keylistEntry->publicKey) {
            // return HTTP response code 200 = O.K.
            $return = array(
                'status' => 200,
                'message' => "Key was installed in car!"
            );
            http_response_code(200);
            $found = true;
            break;
        }
    }
    if (!$found) {
        // return HTTP response code 409 = Conflict (I didn't found a better code)
        $return = array(
            'status' => 409,
            'message' => "Key was not found in key list from vehicle."
        );
        http_response_code(409);
    }
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
