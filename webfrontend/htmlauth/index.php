<?php
// TODO: Create pages
// [x] Statuspage
// [x] Querypage
// [x] Testpage

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";
require_once "loxberry_web.php";

$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging - index.php");

LOGINF("index.php: -------------------- start of index.php -------------------- ");

require_once "defines.php";
require_once "tesla_inc.php";

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
    #custom-border-radius .ui-btn-icon-notext.ui-corner-all {
    -webkit-border-radius: .3125em;
    border-radius: .3125em;
    }
    .redbutton {
        background: #e60000 ;
        color: #ffffff !important;
        font-size: 1rem !important;
        font-weight: normal !important;
        font-style: normal !important;
        font-variant: normal !important;
        border-style: none !important;
        margin: 0.2rem 0.1rem 0.2rem 0.1rem !important;
        border-radius: 5px !important;
        padding: 0.5rem !important;
        text-transform: none !important;
        text-decoration: none !important;
        line-height: 1 !important;
        -webkit-font-smoothing: antialiased;
    }
    .bluebutton {
        background: #0000ff ;
        color: #ffffff !important;
        font-size: 1rem !important;
        font-weight: normal !important;
        font-style: normal !important;
        font-variant: normal !important;
        border-style: none !important;
        margin: 0.2rem 0.1rem 0.2rem 0.1rem !important;
        border-radius: 5px !important;
        padding: 0.5rem !important;
        text-transform: none !important;
        text-decoration: none !important;
        line-height: 1 !important;
        -webkit-font-smoothing: antialiased;
    }

</style>

<!-- Popup: get State via BLE -->
<div data-role="popup" id="popupGetState" data-dismissible="true" style="max-width:400px;">
    <div style="padding: 20px 20px; text-align: left;">
        <h3 class="ui-title">Get state and RSSI</h3>
        <p>Get state information and RSSI via BLE for the car with VIN <b><span id="popupGetStateID"></span></b>
        Please wait ...</p>
        <a href="#" id="btngetstate" class="ui-btn ui-corner-all ui-shadow ui-btn-icon-left ui-icon-alert" data-transition="flow">Stop</a>
    </div>
</div>


<!-- Popup: Delete Keys -->
<div data-role="popup" id="popupDeleteKeys" data-dismissible="true" style="max-width:500px;">
    <div style="padding: 20px 20px; text-align: left;">
        <h3 class="ui-title">Delete key pair</h3>
        <p>Delete the private and public key pair for the car with VIN <b><span id="popupDeleteKeysID"></span></b> on your Loxberry?</p>
        <p><b>NOTE:</b> You have to delete the associated public key in your car manually.</p>
        <p>1. On the touchscreen of your car, touch <b>Controls > Locks</b>.</p>
        <p>2. In the key list, find the key that you would like to delete and touch its associated trash icon. Use the swipe gesture to scroll down the list.</p>
        <p>3. When prompted, scan an authenticated key on the card reader located between the cup holder and the arm rest to confirm the deletion. When complete, the key list no longer includes the deleted key.</p>
        <p>The keys can be recreated at any time, but you have to install the public key in your car again.</p>
        <a href="#" id="btndeletekeys" class="ui-btn ui-corner-all ui-shadow ui-btn-icon-left ui-icon-delete" data-transition="flow">Delete</a>
    </div>
</div>

