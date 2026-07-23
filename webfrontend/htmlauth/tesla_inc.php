<?php
//[x] modified time to os time
//[x] changed epoche time to loxtime
$debugscript = true;

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";
require_once "defines.php";
require_once "phpMQTT/phpMQTT.php";

// Create and start log
// Shutdown function
register_shutdown_function('shutdown');
function shutdown()
{
	global $log;
	
	if(isset($log)) {
		LOGEND("Processing for this PHP function has finished.");
	}
}

// Tesla API
$tesla_api_oauth2 = 'https://auth.tesla.com/oauth2/v3';
$tesla_api_redirect = 'https://auth.tesla.com/void/callback';
$tesla_api_owners = 'https://owner-api.teslamotors.com/oauth/token';
$tesla_api_code_vlc = 86;
$cid = "81527cff06843c8634fdc09e8ac0abefb46ac849f38fe1e431c2ef2106796384";
$cs = "c7257eb71a564034f9419ee651c7d0e5f7aa6bfbd18bafb5c5c033b093bb2fa3"; 
$user_agent = "Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148";

// Init
$vid = false;
$force = false;
$token = false;
$action = "noaction";

// get command list from local JSON file
$commands = get_commands();

// refresh the token
$login = tesla_refreshtoken();


function tesla_refreshtoken()
{
	// Function to read token from file and refresh token, if expired
	// Reads login data from disk, and checks for expiration of the token
	//[x] Add token_expires to mqtt
	LOGINF("Check token.");
	
	global $token;
	
	if( !file_exists(LOGINFILE) ) {
		mqttpublish(0, "/token_valid");
		LOGDEB("tesla_refreshtoken: Loginfile missing, aborting.");
		LOGINF("No valid token, please login.");
		return;
	}
	
	LOGDEB("tesla_refreshtoken: read loginfile.");
	$logindata = tesla_read_login_data();
	if ($logindata === false) {
		mqttpublish(0, "/token_valid");
		LOGDEB("tesla_refreshtoken: File data error, no token found. Fallback to re-login.");
		LOGINF("No valid token, please login.");
		return;
	}
	$login = (object)$logindata;
	
	// Read token
	$accessToken = tesla_get_login_access_token($logindata);
	$refreshToken = tesla_get_login_refresh_token($logindata);
	if( empty($accessToken) ) {
		mqttpublish(0, "/token_valid");
		LOGDEB("tesla_refreshtoken: File data error, no token found. Fallback to re-login.");
		LOGINF("No valid token, please login.");
		return;
	}
	
	// Get date part of token
	$tokenexpires = tesla_get_token_expiration($logindata);

    $timediff = 60*240; //60sec*240min (4h) 

	LOGDEB("tesla_refreshtoken: Time now                  - ". time() ." ".date("Y-m-d H:i:s", time()));
	LOGDEB("tesla_refreshtoken: Refresh Token valid until - ". ($tokenexpires) ." ".date("Y-m-d H:i:s", $tokenexpires));
    LOGDEB("tesla_refreshtoken: Time to Refresh Token     - ". ($tokenexpires-$timediff) ." ".date("Y-m-d H:i:s", $tokenexpires-$timediff));
	
	if( $tokenexpires > 0 && $tokenexpires-$timediff > time() ) {
		// Token is valid
		mqttpublish(1, "/token_valid");
		mqttpublish(epoch2lox($tokenexpires), "/token_expires");
		LOGOK("Token valid (" . date("Y-m-d\TH:i:s", $tokenexpires) . ").");
		$token = $accessToken;
	} elseif (!empty($refreshToken)) {
		// Token expired
		if ($tokenexpires > 0) {
			LOGINF("Token will expire (" . date("Y-m-d\TH:i:s", $tokenexpires) . "), refresh token.");
		} else {
			LOGINF("Token expiration is unknown, refresh token.");
		}

		$token = tesla_oauth2_refresh_token($refreshToken);
		if(!empty($token)) {
			$logindata = tesla_read_login_data();
			$tokenexpires = tesla_get_token_expiration($logindata);
			$login = is_array($logindata) ? (object)$logindata : $login;
			mqttpublish(1, "/token_valid");
			mqttpublish(epoch2lox($tokenexpires), "/token_expires");
		} else {
			mqttpublish(0, "/token_valid");
			mqttpublish(0, "/token_expires");
			LOGINF("Failed to refresh token, please login.");
		}
	} else {
		// no valid token
		mqttpublish(0, "/token_valid");
		mqttpublish(epoch2lox($tokenexpires), "/token_expires");
		LOGINF("No valid token, please login.");
	}
	return $login;
}


function tesla_summary()
{
	// Function to get car summary
	LOGINF("Get Tesla product summary.");
	$data = json_decode(tesla_query( "", "product_list" ));
	$returndata = new stdClass();
	if(isset($data->response)) {
		foreach($data->response as $value) {
			$returndata->{strval($value->id)} = $value;
			mqttpublish($value, "/$value->id");
		}
		return $returndata;
	}
	return $returndata;
} 
/*
// to be deleted in final version - 
function tesla_summary()
{
	// Function to get car summary plus fake entries
	LOGINF("Get Tesla product summary.");
	$data = json_decode(tesla_query( "", "product_list" ));
	//echo"<br>DATA: ";var_dump($data);echo"<br>";

	// Read Fake IDs for testing purpose
	if( file_exists(FAKEFILE) ) {
		LOGDEB("tesla_summary2: read fake entries.");
		$fakedata = file_get_contents(FAKEFILE);
		$fakeIDs = json_decode($fakedata);
	}

	$returndata = new stdClass();
	if(isset($data->response)) {
		foreach($data->response as $value) {
			$returndata->{strval($value->id)} = $value;
			mqttpublish($value, "/$value->id");
		}
		// add fake IDs to vehicles
		foreach($fakeIDs as $fakeID) {
			$returndata->{strval($fakeID->id)} = $fakeID;
		}
		return $returndata;
	}
} */

// TODO: Check if function needed
function tesla_checktoken()
{
	// Function to check if token is valid
	
	$data = json_decode(tesla_query( "", "product_list" ));

	if (is_null($data)) {
		LOGDEB("tesla_checktoken: not valid");
		return false;
	} else {
		LOGDEB("tesla_checktoken: valid");
		return true;
	}
} 


function tesla_check_parameter($action, $values)
{
	// Function to check required parameters
	global $commands;
	$PARAM = new stdClass();
	$PARAM_POST = new stdClass();

	// Check if Vehicle ID nessesary
	if (strpos($commands->{strtoupper($action)}->URI, '{vehicle_tag}') !== false) {
		$PARAM = (object)["vid" => "The id of the vehicle."];
	}

	if(isset($commands->{strtoupper($action)}->PARAM)) {
		foreach ($commands->{strtoupper($action)}->PARAM as $param => $param_desc) {
			$PARAM->$param = $param_desc;
		}
	}

	foreach ($PARAM as $param => $param_desc) {
		if(isset($values["$param"])) {
			LOGDEB("$param: ".$values["$param"]);

			if(isset($commands->{strtoupper($action)}->PARAM->$param)){
				$PARAM_POST->$param = $values["$param"];
			}
		} else {
			echo "Parameter \"$param\" missing! $param_desc\n";
			LOGERR("tesla_command: Parameter \"$param\" missing");
		}
	}
	LOGDEB(json_encode($PARAM));
	LOGDEB(json_encode($PARAM_POST));
	return $PARAM;
}


