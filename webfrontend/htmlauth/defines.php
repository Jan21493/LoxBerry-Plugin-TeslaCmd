<?php
define ("BASEURL", "https://owner-api.teslamotors.com/");
define ("LOGINFILE", "$lbpconfigdir/sessiondata.json");
// to be deleted in final version - define ("FAKEFILE", "$lbpconfigdir/fakedata.json");
define ("APIFILE", "$lbpconfigdir/apidata.json");
define ("COMMANDFILE", "$lbpconfigdir/tesla_commands.json");
define ("MQTTTOPIC", "${lbpplugindir}");
define ("PRIVATEKEYSUFFIX", "-private.pem");
define ("PUBLICKEYSUFFIX", "-public.pem");
define ("KEYPAIRPATH", "$lbpconfigdir");
define ("VEHICLE_TAG", "{vehicle_tag}");

// Template
$template_title = "Tesla Command " . LBSystem::pluginversion();
$helplink = "https://wiki.loxberry.de/plugins/teslacmd/start";

// Command URI
$lbbaseurl = "http://&lt;user&gt;:&lt;pass&gt;@".LBSystem::get_localip();

$lbzeurl = "/admin/plugins/".LBPPLUGINDIR."/send.php";

const OWNERS_API = 0;
const BLE_PLUS_OWNERS_API = 1;
//$default_baseblecmd = $lbpbindir."/tesla-control -ble -vin {vehicle_tag} -key-name {vehicle_tag} {command}";
//$default_baseblecmd = "tesla-control -ble -vin {vehicle_tag} -key-name {vehicle_tag} {command}";
$privateKeyWithPath = KEYPAIRPATH."/".VEHICLE_TAG.PRIVATEKEYSUFFIX;
$publicKeyWithPath = KEYPAIRPATH."/".VEHICLE_TAG.PUBLICKEYSUFFIX;
$default_baseblecmd = "tesla-control -ble -vin ".VEHICLE_TAG." -key-file ".$privateKeyWithPath." {command}";

// command to generate a key pair
$keygencmd = "tesla-keygen -f -key-file ".$privateKeyWithPath." create > ".$publicKeyWithPath;

// parameters to send keys to car (add-key-request {public_key} {role} {form_factor})
$sendkeyscmd = "add-key-request ".$publicKeyWithPath." owner cloud_key";

// verify keys in car (list-keys)
$verifykeyscmd = "list-keys";

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