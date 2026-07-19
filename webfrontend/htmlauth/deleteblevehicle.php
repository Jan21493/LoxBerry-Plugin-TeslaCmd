<?php

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";

$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging - deleteblevehicle.php");

LOGINF("deleteblevehicle.php: -------------------- start of deleteblevehicle.php -------------------- ");

require_once "defines.php";
require_once "tesla_inc.php";

header('Content-Type: application/json');

$vin = "";
if (!empty($_REQUEST["vin"])) {
	$vin = strtoupper(trim($_REQUEST["vin"]));
}

if (!isVIN($vin)) {
	http_response_code(400);
	echo json_encode(array(
		'status' => 400,
		'success' => 0,
		'message' => 'VIN is invalid.'
	));
	LOGERR("deleteblevehicle.php: invalid VIN.");
	exit;
}

$vehicles = read_local_ble_vehicles();
if (isset($vehicles->{$vin})) {
	unset($vehicles->{$vin});
	write_local_ble_vehicles($vehicles);
}

http_response_code(200);
echo json_encode(array(
	'status' => 200,
	'success' => 1,
	'message' => 'BLE mapping deleted.'
));

LOGINF("deleteblevehicle.php: ==================== end of deleteblevehicle.php ==================== ");

?>
