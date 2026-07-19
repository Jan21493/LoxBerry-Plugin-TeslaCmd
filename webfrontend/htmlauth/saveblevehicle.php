<?php

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";

$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging - saveblevehicle.php");

LOGINF("saveblevehicle.php: -------------------- start of saveblevehicle.php -------------------- ");

require_once "defines.php";
require_once "tesla_inc.php";

header('Content-Type: application/json');

$localName = "";
$vin = "";
if (!empty($_REQUEST["local_name"])) {
	$localName = trim($_REQUEST["local_name"]);
}
if (!empty($_REQUEST["vin"])) {
	$vin = strtoupper(trim($_REQUEST["vin"]));
}

if (empty($localName) || !isVIN($vin)) {
	http_response_code(400);
	echo json_encode(array(
		'status' => 400,
		'success' => 0,
		'message' => 'local_name or VIN is invalid.'
	));
	LOGERR("saveblevehicle.php: invalid local_name or VIN.");
	exit;
}

$vehicles = read_local_ble_vehicles();
$entry = new stdClass();
$entry->local_name = $localName;
$entry->vin = $vin;
$entry->display_name = "BLE ".$localName;
if (isset($_REQUEST["rssi"]) && is_numeric($_REQUEST["rssi"])) {
	$entry->rssi = (int)$_REQUEST["rssi"];
}
$entry->state = "local via BLE";
$entry->last_seen = date("Y-m-d H:i:s");

$vehicles->{$vin} = $entry;
write_local_ble_vehicles($vehicles);

http_response_code(200);
echo json_encode(array(
	'status' => 200,
	'success' => 1,
	'message' => 'BLE mapping saved.'
));

LOGINF("saveblevehicle.php: ==================== end of saveblevehicle.php ==================== ");

?>
