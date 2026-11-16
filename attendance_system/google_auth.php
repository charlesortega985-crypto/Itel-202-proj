<?php
// google_auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$client_id = "370645663438-2r5f7hobqfe3p0epmmlpdd3qttphc963.apps.googleusercontent.com";

// Dynamic Redirect URI (awtomatikong kinukuha ang tamang folder sa localhost)
$protocol     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$folder       = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$redirect_uri = $protocol . "://" . $_SERVER['HTTP_HOST'] . $folder . "/google_callback.php";

$params = [
    'client_id'     => $client_id,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
    'access_type'   => 'online',
    'prompt'        => 'select_account'
];

$auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query($params);

header("Location: " . $auth_url);
exit;
?>