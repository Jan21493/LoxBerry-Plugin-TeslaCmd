<?php
//TODO: Add command to check if token valid

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";
require_once "loxberry_web.php";

$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging - send.php");

LOGOK("send.php: -------------------- start of send.php -------------------- ");
LOGINF("send.php: Source IP-address: ".$_SERVER['REMOTE_ADDR']);

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

// Define action
if(!empty($_REQUEST["action"])) { 
	$action = $_REQUEST["action"];
} elseif (!empty($_REQUEST["a"])) { 
	$action = $_REQUEST["a"];
}
$command = $commands->{strtoupper($action)};

// Either ID (vid), or VIN (vin) needs to be specified. Both CAN be provided.
// There are a few general commands that do not need an ID/VID, but all commands that work over BLE
if(!empty($_REQUEST["vehicle"])) { 
	$vid = $_REQUEST["vehicle"];
} elseif (!empty($_REQUEST["v"])) { 
	$vid = $_REQUEST["v"];
} elseif (!empty($_REQUEST["vid"])) { 
	$vid = $_REQUEST["vid"];
} elseif (!empty($_REQUEST["id"])) { 
	$vid = $_REQUEST["id"];
}
if(!empty($_REQUEST["vin"])) { 
	$vin = $_REQUEST["vin"];
} 
//debug may be added to or removed from request, default is the command option from settings menu.
if(!empty($_REQUEST["debug"])) { 
	$debug = $_REQUEST["debug"];
	LOGDEB("send.php: Debugging is turned on: debug=$debug.");
} 
$apidata = read_api_data();
// owner's api is assumed and vin=vid, if no mapping was found
$api = 0;
// VIN was provided. There is no need to look up for vehicles that belong to a specific ID
if (!empty($vin)) {
	$api = getApiProtocol($vin);
	LOGDEB("send.php: VIN: $vin was provided, ".(empty($vid) ? ", no ID" : ", ID: $vid").", ".$apinames[$api]." is used.");
} elseif (!empty($vid)) {
	// Vehicle ID was provided. A lookup is done to get all vehicles / energy sites that are associated with the ID
	$vehicles = tesla_summary();
    foreach ($vehicles as $index => &$vehicle) {
		// vehicle was found
		if ($vid == $vehicle->id_s) {
			$vin = $vehicle->vin;
			$api = getApiProtocol($vin);
		}
	} 
	LOGDEB("send.php: ID: $vid was provided, lookup for VIN got: $vin, ".$apinames[$api]." is used.");
} else {
	LOGDEB("send.php: no ID or VIN was provided, assuming a general command,  ".$apinames[$api]." is used.");
}

// wake up is available for vehicles only, not if a wake up command is selected, and not if body-controller-state is requested
if (!empty($vin) && ($action != "BODY_CONTROLLER_STATE") && ($action != "WAKE_UP") && ($command->BLECMD != "wake")) {
	// Define force
	if(!empty($_REQUEST["force"])) { 
		$force = $_REQUEST["force"];
	} elseif (!empty($_REQUEST["f"])) { 
		$force = $_REQUEST["f"];
	}
}