function tesla_query( $VID, $action, $params=false, $force=false )
{
	// Function to send query to tesla api

	// for GET queries: $params are coded as URI with & between params, e.g. param1=value1&param2=value2
	// for POST queries: $params are coded as array with "param" => value
		
	global $commands;
	$action = strtoupper($action);
	$type = $commands->{"$action"}->TYPE;
	$uri = $commands->{"$action"}->URI;
	$uri = str_replace("{vehicle_tag}", "$VID", $uri);
	$uri = str_replace("{energy_site_id}", "$VID", $uri);
	$timeout = 10;

	LOGINF("tesla_query: $action: start");

	while($timeout > -1) {
		if($type == "GET") {
			//GET
			LOGDEB("tesla_query: $type: $uri");
			//$rawdata = preg_replace('/("\w+"):(\d+(\.\d+)?)/', '\\1:"\\2"', tesla_curl_send( BASEURL.$uri, false ));
			
			// Reformat output from 'curl' command. Add params to URI for GET requests
			$rawdata = preg_replace('/("\w+"):(\d+(\.\d+)?)/', '\\1:"\\2"', tesla_curl_send( BASEURL.$uri."?".$params, false ));
			$data = json_decode($rawdata);
			$data->response->{"sentAtTimeLox"} = epoch2lox();
			$data->response->{"sentAtTimeISO"} = currtime();
			
			if (!empty($data->error)) {
				if (preg_match("/vehicle unavailable/i", $data->error) and $force==true) {
					//Wake-Up Car if force==true
					LOGDEB("tesla_query: $type: vehicle unavailable, wakeup car");
					LOGINF("Query: Vehicle unavailable, wakeup car.");

					$wake_up_uri = $commands->{"WAKE_UP"}->URI;
					$wake_up_uri = str_replace("{vehicle_tag}", "$VID", $wake_up_uri);
					LOGDEB("tesla_query: $type: $wake_up_uri");
					$rawdata = json_decode(preg_replace('/("\w+"):(\d+(\.\d+)?)/', '\\1:"\\2"', tesla_curl_send( BASEURL.$wake_up_uri, false, true)));
					$data = json_decode($rawdata);
					
					sleep(2);
					$timeout = $timeout-1;
					LOGDEB("tesla_query: $type: timeout $timeout");
				} elseif (preg_match("/vehicle unavailable/i", $data->error)) {
					LOGDEB("tesla_query: $type: vehicle unavailable");
					break;
				} elseif (preg_match("/timeout/i", $data->error)) {
					LOGDEB("tesla_query: $type: timeout");
					sleep(1);
					$timeout = $timeout-1;
					LOGDEB("tesla_query: $type: timeout $timeout");
				}
			} else {
				//echo "<br>RESPONSE: ";var_dump($data->response);echo "<br><br>";
				if (isset($data->response)) {
					$returndata = $data->response;
					if (isset($returndata->id)){
						LOGDEB('if(isset($returndata->id))');
						mqttpublish($returndata, "/$returndata->id/".strtolower($action));
					} else {
						// [ ] Bugfix empty $VID
						if (!empty($VID)){
							mqttpublish($returndata, "/$VID/".strtolower($action));
						} else {
							mqttpublish($returndata, "/".strtolower($action));
						}
					}
				} else {
					//[x] fixed status output
						mqttpublish($rawdata, "/".strtolower($action));
				}
				LOGOK("Query: $action: success");
				break;
			}
		} else {
			//POST
			LOGDEB("tesla_query: $type: $uri");
			// Reformat output from 'curl' command. Add params to HTTP header for POST requests (send to function as array)
			$rawdata = preg_replace('/("\w+"):(\d+(\.\d+)?)/', '\\1:"\\2"', tesla_curl_send( BASEURL.$uri, $params, true));
			$data = json_decode($rawdata);
			
			if (!empty($data->error)) {
				
				if (preg_match("/vehicle unavailable/i", $data->error)) {
					// Wake-Up Car
					LOGDEB("tesla_query: $type: vehicle unavailable, wakeup car");
					LOGINF("Query: Vehicle unavailable, wakeup car.");
					
					$wake_up_uri = $commands->{"WAKE_UP"}->URI;
					$wake_up_uri = str_replace("{vehicle_tag}", "$VID", $wake_up_uri);
					LOGDEB("tesla_query: $type: $wake_up_uri");
					$rawdata = preg_replace('/("\w+"):(\d+(\.\d+)?)/', '\\1:"\\2"', tesla_curl_send( BASEURL.$wake_up_uri, false, true));
					$data = json_decode($rawdata);
					
					sleep(2);
					$timeout = $timeout-1;
					LOGDEB("tesla_query: $type: timeout $timeout");
				} elseif (preg_match("/timeout/i", $data->error)) {
					LOGDEB("tesla_query: $type: timeout");
					sleep(1);
					$timeout = $timeout-1;
					LOGDEB("tesla_query: $type: timeout $timeout");
				} else {
					LOGOK("tesla_query: $type: success");
					break;
				}
			} else {
				LOGOK("tesla_query: $action: success");
				break;
			}
		}
	}
	return "$rawdata\n";
}

function tesla_ble_wake_with_check($baseblecmd, $ble_retries, $lock_timeout)
{
	// Purpose: Check, if vehicle is awake. If not, wake it up 

	// turn off debugging, because detailed output is not logged for wakeup check and wake (if required) 
	$baseblecmd = str_replace(DEBUG_OPTION, "", $baseblecmd);
	// check sleep status by sending "body-controller-state" command
	$blefullcmd = str_replace(COMMAND_TAG, BODYCONTROLLERSTATE, $baseblecmd);
	LOGDEB("tesla_ble_wake_with_check: Check if vehicle is asleep: $blefullcmd");
	$result_code = tesla_shell_exec( "$blefullcmd", $output, $ble_retries, $lock_timeout, true);
	$vehicleSleepStatus = "";
	foreach($output as $key => $line) {
		if (strpos($line, '"vehicleSleepStatus":2') > 0) {
			// vehicle is sleeping
			$vehicleSleepStatus = "sleeping";
			$blefullcmd = str_replace(COMMAND_TAG, WAKE, $baseblecmd);
			LOGDEB("tesla_ble_wake_with_check: Need to wake vehicle: $blefullcmd");
			$result_code = tesla_shell_exec( "$blefullcmd", $output, $ble_retries, $lock_timeout, true);
			if ($result_code > 0) {
				LOGDEB("tesla_ble_wake_with_check: Wakeup failed: result_code= $result_code");
			} else {
				LOGDEB("tesla_ble_wake_with_check: Wakeup was successful! Waiting 3 seconds.");
				sleep(3);
			}
			break;
		} else if (strpos($line, '"vehicleSleepStatus":1') > 0) {
			// vehicle is awake
			$vehicleSleepStatus = "awake";
			LOGDEB("tesla_ble_wake_with_check: Vehicle is awake already - no need to wake it up");
			break;
		}
	}
	if (empty($vehicleSleepStatus)) {
		// there is no vehicleSleepStatus in response, if BODY_CONTROLLER_STATE failed, e.g. the vehicle was away.
		LOGINFO("tesla_ble_wake_with_check: No proper vehicleSleepStatus (either asleep or awake) in response. 'body-controller-state' may have failed, e.g. due to the fact that the vehicle was away!");
		// but sending requested command anyway (as without force) - not sure if this should be changed to skip the command.
	}
}

