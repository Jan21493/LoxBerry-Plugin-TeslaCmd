<?php
//TODO: Add more commands
//TODO: Add command to check if token valid
/*
php ./send.php a=summary
php ./send.php action=summary
php ./send.php action=vehicle_data vid=123
php ./send.php a=vehicle_data v=123
/send.php?a=vehicle_data&v=123
*/
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

// Define action
if(!empty($_REQUEST["action"])) { 
	$action = $_REQUEST["action"];
} elseif (!empty($_REQUEST["a"])) { 
	$action = $_REQUEST["a"];
}
$command = $commands->{strtoupper($action)};

// Define vehicle
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
	LOGDEB("tesla_command: debug=$debug.");
} 
read_api_data($baseblecmd, $ble_repeat);
// owner's api is assumed and vin=vid, if no mapping was found
$api = 0;
// VIN was provided 
if (isset($vin)) {
	$vid = $vin;
	$api = getApiProtocol($vin);
	LOGDEB("tesla_command: VIN: $vin was provided. ".$apinames[$api]." is used.");
} elseif (isset($vid)) {
	// Vehicle ID was provided 
	$vehicles = tesla_summary();
    foreach ($vehicles as $index => &$vehicle) {
		// vehicle was found
		if ($vid == $vehicle->id_s) {
			$vin = $vehicle->vin;
			$api = getApiProtocol($vin);
		}
	} 
	LOGDEB("tesla_command: VID: $vid was provided, lookup for VIN got: $vin. ".$apinames[$api]." is used.");
} 

// wake up is available for vehicles only, not if a wake up command is selected, and not if body-controller-state is requested
if (isset($vin) && ($action != "BODY_CONTROLLER_STATE") && ($action != "WAKE_UP") && ($command->BLECMD != "wake")) {
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
	$command_output = "";
	$command_error = false;


	// error if command is not supported with selected api
	if (!in_array($api, $command->API)) {
		$command_output = "Command is not supported in ".$apinames[int($api)].". Verify API settings.\n";
		LOGERR("tesla_command: Command not supported in ".$apinames[int($api)]);
		$command_error = true;
	}

	if (!empty($command->TAG)) {
		// a command for a specific vehicle or energy site is selected
		if(!empty($vid) || !empty($vin)) {
			LOGDEB("tesla_command: vid: ".$vid." and/or vin: ".$vin." was provided".($force ? ", force: $force" : ""));
		} else {
			// error if vid is no provided, but required for the command
			$command_output =  $command_output."Parameter \"VID\" (ID of vehicle or energy site) is missing, but required for the command.\n";
			LOGDEB("tesla_command: Parameter \"VID\" missing, but required");
			$command_error = true;
		}
		$blecmd = $command->BLECMD;
		if(isset($command->PARAM)) {																			
			foreach ($command->PARAM as $param => $param_desc) {
				LOGDEB("tesla_command: Parameter \"$param\": $param_desc");
				$value = $_REQUEST["$param"];
				$optional = strpos($blecmd, "[".$param."]");
				if (!empty($value) || $optional) {
					LOGDEB("tesla_command: $param: ".$value);
					$command_post += array("$param" => $value);
					$command_post_print = $command_post_print.", $param: ".$value;
					if ($optional) 
						$blecmd = str_replace("[".$param."]", $value, $blecmd);
					else
						$blecmd = str_replace("{".$param."}", $value, $blecmd);
				} else {
					$command_output = $command_output."Parameter \"$param\" missing! $param_desc\n";
					LOGDEB("tesla_command: Parameter \"$param\" missing");
					$command_error = true;
				}
			}
		}
		// Fallback to Owner's API, if BLE command is not available (yet)
		if ($api == 1 && empty($command->BLECMD) && in_array(OWNERS_API, $command->API))
			$api = 0;

		if ($api == 1 && empty($vin)) {
			// error if vehicle command API is selected, but VIN is missing (not in API mapping)
			$command_output =  $command_output."Vehicle command API is selected, but VIN is missing. Verify API settings.\n";
			LOGDEB("tesla_command: VIN is missing, but required for Vehicle command API");
			$command_error = true;
		}
		if (($api == 1) && !empty($blecmd) && ($command->AUTH == true) && keyCheck($vin, $baseblecmd, PRIVATE_KEY) != 0) {
			// error if vehicle command API is selected, command requires authentication, but private key is missing
			$command_output =  $command_output."Vehicle command API is selected and command requires authentication, but private key is missing.\n";
			LOGDEB("tesla_command: BLE command requires authentication, but private key is missing.");
			$command_error = true;
		}

		if (!$command_error) {
			// select API - either owner's api or vehicle command via ble 
			if ($api == 0 || empty($blecmd)) {
				$command_output =  tesla_query( $vid, $action, $command_post, $force );
				LOGOK("tesla_command: vid: $vid, vin: $vin, action: $action".$command_post_print.($force ? ", force: $force" : ""));
			} else {
				$baseblecmd = str_replace($command->TAG, $vin, $baseblecmd);

				// if debug option is provided, then adjust debug in command if necessary
				if (!empty($debug)) {
					LOGDEB("tesla_command: verify debug setting.");
					if (strpos($baseblecmd, "-debug") && ($debug === "false")) {
						$baseblecmd = str_replace("-debug", "", $baseblecmd);
					} elseif (!strpos($baseblecmd, "-debug") && ($debug === "true")) {
						$baseblecmd = str_replace("{command}", "-debug {command}", $baseblecmd);
					}
				}
				$command_output = tesla_ble_query( $vid, $action, $baseblecmd, $blecmd, $ble_repeat, $force );
				LOGOK("tesla_command: vid: $vid, vin: $vin, action: $action, cmd: $baseblecmd $blecmd".($force ? ", force: $force" : ""));
			}
		}
	} else {
		// a general command is selected (these commands don't have parameters and are always send via Owner's API)
		if (!$command_error) {
			$command_output =  tesla_query( $vid, $action, $command_post, $force );
			LOGOK("tesla_command: action: $action".($force ? ", force: $force" : ""));
		}
	}
} else {
	$command_output =  "Command not found\n";
	LOGERR("tesla_command: Command not found");
}
echo $command_output;
?>