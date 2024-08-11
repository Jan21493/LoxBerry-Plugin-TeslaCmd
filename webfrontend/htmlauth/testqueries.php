<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "tesla_inc.php";
require_once "defines.php";

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
    read_vehicle_mapping($vmap, $custom_baseblecmd);
    vehicles_add_api_attribute($vehicles, $vmap);
	if (isset($vid) && isset($vehicles->{"$vid"})) {
		$selected_vehicle = $vehicles->{"$vid"};
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
			if (isset($custom_baseblecmd))
				$blebasecmd = $custom_baseblecmd;
			else
				$blebasecmd = $default_baseblecmd;
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
			$tag = strval($vehicle->energy_site_id);
			$name = $vehicle->site_name;
            $info = "VID: ".$tag;
		} else {
			$tag = strval($vehicle->id);
			$name = $vehicle->display_name;
			$info = "VID: ".$tag.", VIN: ".strval($vehicle->vin).", using <span class=\"mono\">".$apinames[$vehicle->api]."</span>";
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
				if (in_array($selected_vehicle->api, $attribute->API) && !empty($attribute->TAG) && ($tag == $attribute->TAG)) {
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
			<td width="1%">&nbsp;</td>
			<td>
				<p class="hint"><?=$command->DESC;?></p>
			</td>
		</tr>
		<tr>
			<td></td>
			<td>
<?php
		// wake up is available for vehicles only and not if a wake up command is selected
		if ($type == $vid && isset($selected_vehicle->vin) && strpos($command->URI, 'wake') === false && strpos($command->BLECMD, 'wake') === false) {
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
					LOGDEB("teslaqueries: vid: ".$vid);

					if (isset($selected_vehicle->vin)) {
						// vehicle
						$uri = str_replace($command->TAG, "$selected_vehicle->vin", $uri);
						$blebasecmd = str_replace($command->TAG, "$selected_vehicle->vin", $blebasecmd);
					} else {
						// energy site or vehicle, but no vin in mapping table
						$uri = str_replace($command->TAG, "$vid", $uri);
					}
				} else {
					$command_output =  $command_output."Parameter 'VID' is missing! The ID of the vehicle.\n";
					LOGINF("Parameter \"VID\" missing");
					$command_error = true;
				}

				if (isset($command->PARAM)) {
					foreach ($command->PARAM as $param => $param_desc) {
						
						if (!empty($_REQUEST["$param"])) {
							LOGDEB("teslaqueries: $param: ".$_REQUEST["$param"]);
							$command_post += array("$param" => $_REQUEST["$param"]);
							$command_post_print = $command_post_print.", $param: ".$_REQUEST["$param"];
							$command_get = $command_get."&$param=".$_REQUEST["$param"];
							// replace param with value for BLECMD
							$blecmd = str_replace("{".$param."}", $_REQUEST["$param"], $blecmd);
						} else {
							$commandoutput = $commandoutput."Parameter \"$param\" missing! $param_desc\n";
							LOGINF("Parameter \"$param\" missing");
							$command_error = true;
						}
					}
				}

				if (!$command_error) {
					// select API - either owner's api or vehicle command via ble 
					if ($selected_vehicle->api == 0 || empty($blecmd)) {
						$commandoutput = tesla_query( $vid, $action, $command_post, $force );
						LOGOK("teslaqueries: vid: $vid, action: $action ".$command_post_print.($force ? ", force: $force" : ""));
					} else {
						$commandoutput = tesla_ble_query( $vid, $action, $blebasecmd, $blecmd, $force );
						LOGOK("teslaqueries: vid: $vid, action: $action, basecmd: $blebasecmd, command: $blecmd".($force ? ", force: $force" : ""));
					}
				}

			} else {
				// general command - owner's api only
				LOGOK("teslaqueries: action: $action");
				//[x] removed $vid
				$commandoutput = tesla_query( "", $action, $command_post );
			}
		} else {
			$commandoutput =  "Command not found\n";
			LOGERR("teslaqueries: Command not found");
		}

		// display URLs, command(s), and response
		$lburi = "?action=".$action.$command_get;

		if (!empty($command->TAG)) { $lburi = $lburi."&vid=$type"; }
		if ($force){ $lburi = $lburi."&force=true"; }
		if (isset($command->URI)){ echo "HTTP GET Request to TeslaConnect Plugin on Loxberry:<br><span class=\"mono\">".strtolower($lbzeurl.$lburi)."</span><br>"; }

		if (isset($commandoutput)) {
			if (!$command_error) {
				if (in_array($selected_vehicle->api, $command->API)) {
					echo "<br>Translated by TeslaConnect Plugin on Loxberry and send using <span class=\"mono\">";
					if ($selected_vehicle->api == 0 || empty($blecmd)) {
						if (isset($command->URI)) { 
							echo $apinames[OWNERS_API]."</span> as TLS GET Request with Bearer-Token to:<br><span class=\"mono\">".BASEURL.$uri."</span><br>"; 
						}
						if (!empty($command_post)) { 
							echo "  Parameter: <span class=\"mono\">".json_encode($command_post)."</span><br>"; 
						}
					} else {
						echo "Vehicle Command API locally via BLE</span> :<br>";
						if ($force) {
							$blefullcmd = str_replace("{command}", $commands->{"BODY_CONTROLLER_STATE"}->BLECMD, $blebasecmd);
							echo "Get status: <span class=\"mono\">".$blefullcmd."</span><br>";
							$blefullcmd = str_replace("{command}", $commands->{"BLE_WAKE_UP"}->BLECMD, $blebasecmd);
							echo "If asleep: <span class=\"mono\">".$blefullcmd."</span><br>";
						}
						$blefullcmd = str_replace("{command}", $blecmd, $blebasecmd);
						echo "<span class=\"mono\">".$blefullcmd."</span><br>";
					}
				}
?>
<h4>Response:</h4>
<div class="mono">
	<p><?php echo pretty_print($commandoutput);?></p>
</div>
<b>Note:</b> Some status information that is retrieved as a string (name for a constant) is translated to a number to make it easier for the Loxone Miniserver to process the response. See enums ClosureState_E, VehicleLockState_E, and VehicleSleepStatus_E
in <a href="https://github.com/teslamotors/vehicle-command/blob/05bc5dd8d0649b4ccb45a765b9127d06f1050a6f/pkg/protocol/protobuf/vcsec.proto" target=”_blank”>vehicle-command/pkg/protocol/protobuf/vcsec.proto</a> for the meaning of these numbers.

<hr>
	
<?php
			}
		}
	}
// if($tokenvalid == "false")
}

LBWeb::lbfooter();
?>