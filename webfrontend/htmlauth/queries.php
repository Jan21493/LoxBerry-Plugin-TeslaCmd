<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "tesla_inc.php";
require_once "defines.php";

$navbar[2]['active'] = True;

// Print LoxBerry header
$L = LBSystem::readlanguage("language.ini");
LBWeb::lbheader($template_title, $helplink, $helptemplate);

//Checktoken
$tokenvalid = tesla_checktoken();
$tokenparts = explode(".", $login->bearer_token);
$tokenexpires = json_decode(base64_decode($tokenparts[1]))->exp;
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
    read_vehicle_mapping($vmap, $custom_baseblecmd);
    vehicles_add_api_attribute($vehicles, $vmap);
?>

<div class="wide">Queries</div>
<p>
    <i><span class="mono">&lt;user&gt;:&lt;pass&gt;</span>must be replaced with your <b>LoxBerry's</b> username and password.</i>
</p>
<h2>General queries</h2>

<?php
    foreach ($commands as $command => $attribute) {
        if ($attribute->TYPE == "GET") {
            // General commands: TYPE is "GET" and specific tag is NOT included in URL, there are no general commands for BLE
            // Same commands for cars/vehicles and energy sites
            if (empty($attribute->TAG)) {				
?>

<div style="display:flex; align-items: center; justify-content: center;">
    <div style="flex: 0 0 95%;padding:5px" data-role="fieldcontain">
        <label for="summarylink">
            <strong><?=strtolower($command)?></strong>
        </label>
        <input
            type="text"
            id="summarylink"
            name="summarylink"
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
            $vid = strval($vehicle->energy_site_id);
            $vehicle_tag = "&vid=$vid";
            $info = $vehicle->site_name. " (VID: " . $vid . ")";
            $tag = "{energy_site_id}";
            $api = OWNERS_API;
        } else {
            $vid = strval($vehicle->id);
            $vin = strval($vehicle->vin);
            $state = $vehicle->state;
            $vehicle_tag = "&vid=$vid";                
            $info = $vehicle->display_name. " (VID: " . $vid . ", VIN: ".$vin . ")";
            $tag = "{vehicle_tag}";
            $api = $vehicle->api;
        }
?>

<h2>Queries for <?=$info."\n"; ?></h2>

<p>This vehicle is using the <span class="mono"><i><?=$apinames[$api]?></i></span> to retrieve and send information.
<?php
        if ($api == BLE_PLUS_OWNERS_API)
            echo ' All commands that are send locally via BLE are tagged with the bluetooth symbol. <img src="./images/Bluetooth.svg" alt="BLE" height="15" ></img>';
?>
</p><p><b>NOTE: The list of available commands <u>and parameters</u> is different for each API! See below for more information about each parameter.</b></p>

<h3>Get informations</h3>

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
	    if (($attribute->TYPE == "GET") && in_array($vehicle->api, $attribute->API) && $tag == $attribute->TAG) {
?>

<div style="display:flex; align-items: center; justify-content: center;">
    <div style="flex: 0 0 95%; padding:5px;" data-role="fieldcontain">
        <label for="summarylink">
            <strong><?=strtolower($command)?></strong>
<?php
            if (!empty($attribute->BLECMD)) {   
                echo '<img src="./images/Bluetooth.svg" alt="BLE" height="15" ></img>';
            }
?>
        </label>
        <input
            type="text"
            id="summarylink"
            name="summarylink"
            data-mini="true"
            value="<?=$lbzeurl."?action=".strtolower($command).$vehicle_tag; ?>"
            readonly="readonly">
        <p style="margin:4px 0px;"><span class="hint"><?= "$attribute->DESC" ?></span></p>
    </div>
</div>

<?php
			}
		}
        if (isset($vehicle->vin)) {
?>

<h3>Send commands</h3>

<?php
			foreach ($commands as $command => $attribute) {
                // Send commands: TYPE is "POST" AND it is a vehicle AND command is supported in selected API of vehicle AND tag is defined for command
				if (($attribute->TYPE == "POST") && isset($vehicle->vin) && in_array($vehicle->api, $attribute->API) && !empty($attribute->TAG)) {
				    $command_get = "";
					if (isset($commands->{strtoupper($command)}->PARAM)) {
						foreach ($commands->{strtoupper($command)}->PARAM as $param => $param_desc) {
							$command_get .= "&$param=<value>";
						}
					}
?>

<div style="display:flex; align-items: center; justify-content: center;">
    <div style="flex: 0 0 95%;padding:5px" data-role="fieldcontain">
        <label for="summarylink">
            <strong><?=strtolower($command)?></strong>
<?php
            if (!empty($attribute->BLECMD)) {   
                echo '<img src="./images/Bluetooth.svg" alt="BLE" height="15" ></img>';
            }
?>
        </label>
        <input
            type="text"
            id="summarylink"
            name="summarylink"
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
		}
    // foreach vehicles
	} 
}
LBWeb::lbfooter();
?>