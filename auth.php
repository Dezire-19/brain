<?php
// ---------- SESSION SECURITY ----------
if (session_status() === PHP_SESSION_NONE) {

    // Force cookies only, prevent URL session IDs
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);

    // Set secure cookie params
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => 0,            // Session expires on browser close
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'],
        'secure'   => $secure,      // Only over HTTPS
        'httponly' => true,         // JS cannot access cookie
        'samesite' => 'Strict',     // Prevent CSRF via cross-site
    ]);

    session_start();

    // Regenerate session ID on first start (prevents fixation)
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

// ---------- SESSION TIMEOUT ----------
$timeout_duration = 1800; // 30 minutes
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    // Session expired
    session_unset();
    session_destroy();
    session_start(); // start new session
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time

// ---------- PROTECT INTERNAL PAGES ----------
if (!defined('IN_SYSTEM')) {
    header("Location: index.php?page=dashboard");
    exit;
}

// ---------- LOGIN CHECK ----------
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>