<!-- Popup: Create Keypair -->
<div data-role="popup" id="popupCreateKeys" data-dismissible="true" style="max-width:600px;" data-theme="a" class="ui-corner-all">
    <div style="padding: 20px 20px; text-align: left;">
        <h3 class="ui-title">Create key pair</h3>
        <p>Create a private and public key pair for the car with VIN <b><span id="popupCreateKeysID"></span></b> on your Loxberry?</p>
        <p>A key pair is mandatory to send commands to control the vehicle. The private key is used to sign messages to authenticate them to your car. 
            The public key needs to be installed in your car to verify the signed messages. </p>
            <p><b>IMPORTANT:</b> Keep the private key that is stored on the Loxberry secret! Anybody who has
              access to the private key can open your car and may drive away.</p>
            <p><b>NOTE:</b> You have to install the public key in your car afterwards. You may initiate the process by clicking on the blue car icon.</p>
            <form>
            <!--
            <label for="addKeysID"><TMPL_VAR KEYS.LABEL_KEYSID></label>
            <input type="text" name="addkeysid" id="addkeysid" value="" placeholder="<TMPL_VAR KEYS.LABEL_KEYSID>" data-theme="a">
            -->
            <a href="javascript:createKeys();" id="btncreatekeys" class="ui-btn ui-corner-all ui-shadow ui-btn-icon-left ui-icon-plus"
            data-transition="flow">Create</a>
            <div class="hint" id="add_hint">&nbsp;</div>
        </form>
    </div>
</div>

<!-- Popup: Install Public Key in Car -->
<div data-role="popup" id="popupInstallKeys" data-dismissible="true" style="max-width:800px;" data-theme="a" class="ui-corner-all">
    <div style="padding: 20px 20px; text-align: left;">
        <h3 class="ui-title">Install Public Key in Car</h3>
        <p>Install a public key in your car with VIN <b><span id="popupInstallKeysID"></span></b>?</p>
        <p><b>NOTE:</b> The public key in your car is used to verify signed messages and important to be able to send control commands that require authentication via BLE to your car.</p>
        <p>1. Click on the <b>Start</b> button to start the process. </p>
        <!-- <p style="color:red"><b>NOTE:</b> The car needs to be awake when the process is started!</p> -->
                <p>2. When the message <b>Tap NFC card!</b> is displayed here, you only have 30 seconds to finish step 3. </p>
        <p>3. Tap one of your two NFC key cards on the card reader located between the cup holder and the arm rest to authorize this process. 
           There is NO message on the touchscreen display in the car for this process until the step 3. was done.</p>
            <div style="text-align: center;"> 
                <img src="./images/authorize-action.png" height="300"></img>
            </div>
        <p>4. If there was NO error in step 3. you should see a popup message on the touchscreen requesting a new phone key pairing. The message needs to be confirmed.
            Technically the message is not correct, because it's not a 'phone' key, but a BLE key. When complete, the key list contains a new key and you should see a 
            message on your Tesla app that a new key has been added to your car.</p>
        <p>5. When the 30 seconds for step 3 have expired, you will see the <b>Verify</b> button here. Click the button to start the verification process,
              which will retrieve the key list from the car and verify that the new public key was found in the list.</p>
        <p><b>NOTE:</b> It is recommended to rename the key to be able to distinguish your keys. Touch <b>Controls > Locks</b> on the touchscreen of your car.
              In the key list, find the right key that you would like to rename and touch its associated pen icon. Use the swipe gesture to scroll down the list.</p>
        <p>If you like to revoke this key from your car at a later time, do the same as described in the note to rename the key and touch its associated trash icon in the last step.</p>
        <a href="#" id="btnInstallKeys" class="ui-btn ui-corner-all ui-shadow ui-btn-icon-left ui-icon-tag" data-transition="flow"><span id="btnInstallKeysName"></span></a>
        <div style="text-align: center; width:100%">
            <h4 class="ui-title"><span id="installKeysMessage"></span></h4>
        </div>
    </div>
