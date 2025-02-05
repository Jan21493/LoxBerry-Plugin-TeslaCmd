<?php

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";
require_once "loxberry_web.php";

$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging - testqueries.php");

LOGINF("testqueries.php: -------------------- start of testqueries.php -------------------- ");
LOGDEB("send.php: Source IP-address: ".$_SERVER['REMOTE_ADDR']);

require_once "defines.php";
require_once "tesla_inc.php";

$navbar[3]['active'] = True;

// Print LoxBerry header
$L = LBSystem::readlanguage("language.ini");
LBWeb::lbheader($template_title, $helplink, $helptemplate);

// $type contains either "General" or the selected vehicle ID
if(!empty($_REQUEST["type"])) { 
	$type = strtoupper($_REQUEST["type"]);
	if ($type !== "GENERAL")
		$vid = $type;
} else {
	$type = "GENERAL";
}

// $action contains the selected command in uppercase
if(!empty($_REQUEST["action"])) { 
	$action = strtoupper($_REQUEST["action"]);
} elseif (!empty($_REQUEST["a"])) { 
	$action = strtoupper($_REQUEST["a"]);
}

//Checktoken
$tokenvalid = tesla_checktoken();
$tokenparts = explode(".", $login->bearer_token);
$tokenexpires = json_decode( base64_decode($tokenparts[1]) )->exp;
?>

<style>
    .mono {
        font-family: monospace;
        font-size: 110%;
        font-weight: bold;
        color: green;
    }
	.ui-table td {
        vertical-align: middle;
    }
</style>

