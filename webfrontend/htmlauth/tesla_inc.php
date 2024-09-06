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
		LOGEND("Processing finished");
	}
}

$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging");

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

$commands = get_commands();
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

function tesla_ble_query( $vehicle_tag, $action, $blebasecmd, $blecmd, $force=false )
{
	// Function to send query via vehicle command API over BLE
		
	global $commands;
	$keyno = 1;

	LOGINF("BLE Query: $action: start");
	$action = strtoupper($action);
	$type = $commands->{"$action"}->TYPE;

	if ($force) {
		// if command is enforced, then check wake status
		$blefullcmd = str_replace("{command}", $commands->{"BODY_CONTROLLER_STATE"}->BLECMD, $blebasecmd);
		LOGDEB("tesla_ble_query: Check if vehicle is asleep: $blefullcmd");
		$result_code = tesla_shell_exec( "$blefullcmd", $output);
		$vehicleSleepStatus = "";
		foreach($output as $key => $line) {
			if (strpos($line, '"vehicleSleepStatus": "VEHICLE_SLEEP_STATUS_ASLEEP"') > 0) {
				$vehicleSleepStatus = "sleeping";
				$blefullcmd = str_replace("{command}", $commands->{"BLE_WAKE"}->BLECMD, $blebasecmd);
				LOGDEB("tesla_ble_query: Need to wake vehicle: $blefullcmd");
				$result_code = tesla_shell_exec( "$blefullcmd", $output);
				if ($result_code > 0) {
					LOGDEB("tesla_ble_query: Wakeup failed: result_code= $result_code");
				} else {
					LOGDEB("tesla_ble_query: Wakeup was successful");
					$blefullcmd = str_replace("{command}", $blecmd, $blebasecmd);
					LOGDEB("tesla_ble_query: $type: $blefullcmd");
					$result_code = tesla_shell_exec( "$blefullcmd", $output);
				}
				break;
			} else if (strpos($line, '"vehicleSleepStatus": "VEHICLE_SLEEP_STATUS_AWAKE"') > 0) {
				$vehicleSleepStatus = "awake";
				LOGDEB("tesla_ble_query: Vehicle is awake already - no need to wake it up");
				break;
			}
		}
		if (empty($vehicleSleepStatus)) {
			LOGDEB("tesla_ble_query: No vehicleSleepStatus in response. Reporting an error!");
		}
	} else {
		$blefullcmd = str_replace("{command}", $blecmd, $blebasecmd);
		LOGDEB("tesla_ble_query: $type: $blefullcmd");
		$result_code = tesla_shell_exec( "$blefullcmd", $output);
	}
	
	// remove logging output from other output
	foreach($output as $key => $line) {
		if (strpos($line, "[") == 26) {
			// logging output 
			if (!empty($logdata))
				$logdata .= ', '; 
			$logdata .= '"'.$line.'"';
			unset($output[$key]);
		}
	} 
	$rawdata = '{"result_code":"'.$result_code.'", ';
	$rawdata .= '"result_msg":"'.get_result_code_msg($result_code).'", ';

	if($type == "GET") {
		if ( $result_code == 0) {
			$rawdata .= '"error_msg":""';
			if ($action == "BODY_CONTROLLER_STATE")
				$rawdata .= ', "vehicleNearby":true';
		
			// reformat list-keys as json
			if ($action == "LIST_KEYS") {
				foreach($output as $key => $line) {
					$keydata = explode("\t", $line);
					$rawdata .= ', "key'.$key.'": {"key":"'.$keydata[0].'", "role":"'.$keydata[1].'", "key_form_factor":"'.$keydata[2].'"}';
					$keyno++;
				}
			} else {
				// remove outer brackets for all other commands (currently only body-controller-state)
				array_shift($output);
				array_pop($output);
				// replace state strings with numbers to make it easier for the Loxone miniserver to process the values
				// enums are defined in https://github.com/teslamotors/vehicle-command/pkg/protocol/protobuf/vcsec.proto
				//	 ClosureState_E, VehicleLockState_E, VehicleSleepStatus_E, UserPresence_E 
				$rawdata .= ', ';
				foreach($output as $line) {
					$line = str_replace('"CLOSURESTATE_CLOSED"', "0", $line);
					$line = str_replace('"CLOSURESTATE_OPEN"', "1", $line);
					$line = str_replace('"CLOSURESTATE_AJAR"', "2", $line);
					$line = str_replace('"CLOSURESTATE_UNKNOWN"', "3", $line);
					$line = str_replace('"CLOSURESTATE_FAILED_UNLATCH"', "4", $line);
					$line = str_replace('"CLOSURESTATE_OPENING"', "5", $line);
					$line = str_replace('"CLOSURESTATE_CLOSING"', "6", $line);
					$line = str_replace('"VEHICLELOCKSTATE_UNLOCKED"', "0", $line);
					$line = str_replace('"VEHICLELOCKSTATE_LOCKED"', "1", $line);
					$line = str_replace('"VEHICLELOCKSTATE_INTERNAL_LOCKED"', "2", $line);
					$line = str_replace('"VEHICLELOCKSTATE_SELECTIVE_UNLOCKED"', "3", $line);
					$line = str_replace('"VEHICLE_SLEEP_STATUS_UNKNOWN"', "0", $line);
					$line = str_replace('"VEHICLE_SLEEP_STATUS_AWAKE"', "1", $line);
					$line = str_replace('"VEHICLE_SLEEP_STATUS_ASLEEP"', "2", $line);
					$line = str_replace('"VEHICLE_USER_PRESENCE_UNKNOWN"', "0", $line);
					$line = str_replace('"VEHICLE_USER_PRESENCE_NOT_PRESENT"', "1", $line);
					$line = str_replace('"VEHICLE_USER_PRESENCE_PRESENT"', "2", $line);
					$rawdata .= $line;
				}
			} 
		} else {
			$rawdata .= '"error_msg":"'.end($output).'"';
			if ($action == "BODY_CONTROLLER_STATE")
				$rawdata .= ', "vehicleNearby":false';
		}
		$rawdata .= ' }';
		mqttpublish(json_decode($rawdata), "/$vehicle_tag/".strtolower($action));
	} else {
		//POST
		if ( $result_code == 0) {
			$rawdata .= '"error_msg":""';
		} else {
			$rawdata .= '"error_msg":"'.end($output).'"';
		}
		$rawdata .= ' }';
		mqttpublish(json_decode($rawdata), "/$vehicle_tag/".strtolower($action));
	}
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

function pretty_print($json_data)
{
	//Declare the custom function for formatting
	//Initialize variable for adding space
	$space = 0;
	$withinQuotes = false;

	//Using <pre> tag to format alignment and font
	echo "<pre>";

	//loop for iterating the full json data
	for($counter=0; $counter<strlen($json_data); $counter++)
	{
		if (!$withinQuotes) {
			//Checking ending second and third brackets
			if ($json_data[$counter] == '}' || $json_data[$counter] == ']') {
				$space--;
				if ($json_data[$counter-1] != '{' && $json_data[$counter-1] != '[') {
					echo "\n";
					echo str_repeat(' ', ($space*2));
				}
			}
		
			//Checking for double quote(“) and comma (,)
			if ($json_data[$counter] == '"') {
				if ($json_data[$counter-1] == ',' || $json_data[$counter-2] == ',' || $json_data[$counter-3] == ',') {
					echo "\n";
					echo str_repeat(' ', ($space*2));
				}
				if ( $json_data[$counter-1] == ':' || $json_data[$counter-2] == ':' ) {
					//Add formatting for text
					echo '<span style="color:blue;font-weight:bold">';
				} else {
					//Add formatting for options
					echo '<span style="color:red;">';
				}
				$withinQuotes = !$withinQuotes;
			}
			if ($json_data[$counter] != "\t")
				echo $json_data[$counter];
			if ( $json_data[$counter] == ':' && $json_data[$counter+1] != ' ' )
				echo " ";
			//Checking starting second and third brackets
			if ( $json_data[$counter] == '{' || $json_data[$counter] == '[') {
				if ($json_data[$counter+1] != '}' && $json_data[$counter+1] != ']') {
					$space++;
				}
				echo "\n";
				echo str_repeat(' ', ($space*2));
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


function mqttpublish($data, $mqttsubtopic = "")
{
	// Function to send data to mqtt
	// [ ] Bugfix missing 0 values
	// [ ] Bugfix missing false output

	// MQTT requires a unique client id
	$client_id = uniqid(gethostname() . "_client");
	$creds = mqtt_connectiondetails();

	// Be careful about the required namespace on inctancing new objects:
	$mqtt = new Bluerhinos\phpMQTT($creds['brokerhost'], $creds['brokerport'], $client_id);

	if ($mqtt->connect(true, NULL, $creds['brokeruser'], $creds['brokerpass'])) {
		LOGDEB("mqttpublish: MQTT connection successful, topic: ".MQTTTOPIC);
		LOGOK("MQTT: Connection successful.");

		if (is_object($data) or is_array($data)) {
			foreach ($data as $key => $value) {
				if (is_object($value)) {
					foreach ($value as $skey => $svalue) {
						if (is_object($svalue)) {
							foreach ($svalue as $sskey => $ssvalue) {
								if (isset($ssvalue)) {
									if ($sskey == "timestamp") {
										$ssvalue = epoch2lox(substr($ssvalue, 0, 10));
									} //epochetime maxlength
									if ($ssvalue === false) {
										$ssvalue = 0;
									}
									$mqtt->publish(MQTTTOPIC . "$mqttsubtopic/$key/$skey/$sskey", $ssvalue, 0, 1);
									LOGDEB("mqttpublish(3): " . MQTTTOPIC . "$mqttsubtopic/$key/$skey/$sskey: $ssvalue");
								}
							}
						} else {
							if (is_array($svalue)) {
								if (is_object($svalue[0])) {
									foreach ($svalue as $sskey => $ssvalue) {
										if (is_object($ssvalue)) {
											foreach ($ssvalue as $ssskey => $sssvalue) {
												$mqtt->publish(MQTTTOPIC . "$mqttsubtopic/$key/$skey/$sskey/$ssskey", $sssvalue, 0, 1);
												LOGDEB("mqttpublish(4): " . MQTTTOPIC . "$mqttsubtopic/$key/$skey/$sskey/$ssskey: $sssvalue");
											}
										} else {

											if (isset($ssvalue)) {
												if ($sskey == "timestamp") {
													$ssvalue = epoch2lox(substr($ssvalue, 0, 10));
												} //epochetime maxlength
												if ($ssvalue === false) {
													$ssvalue = 0;
												}
												$mqtt->publish(MQTTTOPIC . "$mqttsubtopic/$key/$skey/$sskey", $ssvalue, 0, 1);
												LOGDEB("mqttpublish(5): " . MQTTTOPIC . "$mqttsubtopic/$key/$skey/$sskey: $ssvalue");
											}
										}
									}
								} else {
									$svalue = implode(",", $svalue);

									if (isset($svalue)) {
										if ($skey == "timestamp") {
											$svalue = epoch2lox(substr($svalue, 0, 10));
										} //epochetime maxlength
										if ($svalue === false) {
											$svalue = 0;
										}
										$mqtt->publish(MQTTTOPIC . "$mqttsubtopic/$key/$skey", $svalue, 0, 1);
										LOGDEB("mqttpublish(6): " . MQTTTOPIC . "$mqttsubtopic/$key/$skey: $svalue");
									}
								}
							} else {
								if (isset($svalue)) {
									if ($skey == "timestamp") {
										$svalue = epoch2lox(substr($svalue, 0, 10));
									} //epochetime maxlength
									if ($svalue === false) {
										$svalue = 0;
									}
									$mqtt->publish(MQTTTOPIC . "$mqttsubtopic/$key/$skey", $svalue, 0, 1);
									LOGDEB("mqttpublish(7): " . MQTTTOPIC . "$mqttsubtopic/$key/$skey: $svalue");
								}
							}
						}
					}
				} else {
					if (isset($value)) {
						if (is_array($value)) {
							$value = implode(",", $value);
						}
						if ($value === false) {
							$value = 0;
						}
						if ($value === "") {
							$value = "-";
						}
						$countsubtopics = explode("/", $mqttsubtopic);
						if ($countsubtopics < 3) {
							$mqtt->publish(MQTTTOPIC . "/summary$mqttsubtopic/$key", $value, 0, 1);
							LOGDEB("mqttpublish(8): " . MQTTTOPIC . "/summary$mqttsubtopic/$key: $value");
						} else {
							$mqtt->publish(MQTTTOPIC . "$mqttsubtopic/$key", $value, 0, 1);
							LOGDEB("mqttpublish(9): " . MQTTTOPIC . "$mqttsubtopic/$key: $value");
						}
					}
				}
			}
		} else {
			$mqtt->publish(MQTTTOPIC . "$mqttsubtopic", $data, 0, 1);
			LOGDEB("mqttpublish(10): " . MQTTTOPIC . "$mqttsubtopic: $data");
		}
		//[x] Query timestamp added, changed to mqtt_timestamp
		$mqtt->publish(MQTTTOPIC . "/mqtt_timestamp", epoch2lox(time()), 0, 1);
		LOGDEB("mqttpublish(11): " . MQTTTOPIC . "/mqtt_timestamp: " . epoch2lox(time()));
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

function tesla_shell_exec( $command, &$output)
{
	// Function to execute shell command
	//[ ] If Timeout, restart apache server: sudo systemctl restart apache2
	
	$command .= " 2>&1";
	if( !empty($command) ) {
		LOGDEB("tesla_shell_exec: $command");
	} else {
		LOGERR("tesla_shell_exec: empty command");
		return NULL;
	}

	$output=NULL;
	$result_code=NULL;
	exec($command, $output, $result_code);
//	$result_code = 0;
//	$output = $command;

	//Did an error occur? If so, dump it out.
	if(is_null($result_code != 0)){
		LOGERR("tesla_shell_exec: result code " . $result_code);
	}

	LOGDEB("tesla_shell_exec: exec finished");
	// Debugging
	LOGDEB("tesla_shell_exec: result code: " . $result_code);

	foreach($output as $key => $line) {
		LOGDEB("tesla_shell_exec: output: " . str_replace("\t", "  ", $line));
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

function read_vehicle_mapping(&$vmap, &$custom_baseblecmd)
{
	// Function to read ID to VIN mapping and type of API to use
	// JSON Array with "device_id", "device_vin", "device_api"
	// used for vehicle-command API that requires VIN. Currently only type BLE is implemented
	LOGINF("Read ID to VIN, API mapping, and BLE command.");
	
	if( !file_exists(APIFILE) ) {
		
		LOGDEB("Empty ID to VIN and API mapping.");
		return;
	}
	
	LOGDEB("read_vehicle_mapping: read apifile.");
	$apidata = json_decode(file_get_contents(APIFILE));
	$vmap = $apidata->{"id-mapping"};
	if ($apidata->{"baseblecmd"})
		$custom_baseblecmd = $apidata->{"baseblecmd"};
	else
		$custom_baseblecmd = $default_baseblecmd;
}

function write_vehicle_mapping($vmap, $custom_baseblecmd)
{
	// Function to write ID to VIN mapping and type of API to use
    // see read function for details about content
	LOGINF("Write ID to VIN and API mapping.");
	
	$apidata = new stdClass();
	if (isset($vmap))
		$apidata->{"id-mapping"} = $vmap;
	if (isset($custom_baseblecmd))
		$apidata->{"baseblecmd"} = $custom_baseblecmd;

	$apidata = json_encode($apidata);	
	LOGDEB("write_vehicle_mapping: write apifile.");
	file_put_contents(APIFILE, $apidata);
	
	return;
}

function vehicles_add_api_attribute(&$vehicles, $vmap) {

	if(isset($vehicles)) {
		// add API attribute to each vehicle
		foreach ($vehicles as $index => &$vehicle) {
			$vehicle->api = 0;  // default to old owner's api
			// only cars may use the new api, not energy sites
			if(!isset($vehicle->energy_site_id)) {
				// add attribute for API to each vehicle with selected API from mappings table
				if (isset($vmap))
					if (isset($vmap->{strval($vehicle->id)})) {
						$ventry = $vmap->{strval($vehicle->id)};
						$vehicle->api = $ventry->api;
					}
			}
		}
	}
}