</div>

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
    // Save API settings
    $apidata = new stdClass();
    $apidata->command_timeout = 0;
    $apidata->connect_timeout = 0;
    $apidata->tesla_debug = 0;
    $apidata->ble_retries = 1;
    foreach ($_POST as $index => $entry) {
        if ($index == "command_timeout")  {
            $apidata->command_timeout = $entry;
        } elseif ($index == "connect_timeout")  {
            $apidata->connect_timeout = $entry;
        } elseif ($index == "tesla_debug")  {
            $apidata->tesla_debug = (int)($entry == "on");
        } elseif ($index == "ble_retries")  {
            $apidata->ble_retries = $entry;
        } 
    }
    write_api_data($apidata);
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
<div class="wide">API selection</div>
<p>Tesla has introduced a new <a href="https://github.com/teslamotors/vehicle-command/blob/main/README.md" target=”_blank”>Tesla Vehicle Command SDK</a> in October 2023 
as a successor of the <a href="https://tesla-api.timdorr.com/">(inofficial) Owner's API</a>. 
Pre-2021 model S and X vehicles do not support this new protocol, but all other models will be shifted to the new protocol in 2024.</p> 

<p style="color:green">
    <b>The following devices are found in your Tesla account and requested via <?php echo $apinames[OWNERS_API]; ?>.</b>
</p>

<?php
	$vehicles = tesla_summary();
    $apidata = read_api_data();
?>

<div class="form-group">
	<table data-role="table" data-mode="columntoggle" data-filter="true" data-input="#filterTable-input" class="ui-body-d ui-shadow table-stripe ui-responsive" data-column-btn-text="Show columns">
		<thead>
		<tr class="ui-bar-d">
            <th data-priority="1">ID</th>
            <th data-priority="5">Access Type</th>
			<th data-priority="2">VIN</th>
			<th data-priority="2">Model</th>
			<th data-priority="2">Model Year</th>
			<th data-priority="4">Name</th>
			<th data-priority="4">State</th>
			<th data-priority="1">API</th>
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
				<td><?php echo getModelFromVIN($vehicle->vin); ?></td>
				<td><?php echo getYearFromVIN($vehicle->vin); ?></td>
				<td><?php echo $vehicle->display_name; ?></td>
				<td><?php echo $vehicle->state; ?></td>
				<td><?php echo $apinames[getApiProtocol($vehicle->vin)]; ?></td>
			</tr>

<?php
        // energy site
        } else {
?>    
			<tr>
                <td><?php echo $vehicle->energy_site_id; ?></td>
				<td><?php echo $vehicle->access_type; ?></td>
				<td>-</td>
				<td>-</td>
				<td>-</td>
				<td><?php echo $vehicle->site_name; ?></td>
				<td>-</td>
				<td><?php echo $apinames[OWNERS_API]; ?></td>
            </tr>
<?php
        }

    // foreach vehicle
	}
?>

		</tbody>
		</table>
    </div>

<?php
	}
?>
<br><br>
<!-- Vehicle Command API -->
<div class="wide">Vehicle Command API settings </div>

<p>The Tesla Vehicle Command SDK includes the <a href="https://github.com/teslamotors/vehicle-command/tree/main/cmd/tesla-control#tesla-control-utility" target=”_blank”>Tesla Control Utility</a>,
a command-line interface for sending commands to Tesla vehicles either via Bluetooth Low Energy (BLE) or over the Internet (OAuth token required!).</p>

<p><b>NOTE:</b> This plugin is using that utility to send commands to the vehicle via BLE. See <a href="https://wiki.loxberry.de/plugins/teslacmd/start#installation" target=”_blank”>important notes</a> for details.</p>

<p style="color:green">
    <b>You may modify the parameters, and define how many times the command will be retried if execution has failed.</b>