function tesla_ble_query( $vehicle_tag, $action, $baseblecmd, $blecmd, $ble_retries, $lock_timeout, $force=false )
{
	// Function to send query via vehicle command API over BLE
		
	global $commands;
	$keyno = 1;

	LOGINF("BLE Query: $action: start");
	$action = strtoupper($action);
	$type = $commands->{"$action"}->TYPE;
	$baseblecmd = str_replace(VEHICLE_TAG, $vehicle_tag, $baseblecmd);

	if ($force) {
		// if wake up for command is enforced, then check sleep status and wake it up if necessary
		tesla_ble_wake_with_check($baseblecmd, $ble_retries, $lock_timeout);
		// so far, there is no result code to stop here, e.g. if status and wake have failed
	}
	// sending requested command
	$blefullcmd = str_replace(COMMAND_TAG, $blecmd, $baseblecmd);
	LOGDEB("tesla_ble_query: (type: $type) executing command: $blefullcmd");
	$result_code = tesla_shell_exec( "$blefullcmd", $output, $ble_retries, $lock_timeout, true);
	
	// raw output with full debugging (if enabled)
	LOGDEB("tesla_ble_query: -------------------------------------------------------------------------------------");
	foreach($output as $key => $line) {
		LOGDEB("$line");
	}
	LOGDEB("tesla_ble_query: -------------------------------------------------------------------------------------");
	
	$jsondata = "";
	// separate debug output from other (json) output; debug info is removed from output 
	foreach($output as $key => $line) {
		if (strpos($line, "20") === 0 && strpos($line, "[") > 20 && strpos($line, "]") > 25 && strpos($line, "]") < 35) {
			// logging output 
			if (!empty($logdata))
				$logdata .= ', '; 
			$logdata .= '"'.$line.'"';
			unset($output[$key]);
		} else {
			$jsondata .= $line.' ';
		}
	} 
	$rawdata = '{"result_code":'.$result_code.', ';
	$rawdata .= '"result_msg":"'.get_result_code_msg($result_code).'", ';
	$rawdata .= '"sentAtTimeLox":'.epoch2lox().', ';
	$rawdata .= '"sentAtTimeISO":"'.currtime().'", ';
	// GET is used to retrieve status and other information, while POST sends actions
	if($type == "GET") {
		if ( $result_code == 0) {
			$rawdata .= '"error_msg":""';
			if ($action == "BODY_CONTROLLER_STATE") {
				$rawdata .= ', "vehicleNearby":true';
			}
			//echo "<pre>OUTPUT:<br>";var_dump($jsondata);echo "</pre>";
			$rawdata .= ', '.substr($jsondata, 1);
		} else {
			$rawdata .= '"error_msg":"'.end($output).'"';
			if ($action == "BODY_CONTROLLER_STATE") {
				$rawdata .= ', "vehicleNearby":false';
			}
			$rawdata .= ' }';
		}
		//echo "<pre>RAWDATA:<br>";var_dump($rawdata);echo "</pre>";
		mqttpublish(json_decode($rawdata), "/$vehicle_tag/".strtolower($action));
	} else {
		//POST - these commands do not send JSON output, only last line is taken
		if ( $result_code == 0) {
			$rawdata .= '"error_msg":"", ';
			$rawdata .= '"output_msg":"'.end($output).'"';
		} else {
			$rawdata .= '"error_msg":"'.end($output).'", ';
			$rawdata .= '"output_msg":""';
		}
		$rawdata .= ' }';
		mqttpublish(json_decode($rawdata), "/$vehicle_tag/".strtolower($action));
	}
	LOGDEB("tesla_ble_query: finished sucessfully.");
	if (empty($logdata))
		return $rawdata;
	else
		return $rawdata."\n{".$logdata."}";
}


function get_commands()
{
	// Get Command list from file
	if( !file_exists(COMMANDFILE) ) {
		LOGDEB("get_commands: Commandfile missing, aborting");
		LOGERR("Commandfile not found, aborting.");
	} else {
		LOGDEB("get_commands: Read commandfile");
		$commands = json_decode(file_get_contents(COMMANDFILE));
	}
	return $commands;
}

function get_result_code_msg($result_code)
{
	switch ($result_code) {
		case 0:
			$result_code_msg = "Done.";
			break;
		case 1:
			$result_code_msg = "General error.";
			break;
		case 2:
			$result_code_msg = "Misuse of shell builtins.";
			break;
		case 126:
			$result_code_msg = "Command invoked cannot execute.";
			break;
		case 127:
			$result_code_msg = "Command not found. Is Tesla control utility installed and included in path?";
			break;
		default:	
			$result_code_msg = "Unknown error. Result code = $result_code";
			break;
		}
	return $result_code_msg;
}

function pretty_print_old($json)
{
    $array = json_decode($json, true);
    $json = json_encode($array, JSON_PRETTY_PRINT);
	
	//Using <pre> tag to format alignment and font
	echo "<pre>";
	echo $json;
	echo "</pre>";
}

function pretty_print($json_data)
{
	//Declare the custom function for formatting
	//Initialize variable for adding space
	$space = 0;
	$withinQuotes = false;
	$aftercolon = false;

	//Using <pre> tag to format alignment and font
	echo "<pre>";

	//loop for iterating the full json data
	for($counter=0; $counter<strlen($json_data); $counter++)
	{
		if (!$withinQuotes) {
			//Checking ending second and third brackets
			if ($json_data[$counter] == '}' || $json_data[$counter] == ']') {
				$space--;
				if ( $json_data[$counter-1] != '{' && $json_data[$counter-1] != '[') {
					echo "\n";
					echo str_repeat(' ', ($space * 2));
				}
				$aftercolon = false;
			}
			//Checking for double quote(“) and comma (,)
			if ($json_data[$counter] == '"') {
				if ( $aftercolon ) {
					//Add formatting for text
					echo '<span style="color:blue;font-weight:bold">';
				} else {
					//Add formatting for options
					echo '<span style="color:red;">';
				}
				$withinQuotes = !$withinQuotes;
			}
			if (($json_data[$counter] != "\t") && ($json_data[$counter] != " "))
				echo $json_data[$counter];
			if ( $json_data[$counter] == ':' ) {
				echo " ";
				$aftercolon = true;
			}
			if ($json_data[$counter] == ',') {
				echo "\n";
				echo str_repeat(' ', ($space * 2));
				$aftercolon = false;
			}
			//Checking starting second and third brackets
			if ( $json_data[$counter] == '{' || $json_data[$counter] == '[') {
				$space++;
				if ($json_data[$counter+1] != '}' && $json_data[$counter+1] != ']') {
					echo "\n";
					echo str_repeat(' ', ($space * 2));
				}
				$aftercolon = false;
			}
		} else {
			// within quotes - just print and check for closing quote
			if ($json_data[$counter] != "\t")
				echo $json_data[$counter];
			//Checking conditions for adding closing span tag
			if ($json_data[$counter] == '"') {
				echo '</span>';
				$withinQuotes = !$withinQuotes;
			}
		}
	}
	echo "</pre>";
}