<?php
// if($tokenvalid == "false")
if($tokenvalid == "false") {
?>

<!-- Status -->
<div class="wide">Status</div>
<p style="color:red">
    <b>You are not logged in.</b>
</p>
<br>

<?php
// if($tokenvalid == "false")
} else {
?>

<!-- Queries -->
<?php
	$vehicles = tesla_summary();
    $apidata = read_api_data();
	$baseblecmd = $apidata->baseblecmd;
	if (isset($vid) && isset($vehicles->{"$vid"})) {
		$selected_vehicle = $vehicles->{"$vid"};
		if(isset($selected_vehicle->vin)) {
			$vin = $selected_vehicle->vin;
		} else {
			$vin = "";
		}

	}
	$send_command = false;
	// submit button was pressed and query should be send
	if (isset($_GET['test_query'])) {
		if (isset($_POST['action'])){
			$action = strtoupper($_POST['action']);
			if (isset($_POST['force']))
				$force = $_POST['force'];
			else
				$force = "";
			// $command contains the selected command
			$command = $commands->{"$action"};
			$uri = $command->URI;
			$blecmd = $command->BLECMD;
			$send_command = true;
		}
	}
?>

<div class="wide">Test Queries</div>

<form method="post" name="main_form" action="?test_query">
    <div class="form-group">
        <table class="formtable" border="0" width="100%">
		<tr>
			<td width="20%">
				<label id="labeldepth">
					<h3>Type</h3>
				</label>
			</td>
			<td width="40%">
				<!--Build drop down selection list with 'General' and each vehicle / energy site-->
				<select name="type" onchange="self.location='?type='+this.options[this.selectedIndex].value;">
					<option value="General" <?php if($type == "GENERAL"){ echo " selected"; } ?>>
						General
					</option>

<?php
	// foreach vehicle
	foreach ($vehicles as $vehicle) {
		if(isset($vehicle->energy_site_id)) {
			$tag = number_format(strval($vehicle->energy_site_id), 0, ',', '');
			if ($vehicle->site_name != "")
				$name = "Energy site ".$vehicle->site_name;
			else
				$name = "Energy site ID $tag";
            $info = "ID: ".$tag;
			if (($vehicle->resource_type == "solar") && ($vehicle->solar_type == "pv_panel")) {
				$info .= ", Solar PV-Panel ".number_format($vehicle->solar_power, 0, ',', '.')."Wp";
			} else if (($vehicle->resource_type == "battery") && ($vehicle->battery_type == "ac_powerwall")) {
				$info .= ", AC Powerwall, ".round($vehicle->total_pack_energy / 1000, 2)."kWh, SoC ".round($vehicle->percentage_charged, 1)."%";
			}
		} else {
			$tag = strval($vehicle->id);
			$name = $vehicle->display_name;
			$info = "VID: ".$tag.", VIN: ".strval($vehicle->vin).", using <span class=\"mono\">".$apinames[getApiProtocol($vehicle->vin)]."</span>";
		}
?>
					<option value="<?=$tag;?>" <?php if($type == $tag){ echo " selected"; $selected_info = $info; $selected_vehicle = $vehicle; }?>>
						<?=$name;?>
					</option>
<?php
		// foreach vehicle	
		}
?>
				</select>
			</td>
			<td width="2%">&nbsp;</td>
			<td width="38%">
				<p class="hint"><?php if($type != "GENERAL"){ echo "$selected_info"; }?></p>
			</td>
		</tr>
		<tr>
			<td>
				<label id="labeldepth">
					<h3>Command</h3>
				</label>
			</td>
			<td>
				<!--Build drop down selection list with either 'General' commands or commands for selected vehicle / energy site-->
				<select name="action" onchange="self.location='?type=<?=$type?>&action='+this.options[this.selectedIndex].value;">
					<option disabled selected>
							Please select
					</option>
<?php
		foreach ($commands as $cmd => $attribute) {
			// 'general' commands don't have a tag
			if ($type == "GENERAL" && empty($attribute->TAG)) {
?>
					<option value="<?=$cmd;?>" <?php if($cmd == $action){ echo " selected"; $command = $attribute; } ?>>
					<?=$cmd;?>
					</option>
<?php
			}
			// specific vehicle or energy site is selected (not 'GENERAL')
			if ($type == $vid) {
				if(isset($selected_vehicle->energy_site_id)) {
					$tag = "{energy_site_id}";
				} else {
					$tag = "{vehicle_tag}";
				}
				// show command if it is matching the selected API, TAG is defined for the command and matching the vehicle / energy site
				// energy sites have empty vin, so Owner's API is choosen, API for vehicles is calculated by VIN
				if (in_array(getApiProtocol($vin), $attribute->API) && !empty($attribute->TAG) && ($tag == $attribute->TAG)) {
?>
					<option value="<?=$cmd;?>" <?php if($cmd == $action){ echo " selected"; $command = $attribute; } ?>>
					<?=$cmd;?>
					</option>
<?php
				}
			}
		// foreach $commands
		}
?>
				</select>
			</td>
			<td width="1%" align=right>
<?php
            	if (!empty($command->BLECMD)) {   
                	echo '<img src="./images/Bluetooth.svg" alt="BLE" height="15" ></img>';
            	} else {
					echo '&nbsp;';
				}
?>
			</td>
			<td>
				<p class="hint">
					<?=$command->DESC;?>
				</p>
			</td>
		</tr>
		<tr>
			<td>
			</td>
			<td>
<?php
		// wake up is available for vehicles only, not if a wake up command is selected, and not if body-controller-state is requested
		if (($type == $vid) && (!empty($vin)) && ($action != "BODY_CONTROLLER_STATE") && ($action != "WAKE_UP") && ($command->BLECMD != "wake")) {
?>				
                    <fieldset data-role="controlgroup">
                        <input
                            type="checkbox"
                            name="force"
                            id="force"
                            <?php if($force){ echo " checked"; } ?>
                            class="refreshdisplay">
                        <label for="force">Wake up (force)</label>
                    </fieldset>
				</td>
				<td></td>
				<td>
					<p class="hint">Checking this box will wake the vehicle if it is sleeping.</p>
					</td>
			</tr>
<?php
		}
		if(isset($command->PARAM)) {
?>
            <tr>
                <td >
                    <h4>Parameter</h4>
                </td>
                <td>
<?php
			foreach ($command->PARAM as $param => $param_desc) {
?>
                    <tr>
                        <td>
                            <label id="labeldepth"><?=$param;?></label>
                        </td>
                        <td>
                            <input type="text" name="<?=$param;?>" value="<?=$_REQUEST["$param"];?>">
                        </td><td></td>
						<td>
							<p class="hint"><?=$param_desc;?></p>
						</td>
                    </tr>
<?php
			}
		}
?>
				</td>
			</tr>
		</table>
<hr>
		<table class="formtable" border="0" width="100%">
			<tr>
				<td width="20%">&nbsp;</td>
				<td width="40%">
					<input type="submit" value="Submit"></td>
				<td width="2%">&nbsp;</td>
                <td width="38%">&nbsp;</td>
            </tr>
        </table>
    </div>
</form>
<hr>

<!-- Output -->
<h3>Command Flow</h3>

<?php
	// prepare and execute command
	if ($send_command) {
		if (isset($command)) {
			$command_post = [];
			$command_post_print = "";
			$command_get = "";
			$command_output = "";
			$command_error = false;
			
			// replace any tags with ID or VIN, if command is for a specific vehicle / energy site
			if (!empty($command->TAG)) {
				if (!empty($vid)) {
					if (!empty($vin)) {
						// vehicle
						$uri = str_replace($command->TAG, "$vin", $uri);
						$baseblecmd = str_replace($command->TAG, "$vin", $apidata->baseblecmd);
						LOGDEB("testqueries: vehicle ID: ".$vid.", VIN: ".$vin);
					} else {
						// energy site or vehicle, but no vin in mapping table
						$uri = str_replace($command->TAG, "$vid", $uri);
						LOGDEB("testqueries: energy site or vehicle, but no VIN found, using ID: ".$vid.", VIN: ".$vin);
					}
				} else {
					$command_output =  $command_output."Parameter 'VID' is missing! The ID of the vehicle.\n";
					LOGERR("Parameter \"VID\" missing");
					$command_error = true;
				}

				if (isset($command->PARAM)) {
					foreach ($command->PARAM as $param => $param_desc) {
						$value = $_REQUEST["$param"];
						$optional = strpos($blecmd, "[".$param."]");
						if (!empty($value) || $optional) {
							$command_post += array("$param" => $value);
							// optional parameters with empty value are skipped
							if (!$optional || ($value != "")) {
								$command_post_print .= ", $param: ".$value;
								$command_get = $command_get."&$param=".$value;
							} 
							// replace param with value for BLECMD - params that are required are in curly brackets, params that are optional are in square brackets
							if ($optional) {
								$blecmd = str_replace("[".$param."]", $value, $blecmd);
							} else {
								$blecmd = str_replace("{".$param."}", $value, $blecmd);
							}
						} else {
							$commandoutput = $commandoutput."Parameter '$param' missing! $param_desc\n";
							LOGERR("Parameter '$param' missing");
							$command_error = true;
						}
					}
				}
				if (!$command_error) {
					// select API - either owner's api or vehicle command via ble 
					if (getApiProtocol($vin) == OWNERS_API || empty($blecmd)) {
						LOGDEB("testqueries: sending command via Internet (Owner's API) ... ");
						if (empty($vin)) {
							$commandoutput = tesla_query( $vid, $action, $command_post, $force );
							LOGOK("testqueries: using ID: $vid, action: $action ".$command_post_print.($force ? ", force: $force" : ""));
						} else {
							$commandoutput = tesla_query( $vin, $action, $command_post, $force );
							LOGOK("testqueries: using VIN: $vin (ID: $vid), action: $action ".$command_post_print.($force ? ", force: $force" : ""));
						}
					} else {
						LOGDEB("testqueries: sending command via BLE ... ");
						if (empty($vin)) {
							$commandoutput = tesla_ble_query( $vid, $action, $baseblecmd, $blecmd, $apidata->ble_retries, $apidata->lock_timeout, $force );
							LOGOK("testqueries: using ID: $vid, action: $action, basecmd: $baseblecmd, command: $blecmd".($force ? ", force: $force" : ""));
						} else {
							$commandoutput = tesla_ble_query( $vin, $action, $baseblecmd, $blecmd, $apidata->ble_retries, $apidata->lock_timeout, $force );
							LOGOK("testqueries: using VIN: $vin (ID: $vid), action: $action, basecmd: $baseblecmd, command: $blecmd".($force ? ", force: $force" : ""));
						}
					}
				}

			} else {
				// general command - owner's api only
				LOGOK("testqueries: action: $action");
				//[x] removed $vid
				$commandoutput = tesla_query( "", $action, $command_post );
			}
		} else {
			$commandoutput =  "Command not found\n";
			LOGERR("testqueries: Command not found");
		}

		// display URLs, command(s), and response
		$lburi = "?action=".$action.$command_get;

		if (!empty($command->TAG)) {
			if (!empty($vin)) {
				$lburi = strtolower($lburi)."&vin=".$vin; 
			} else {
				$lburi = strtolower($lburi."&vid=".$type); 
			}
		}
		if ($force){ $lburi = $lburi."&force=true"; }
		if (isset($command->URI)){ echo "HTTP GET Request to TeslaConnect Plugin on Loxberry:<br><span class=\"mono\">".strtolower($lbbaseurl.$lbzeurl).$lburi."</span><br>"; }

		if (isset($commandoutput)) {
			if (!$command_error) {
				if (in_array(getApiProtocol($vin), $command->API)) {
					echo "<br>Translated by TeslaConnect Plugin on Loxberry and send using <span class=\"mono\">";
					if (getApiProtocol($vin) == OWNERS_API || empty($blecmd)) {
						if (isset($command->URI)) { 
							echo $apinames[OWNERS_API]."</span> as TLS GET Request with Bearer-Token to:<br><span class=\"mono\">".BASEURL.$uri."</span><br>"; 
						}
						if (!empty($command_post)) { 
							echo "  Parameter: <span class=\"mono\">".json_encode($command_post)."</span><br>"; 
						}
					} else {
						echo "Vehicle Command API locally via BLE</span> :<br>";
						if ($force) {
							$blefullcmd = str_replace(COMMAND_TAG, $commands->{"BODY_CONTROLLER_STATE"}->BLECMD, $baseblecmd);
							echo "Get status: <span class=\"mono\">".$blefullcmd."</span><br>";
							$blefullcmd = str_replace(COMMAND_TAG, $commands->{"BLE_WAKE"}->BLECMD, $baseblecmd);
							echo "If asleep: <span class=\"mono\">".$blefullcmd."</span><br>";
						}
						$blefullcmd = str_replace(COMMAND_TAG, $blecmd, $baseblecmd);
						echo "<span class=\"mono\">".$blefullcmd."</span><br>";
					}
				}
?>
	<h4>Response:</h4>
	<div class="mono">
		<!--<p>###<?php var_dump(json_decode($commandoutput));?>###</p>-->
		<p><?php echo pretty_print($commandoutput);?></p>
	</div>
	<b>Note:</b> Status information that is coded as an enum may be retrieved either as an enum string or a number to make it easier for the Loxone Miniserver to process the response. See e.g. enums ClosureState_E, VehicleLockState_E, and VehicleSleepStatus_E
	in <a href="https://github.com/teslamotors/vehicle-command/blob/05bc5dd8d0649b4ccb45a765b9127d06f1050a6f/pkg/protocol/protobuf/vcsec.proto#L228 for the meaning of the numbers." target=”_blank”>vehicle-command/pkg/protocol/protobuf/vcsec.proto#L228</a> for the meaning of these numbers.

	<hr>
	
<?php
			} else {
?>
	<h4>Error:</h4>
	<span style="color:red;"><p><?php echo $commandoutput;?></p></span>

	<hr>
	
<?php
			}
		}
	}
// if($tokenvalid == "false")
}

LBWeb::lbfooter();
LOGINF("testqueries.php: ==================== end of testqueries.php ==================== ");

?>