</p>
<form method="post">
    <input type="hidden" name="setAPI" value="">
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
                <label for="command_timeout"><strong>Command timeout</strong><br>
                <span class="hint">Set the timeout for commands sent to the vehicle via BLE in seconds. (default 5s).</span></label>
            </td>
            <td colspan=4>
                <input
                    type="number"
                    min="1" max="120"
                    id="command_timeout"
                    name="command_timeout"
                    data-mini="true"
                    value="<?=$apidata->command_timeout; ?>">
            </td>
        </tr>
        <tr>
            <td>
                <label for="connect_timeout"><strong>Connect timeout</strong><br>
                <span class="hint">Set the timeout for establishing initial connection via BLE with the vehicle in seconds. (default 20s).</span></label>
            </td>
            <td colspan=4>
                <input
                type="number"
                    min="1" max="240"
                    id="connect_timeout"
                    name="connect_timeout"
                    data-mini="true"
                    value="<?=$apidata->connect_timeout; ?>">
            </td>
        </tr>
        <tr>
            <td>
                <label for="tesla_debug"><strong>Command debug</strong><br>
                <span class="hint">Enable debugging for tesla commands to get detailled information during troubleshooting.</span></label>
            </td>
            <td colspan=4>
            <input type="checkbox" data-role="flipswitch" name="tesla_debug" id="tesla_debug" data-on-text="On" data-off-text="Off" data-wrapper-class="custom-label-flipswitch" <?php if ($apidata->tesla_debug == 1) echo 'checked=""'; ?>
            </td>
        </tr>
        <tr>
            <td>
                <strong>Retry BLE command</strong><br>
                <span class="hint">Define how often the BLE command is retried in case an error is returned to increase reliability. Note: it may take longer to execute the command.</span>
            </td>
            <td><input type="radio" id="ble_retries0" name="ble_retries" data-mini="true" value="0" <?php if ($apidata->ble_retries == "0") echo "checked"; ?>/><label for="ble_retries0">No retry</label></td>
            <td><input type="radio" id="ble_retries1" name="ble_retries" data-mini="true" value="1" <?php if ($apidata->ble_retries == "1") echo "checked"; ?>/><label for="ble_retries1">Retry once</label></td>
            <td><input type="radio" id="ble_retries2" name="ble_retries" data-mini="true" value="2" <?php if ($apidata->ble_retries == "2") echo "checked"; ?>/><label for="ble_retries2">Retry twice</label></td>
        </tr>
    </table>   
    <input type="submit" value="Save Vehicle Command API settings">
</form>
<br>

<script>

// Get state from car via BLE popup (Question)
function getState( vin ) {
	$("#popupGetStateID").html(vin);
	$("#btngetstate").attr("href", "javascript:getStateStop('" + vin + "');");
	$("#popupGetState").popup("open");
    $.ajax( { 
        url: "./getstate.php",
        method: "POST",
        data: { ajax: 'getstate', keysID: vin },
        success: function(response) {
            var data = $.parseJSON(response);
            if (data.status == 200) {
                element = "#txt-"+data.vin+"-sleepStatus";
                console.log( "getstate .success", element )
                $(element).html(data.sleepStatus);
                element = "#txt-"+data.vin+"-rssi";
                $(element).html(data.rssi);
                if (data.rssi == 0) {
                    $(element).html("<div><span style=\"color:red\">not available!</span></div>");
                } else if (data.rssi > -50) {
                    $(element).html("<div>"+data.rssi+"&nbsp;<span style=\"color:darkgreen\">(very strong)</span></div>");
                } else if (data.rssi > -67) {
                    $(element).html("<div>"+data.rssi+"&nbsp;<span style=\"color:green\">(strong)</span></div>");
                } else if (data.rssi > -80) {
                    $(element).html("<div>"+data.rssi+"&nbsp;<span style=\"color:gold\">(medium)</span></div>");
                } else if (data.rssi > -90) {
                    $(element).html("<div>"+data.rssi+"&nbsp;<span style=\"color:orange\">(weak)</span></div>");
                } else {
                    $(element).html("<div>"+data.rssi+"&nbsp;<span style=\"color:red\">(very weak)</span></div>");
                }
            }
            console.log( "getstate .success end" )
        }
	} )
	.fail(function( data ) {
        element = "#txt-"+vin+"-sleepStatus";
        $(element).html('unavailable');
        element = "#txt-"+vin+"-rssi";
        console.log( "getstate .fail", data.responseText )
		console.log( "getstate .fail", vin );
	})
	.done(function( data ) {
		console.log( "getstate .done: ", vin );
        //location.replace(location.href);
	})
	.always(function( data ) {
        console.log( "getstate .always" );
		$("#popupGetState").popup("close");
		console.log( "getstate Finished" );
	});
}

