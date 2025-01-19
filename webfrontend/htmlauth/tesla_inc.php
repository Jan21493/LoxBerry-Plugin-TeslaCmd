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
		
		LOGDEB("tesla_refreshtoken: Loginfile missing, aborting.");
		LOGERR("No valid token, please login.");
		return;
	}
	
	LOGDEB("tesla_refreshtoken: read loginfile.");
	$logindata = file_get_contents(LOGINFILE);
	$login = json_decode($logindata);
	
	// Read token
	if( empty($login->bearer_token) ) {
		LOGDEB("tesla_refreshtoken: File data error, no token found. Fallback to re-login.");
		LOGERR("No valid token, please login.");
		return;
	}
	
	// Get date part of token
	$tokenparts = explode(".", $login->bearer_token);
	$tokenexpires = json_decode( base64_decode($tokenparts[1]) )->exp;

    $timediff = 60*240; //60sec*240min (4h) 

	LOGDEB("tesla_refreshtoken: Time now                  - ". time() ." ".date("Y-m-d H:i:s", time()));
	LOGDEB("tesla_refreshtoken: Refresh Token valid until - ". ($tokenexpires) ." ".date("Y-m-d H:i:s", $tokenexpires));
    LOGDEB("tesla_refreshtoken: Time to Refresh Token     - ". ($tokenexpires-$timediff) ." ".date("Y-m-d H:i:s", $tokenexpires-$timediff));
	
	if( $tokenexpires-$timediff > time() ) {
		// Token is valid
		mqttpublish(1, "/token_valid");
		mqttpublish(epoch2lox($tokenexpires), "/token_expires");
		LOGOK("Token valid (" . date("Y-m-d\TH:i:s", $tokenexpires) . ").");
		$token = $login->bearer_token;
	} elseif ($tokenexpires > time()) {
		// Token expired
		LOGINF("Token will expire (" . date("Y-m-d\TH:i:s", $tokenexpires) . "), refresh token.");

		$token = tesla_oauth2_refresh_token( $login->bearer_refresh_token );
		if(!empty($token)) {
			mqttpublish(1, "/token_valid");
			mqttpublish(epoch2lox($tokenexpires), "/token_expires");
		} else {
			mqttpublish(0, "/token_valid");
			mqttpublish(0, "/token_expires");
		}
	} else {
		// no valid token
		mqttpublish(0, "/token_valid");
		mqttpublish(epoch2lox($tokenexpires), "/token_expires");
		LOGERR("No valid token, please login.");
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
} 

/* to be deleted in final version - 
function tesla_summary2()
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
} 
*/

