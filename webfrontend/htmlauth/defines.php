<?php
define ("BASEURL", "https://owner-api.teslamotors.com/");
define ("LOGINFILE", "$lbpconfigdir/sessiondata.json");
// to be deleted in final version - define ("FAKEFILE", "$lbpconfigdir/fakedata.json");
define ("APIFILE", "$lbpconfigdir/apidata.json");
define ("COMMANDFILE", "$lbpconfigdir/tesla_commands.json");
define ("MQTTTOPIC", "${lbpplugindir}");

// Template
$template_title = "TeslaConnect " . LBSystem::pluginversion();
$helplink = "https://wiki.loxberry.de/plugins/teslacmd/start";

// Command URI
$lbzeurl ="http://&lt;user&gt;:&lt;pass&gt;@".LBSystem::get_localip()."/admin/plugins/".LBPPLUGINDIR."/teslacmd.php";

const OWNERS_API = 0;
const BLE_PLUS_OWNERS_API = 1;
$default_baseblecmd = "tesla-control -ble -vin {vehicle_tag} -key-name {vehicle_tag} {command}";

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