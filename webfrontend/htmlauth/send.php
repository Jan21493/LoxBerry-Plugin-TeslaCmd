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
read_vehicle_mapping($vmap, $custom_baseblecmd);
// owner's api is assumed and vin=vid, if no mapping was found
$api = 0;
// Vehicle ID was provided with command
if (isset($vid)) {
	if (isset($vmap) && isset($vmap->{strval($vid)})) {
		$ventry = $vmap->{strval($vid)};
		$api = $ventry->api;
		$vin = $ventry->vin;
	} 
} 

// wake up is available for vehicles only, not if a wake up command is selected, and not if body-controller-state is requested
if ($type == $vid && isset($selected_vehicle->vin) && ($action != "BODY_CONTROLLER_STATE") && ($action != "WAKE_UP") && ($command->BLECMD != "wake")) {
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
		if(!empty($vid)) {
			LOGDEB("tesla_command: vid: ".$vid.", vin: ".$vin);
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
				
				if(isset($_REQUEST["$param"])) {
					LOGDEB("$param: ".$_REQUEST["$param"]);
					$command_post += array("$param" => $_REQUEST["$param"]);
					$command_post_print = $command_post_print.", $param: ".$_REQUEST["$param"];
					$blecmd = str_replace("{".$param."}", $_REQUEST["$param"], $blecmd);
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

		if (!$command_error) {
			// select API - either owner's api or vehicle command via ble 
			if ($api == 0 || empty($blecmd)) {
				$command_output =  tesla_query( $vid, $action, $command_post, $force );
				LOGOK("tesla_command: vid: $vid, action: $action".$command_post_print.($force ? ", force: $force" : ""));
			} else {
				if (isset($custom_baseblecmd))
					$blebasecmd = $custom_baseblecmd;
				else
					$blebasecmd = $default_baseblecmd;
				$blebasecmd = str_replace($command->TAG, "$vin", $blebasecmd);
				$command_output = tesla_ble_query( $vid, $action, $blebasecmd, $blecmd, $force );
				LOGOK("tesla_command: vid: $vid, action: $action, cmd: $blebasecmd $blecmd".($force ? ", force: $force" : ""));
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