// Delete key pair
function getStateStop( vin ) {
    $("#popupGetState").popup("close");
    console.log( "getState Stopped" );
}

// Delete key pair popup (Question)
function askDeleteKeys( vin ) {
	$("#popupDeleteKeysID").html(vin);
	$("#btndeletekeys").attr("href", "javascript:deleteKeys('" + vin + "');");
	$("#popupDeleteKeys").popup("open");
}

// Delete key pair
function deleteKeys( vin ) {
	$.ajax( { 
        url: "./deletekeys.php",
        method: "POST",
        data: { ajax: 'deletekeys', keysID: vin }
	} )
	.fail(function( vin ) {
		console.log( "deletekeys Fail", vin );
	})
	.done(function( data ) {
		console.log( "deletekeys Success: ", vin );
        location.replace(location.href);
	})
	.always(function( vin ) {
		$("#popupDeleteKeys").popup("close");
		console.log( "deletekeys Finished" );
	});
}

// Create key pair popup (Question)
function askCreateKeys( vin ) {
	$("#popupCreateKeysID").html(vin);
	$("#btncreatekeys").attr("href", "javascript:createKeys('" + vin + "');");
	$("#popupCreateKeys").popup("open");
}

// Create key pair on Loxberry for vin
function createKeys( vin ) {
	$.ajax( { 
        url: "./createkeys.php",
        method: "POST",
        data: { ajax: 'createkeys', keysID: vin }
    } )
	.fail(function( vin ) {
		console.log( "createkeys Fail", vin );
	})
	.done(function( data ) {
		console.log( "createkeys Success: ", vin );
        location.replace(location.href);
	})
	.always(function( vin ) {
		$("#popupCreateKeys").popup("close");
		console.log( "createkeys Finished" );
	});
}

// Ask if keys should be installed in car (Question)
function askInstallKeys( vin ) {
	$("#popupInstallKeysID").html(vin);
    $("#btnInstallKeysName").html("Start");
    $("#installKeysMessage").html("");
	$("#btnInstallKeys").attr("href", "javascript:installKeysStep1('" + vin + "');");
	$("#popupInstallKeys").popup("open");
}

var timer;
var step1success;
var step2success;

// Install keys step 1 - Send keys to car
function installKeysStep1( vin ) {
    step1success = 0;
    $("#btnInstallKeysName").html("Waiting...");
	$.ajax( { 
        url: "./sendkeytocar.php",
        method: "POST",
        data: { ajax: 'installKeysStep1', keysID: vin },
        success: function(response) {
            var data = $.parseJSON(response);
            if (data.success == 1) {
                step1success = 1;
                $("#btnInstallKeysName").html(data.message);
                $("#installKeysMessage").html("SUCCESS: keys were sent to vehicle.");
                $("#btnInstallKeys").attr("href", "javascript:installKeysStep2('" + vin + "');");
                console.log( "installKeysStep1 SUCCESS!");
            }
            if (data.success == 0) {
                step1success = 0;
                $("#btnInstallKeysName").html("Close");
                $("#installKeysMessage").html("ERROR: " + data.message);
                $("#btnInstallKeys").attr("href", "javascript:installKeysStep3('" + vin + "');");
		        console.log( "installKeysStep1 ERROR: " + data.message);
            }
            responseMessage = data.message;
        }
    } )
	.fail(function( data ) {
        $("#btnInstallKeysName").html("Close");
        $("#installKeysMessage").html("ERROR: " + data.message);
        $("#btnInstallKeys").attr("href", "javascript:installKeysStep3('" + vin + "');");
		console.log( "installKeysStep1 Fail", vin );
	})
	.done(function( data ) {
        if (step1success) {
            var count = 30;
            timer = setInterval(function() {
                count--;
                $("#installKeysMessage").html("You have " + count + " seconds left to tap an NFC card.");
                if(count == 0) {
                    clearInterval(timer);
                    $("#btnInstallKeysName").html("Verify");
                    $("#installKeysMessage").html("There is no time left to tap an NFC card anymore.");
                    $("#btnInstallKeys").attr("href", "javascript:installKeysStep2('" + vin + "');");
                    console.log( "installKeysStep1 : timer finished, goto step 2, vin: " + vin );
                } 
            }, 1000);
            $("#btnInstallKeys").attr("href", "javascript:installKeysStep1b('" + vin + "');");
        }
		console.log( "installKeysStep1 done: ", vin );
	})
	.always(function( vin ) {
		console.log( "installKeysStep1 Finished" );
	});
}