if(isset($command)) {
	$command_post = [];
	$command_post_print = "";
	$command_get_params = "";
	$command_output = "";
	$command_error = false;

	// error if command is not supported with selected api
	if (!in_array($api, $command->API)) {
		$command_output = "Command is not supported in ".$apinames[int($api)].". Verify API settings.\n";
		LOGERR("send.php: Command not supported in ".$apinames[int($api)]);
		$command_error = true;
	}

	if (!empty($command->TAG)) {
		// a command for a specific vehicle or energy site is selected
		if(!empty($vid) || !empty($vin)) {
			LOGDEB("send.php: ID: ".$vid." and/or VIN: ".$vin." was/were provided".($force ? ", force: $force." : ", no force."));
		} else {
			// error if vid is no provided, but required for the command
			$command_output =  $command_output."Parameter \"VID\" (ID of vehicle or energy site) or \"VIN\" (for vehicles only) is missing, but required for the command.\n";
			LOGDEB("send.php: Parameter \"VID\" or \"VIN\" are missing, but one of them is required for command \"$action\".");
			$command_error = true;
		}
		$blecmd = $command->BLECMD;
		if(isset($command->PARAM)) {																			
			foreach ($command->PARAM as $param => $param_desc) {
				$value = $_REQUEST["$param"];
				$optional = strpos($blecmd, "[".$param."]");
				if (!empty($value) || $optional) {
					if (!empty($value)) {
						LOGDEB("send.php: parameter \"$param\"=\"$value\", description: $param_desc");
					}
					// need to send all optional parameters even if empty in case another (non-empty) parameter is following
					// special case - integer value needs to be send
					if ($param =="backup_reserve_percent")
						$command_post += array("$param" => floatval($value));
					else
						$command_post += array("$param" => $value); 
					$command_post_print = $command_post_print.", $param: ".$value;
					if ($command_get_params != "")
						$command_get_params += "&";
					$command_get_params += "$param=$value";
					if ($optional) 
						$blecmd = str_replace("[".$param."]", $value, $blecmd);
					else
						$blecmd = str_replace("{".$param."}", $value, $blecmd);
				} else {
					$command_output = $command_output."Parameter \"$param\" missing! $param_desc\n";
					LOGDEB("send.php: Parameter \"$param\" missing");
					$command_error = true;
				}
			}
		}
		if ($api == BLE_PLUS_OWNERS_API && empty($vin)) {
			// error if vehicle command API is selected, but VIN is missing (not in API mapping)
			$command_output =  $command_output."Vehicle command API is selected, but VIN is missing. Verify API settings.\n";
			LOGDEB("send.php: VIN is missing, but required for Vehicle command API");
			$command_error = true;
		}
		// Fallback to Owner's API, if BLE command is not available (yet)
		if ($api == BLE_PLUS_OWNERS_API && empty($blecmd) && in_array(OWNERS_API, $command->API)) {
			$api = OWNERS_API;
			LOGDEB("send.php: fallback to Owner's API, because BLE command is not available (yet). NOTE: It may not work!");
		}
	
		if (($api == BLE_PLUS_OWNERS_API) && !empty($blecmd) && ($command->AUTH == true) && keyCheck($vin, $apidata->baseblecmd, PRIVATE_KEY) != 0) {
			// error if vehicle command API is selected, command requires authentication, but private key is missing
			$command_output =  $command_output."Vehicle command API is selected and command requires authentication, but private key is missing.\n";
			LOGDEB("send.php: BLE command requires authentication, but private key is missing.");
			$command_error = true;
		}

		if (!$command_error) {
			// select API - either owner's api or vehicle command via ble 
			if ($api == OWNERS_API || empty($blecmd)) {
				if (empty($vin)) {
					if ($command->TYPE == "GET") {
						// for GET requests the parameters are provided with the URI
						$command_output =  tesla_query( $vid, $action, $command_get_params, $force );
						LOGOK("send.php: ID: $vid, no vin, GET action: $action".$command_post_print.($force ? ", force: $force." : ", no force."));
					} else {
						// for POST requests the parameters are provided in the HTTP header
						$command_output =  tesla_query( $vid, $action, $command_post, $force );
						LOGOK("send.php: ID: $vid, no vin, POST action: $action".$command_post_print.($force ? ", force: $force." : ", no force."));
					}
				} else {
					// for vehicles there are no GET commands with parameters
					$command_output =  tesla_query( $vin, $action, $command_post, $force );
					LOGOK("send.php: VIN: $vin, (vid: $vid), action: $action".$command_post_print.($force ? ", force: $force." : ", no force."));				
				}
			} else {
				// $api == BLE_PLUS_OWNERS_API
				$baseblecmd = str_replace($command->TAG, $vin, $apidata->baseblecmd);

				// if debug option is provided, then adjust debug in command if necessary (command line options override selected settings)
				if (!empty($debug)) {
					LOGDEB("send.php: debug option provided. \"debug\"=\"$debug\" This overrides selected setting.");
					if (strpos($baseblecmd, DEBUG_OPTION) && ($debug === "false")) {
						$baseblecmd = str_replace(DEBUG_OPTION, "", $baseblecmd);
					} elseif (!strpos($baseblecmd, DEBUG_OPTION) && ($debug === "true")) {
						$baseblecmd = str_replace(COMMAND_TAG, DEBUG_OPTION." ".COMMAND_TAG, $baseblecmd);
					}
				}
				$command_output = tesla_ble_query( $vin, $action, $baseblecmd, $blecmd, $apidata->ble_retries, $apidata->lock_timeout, $force );
				LOGOK("send.php: VIN: $vin, (ID: $vid), action: $action, cmd: $baseblecmd $blecmd".($force ? ", force: $force" : ", no force."));
			}
		}
	} else {
		// a general command is selected (these commands don't have parameters and are always send via Owner's API)
		if (!$command_error) {
			$command_output =  tesla_query( $vid, $action, $command_post, $force );
			LOGOK("send.php: ID: $vid, action: $action (general command)".($force ? ", force: $force" : ", no force."));
		}
	}
} else {
	$command_output =  "Command not found\n";
	LOGERR("send.php: Command not found");
}
echo $command_output;
LOGINF("send.php: ==================== end of send.php ==================== ");

?>