function mqttpublishdata($mqtt, $data, $mqttsubtopic)
{
	//LOGDEB("mqttpublishdata: " . MQTTTOPIC . "$mqttsubtopic ");
	// if data is an object or array, then call this function for each element
	if (is_object($data) or is_array($data)) {
		$count = 0;
		foreach ($data as $key => $value) {
			$count++;
			// LOGDEB("mqttpublishdata: " . MQTTTOPIC . "$mqttsubtopic - key: $key");
			mqttpublishdata($mqtt, $value, "$mqttsubtopic/$key");
		}
		if ($count == 0) {
			// no value, e.g. empty array. Some commands e.g. state charge have empty objects. Set to NULL (needs to be tested!)
			$mqtt->publish(MQTTTOPIC . "$mqttsubtopic", "", 0, 1);
			LOGDEB("mqttpublish: " . MQTTTOPIC . "$mqttsubtopic: NULL");
		} 
	} else {
		if (is_array($data)) {
			$data = implode(",", $data);
		}
		// bugfix for false
		if (is_bool($data)) {
			if ($data)
				$data = 1;
			else
				$data = 0;
		}
		if (!isset($data)) {
			$data = "NULL";
		}
		$mqtt->publish(MQTTTOPIC . "$mqttsubtopic", $data, 0, 1);
		LOGDEB("mqttpublish: " . MQTTTOPIC . "$mqttsubtopic: $data");
	}
}

function mqttpublish($data, $mqttsubtopic = "")
{
	// Function to send data to mqtt
	//echo "<pre>DATA:<br>";var_dump($data);echo "</pre>";

	// MQTT requires a unique client id
	$client_id = uniqid(gethostname() . "_client");
	$creds = mqtt_connectiondetails();
	// Be careful about the required namespace on inctancing new objects:
	$mqtt = new Bluerhinos\phpMQTT($creds['brokerhost'], $creds['brokerport'], $client_id);

	if ($mqtt->connect(true, NULL, $creds['brokeruser'], $creds['brokerpass'])) {

		LOGINF("mqttpublish: start of topic: " . MQTTTOPIC . ", MQTT broker: ".$creds['brokerhost'].":".$creds['brokerport']);
		// publish all data
		mqttpublishdata($mqtt, $data, $mqttsubtopic);

		//[x] Query timestamp added, changed to mqtt_timestamp
		$mqtt->publish(MQTTTOPIC . "/mqtt_timestamp", epoch2lox(time()), 0, 1);
		LOGDEB("mqttpublish: " . MQTTTOPIC . "/mqtt_timestamp: " . epoch2lox(time()));
		LOGOK("mqttpublish: MQTT connection successful, topic: ".MQTTTOPIC);
		$mqtt->close();
	} else {
		LOGERR("MQTT: Connection failed.");
	}
}


function tesla_curl_send( $url, $payload, $post=false )
{
	// Function to send curl command
	//[ ] If Timeout, restart apache server: sudo systemctl restart apache2
	
	global $token;
	$curl = curl_init();

	if( !empty($payload) ) {
		$payload = json_encode ( $payload );
	} else {
		$payload = "";
	}
	
	$header = [ ];

	if( !empty($token) ) {
		LOGINF("tesla_curl_send: curl started, Token given");
		array_push( $header, "Authorization: Bearer $token" );
		LOGDEB("tesla_curl_send: curl options: -H \"Authorization: Bearer *****\" \\");
	} else {
		LOGINF("tesla_curl_send: curl started, no Token");
	}
	
	if($post==true) {
		array_push( $header, "Content-Type: application/json;charset=UTF-8" );
		LOGDEB("tesla_curl_send: curl options: -H 'Content-Type: application/json;charset=UTF-8' \\");
		array_push( $header, "Content-Length: " . strlen($payload) );
		LOGDEB("tesla_curl_send: curl options: -H 'Content-Length: ".strlen($payload)."' \\");
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		LOGDEB("tesla_curl_send: curl options: -X POST \\");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		LOGDEB("tesla_curl_send: curl options: --data '$payload' \\");
	}
	
	//cURL connection timeout 5 seconds
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);

	//cURL timeout 10 seconds
	curl_setopt($curl, CURLOPT_TIMEOUT, 10);

	curl_setopt($curl, CURLOPT_URL, $url);
	LOGDEB("tesla_curl_send: curl options: -i '$url'");
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header );

	$response = curl_exec($curl);

	//Did an error occur? If so, dump it out.
	if(curl_errno($curl))
		LOGERR("tesla_curl_send: ".curl_error($curl));

	// Debugging
	$crlinf = curl_getinfo($curl);
	LOGINF("tesla_curl_send: curl finished with status: ". $crlinf['http_code']);

	return $response;
}

function makeDir($path)
{
     return is_dir($path) || mkdir($path);
}

function tesla_shell_exec( $command, &$output, $retries = 0, $lock_timeout = 30, $exclusive = false)
{
	$lockfile = "";
	$lockHandle = NULL;
	$lockAcquired = false;

	// Function to execute shell command
	//[ ] If Timeout, restart apache server: sudo systemctl restart apache2
	
	LOGINF("tesla_shell_exec: Start executing a shell command ...");
	$command .= " 2>&1";
	if( !empty($command) ) {
		LOGDEB("tesla_shell_exec: command: $command");
	} else {
		LOGERR("tesla_shell_exec: empty command");
		return NULL;
	}

	if ($exclusive) {
		LOGINF("tesla_shell_exec: use flock-based locking for exclusive BLE access!");
		// Use a single kernel-backed lock file. flock is automatically released when a process exits.
		// set temp directory to user loxberry UID, e.g. 1001
		$tmpdir = "/run/user/".getmyuid().'/tesla';
		if (makeDir($tmpdir)) {
			$lockfile = $tmpdir.'/ble.lock';
			$lockHandle = fopen($lockfile, 'c');
			if ($lockHandle !== false) {
				$lockSeconds = max(30, (int)$lock_timeout) * ((int)$retries + 1);
				$waitSeconds = 0;
				LOGDEB("tesla_shell_exec: waiting for flock lock file: $lockfile, timeout: $lockSeconds seconds.");
				while ($waitSeconds < $lockSeconds) {
					if (flock($lockHandle, LOCK_EX | LOCK_NB)) {
						$lockAcquired = true;
						break;
					}
					sleep(1);
					$waitSeconds++;
				}
				LOGINF("tesla_shell_exec: Waiting time for BLE lock: $waitSeconds seconds.");
				if (!$lockAcquired) {
					LOGWARN("tesla_shell_exec: Timeout while waiting for BLE lock. Continue without exclusive access.");
				}
			} else {
				LOGERR("tesla_shell_exec: can't open lock file: $lockfile. Try command without exclusive access!");
			}
		} else {
			LOGERR("tesla_shell_exec: ".$tmpdir." does not exist and can't be created. Try command now without exclusive access!");
		}
	}
	$eta=-hrtime(true);
	$output=NULL;
	$result_code=NULL;
	exec($command, $output, $result_code);
	$eta+=hrtime(true);
	LOGINF("tesla_shell_exec: execution time for shell command: ".($eta/1e+6)." milliseconds.");

	//Did an error occur? If so, retry the command
	if ($result_code > 0) {
		LOGWARN("tesla_shell_exec: command has returned an error! The result code was: " . $result_code);
		// On an Orange PI zero 3 with DietPi v9.7.1 (Bookworm, released July 2024) the command returned errors after typically 1-2 hours
		// so this 'dirty' fix was added that restarts the bluetooth service. There might be an error in the bluetooth driver that I can't fix.
		exec("cat /sys/firmware/devicetree/base/model", $output2, $result_code2);
		$model = $output2[0];
		LOGINF("tesla_shell_exec: '$model' detected!");
		if (($result_code2 == 0) && ($model=="OrangePi Zero3")) {
			// restart bluetooth service on Orange PI Zero 3 - does not really work great
			// and retry the command (one time - fixed)
			LOGINF("tesla_shell_exec: restarting aw859a-bluetooth.service and waiting for 5 seconds!");
			exec("sudo systemctl restart aw859a-bluetooth.service", $output2, $result_code2);
			sleep(5);
		}
		// retry command depending on 'retries' setting
		if ($retries == "0") {
			LOGDEB("tesla_shell_exec: Last command will not be repeated, because 'retries' setting is set to 0 times!");
		} else {
			LOGDEB("tesla_shell_exec: Last command will be repeated after waiting for 5 seconds ...");				
			sleep(5);
			exec($command, $output, $result_code);
			if (($retries == "2") && ($result_code != 0)) {
				LOGDEB("tesla_shell_exec: Last command failed again and is repeated again after waiting 5 seconds ...");				
				sleep(5);
				exec($command, $output, $result_code);
			}
		} 
		// additional detections and restart of service may be added for specific platforms if required
	}
	//Did an error occur? If so, dump it out.
	if(is_null($result_code != 0)){
		LOGINF("tesla_shell_exec: command has returned an error. Final result code:" . $result_code);
	}
	// remove file lock
	if ($exclusive && $lockHandle !== NULL) {
		if ($lockAcquired) {
			flock($lockHandle, LOCK_UN);
			LOGDEB("tesla_shell_exec: released flock lock: $lockfile");
		}
		fclose($lockHandle);
	}

	// Debugging
	if ($result_code == 0) {
		LOGOK("tesla_shell_exec: finished successfully!");
	} else {
		LOGERR("tesla_shell_exec: finished with error! result code: " . $result_code);
	}

	return $result_code;
}

