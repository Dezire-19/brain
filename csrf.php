<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper to get token
function csrf_token() {
    return $_SESSION['csrf_token'];
}

// Helper to validate token
function validate_csrf($token) {
    return hash_equals($_SESSION['csrf_token'], $token ?? '');
}