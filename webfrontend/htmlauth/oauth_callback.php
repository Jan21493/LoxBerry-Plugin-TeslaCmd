<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "loxberry_log.php";

$log = LBLog::newLog([ "name" => "TeslaCmd", "stderr" => 1, "addtime" => 1 ]);
LOGSTART("Start Logging - oauth_callback.php");
LOGINF("oauth_callback.php: -------------------- start of oauth_callback.php -------------------- ");

require_once "defines.php";
require_once "tesla_inc.php";

function redirect_to_index($status, $message)
{
    $target = "index.php?".http_build_query(array(
        "oauth_status" => $status,
        "oauth_message" => $message
    ));
    header("Location: ".$target);
    echo '<!DOCTYPE html><html><body><p><a href="'.htmlspecialchars($target).'">Continue</a></p></body></html>';
    exit;
}

$oauthLogin = !empty($_SESSION["teslacmd_oauth"]) && is_array($_SESSION["teslacmd_oauth"]) ? $_SESSION["teslacmd_oauth"] : array();
unset($_SESSION["teslacmd_oauth"]);
session_write_close();

if (!empty($_GET["error"])) {
    $message = !empty($_GET["error_description"]) ? trim($_GET["error_description"]) : "Tesla login failed.";
    LOGINF("oauth_callback.php: Tesla OAuth returned an error.");
    redirect_to_index("error", $message);
}

$code = !empty($_GET["code"]) ? trim($_GET["code"]) : "";
$state = !empty($_GET["state"]) ? trim($_GET["state"]) : "";

if (empty($code) || empty($state)) {
    redirect_to_index("error", "Tesla login did not return an authorization code.");
}

if (empty($oauthLogin["state"]) || empty($oauthLogin["code_verifier"]) || empty($oauthLogin["redirect_uri"])) {
    redirect_to_index("error", "Tesla login session expired. Please start the OAuth login again from the settings page.");
}

if (!hash_equals($oauthLogin["state"], $state)) {
    LOGINF("oauth_callback.php: State validation failed.");
    redirect_to_index("error", "Tesla login could not be verified. Please try again.");
}

if (!empty($oauthLogin["created_at"]) && ((int)$oauthLogin["created_at"] + 900) < time()) {
    redirect_to_index("error", "Tesla login timed out. Please start the OAuth login again.");
}

$result = tesla_exchange_authorization_code($code, $oauthLogin["code_verifier"], $oauthLogin["redirect_uri"]);
if (!$result["success"]) {
    LOGINF("oauth_callback.php: Token exchange failed.");
    redirect_to_index("error", $result["message"]);
}

LOGOK("oauth_callback.php: Tesla login successful.");
redirect_to_index("success", "Tesla tokens were saved successfully.");
