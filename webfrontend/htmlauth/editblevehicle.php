<?php

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";

$log = LBLog::newLog([ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1 ]);
LOGSTART("Start Logging - editblevehicle.php");

LOGINF("editblevehicle.php: -------------------- start of editblevehicle.php -------------------- ");

require_once "defines.php";
require_once "tesla_inc.php";

header('Content-Type: application/json');

$oldVin = "";
$newVin = "";
$displayName = "";

if (!empty($_REQUEST["old_vin"])) {
	$oldVin = strtoupper(trim($_REQUEST["old_vin"]));
}
if (!empty($_REQUEST["vin"])) {
	$newVin = strtoupper(trim($_REQUEST["vin"]));
}
if (isset($_REQUEST["display_name"])) {
	$displayName = trim($_REQUEST["display_name"]);
}

if (!isVIN($oldVin) || !isVIN($newVin)) {
	http_response_code(400);
	echo json_encode(array(
		'status' => 400,
		'success' => 0,
		'message' => 'old_vin or VIN is invalid.'
	));
	LOGERR("editblevehicle.php: invalid old_vin or VIN.");
	exit;
}

$vehicles = read_local_ble_vehicles();
if (!isset($vehicles->{$oldVin})) {
	http_response_code(404);
	echo json_encode(array(
		'status' => 404,
		'success' => 0,
		'message' => 'BLE mapping not found.'
	));
	LOGERR("editblevehicle.php: mapping for old_vin not found.");
	exit;
}

$entry = $vehicles->{$oldVin};
$entry->vin = $newVin;
if (!empty($displayName)) {
	$entry->display_name = $displayName;
} elseif (empty($entry->display_name) && !empty($entry->local_name)) {
	$entry->display_name = "BLE " . $entry->local_name;
}
$entry->discovered = date("Y-m-d H:i:s");

if ($oldVin !== $newVin) {
	unset($vehicles->{$oldVin});
}
$vehicles->{$newVin} = $entry;

write_local_ble_vehicles($vehicles);

http_response_code(200);
echo json_encode(array(
	'status' => 200,
	'success' => 1,
	'message' => 'BLE mapping updated.'
));

LOGINF("editblevehicle.php: ==================== end of editblevehicle.php ==================== ");

?>
