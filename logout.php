<?php
session_start();
$session_cookie_params = session_get_cookie_params();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
	setcookie(session_name(), '', time() - 42000, $session_cookie_params['path'], $session_cookie_params['domain'], $session_cookie_params['secure'], $session_cookie_params['httponly']);
}
session_destroy();
header("Location: /stocktrack/login.php");
exit();
