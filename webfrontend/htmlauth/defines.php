<?php
define('BASEURL',           "https://owner-api.teslamotors.com/"); 
define('LOGINFILE',         "$lbpconfigdir/sessiondata.json"); 
// to be deleted in final version - define('FAKEFILE', "$lbpconfigdir/fakedata.json"); 
define('APIFILE',           "$lbpconfigdir/apidata.json"); 
define('COMMANDFILE',       "$lbpconfigdir/tesla_commands.json"); 
define('MQTTTOPIC',         "${lbpplugindir}"); 
define('PRIVATEKEYSUFFIX',  "-private.pem"); 
define('PUBLICKEYSUFFIX',   "-public.pem"); 
define('KEYPAIRPATH',       "$lbpconfigdir"); 
define('VEHICLE_TAG',       "{vehicle_tag}"); 
define('ENERGY_SITE_ID',    "{energy_site_id}"); 
define('COMMAND_TAG',       "{command}"); 
define('COMMAND_TIMEOUT',   "-command-timeout "); 
define('CONNECT_TIMEOUT',   "-connect-timeout "); 
define('DEBUG_OPTION',      "-debug"); 

define('PRIVATE_KEY_WITH_PATH', KEYPAIRPATH."/".VEHICLE_TAG.PRIVATEKEYSUFFIX);
define('PUBLIC_KEY_WITH_PATH', KEYPAIRPATH."/".VEHICLE_TAG.PUBLICKEYSUFFIX);

define('TESLA_CONTROL', "tesla-control -ble ");
define('LIST_KEYS', "list-keys");
define('BODYCONTROLLERSTATE', "body-controller-state");
define('WAKE', "wake");

//define('$default_baseblecmd', "tesla-control -ble -vin ".VEHICLE_TAG." -key-file ".$privateKeyWithPath." {command}"); 
define('TESLA_CONTROL_CMD', TESLA_CONTROL."-vin ".VEHICLE_TAG." -key-file ".PRIVATE_KEY_WITH_PATH);

// command to generate a key pair
//define('$keygencmd', "tesla-keygen -f -key-file ".$privateKeyWithPath." create > ".$publicKeyWithPath);
define('TESLA_KEYGEN_CMD', "tesla-keygen -f -key-file ".PRIVATE_KEY_WITH_PATH." create > ".PUBLIC_KEY_WITH_PATH);

// parameters to send keys to car (add-key-request {public_key} {role} {form_factor})
//define('$sendkeyscmd', "add-key-request ".$publicKeyWithPath." owner cloud_key"); 
define('ADD_KEY_REQUEST', "add-key-request ".PUBLIC_KEY_WITH_PATH." owner cloud_key");

// verify keys in car
//define('$verifykeyscmd', "list-keys"); 

// get state from car
//define('$getstateandrssi', "body-controller-state"); 

// Template
$template_title = "Tesla Command " . LBSystem::pluginversion();
$helplink = "https://wiki.loxberry.de/plugins/teslacmd/start";

// Command URI
$lbbaseurl = "http://&lt;user&gt;:&lt;pass&gt;@".LBSystem::get_localip();

$lbzeurl = "/admin/plugins/".LBPPLUGINDIR."/send.php";

const OWNERS_API = 0;
const BLE_PLUS_OWNERS_API = 1;

const UNKNOWN_KEY = 0;
const PUBLIC_KEY = 1;
const PRIVATE_KEY = 2;

$keyTypeNames = array();
$keyTypeNames[UNKNOWN_KEY] = "unknown key";
$keyTypeNames[PUBLIC_KEY] = "public key";
$keyTypeNames[PRIVATE_KEY] = "private key";

$apinames = array();
$apinames[OWNERS_API] = "(inofficial) Owner's API";
$apinames[BLE_PLUS_OWNERS_API] = "Owner's plus Vehicle Command API via BLE";

// The Navigation Bar
$navbar[1]['Name'] = "Settings";
$navbar[1]['URL'] = 'index.php';
$navbar[2]['Name'] = "Queries";
$navbar[2]['URL'] = 'queries.php';
$navbar[3]['Name'] = "Test queries";
$navbar[3]['URL'] = 'testqueries.php';
$navbar[99]['Name'] = "Logfiles";
$navbar[99]['URL'] = '/admin/system/logmanager.cgi?package='.LBPPLUGINDIR;
$navbar[99]['target'] = '_blank';

?>