function delete_token()
{
	// delete file with token
	if (file_exists(LOGINFILE)) {
		unlink(LOGINFILE);
		LOGDEB("delete_token: File " . LOGINFILE . "deleted.");
	}
	LOGINF("Token deleted.");
}


function setlogintoken($bearer_token, $refresh_token)
{
	// Add Tokens to file
	if(empty($bearer_token) || empty($refresh_token)) { return return_msg(0, "Please enter both an access token and a refresh token."); }

	$tokens = array(
		"access_token" => trim($bearer_token),
		"refresh_token" => trim($refresh_token)
	);
	$result = tesla_store_tokens($tokens, "manual");
	if (!$result["success"]) {
		return return_msg(0, $result["message"]);
	}

    // Output
    return return_msg(1, "Token data saved.");  
}


####################################################
# Tesla Authorization fuctions
# Based on: https://github.com/timdorr/tesla-api/discussions/362
####################################################

function tesla_connect($url, $returntransfer=1, $referer="", $http_header="", $post="", $need_header=0, $cookies="", $timeout = 10)
{
    if(!empty($post)) { $cpost = 1; } else { $cpost = 0; }
    if(is_array($http_header)) { $chheader = 1; } else { $chheader = 0; }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, $returntransfer);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_HEADER, $need_header);
    curl_setopt($ch, CURLOPT_POST, $cpost);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_MAX_TLSv1_2);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    if(!empty($referer)) { curl_setopt($ch, CURLOPT_REFERER, $referer); }

    if($chheader == 1) { curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header); }

    if($cpost == 1) { curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    
    if(!empty($cookies)) { curl_setopt($ch, CURLOPT_COOKIE, $cookies); }

    $response = curl_exec($ch);
    $header = curl_getinfo($ch);
    curl_close($ch);

    return array("response" => $response, "header" => $header);
}


function gen_challenge()
{
    global $tesla_api_code_vlc;

    $code_verifier = tesla_base64url_encode(random_bytes($tesla_api_code_vlc));
    $code_verifier = substr($code_verifier, 0, $tesla_api_code_vlc);
    $code_challenge = tesla_base64url_encode(hash('sha256', $code_verifier, true)); 
    
    $state = tesla_base64url_encode(random_bytes(24)); 

    return array("code_verifier" => $code_verifier, "code_challenge" => $code_challenge, "state" => $state);
}


function gen_url($code_challenge, $state, $redirect_uri = "")
{
    global $tesla_api_oauth2, $tesla_api_redirect;

    if (empty($redirect_uri)) {
    	$redirect_uri = $tesla_api_redirect;
    }

    $datas = array(
          'audience' => '',
          'client_id' => 'ownerapi',
          'code_challenge' => $code_challenge,
          'code_challenge_method' => 'S256',
          'locale' => 'en-US',
          'prompt' => 'login',
          'redirect_uri' => $redirect_uri,
          'response_type' => 'code',
          'scope' => 'openid email offline_access',
          'state' => $state
    );

    return $tesla_api_oauth2."/authorize?".http_build_query($datas);
}


function return_msg($code, $msg)
{
    return json_encode(array("success" => $code, "message" => $msg));
}


function tesla_base64url_encode($value)
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}


function tesla_base64url_decode($value)
{
	$remainder = strlen($value) % 4;
	if ($remainder > 0) {
		$value .= str_repeat("=", 4 - $remainder);
	}
	return base64_decode(strtr($value, '-_', '+/'));
}