function installKeysStep1b( vin ) {
    clearInterval(timer);
    $("#btnInstallKeysName").html("Verify");
    $("#installKeysMessage").html("User has manually stopped the time to tap an NFC card.");
    $("#btnInstallKeys").attr("href", "javascript:installKeysStep2('" + vin + "');");
	console.log( "installKeysStep1b User has stopped: ", vin );
}

// Install keys step 2 - Verify if key was installed
function installKeysStep2( vin ) {
    var responseMessage;
    step2success = 0;

    $("#btnInstallKeysName").html("Waiting...");
    $("#installKeysMessage").html("Getting key list from car to verify if public key was installed.");
	$.ajax( { 
        url: "./verifykeyincar.php",
        method: "POST",
        data: { ajax: 'installKeysStep2', keysID: vin },
        success: function(response) {
            var data = $.parseJSON(response);
            if (data.success == 1) {
                step2success = 1;
                $("#installKeysMessage").html("SUCCESS: " + data.message + "<br>You should rename the key, if not done already.");
                console.log( "installKeysStep2 SUCCESS!");
            }
            if (data.success == 0) {
                step2success = 0;
                $("#installKeysMessage").html("FAILURE: " + data.message);
		        console.log( "installKeysStep2 FAILURE: " + data.message);
            }
            $("#btnInstallKeysName").html("Close");
            $("#btnInstallKeys").attr("href", "javascript:installKeysStep3('" + vin + "');");
            responseMessage = data.message;
        },
        error: function(jqXHR) {
            var data = $.parseJSON(jqXHR.responseText);
            $("#installKeysMessage").html("FAILURE: " + data.message);
            console.log( "installKeysStep2 ERROR! ", data.message + ", status " + data.status);
            responseMessage = data.message;
        }
    } )
	.fail(function( vin ) {
        $("#installKeysMessage").html("ERROR: " + responseMessage);
        console.log( "installKeysStep2 fail ", responseMessage );
        console.log( "installKeysStep2 vin ", vin );
	})
	.done(function( data ) {
		console.log( "installKeysStep2 done: ", data );
	})
	.always(function( vin ) {
        $("#btnInstallKeysName").html("Close");
        $("#btnInstallKeys").attr("href", "javascript:installKeysStep3('" + vin + "');");
		console.log( "installKeysStep2 Finished: " + vin );
	});
}

// Install keys step 3 - Wait to close popup 
function installKeysStep3( vin ) {
    $("#popupInstallKeys").popup("close");
	console.log( "installKeysStep3 popup closed: ", vin );
    //location.replace(location.href);
}

</script>

<p style="color:green">
    <b>The following devices are using the Vehicle Command API.</b>   
</p>
<p>In the first step you have to generate a public/private key pair by clicking on the "+" icon. In the next step the public key 
   needs to be send to the vehicle by clicking on the blue vehicle icon that appears if a keypair has been found for that vehicle.  
