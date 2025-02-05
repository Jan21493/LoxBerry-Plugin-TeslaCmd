<?php

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";
require_once "loxberry_web.php";

$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging - queries.php");

LOGINF("queries.php: -------------------- start of queries.php -------------------- ");

require_once "defines.php";
require_once "tesla_inc.php";

$navbar[2]['active'] = True;

// Print LoxBerry header
$L = LBSystem::readlanguage("language.ini");
LBWeb::lbheader($template_title, $helplink, $helptemplate);

//Checktoken
$tokenvalid = tesla_checktoken();
$tokenparts = explode(".", $login->bearer_token);
$tokenexpires = json_decode(base64_decode($tokenparts[1]))->exp;

$useVIN = true;

// obsolete, done on client side
if(!empty($_POST["useVIN"])) { 
    $useVIN = $_POST["useVIN"];
} 
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
if($tokenvalid == "false") {
?>

<!-- Status -->
<div class="wide">Status</div>
<p style="color:red">
    <b>You are not logged in.</b>
</p><br>

<?php
} else {
?>

<!-- Queries -->
<?php
	$vehicles = tesla_summary();
    $apidata = read_api_data();
?>

<div class="wide">Function Blocks in Loxone Config</div>
<p>
    This page lists all http commands that can be issued from the Loxone Miniserver. These commands are either forwarded by this plugin to Tesla via https over the Internet or send locally to your car via BLE. 
</p>
<form>
    <div style="display: flex;"> 
        <div style="flex: 10%; align-content: center;"><input type="checkbox" data-role="flipswitch" name="useVIN" id="useVIN" data-on-text="VIN" data-off-text="VID" data-wrapper-class="custom-label-flipswitch" <?php if ($useVIN) echo 'checked=""'; ?>></div> 
        <div style="flex: 1%; align-content: center;"></div> 
        <div style="flex: 89%; align-content: center;"><label for="useVIN"><p>Either the vehicle's VID or VIN can be used for all commands (following Tesla's API's). Using the VIN avoids retrieving it via the Owner' API, because the VIN is required for all commands via BLE.</p>
            <b>NOTE:</b> In previous versions of the plugin, only the VID was allowed.</label></div> 
    </div> 
</form>

<script>

$( document ).on( "change", "#useVIN",  function( event, ui ) {
    var id = this.id,
        value = this.checked;
    console.log(id + " has been changed to " + value);
    
    <!-- javascript code to enter values depending on VID / VIN -->
    if (value) {
<?php
        foreach ($vehicles as $vehicle) {
            if(isset($vehicle->energy_site_id)) {
                $vid = strval($vehicle->energy_site_id);
                $vehicle_tag = "&vid=$vid";
                $tag = ENERGY_SITE_ID;
                $api = OWNERS_API;
            } else {
                $vid = strval($vehicle->id);
                $vin = strval($vehicle->vin);
                $state = $vehicle->state;
                $vehicle_tag = "&vin=$vin";
                $tag = VEHICLE_TAG;
                $api = getApiProtocol($vin);
            }
            foreach ($commands as $command => $attribute) {
                // Get informations: TYPE is "GET" AND command is supported in selected API of vehicle (API for energy sites is always 0) AND
                // tag is matching type of vehicle (car or energy site)
                if (in_array($api, $attribute->API) && ($tag == $attribute->TAG)) {
                    $command_get = "";
                    if (isset($commands->{strtoupper($command)}->PARAM)) {
                        foreach ($commands->{strtoupper($command)}->PARAM as $param => $param_desc) {
                            $command_get .= "&$param=<value>";
                        }
                    }
                    echo "inputF = document.getElementById(\"summarylink-".$vid."-".$command."\");\n";
                    echo "inputF.value = \"".htmlspecialchars_decode($lbzeurl)."?action=".strtolower($command).$vehicle_tag.$command_get."\"\n";
                }
            }
        }
?>
    } else {
<?php
        foreach ($vehicles as $vehicle) {
            if(isset($vehicle->energy_site_id)) {
                $vid = strval($vehicle->energy_site_id);
                $vehicle_tag = "&vid=$vid";
                $tag = "{energy_site_id}";
                $api = OWNERS_API;
            } else {
                $vid = strval($vehicle->id);
                $vin = strval($vehicle->vin);
                $state = $vehicle->state;
                $vehicle_tag = "&vid=$vid";                
                $tag = "{vehicle_tag}";
                $api = getApiProtocol($vin);
            }
            foreach ($commands as $command => $attribute) {
                // Get informations: TYPE is "GET" AND command is supported in selected API of vehicle (API for energy sites is always 0) AND
                // tag is matching type of vehicle (car or energy site)
                if (in_array($api, $attribute->API) && $tag == $attribute->TAG) {
                    $command_get = "";
                    if (isset($commands->{strtoupper($command)}->PARAM)) {
                        foreach ($commands->{strtoupper($command)}->PARAM as $param => $param_desc) {
                            $command_get .= "&$param=<value>";
                        }
                    }
                    echo "inputF = document.getElementById(\"summarylink-".$vid."-".$command."\");\n";
                    echo "inputF.value = \"".htmlspecialchars_decode($lbzeurl)."?action=".strtolower($command).$vehicle_tag.$command_get."\"\n";
                }
            }
        }
?>
    }
} );

</script>

<h2>Virtual Output</h2>

<p style="margin:4px 0px;">Create a virtual output function block in Loxone config as a connector for this Loxberry plugin.
        <i><span class="mono">&lt;user&gt;:&lt;pass&gt;</span></i> must be replaced with the <b>LoxBerry's</b> username and password.
</p>
<div style="display:flex; align-items: center; justify-content: center;">
    <div style="flex: 0 0 95%;padding:5px" data-role="fieldcontain">
        <label for="virtualOutput">
            <strong>Connector to Tesla Command Plugin</strong>
        </label>
        <input
            type="text"
            id="virtualOutput"
            name="virtualOutput"
            data-mini="true"
            value="<?=$lbbaseurl ?>"
            readonly="readonly">
        <p style="margin:4px 0px;"><span class="hint">Tick <i><span class="mono">'Close connection after send'</span></i> in the settings of the function block.</span></p>
    </div>
</div>

<h2>Virtual Output Commands</h2>

<p>
    The following list contain all virtual output command function blocks that are available for this plugin. 
    Enter the value from the input into the parameter <i><span class="mono">'Command for ON'</span></i> and tick <i><span class="mono">'use as digital output'</span></i>. 
</p>


<h3>General queries</h3>

<?php
    foreach ($commands as $command => $attribute) {
        if ($attribute->TYPE == "GET") {
            // General commands: TYPE is "GET" and specific tag is NOT included in URL, there are no general commands for BLE
            // Same commands for cars/vehicles and energy sites
            if (empty($attribute->TAG)) {				
?>

<div style="display:flex; align-items: center; justify-content: center;">
    <div style="flex: 0 0 95%;padding:5px" data-role="fieldcontain">
        <label for="summarylink-<?php echo $command; ?>">
            <strong><?=strtolower($command)?></strong>
        </label>
        <input
            type="text"
            id="summarylink-<?php echo $command; ?>"
            name="summarylink-<?php echo $command; ?>"
            data-mini="true"
            value="<?=$lbzeurl."?action=".strtolower($command); ?>"
            readonly="readonly">
        <p style="margin:4px 0px;"><span class="hint"><?= "$attribute->DESC" ?></span></p>
    </div>
</div>

<?php
			}
		}
	}
?>

<hr>

<?php
    // list all available commands for each vehicle/car and energy site
    foreach ($vehicles as $vehicle) {
        if(isset($vehicle->energy_site_id)) {
            $type = "energy site";
            $vid = strval($vehicle->energy_site_id);
            $vehicle_tag = "&vid=$vid";
            $info = "Energy Site ".$vehicle->site_name. " (ID: " . $vid . ")";
            $tag = ENERGY_SITE_ID;
            $api = OWNERS_API;
            if (($vehicle->resource_type == "solar") && ($vehicle->solar_type == "pv_panel")) {
                $info .= ", Solar PV-Panel";
            } else if (($vehicle->resource_type == "battery") && ($vehicle->battery_type == "ac_powerwall")){
                $info .= ", AC Powerwall";
            }
        } else {
            $type = "vehicle";
            $vid = strval($vehicle->id);
            $vin = strval($vehicle->vin);
            $state = $vehicle->state;
            if ($useVIN)
                $vehicle_tag = "&vin=$vin";
            else
                $vehicle_tag = "&vid=$vid";                
            $info = $vehicle->display_name. " (VID: " . $vid . ", VIN: ".$vin . ")";
            $tag = VEHICLE_TAG;
            $api = getApiProtocol($vin);
        }
?>

<h3>Queries for <?=$info."\n"; ?></h3>

<p>This <?php echo $type;?> is using the <span class="mono"><i><?=$apinames[$api]?></i></span> to retrieve and send information.
<?php
        if ($api == BLE_PLUS_OWNERS_API)
            echo ' All commands that are send locally via BLE are tagged with the bluetooth symbol. <img src="./images/Bluetooth.svg" alt="BLE" height="15" ></img>';
?>
</p><p><b>NOTE: The list of available commands <u>and parameters</u> is different for each API! See below for more information about each parameter.</b></p>

<h4>Get informations</h4>

<?php
        if (isset($vehicle->vin)) {
?>

<p>
    <i>If you add the parameter <span class="mono">&force=true</span>, the vehicle will be woken up 
    if the request is not possible. Currently the vehicle is <span class="mono"><?=$state?></span>.</i>
</p>

<?php
}
    foreach ($commands as $command => $attribute) {
        // Get informations: TYPE is "GET" AND command is supported in selected API of vehicle (API for energy sites is always 0) AND
        // tag is matching type of vehicle (car or energy site)
	    if (($attribute->TYPE == "GET") && in_array($api, $attribute->API) && $tag == $attribute->TAG) {
            $command_get = "";
            if (isset($commands->{strtoupper($command)}->PARAM)) {
                foreach ($commands->{strtoupper($command)}->PARAM as $param => $param_desc) {
                    $command_get .= "&$param=<value>";
                }
            }
?>

<div style="display:flex; align-items: center; justify-content: center;">
    <div style="flex: 0 0 95%; padding:5px;" data-role="fieldcontain">
        <label for="summarylink-<?php echo $vid."-".$command; ?>">
            <strong><?=strtolower($command)?></strong>
<?php
            if (!empty($attribute->BLECMD)) {   
                echo '<img src="./images/Bluetooth.svg" alt="BLE" height="15" ></img>';
            }
?>
        </label>
        <input
            type="text"
            id="summarylink-<?php echo $vid."-".$command; ?>"
            name="summarylink-<?php echo $vid."-".$command; ?>"
            data-mini="true"
            value="<?=$lbzeurl."?action=".strtolower($command).$vehicle_tag.$command_get; ?>"
            readonly="readonly">
        <p style="margin:4px 0px;"><span class="hint"><?= "$attribute->DESC" ?></span></p>
<?php
            if(isset($attribute->PARAM)) {
                echo '<table class="formtable" border="0">';
    			foreach ($attribute->PARAM as $param => $param_desc) {
                    echo '<tr><td><span class="hint"><i>'.$param.'</i>: </span></td><td><span class="hint">'.$param_desc.'</span></td></tr>';
                }
                echo '</table>';
            }
?>
    </div>
</div>

<?php
			}
		}
?>

<h4>Send commands</h4>

<?php
			foreach ($commands as $command => $attribute) {
                // Send commands: TYPE is "POST" AND it is a vehicle AND command is supported in selected API of vehicle AND tag is defined for command
				if (($attribute->TYPE == "POST") && in_array($api, $attribute->API) && $tag == $attribute->TAG) {
				    $command_get = "";
					if (isset($commands->{strtoupper($command)}->PARAM)) {
						foreach ($commands->{strtoupper($command)}->PARAM as $param => $param_desc) {
							$command_get .= "&$param=<value>";
						}
					}
?>

<div style="display:flex; align-items: center; justify-content: center;">
    <div style="flex: 0 0 95%;padding:5px" data-role="fieldcontain">
        <label for="summarylink-<?php echo $vid."-".$command; ?>">
            <strong><?=strtolower($command)?></strong>
<?php
            if (!empty($attribute->BLECMD)) {   
                echo '<img src="./images/Bluetooth.svg" alt="BLE" height="15" ></img>';
            }
?>
        </label>
        <input
            type="text"
            id="summarylink-<?php echo $vid."-".$command; ?>"
            name="summarylink-<?php echo $vid."-".$command; ?>"
            data-mini="true"
            value="<?=$lbzeurl."?action=".strtolower($command).$vehicle_tag.$command_get; ?>"
            readonly="readonly">
        <p style="margin:4px 0px;"><span class="hint"><?= "$attribute->DESC" ?></span></p>
<?php
            if(isset($attribute->PARAM)) {
                echo '<table class="formtable" border="0">';
    			foreach ($attribute->PARAM as $param => $param_desc) {
                    echo '<tr><td><span class="hint"><i>'.$param.'</i>: </span></td><td><span class="hint">'.$param_desc.'</span></td></tr>';
                }
                echo '</table>';
            }
?>
        </div>
</div>

<?php
				}
			}
    // foreach vehicles
	} 
?>
<h2>Testing</h2>

<p style="margin:4px 0px;">To verify that one of the http commands that are send from the Loxone MS to the Tesla Command plugin is working in general, you may use standard utilities like
    'curl' (transferring data from or to a server using URLs) and 'jq' (JSON processor) and send them from your PC instead.</p>

<p>curl -s '<?php echo strtolower($lbbaseurl.$lbzeurl)."?action=body_controller_state&vin=".$vin; ?>' | jq</p>

<p><b>NOTE:</b> <i><span class="mono">&lt;user&gt;:&lt;pass&gt;</span></i> must be replaced with the <b>LoxBerry's</b> username and password.
</p>

<?php 
// token is valid
}
LBWeb::lbfooter();

LOGINF("queries.php: ==================== end of queries.php ==================== ");

?>