<?php
// TODO: Create pages
// [x] Statuspage
// [x] Querypage
// [x] Testpage

require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "tesla_inc.php";
require_once "defines.php";

$navbar[1]['active'] = True;

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
    .ui-table td {
        vertical-align: middle;
    }
</style>

<!-- Status -->
<div class="wide">Status</div>

<?php
if($tokenvalid == "true") {
?>

<p style="color:green">
    <b>You are logged in, token is valid until
        <?=date("Y-m-d H:i:s", $tokenexpires)?>
        (<a href="?delete_token">delete token</a>).</b>
</p><br>

<?php
} else {
?>

<p style="color:red">
    <b>You are not logged in.</b>
</p><br>

<?php
}

if (isset($_GET['delete_token'])) {
	delete_token();
	echo "<script> location.href='index.php'; </script>";
} else if(isset($_POST["setlogintoken"])) {
    setlogintoken($_POST["access_token"], $_POST["refresh_token"]);
    echo "<script> location.href='index.php'; </script>";
} else if(isset($_POST["setAPI"])) {
    // Save API settings for each car
    $vmap = array();
    foreach ($_POST as $index => $entry) {
        if ($index == "baseblecmd") {
            $custom_baseblecmd = $entry;
        } elseif ($index == "ble_repeat")  {
            $ble_repeat = $entry;
        } elseif ($index != "dummy")  {
            $pieces = explode("-", $entry);
            if ($pieces[0] != "") {
                $vmap{strval($pieces[0])}->vin = $pieces[1];
                $vmap{strval($pieces[0])}->api = (int)$pieces[2];
            }
        }
    }
    write_vehicle_mapping($vmap, $custom_baseblecmd, $ble_repeat);
    echo "<script> location.href='index.php'; </script>";
} else if(isset($_POST["login"])) {
	$output = json_decode(login($_POST["weburl"], $_POST["code_verifier"], $_POST["code_challenge"], $_POST["state"]));

	if($output->success == 0) {
		echo "<br><br>".$output->message."<br><br>";
		echo "Try again. <a href=index.php>Click here</a> to login.<br><br>";
	} else {
		echo "Login successful. <a href=index.php>Click here to continue</a>.";
        echo "<script> location.href='index.php'; </script>";
	}
}

if($tokenvalid == "false") {
		// $challenge = gen_challenge();
		// $code_verifier = $challenge["code_verifier"];
		// $code_challenge = $challenge["code_challenge"];
		// $state = $challenge["state"];
		// $timestamp = time();
?>
<div class="wide">Login</div>

<p>Enter the Access Token & Refresh Token:<br><br>

You can use one of the following apps or browser extention to generate an Access Token & Refresh Token from the Tesla server.
<li><a href="https://chrome.google.com/webstore/detail/tesla-access-token-genera/kokkedfblmfbngojkeaepekpidghjgag" target="_blank">Access Token Generator for Tesla (Chrome Web Store)</a> / <a href="https://github.com/DoctorMcKay/chromium-tesla-token-generator" target="_blank">GitHub</a></li>
<li><a href="https://microsoftedge.microsoft.com/addons/detail/tesla-access-token-genera/mjpplpkadjdmedpklcioagjgaflfphbo" target="_blank">Access Token Generator for Tesla (Microsoft Edge-Add-ons)</a> / <a href="https://github.com/DoctorMcKay/chromium-tesla-token-generator" target="_blank">GitHub</a></li>
<li><a href="https://play.google.com/store/apps/details?id=net.leveugle.teslatokens" target="_blank">Tesla Tokens (Android)</a></li>
<li><a href="https://apps.apple.com/us/app/auth-app-for-tesla/id1552058613#?platform=iphone" target="_blank">Auth app for Tesla (iOS)</a></li>
</p>

<form method="post">
    <input type="hidden" name="setlogintoken" value="">
    <label for="access_token">Access Token:</label>
    <textarea id="access_token" name="access_token" required="required"></textarea>
    <label for="refresh_token">Refresh Token:</label>
    <textarea id="refresh_token" name="refresh_token" required="required"></textarea>
    <input type="submit" value="Save Tokens">
</form>
<br>

<?php
/* Disabled, because of login errors


<form method="post">
    <input type="hidden" name="login" value="">
    <input type="hidden" name="code_verifier" value="<?php echo $code_verifier; ?>">
    <input
        type="hidden"
        name="code_challenge"
        value="<?php echo $code_challenge; ?>">
    <input type="hidden" name="state" value="<?php echo $state; ?>">
    <p>Please follow the steps below to log in:</p>

    Step 1: Please
    <strong>
        <a href="#<?php echo $timestamp; ?>" onclick="teslaLogin();return false();">click here</a>
    </strong>
    to log in to Tesla (A popup window will open, please allow popups).<br>
    Step 2: Please enter your Tesla login data on the Tesla website.<br>
    Step 3: If the login was successful, you will receive a
    <strong>Page not found</strong>
    information on the Tesla website. Copy the complete web address (e.g.
    <strong><?php echo $tesla_api_redirect; ?>?code=.....&state=...&issuer=....</strong>)<br>
    Step 4: Paste the copied web address here and press the
    <strong>Get Token</strong>-Button:<br>
    <input type="text" name="weburl" size="100" required="required"><input type="submit" value="Get Token">
</form>
<br>
<script>
    function teslaLogin() {
        teslaLogin = window.open(
            "<?php echo gen_url($code_challenge, $state);?>",
            "TeslaLogin",
            "width=600,height=400,status=yes,scrollbars=yes,resizable=yes"
        );
        teslaLogin.focus();
    }
</script>
*/
?>
<?php
	}