</p>
<div class="form-group">
	<table data-role="table" data-mode="columntoggle" data-filter="true" data-input="#filterTable-input" class="ui-body-d ui-shadow table-stripe ui-responsive" data-column-btn-text="Show columns">
        <colgroup>
            <col span="1" style="width: 15%;">
            <col span="1" style="width: 15%;">
            <col span="1" style="width: 15%;">
            <col span="1" style="width: 10%;">
            <col span="1" style="width: 10%;">
            <col span="1" style="width: 10%;">
            <col span="1" style="width: 10%;">
        </colgroup>
    <thead>
		<tr class="ui-bar-d">
            <th data-priority="1">ID</th>
			<th data-priority="2">VIN</th>
			<th data-priority="4">Name</th>
			<th data-priority="4">State via BLE</th>
			<th data-priority="4">RSSI</th>
            <th data-priority="1">Private key</th>
            <th data-priority="1">Key Actions</th>
		</tr>
		</thead>
		<tbody>
<?php
     // foreach vehicle
	foreach ($vehicles as $index => &$vehicle) {
        // only cars are shown, no energy sites
		if (!isset($vehicle->energy_site_id) && getApiProtocol($vehicle->vin)) {
            $rssi = null;
?>
			<tr>
                <td><?php echo $vehicle->id; ?></td>
				<td><?php echo $vehicle->vin; ?></td>
				<td><?php echo $vehicle->display_name; ?></td>
				<td>
                    <a href="javascript:getState('<?php echo $vehicle->vin; ?>')" class="bluebutton pi pi-question-circle ui-link" \
                            data-intkeysid="<?php echo $vehicle->vin; ?>" title="Get state from vehicle via BLE."\
                            id="btngetstate+<?php echo $vehicle->vin; ?>" name="btngetstate+<?php echo $vehicle->vin; ?>"></a>
                    <span id="txt-<?php echo $vehicle->vin; ?>-sleepStatus"></span>
                </td>
                <td>
                    <span id="txt-<?php echo $vehicle->vin; ?>-rssi">-</span>
                </td>
				<td><?php 
                    switch (keyCheck($vehicle->vin, PRIVATE_KEY)) {
                        case 0: 
                            echo "Available";
                            break;
                        case 1: 
                            echo "Not found";
                            break;
                        case 2: 
                            echo "Wrong Format";
                            break;
                        default:
                            echo "Unknown error";
                    }
                    ?>
                </td>
				<td>
                    <?php if (keyCheck($vehicle->vin, PUBLIC_KEY) == 0) { ?>
                        <a href="javascript:askInstallKeys('<?php echo $vehicle->vin; ?>')" class="bluebutton pi pi-car ui-link" \
                            data-intkeysid="<?php echo $vehicle->vin; ?>" title="Install public key in car."\
                            id="btnkeyscreate+<?php echo $vehicle->vin; ?>" name="btnkeyscreate+<?php echo $vehicle->vin; ?>"></a>
                    <?php } ?>
                    <?php if (keyCheck($vehicle->vin, PRIVATE_KEY) == 0) { ?>
                        <a href="javascript:askDeleteKeys('<?php echo $vehicle->vin; ?>')" class="redbutton pi pi-trash ui-link" \
                            data-intkeysid="<?php echo $vehicle->vin; ?>" title="Delete key pair after confirmation."\
                            id="btnkeysdelete+<?php echo $vehicle->vin; ?>" name="btnkeysdelete+<?php echo $vehicle->vin; ?>"></a>
                    <?php } else { ?>
                        <a href="javascript:askCreateKeys('<?php echo $vehicle->vin; ?>')" class="headerbutton pi pi-plus ui-link" \
                            data-intkeysid="<?php echo $vehicle->vin; ?>" title="Create key pair."\
                            id="btnkeyscreate+<?php echo $vehicle->vin; ?>" name="btnkeyscreate+<?php echo $vehicle->vin; ?>"></a>
                    <?php } ?>
          

                </div>
                </td>
			</tr>
<?php
        }

    // foreach vehicle
	}
?>
		</tbody>
	</table>
</div>
<br><br>

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

LOGINF("index.php: ==================== end of index.php ==================== ");

?>