// TODO: Check if function needed
function tesla_checktoken()
{
	// Function to check if token is valid
	
	$data = json_decode(tesla_query( "", "product_list" ));

	if (is_null($data)) {
		LOGDEB("tesla_checktoken: not valid");
		return "false";
	} else {
		LOGDEB("tesla_checktoken: valid");
		return "true";
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


function tesla_query( $VID, $action, $POST=false, $force=false )
{
	// Function to send query to tesla api
		
	global $commands;
	$action = strtoupper($action);
	$type = $commands->{"$action"}->TYPE;
	$uri = $commands->{"$action"}->URI;
	$uri = str_replace("{vehicle_tag}", "$VID", $uri);
	$uri = str_replace("{energy_site_id}", "$VID", $uri);
	$timeout = 10;

	LOGINF("Query: $action: start");

	while($timeout > -1) {
		if($type == "GET") {
			//GET
			LOGDEB("tesla_query: $type: $uri");
			$rawdata = preg_replace('/("\w+"):(\d+(\.\d+)?)/', '\\1:"\\2"', tesla_curl_send( BASEURL.$uri, false ));
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
			$rawdata = preg_replace('/("\w+"):(\d+(\.\d+)?)/', '\\1:"\\2"', tesla_curl_send( BASEURL.$uri, $POST, true));
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
				LOGOK("Query: $action: success");
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
	// LOGDEB("mqttpublishdata: " . MQTTTOPIC . "$mqttsubtopic - called");
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
			LOGDEB("mqttpublishdata: " . MQTTTOPIC . "$mqttsubtopic: NULL");
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
		LOGDEB("mqttpublishdata: " . MQTTTOPIC . "$mqttsubtopic: $data");
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
		LOGDEB("mqttpublish: MQTT connection successful, topic: ".MQTTTOPIC);
		LOGOK("MQTT: Connection successful.");
		// publish all data
		mqttpublishdata($mqtt, $data, $mqttsubtopic);

		//[x] Query timestamp added, changed to mqtt_timestamp
		$mqtt->publish(MQTTTOPIC . "/mqtt_timestamp", epoch2lox(time()), 0, 1);
		LOGDEB("mqttpublish: " . MQTTTOPIC . "/mqtt_timestamp: " . epoch2lox(time()));
		$mqtt->close();
	} else {
		LOGDEB("mqttpublish: MQTT connection failed");
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
		LOGDEB("tesla_curl_send: Payload: $payload");
	} else {
		$payload = "";
	}
	
	$header = [ ];
	
	if( !empty($token) ) {
		LOGDEB("tesla_curl_send: Token given");
		array_push( $header, "Authorization: Bearer $token" );
	}
	
	if($post==true) {
		array_push( $header, "Content-Type: application/json;charset=UTF-8" );
		array_push( $header, "Content-Length: " . strlen($payload) );
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
	}
	
	//cURL connection timeout 5 seconds
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);

	//cURL timeout 10 seconds
	curl_setopt($curl, CURLOPT_TIMEOUT, 10);

	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header );

	LOGDEB("tesla_curl_send: curl_send URL: $url");
	$response = curl_exec($curl);

	//Did an error occur? If so, dump it out.
	if(curl_errno($curl)){
		LOGERR("tesla_curl_send: ".curl_error($curl));
	}

	LOGDEB("tesla_curl_send: curl_exec finished");
	// Debugging
	$crlinf = curl_getinfo($curl);
	LOGDEB("tesla_curl_send: Status: " . $crlinf['http_code']);
	
	return $response;
}

function makeDir($path)
{
     return is_dir($path) || mkdir($path);
}

function tesla_shell_exec( $command, &$output, $retries = 0, $lock_timeout = 15, $exclusive = false)
{
	$lockfile = "";

	// Function to execute shell command
	//[ ] If Timeout, restart apache server: sudo systemctl restart apache2
	
	LOGINF("tesla_shell_exec: Start to executing a shell command ...");
	$command .= " 2>&1";
	if( !empty($command) ) {
		LOGDEB("tesla_shell_exec: command: $command");
	} else {
		LOGERR("tesla_shell_exec: empty command");
		return NULL;
	}

	if ($exclusive) {
		LOGINF("tesla_shell_exec: use locking and queuing to get exclusive access to BLE device!");
		// simple queuing system, because only one utiliy that use BLE can be run at a time!
		// set temp directory to user loxberry UID, e.g. 1001
		$tmpdir = "/run/user/".getmyuid().'/tesla';
		if (makeDir($tmpdir)) {
			// create a file with current time stamp - it's unlikely that two files will have the same name
			$timestamp =  hrtime(true);
			$lockfile = $tmpdir.'/'.$timestamp;
			touch($lockfile);
			LOGDEB("tesla_shell_exec: created lock file: $lockfile.");
			// grant $lock_timeout seconds (multiplied with retries counter +1) for the first process in list finish, that means to delete the file lock
			$lockSeconds = $lock_timeout * ((int)$retries + 1);
			LOGDEB("tesla_shell_exec: lock for: $lockSeconds seconds.");
			$waitSeconds = 0;
			$fileList = scandir($tmpdir);
			foreach ($fileList as $key => $fileEntry) {
				LOGDEB("tesla_shell_exec: file list entry no: $key, entry: $fileEntry");
			}
			// . and .. are the first two entries, starting from 0, so 2 is the first real entry in list
			if (count($fileList) > 2) {
				$firstFile = $fileList[2];
				LOGDEB("tesla_shell_exec: first entry: $firstFile");
				LOGDEB("tesla_shell_exec: queue length: ".(count($fileList)-2).", position: ".(array_search($timestamp, $fileList)-1));
				if ($firstFile == $timestamp) {
					LOGDEB("tesla_shell_exec: queue is empty - very good.");
				}
				while ($firstFile != $timestamp && $waitSeconds < 300) {
					sleep(1);
					$lockSeconds--;
					$waitSeconds++;
					$fileList = scandir($tmpdir);
					if (count($fileList) > 2) {
						if ($firstFile != $fileList[2]) {
							// firstFile has changed, so reset lock seconds
							LOGDEB("tesla_shell_exec: first entry has changed to: ".$fileList[2]);
							foreach ($fileList as $key => $fileEntry) {
								LOGDEB("tesla_shell_exec: file list entry no: $key, entry: $fileEntry");
							}
							$lockSeconds = $lock_timeout * ((int)$retries + 1);
							$firstFile = $fileList[2];
							LOGDEB("tesla_shell_exec: queue length: ".(count($fileList)-2).", position: ".(array_search($timestamp, $fileList)-1).", waiting: ".$waitSeconds." seconds.");
						}
					} else {
						LOGERR("tesla_shell_exec: ERROR - file list has no entries except . and ..");
						$firstFile = $timestamp;
					}

					// no other process has deleted the file so far, so do it now.
					if ($lockSeconds == 0 && $firstFile != $timestamp) {
						unlink($tmpdir.'/'.$firstFile);
					}
				}
				LOGINF("tesla_shell_exec: Waiting time in queue for other BLE commands to finish: $waitSeconds seconds.");
				if ($waitSeconds >= 300) {
					LOGWARN("tesla_shell_exec: We finally gave up and are now trying to execute the command without exclusive access anyway.");
				} 
			} else {
				LOGERR("tesla_shell_exec: file list that is used for queuing has no entries except . and .. ! File lock for this process is missing.");	
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
	if ($exclusive) {
		unlink($lockfile);
		LOGDEB("tesla_shell_exec: removed lock file: $lockfile");
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
	unlink(LOGINFILE);
	LOGDEB("delete_token: File " . LOGINFILE . "deleted.");
	LOGINF("Token deleted.");
}


function setlogintoken($bearer_token, $refresh_token)
{
	// Add Tokens to file
	if(empty($bearer_token)) { return return_msg(0, "Bearer Token issue"); }

	$tokens = json_decode($response["response"], true);
    $tokens["bearer_token"] = $bearer_token;
    $tokens["bearer_refresh_token"] = $refresh_token;
    $return_message = json_encode($tokens);

    // Write data to disk
    file_put_contents(LOGINFILE, $return_message);    

    // Output
    return return_msg(1, $return_message);  
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

    $code_verifier = substr(hash('sha512', mt_rand()), 0, $tesla_api_code_vlc);
    $code_challenge = rtrim(strtr(base64_encode($code_verifier), '+/', '-_'), '='); 
    
    $state = rtrim(strtr(base64_encode(substr(hash('sha256', mt_rand()), 0, 12)), '+/', '-_'), '='); 

    return array("code_verifier" => $code_verifier, "code_challenge" => $code_challenge, "state" => $state);
}


function gen_url($code_challenge, $state)
{
    global $tesla_api_oauth2, $tesla_api_redirect;


    $datas = array(
          'audience' => '',
          'client_id' => 'ownerapi',
          'code_challenge' => $code_challenge,
          'code_challenge_method' => 'S256',
          'locale' => 'en-US',
          'prompt' => 'login',
          'redirect_uri' => $tesla_api_redirect,
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


function login($weburl, $code_verifier, $code_challenge, $state)
{
    global $tesla_api_redirect, $user_agent, $tesla_api_oauth2, $cid, $cs, $tesla_api_owners;

    
	$urlparm = explode('https://auth.tesla.com/void/callback?', $weburl);
	LOGDEB("login: code: ".json_encode($urlparm));
	parse_str($urlparm[1], $parm);
    $code = $parm['code'];
	LOGDEB("login: code: $code");


    if(empty($code)) { return return_msg(0, "Something is wrong ... Code not exists"); }

    // Get the Bearer token
    $http_header = array('Content-Type: application/json', 'Accept: application/json', 'User-Agent: '.$user_agent);
    $post = json_encode(array("grant_type" => "authorization_code", "client_id" => "ownerapi", "code" => $code, "code_verifier" => $code_verifier, "redirect_uri" => $tesla_api_redirect));
    $response = tesla_connect($tesla_api_oauth2."/token", 1, "", $http_header, $post, 0);

    $token_res = json_decode($response["response"], true);
	
    $bearer_token = $token_res["access_token"];
    $refresh_token = $token_res["refresh_token"];

    if(empty($bearer_token)) { return return_msg(0, "Bearer Token issue"); }

	$tokens = json_decode($response["response"], true);
    $tokens["bearer_token"] = $bearer_token;
    $tokens["bearer_refresh_token"] = $refresh_token;
    $return_message = json_encode($tokens);

    // Write data to disk
    file_put_contents(LOGINFILE, $return_message);    

    // Output
    return return_msg(1, $return_message);  
}


function tesla_oauth2_refresh_token($bearer_refresh_token)
{
    global $tesla_api_oauth2, $tesla_api_redirect, $tesla_api_owners, $tesla_api_code_vlc, $cid, $cs;

    $brt = $bearer_refresh_token;

    // Get the Bearer token
    $http_header = array('Content-Type: application/json', 'Accept: application/json');
    $post = json_encode(array("grant_type" => "refresh_token", "client_id" => "ownerapi", "refresh_token" => $brt, "scope" => "openid email offline_access"));
    $response = tesla_connect($tesla_api_oauth2."/token", 1, "https://auth.tesla.com/", $http_header, $post, 0);

    $token_res = json_decode($response["response"], true);

    $bearer_token = $token_res["access_token"];
    $refresh_token = $token_res["refresh_token"];


    if(empty($bearer_token)) { return return_msg(0, "Bearer Refresh Token is not valid"); }

    $tokens = json_decode($response["response"], true);

    if(empty($tokens['access_token'])) { return return_msg(0, "Token issue"); }

    $tokens["bearer_token"] = $bearer_token;
    $tokens["bearer_refresh_token"] = $refresh_token;
    $return_message = json_encode($tokens);

    // Write data to disk
    file_put_contents(LOGINFILE, $return_message);

    // Output
    return $bearer_token;
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

		$apidata->lock_timeout = $apidata->command_timeout + $apidata->connect_timeout + 1;

		LOGDEB("read_api_data: command timeout: ".$apidata->command_timeout);
		LOGDEB("read_api_data: connect timeout: ".$apidata->connect_timeout);
		LOGDEB("read_api_data: debug option: ".$apidata->tesla_debug);
		LOGDEB("read_api_data: retries: ".$apidata->ble_retries);
	}
	
	// create generic tesla-control command with options
	$apidata->baseblecmd = TESLA_CONTROL_CMD." ".COMMAND_TIMEOUT.$apidata->command_timeout."s ".CONNECT_TIMEOUT.$apidata->connect_timeout."s ";
	if ($apidata->tesla_debug) {
		$apidata->baseblecmd .= DEBUG_OPTION." ";
	}
	$apidata->baseblecmd .= COMMAND_TAG;
	LOGDEB("read_api_data: base BLE command: ".$apidata->baseblecmd);
	return $apidata;
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
			$year = 2000 + int($yearCode);
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

function getApiProtocol($vin) {

	if (strlen($vin) == 17) {
		$modelCode = substr($vin, 3, 1);
		if ( (($modelCode == "Y") || ($modelCode == "S")) && (getYearFromVIN($vin) < 2021) )
			return 0;
		return 1;
	}
	return 0;
}

function isVIN($vin) {

	if (strlen($vin) == 17) {
		return 1;
	}
	return 0;
}

?>