?>

<!-- API -->
<?php
if($tokenvalid == "true") {
?>    
<div class="wide">API</div>
<p>Tesla has introduced a new <a href="https://github.com/teslamotors/vehicle-command/blob/main/README.md" target=”_blank”>Tesla Vehicle Command SDK</a> in October 2023 
as a successor of the <a href="https://tesla-api.timdorr.com/">(inofficial) Owner's API</a>. 
Pre-2021 model S and X vehicles do not support this new protocol, but all other models will be shifted to the new protocol in 2024.</p> 

<p>The Tesla Vehicle Command SDK includes the <a href="https://github.com/teslamotors/vehicle-command/tree/main/cmd/tesla-control#tesla-control-utility" target=”_blank”>Tesla Control Utility</a>,
a command-line interface for sending commands to Tesla vehicles either via Bluetooth Low Energy (BLE) or over the Internet (OAuth token required!).</p>

<p><b>NOTE:</b> To use the Tesla Control Utility via BLE you have to install the tool, set up local keys and authorize the new key by tapping your Tesla NFC card on the center console in your car. 
See <a href="https://github.com/teslamotors/vehicle-command/tree/main/cmd/tesla-control#tesla-control-utility" target=”_blank”>README.md</a> for details.</p>


<p style="color:green">
    <b>Select the API for each of your cars.</b>
</p>
<form method="post">
    <input type="hidden" name="setAPI" value="">

<?php
	$vehicles = tesla_summary();
    read_vehicle_mapping($vmap, $custom_baseblecmd, $ble_repeat);
    vehicles_add_api_attribute($vehicles, $vmap);
?>
    <table>
        <colgroup>
            <col span="1" style="width: 30%;">
            <col span="1" style="width: 10%;">
            <col span="1" style="width: 10%;">
            <col span="1" style="width: 10%;">
            <col span="1" style="width: 50%;">
        </colgroup>
        <tr>
            <td>
                <label for="summarylink"><strong>Default BLE command</strong><br>
                <span class="hint">Local Tesla control command. {vehicle_tag} tag will be replaced by VIN. {command} will be replaced with specific Tesla control command and params.</span></label>
            </td>
            <td colspan=4>
                <input
                    type="text"
                    id="dummy"
                    name="dummy"
                    data-mini="true"
                    value="<?=$default_baseblecmd; ?>"
                    readonly="readonly">
            </td>
        </tr>
        <tr>
            <td>
                <label for="summarylink"><strong>Custom BLE command</strong><br>
                <span class="hint">Overwrite default BLE command or leave empty for default. Only single quotes allowed (no double quotes).</span></label>
            </td>
            <td colspan=4>
                <input
                    type="text"
                    id="baseblecmd"
                    name="baseblecmd"
                    data-mini="true"
                    value="<?=$custom_baseblecmd; ?>">
            </td>
        </tr>
        <tr>
            <td>
                <label for="summarylink"><strong>Repeat BLE command</strong><br>
                <span class="hint">Repeat BLE commands in case an error is returned to increase reliability. Note: it may take longer to execute the command.</span></label>
            </td>
            <td><input type="radio" id="rep0" name="ble_repeat" data-mini="true" value="0" <?php if ($ble_repeat == "0") echo "checked"; ?>/><label for="rep0">No repeat</label></td>
            <td><input type="radio" id="rep1" name="ble_repeat" data-mini="true" value="1" <?php if ($ble_repeat == "1") echo "checked"; ?>/><label for="rep1">Repeat once</label></td>
            <td><input type="radio" id="rep2" name="ble_repeat" data-mini="true" value="2" <?php if ($ble_repeat == "2") echo "checked"; ?>/><label for="rep2">Repeat twice</label></td>
        </tr>
    </table>            

    <div class="form-group">
	<table data-role="table" data-mode="columntoggle" data-filter="true" data-input="#filterTable-input" class="ui-body-d ui-shadow table-stripe ui-responsive" data-column-btn-text="Show columns">
		<thead>
		<tr class="ui-bar-d">
            <th data-priority="1">ID</th>
            <th data-priority="5">Access Type</th>
			<th data-priority="2">VIN</th>
			<th data-priority="4">Name</th>
			<th data-priority="4">State</th>
			<th data-priority="1">API for control commands</th>
		</tr>
		</thead>
		<tbody>

<?php
     // foreach vehicle
	foreach ($vehicles as $index => &$vehicle) {
        // only cars are shown, no energy sites
		if (!isset($vehicle->energy_site_id)) {
?>
			<tr>
                <td><?php echo $vehicle->id; ?></td>
				<td><?php echo $vehicle->access_type; ?></td>
				<td><?php echo $vehicle->vin; ?></td>
				<td><?php echo $vehicle->display_name; ?></td>
				<td><?php echo $vehicle->state; ?></td>
				<td>
                    <select name="api-<?=$index?>" >
<?php
	// foreach api name
	foreach ($apinames as $key => $apiname) {
?>
        <option value="<?=$vehicle->id."-".$vehicle->vin."-".$key;?>" <?php if($key == $vehicle->api){ echo " selected"; }?>>
            <?=$apiname;?>
        </option>
<?php
        // for each api name
		}
?>
                </td>
			</tr>

<?php
        // energy site
        } else {
?>    
			<tr>
                <td><?php echo $vehicle->energy_site_id; ?></td>
				<td><?php echo $vehicle->access_type; ?></td>
				<td>No VIN</td>
				<td><?php echo $vehicle->site_name; ?></td>
				<td>No state</td>
				<td><?php echo $apinames[OWNERS_API]; ?></td>
            </tr>
<?php
        }

    // foreach vehicle
	}
?>

		</tbody>
		</table>
    <input type="submit" value="Save API settings">
    </div>
</form>
<hr>

<?php
	}
?>


<!-- MQTT -->
<div class="wide">MQTT</div>
<p>All data is transferred via MQTT. The subscription for this is
    <span class="mono"><?=MQTTTOPIC?>/#</span>
    and is automatically registered in the MQTT gateway plugin.</p>

<?php
	// Query MQTT Settings
	$mqttcred = mqtt_connectiondetails();
	if ( !isset($mqttcred) ) {
?>

<p style="color:red">
    <b>MQTT gateway not installed!</b>
</p>

<?php
	} else {		
?>

<p style="color:green">
    <b>MQTT gateway found and it will be used.</b>
</p>

<?php
	}
	
LBWeb::lbfooter();
?>