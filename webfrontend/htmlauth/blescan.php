<?php

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";

$log = LBLog::newLog( [ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1] );
LOGSTART("Start Logging - blescan.php");

LOGINF("blescan.php: -------------------- start of blescan.php -------------------- ");

require_once "defines.php";
require_once "tesla_inc.php";

header('Content-Type: application/json');

list($result_code, $output, $scanResults) = tesla_ble_scan();

if ($result_code == 0 && is_array($scanResults)) {
	$mappedVehicles = read_local_ble_vehicles();
	$return = array(
		'status' => 200,
		'success' => 1,
		'scanResults' => array()
	);

	foreach ($scanResults as $scanResult) {
		$mappedVin = "";
		foreach ($mappedVehicles as $mappedVehicle) {
			if (isset($mappedVehicle->local_name) && ($mappedVehicle->local_name == $scanResult->localName)) {
				$mappedVin = $mappedVehicle->vin;
				break;
			}
		}
		$return['scanResults'][] = array(
			'localName' => $scanResult->localName,
			'rssi' => isset($scanResult->rssi) ? $scanResult->rssi : null,
			'distance' => get_ble_scan_distance(isset($scanResult->rssi) ? $scanResult->rssi : null),
			'mappedVin' => $mappedVin
		);
	}
	http_response_code(200);
} else {
	$return = array(
		'status' => 500,
		'success' => 0,
		'message' => trim(implode("\n", $output))
	);
	http_response_code(500);
}

echo json_encode($return);

LOGINF("blescan.php: ==================== end of blescan.php ==================== ");

?>