function tesla_get_plugin_url($script_name = "")
{
	$scheme = "http";
	if (!empty($_SERVER["HTTP_X_FORWARDED_PROTO"])) {
		$scheme = trim(explode(",", $_SERVER["HTTP_X_FORWARDED_PROTO"])[0]);
	} elseif ((!empty($_SERVER["HTTPS"]) && strtolower($_SERVER["HTTPS"]) !== "off") || (!empty($_SERVER["SERVER_PORT"]) && (int)$_SERVER["SERVER_PORT"] === 443)) {
		$scheme = "https";
	}

	$host = !empty($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : LBSystem::get_localip();
	$scriptDir = "/admin/plugins/".LBPPLUGINDIR;
	if (!empty($_SERVER["SCRIPT_NAME"])) {
		$scriptDir = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
	}
	if ($scriptDir === "/") {
		$scriptDir = "";
	}

	$path = $scriptDir;
	if (!empty($script_name)) {
		$path .= "/".$script_name;
	}
	return $scheme."://".$host.$path;
}


function tesla_get_oauth_callback_url()
{
	return tesla_get_plugin_url("oauth_callback.php");
}


function tesla_prepare_oauth_login($redirect_uri = "")
{
	if (empty($redirect_uri)) {
		$redirect_uri = tesla_get_oauth_callback_url();
	}

	$challenge = gen_challenge();
	$challenge["redirect_uri"] = $redirect_uri;
	$challenge["auth_url"] = gen_url($challenge["code_challenge"], $challenge["state"], $redirect_uri);
	return $challenge;
}


function tesla_parse_token_expiration($access_token)
{
	if (empty($access_token)) {
		return 0;
	}

	$tokenparts = explode(".", $access_token);
	if (!isset($tokenparts[1])) {
		return 0;
	}

	$payload = json_decode(tesla_base64url_decode($tokenparts[1]), true);
	if (empty($payload["exp"])) {
		return 0;
	}

	return (int)$payload["exp"];
}


function tesla_get_token_expiration($tokens)
{
	if (is_object($tokens)) {
		$tokens = (array)$tokens;
	}
	if (!is_array($tokens)) {
		return 0;
	}
	if (!empty($tokens["expires_at"])) {
		return (int)$tokens["expires_at"];
	}
	if (!empty($tokens["created_at"]) && !empty($tokens["expires_in"])) {
		return (int)$tokens["created_at"] + (int)$tokens["expires_in"];
	}
	return tesla_parse_token_expiration(tesla_get_login_access_token($tokens));
}


function tesla_get_login_access_token($login)
{
	if (is_object($login)) {
		$login = (array)$login;
	}
	if (!is_array($login)) {
		return "";
	}
	if (!empty($login["bearer_token"])) {
		return $login["bearer_token"];
	}
	if (!empty($login["access_token"])) {
		return $login["access_token"];
	}
	return "";
}


function tesla_get_login_refresh_token($login)
{
	if (is_object($login)) {
		$login = (array)$login;
	}
	if (!is_array($login)) {
		return "";
	}
	if (!empty($login["bearer_refresh_token"])) {
		return $login["bearer_refresh_token"];
	}
	if (!empty($login["refresh_token"])) {
		return $login["refresh_token"];
	}
	return "";
}


function tesla_read_login_data()
{
	if (!file_exists(LOGINFILE)) {
		return false;
	}

	$logindata = json_decode(file_get_contents(LOGINFILE), true);
	if (!is_array($logindata)) {
		return false;
	}

	return $logindata;
}


function tesla_write_json_file($filename, $content)
{
	$result = file_put_contents($filename, $content, LOCK_EX);
	if ($result === false) {
		return false;
	}

	@chmod($filename, 0640);
	return true;
}


function tesla_store_tokens($tokens, $token_source = "oauth", $fallback_refresh_token = "")
{
	if (is_object($tokens)) {
		$tokens = (array)$tokens;
	}
	if (!is_array($tokens)) {
		return array("success" => 0, "message" => "Tesla did not return token data.");
	}

	$access_token = "";
	if (!empty($tokens["access_token"])) {
		$access_token = trim($tokens["access_token"]);
	} elseif (!empty($tokens["bearer_token"])) {
		$access_token = trim($tokens["bearer_token"]);
	}

	$refresh_token = "";
	if (!empty($tokens["refresh_token"])) {
		$refresh_token = trim($tokens["refresh_token"]);
	} elseif (!empty($tokens["bearer_refresh_token"])) {
		$refresh_token = trim($tokens["bearer_refresh_token"]);
	} elseif (!empty($fallback_refresh_token)) {
		$refresh_token = trim($fallback_refresh_token);
	}

	if (empty($access_token) || empty($refresh_token)) {
		return array("success" => 0, "message" => "Tesla did not return both an access token and a refresh token.");
	}

	$tokens["access_token"] = $access_token;
	$tokens["refresh_token"] = $refresh_token;
	$tokens["bearer_token"] = $access_token;
	$tokens["bearer_refresh_token"] = $refresh_token;
	$tokens["created_at"] = time();
	$expires_at = tesla_get_token_expiration($tokens);
	if ($expires_at > 0) {
		$tokens["expires_at"] = $expires_at;
	}
	$tokens["token_source"] = $token_source;
	$tokens["updated_at"] = time();

	$return_message = json_encode($tokens);
	if ($return_message === false) {
		return array("success" => 0, "message" => "Token data could not be encoded.");
	}

	if (!tesla_write_json_file(LOGINFILE, $return_message)) {
		return array("success" => 0, "message" => "Token data could not be written to disk.");
	}

	return array("success" => 1, "message" => "Token data saved.", "data" => $tokens);
}


function tesla_get_oauth_error_message($response_body, $default_message)
{
	$response = json_decode($response_body, true);
	if (!is_array($response)) {
		return $default_message;
	}
	if (!empty($response["error_description"])) {
		return $response["error_description"];
	}
	if (!empty($response["error"])) {
		return str_replace("_", " ", $response["error"]);
	}
	return $default_message;
}


function tesla_exchange_authorization_code($code, $code_verifier, $redirect_uri)
{
	global $user_agent, $tesla_api_oauth2;

	if (empty($code) || empty($code_verifier) || empty($redirect_uri)) {
		return array("success" => 0, "message" => "Tesla login could not be completed because required OAuth data is missing.");
	}

	$http_header = array('Content-Type: application/json', 'Accept: application/json', 'User-Agent: '.$user_agent);
	$post = json_encode(array("grant_type" => "authorization_code", "client_id" => "ownerapi", "code" => $code, "code_verifier" => $code_verifier, "redirect_uri" => $redirect_uri));
	$response = tesla_connect($tesla_api_oauth2."/token", 1, "", $http_header, $post, 0);
	$token_res = json_decode($response["response"], true);

	if (empty($token_res["access_token"]) || empty($token_res["refresh_token"])) {
		return array("success" => 0, "message" => tesla_get_oauth_error_message($response["response"], "Tesla login did not return valid token data."));
	}

	$store_result = tesla_store_tokens($token_res, "oauth");
	if (!$store_result["success"]) {
		return $store_result;
	}

	return array("success" => 1, "message" => "Tesla login successful.", "data" => $store_result["data"]);
}


function login($weburl, $code_verifier, $code_challenge, $state)
{
    global $tesla_api_redirect;

	$parts = parse_url($weburl);
	if (empty($parts["query"])) { return return_msg(0, "The Tesla callback URL does not contain an authorization code."); }
	parse_str($parts["query"], $parm);
    $code = !empty($parm['code']) ? $parm['code'] : "";


    if(empty($code)) { return return_msg(0, "Something is wrong ... Code not exists"); }

	$result = tesla_exchange_authorization_code($code, $code_verifier, $tesla_api_redirect);
	if (!$result["success"]) {
		return return_msg(0, $result["message"]);
	}

    // Output
    return return_msg(1, "Tesla login successful.");  
}


function tesla_oauth2_refresh_token($bearer_refresh_token)
{
    global $tesla_api_oauth2, $tesla_api_redirect, $tesla_api_owners, $tesla_api_code_vlc, $cid, $cs;

    $brt = $bearer_refresh_token;

    // Get the ******
    $http_header = array('Content-Type: application/json', 'Accept: application/json');
    $post = json_encode(array("grant_type" => "refresh_token", "client_id" => "ownerapi", "refresh_token" => $brt, "scope" => "openid email offline_access"));
    $response = tesla_connect($tesla_api_oauth2."/token", 1, "https://auth.tesla.com/", $http_header, $post, 0);

    $token_res = json_decode($response["response"], true);

    if(empty($token_res["access_token"])) {
    	LOGINF("tesla_oauth2_refresh_token: Refresh request failed.");
    	return false;
    }

    $store_result = tesla_store_tokens($token_res, "oauth", $brt);
    if (!$store_result["success"]) {
    	LOGINF("tesla_oauth2_refresh_token: Refreshed token could not be saved.");
    	return false;
    }

    // Output
    return $store_result["data"]["bearer_token"];
}

function read_api_data()
{
	// used for vehicle-command API that requires VIN. Currently only type BLE is implemented
	LOGINF("read_api_data: Read API settings (BLE timeouts, debug option and number of retries).");

	if( !file_exists(APIFILE) ) {
		LOGDEB("read_api_data: No file with API settings found. Using defaults.");

		$apidata = new stdClass();
		$apidata->command_timeout = 5;
    	$apidata->connect_timeout = 20;
    	$apidata->tesla_debug = "off";
    	$apidata->ble_retries = 1;
	} else {
		LOGDEB("read_api_data: Reading content from API file: ".APIFILE);
		$apidata = json_decode(file_get_contents(APIFILE));
		if (is_numeric($apidata->command_timeout))
			$apidata->command_timeout = (int)$apidata->command_timeout;
		else
			$apidata->command_timeout = 5;
		if (is_numeric($apidata->connect_timeout))
			$apidata->connect_timeout = (int)$apidata->connect_timeout;
		else
			$apidata->connect_timeout = 20;
		if (is_numeric($apidata->tesla_debug))
			$apidata->tesla_debug = (int)$apidata->tesla_debug;
		else
			$apidata->tesla_debug = 0;
		if (is_numeric($apidata->ble_retries))
			$apidata->ble_retries = (int)$apidata->ble_retries;
		else
			$apidata->ble_retries = 1;

		LOGDEB("read_api_data: command timeout: ".$apidata->command_timeout);
		LOGDEB("read_api_data: connect timeout: ".$apidata->connect_timeout);
		LOGDEB("read_api_data: debug option: ".$apidata->tesla_debug);
		LOGDEB("read_api_data: retries: ".$apidata->ble_retries);
	}

	$apidata->lock_timeout = $apidata->command_timeout + $apidata->connect_timeout + 1;
	
	// create generic tesla-control command with options
	$apidata->baseblecmd = TESLA_CONTROL_CMD." ".COMMAND_TIMEOUT.$apidata->command_timeout."s ".CONNECT_TIMEOUT.$apidata->connect_timeout."s ";
	if ($apidata->tesla_debug) {
		$apidata->baseblecmd .= DEBUG_OPTION." ";
	}
	$apidata->baseblecmd .= COMMAND_TAG;
	LOGDEB("read_api_data: base command with options: ".$apidata->baseblecmd);
	return $apidata;
}

function object_count($object)
{
	if (!is_object($object)) {
		return 0;
	}
	return count(get_object_vars($object));
}

function read_local_ble_vehicles()
{
	LOGINF("read_local_ble_vehicles: Read locally mapped BLE vehicles.");

	if( !file_exists(LOCALBLEFILE) ) {
		LOGDEB("read_local_ble_vehicles: No local BLE vehicle file found.");
		return new stdClass();
	}

	$vehicles = json_decode(file_get_contents(LOCALBLEFILE));
	if (!is_object($vehicles)) {
		LOGWARN("read_local_ble_vehicles: File content is invalid. Returning empty list.");
		return new stdClass();
	}

	foreach ($vehicles as $key => $vehicle) {
		if (!isset($vehicle->discovered) && isset($vehicle->last_seen)) {
			$vehicle->discovered = $vehicle->last_seen;
		}
		$vehicles->{$key} = $vehicle;
	}

	return $vehicles;
}

function write_local_ble_vehicles($vehicles)
{
	LOGINF("write_local_ble_vehicles: Write locally mapped BLE vehicles.");
	file_put_contents(LOCALBLEFILE, json_encode($vehicles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function get_local_ble_vehicle_by_vin($vin)
{
	static $vehiclesByVin = null;

	if ($vehiclesByVin === null) {
		$vehiclesByVin = [];
		$vehicles = read_local_ble_vehicles();
		foreach ($vehicles as $mappedVehicle) {
			if (isset($mappedVehicle->vin) && ($mappedVehicle->vin !== "")) {
				$vehiclesByVin[(string)$mappedVehicle->vin] = $mappedVehicle;
			}
		}
	}

	if (isset($vehiclesByVin[(string)$vin])) {
		return $vehiclesByVin[(string)$vin];
	}

	return null;
}

function create_local_ble_vehicle($mappedVehicle)
{
	$vehicle = new stdClass();
	$vehicle->id = $mappedVehicle->local_name;
	$vehicle->id_s = strval($mappedVehicle->local_name);
	$vehicle->vin = $mappedVehicle->vin;
	$vehicle->display_name = empty($mappedVehicle->display_name) ? "BLE ".$mappedVehicle->local_name : $mappedVehicle->display_name;
	$vehicle->state = empty($mappedVehicle->state) ? "local via BLE" : $mappedVehicle->state;
	$vehicle->access_type = "BLE scan";
	$vehicle->local_name = $mappedVehicle->local_name;
	$vehicle->local_rssi = isset($mappedVehicle->rssi) ? $mappedVehicle->rssi : null;
	$vehicle->discovered = isset($mappedVehicle->discovered) ? $mappedVehicle->discovered : (isset($mappedVehicle->last_seen) ? $mappedVehicle->last_seen : "");
	$vehicle->local_ble = true;
	return $vehicle;
}

function get_all_vehicles($ownerVehicles = null)
{
	if (!is_object($ownerVehicles)) {
		$ownerVehicles = new stdClass();
	}

	$vehicles = new stdClass();
	foreach ($ownerVehicles as $key => $vehicle) {
		$vehicles->{$key} = $vehicle;
	}

	$localVehicles = read_local_ble_vehicles();
	foreach ($localVehicles as $localVehicle) {
		if (empty($localVehicle->vin) || empty($localVehicle->local_name)) {
			continue;
		}
		$found = false;
		foreach ($vehicles as $key => $vehicle) {
			if (isset($vehicle->vin) && ($vehicle->vin == $localVehicle->vin)) {
				$vehicle->local_name = $localVehicle->local_name;
				$vehicle->local_rssi = isset($localVehicle->rssi) ? $localVehicle->rssi : null;
				$vehicle->discovered = isset($localVehicle->discovered) ? $localVehicle->discovered : (isset($localVehicle->last_seen) ? $localVehicle->last_seen : "");
				$vehicle->local_ble = true;
				$vehicles->{$key} = $vehicle;
				$found = true;
				break;
			}
		}
		if (!$found) {
			$vehicles->{strval($localVehicle->local_name)} = create_local_ble_vehicle($localVehicle);
		}
	}

	return $vehicles;
}

function get_ble_scan_distance($rssi)
{
	if (!is_numeric($rssi)) {
		return "not available";
	}
	if ($rssi > -50) {
		return "very strong";
	} elseif ($rssi > -67) {
		return "strong";
	} elseif ($rssi > -80) {
		return "medium";
	} elseif ($rssi > -90) {
		return "weak";
	}
	return "very weak";
}

function tesla_ble_scan()
{
	$apidata = read_api_data();
	$scanCmd = TESLA_BLESCAN.COMMAND_TIMEOUT.$apidata->command_timeout."s ".CONNECT_TIMEOUT.$apidata->connect_timeout."s ";
	if ($apidata->tesla_debug) {
		$scanCmd .= DEBUG_OPTION." ";
	}
	$scanCmd .= BODYCONTROLLERSTATE;

	LOGINF("tesla_ble_scan: Scan for Tesla vehicles via BLE.");
	$result_code = tesla_shell_exec($scanCmd, $output, $apidata->ble_retries, $apidata->lock_timeout, true);

	LOGDEB("tesla_ble_scan: -------------------------------------------------------------------------------------");
	foreach($output as $line) {
		LOGDEB("$line");
	}
	LOGDEB("tesla_ble_scan: -------------------------------------------------------------------------------------");

	$json = trim(implode("", $output));
	$start = strpos($json, "{");
	$end = strrpos($json, "}");
	if ($start !== false && $end !== false && $end >= $start) {
		$json = substr($json, $start, $end - $start + 1);
	}
	$data = json_decode($json);

	if (($result_code != 0) || !isset($data->scanResults) || !is_array($data->scanResults)) {
		return array($result_code, $output, null);
	}

	return array($result_code, $output, $data->scanResults);
}

function write_api_data($apidata)
{
    // see read function for details about content
	LOGINF("write_api_data: Write API settings.");
	
	// will be calculated
	unset($apidata->lock_timeout);

	$apidata = json_encode($apidata);	
	LOGDEB("write_api_data: write API data to file: ".APIFILE);
	file_put_contents(APIFILE, $apidata);
	
	return;
}

function keyCheck($vin, $keytype = PRIVATE_KEY)
{
	global $keyTypeNames;

	// private key needs to be specified in BLE command with '{vehicle_tag}-private.pem' ({vehicle_tag} is replaced with VIN of vehicle)
	LOGINF("keyCheck: Checking if a ".$keyTypeNames[(int)$keytype]." exists for VIN: $vin and is valid.");
	if ((int)$keytype == PUBLIC_KEY) {
		$keyfile = str_replace(VEHICLE_TAG, $vin, PUBLIC_KEY_WITH_PATH);
	} else {
		$keyfile = str_replace(VEHICLE_TAG, $vin, PRIVATE_KEY_WITH_PATH);
	}
	LOGINF("keyCheck: Read key file '$keyfile'.");

	if( !file_exists($keyfile) ) {	
		LOGDEB("keyCheck: Key file '$keyfile' missing.");
		return 1;
	}
	$keylines = [];
	$line = strtok(file_get_contents($keyfile), "\r\n");
	while ($line !== false) {
    	$keylines[] = $line;
    	$line = strtok("\r\n");
	}
	// very brief check of key file
	foreach ($keylines as $key => $line) {
		if (substr( $line, 0, 10 ) === "-----BEGIN")
			$startOfKey = $key + 1;
		if (substr( $line, 0, 8 ) === "-----END")
			$endOfKey = $key;
	}
	if ($startOfKey > 0 && $startOfKey < $endOfKey) {
		LOGDEB("keyCheck: Key file seems to be O.K.");
		return 0;
	} else {
		LOGDEB("keyCheck: Wrong format of key file (PEM format expected: key must be after a line with '-----BEGIN ...' and before '-----END ...'.");
		return 2;
	}
}

function getPublicKeyHex($vin, &$hexKey)
{
	global $keyTypeNames;
	
	LOGINF("getPublicKeyHex: Retrieving public key for VIN: $vin in hex format.");
	$keyfile = str_replace(VEHICLE_TAG, $vin, PUBLIC_KEY_WITH_PATH);
	LOGINF("getPublicKeyHex: Read key file '$keyfile'.");

	if( !file_exists($keyfile) ) {	
		LOGDEB("getPublicKeyHex: Key file '$keyfile' missing.");
		return 1;
	}
	$keylines = [];
	$line = strtok(file_get_contents($keyfile), "\r\n");
	while ($line !== false) {
    	$keylines[] = $line;
    	$line = strtok("\r\n");
	}
	$startOfKey = 0;
	$endOfKey = 0;
	$base64Key = "";
	// very brief check of key file
	foreach ($keylines as $key => $line) {
		if (substr( $line, 0, 10 ) === "-----BEGIN") {
			$startOfKey = $key + 1;
		} else if (substr( $line, 0, 8 ) === "-----END") {
			$endOfKey = $key;
		} else if ($startOfKey > 0 && $endOfKey == 0) {
			$base64Key .= $line;
		}
	}

	if ($startOfKey > 0 && $startOfKey < $endOfKey) {
		LOGDEB("getPublicKeyHex: Key file seems to be O.K.");
		LOGDEB("getPublicKeyHex: base64Key: ".$base64Key);
		$hexKey = substr(bin2hex(base64_decode($base64Key)), 52);
		LOGDEB("getPublicKeyHex: raw public key in hex format: ".$hexKey);

		return 0;
	} else {
		LOGDEB("getPublicKeyHex: Wrong format of key file (PEM format expected: key must be after a line with '-----BEGIN ...' and before '-----END ...'.");
		$hexKey = "";
		return 2;
	}
}

function keyDelete($vin, $keytype = PRIVATE_KEY)
{
	global $keyTypeNames;
	
	LOGINF("keyDelete: Checking if a ".$keyTypeNames[(int)$keytype]." exists for VIN: $vin.");

	if ((int)$keytype == PUBLIC_KEY) {
		$keyfile = str_replace(VEHICLE_TAG, $vin, PUBLIC_KEY_WITH_PATH);
	} else {
		$keyfile = str_replace(VEHICLE_TAG, $vin, PRIVATE_KEY_WITH_PATH);
	}
	LOGINF("keyDelete: Delete key file '$keyfile'.");

	if( !file_exists($keyfile) ) {	
		LOGWARN("keyDelete: Key file '$keyfile' missing.");
		return 1;
	}
	unlink($keyfile);
	LOGOK("keyDelete: Key file was deleted.");
	return 0;
}

function getYearFromVIN($vin) {

	$year = -1;
	if (strlen($vin) == 17) { 
		$yearCode = substr($vin, 9, 1);
		if ($yearCode >= "6" && $yearCode <= "9")
			$year = 2000 + intval($yearCode);
		if ($yearCode >= "A" && $yearCode <= "H")
			$year = 1945 + ord($yearCode);
		if ($yearCode >= "J" && $yearCode <= "N")
			$year = 1944 + ord($yearCode);
		if ($yearCode >= "P" && $yearCode <= "S")
			$year = 1943 + ord($yearCode);
	}
	return $year;
}

function getModelFromVIN($vin) {

	$model = "Unknown";
	if (strlen($vin) == 17) {
		$modelCode = substr($vin, 3, 1);
		switch ($modelCode) {
			case "R":
				$model = "Roadster";
				break;
			case "T":
				$model = "Semi";
				break;
			case "C":
				$model = "Cybertruck";
				break;
			default:
				$model = "Model ".$modelCode;
		}
	}
	return $model;
}

function getApiProtocol($vin, $tokenvalid = true) {

	if (!empty($vin) && get_local_ble_vehicle_by_vin($vin) != null) {
		if ($tokenvalid)
			return BLE_PLUS_OWNERS_API;
		else
			return BLE_ONLY;
	}

	if (strlen($vin) == 17) {
		$modelCode = substr($vin, 3, 1);
		if ( (($modelCode == "Y") || ($modelCode == "S")) && (getYearFromVIN($vin) < 2021) )
			return OWNERS_API;
		return BLE_PLUS_OWNERS_API;
	}
	return OWNERS_API;
}

function isVIN($vin) {

	if (strlen($vin) == 17) {
		return 1;
	}
	return 0;
}

?>