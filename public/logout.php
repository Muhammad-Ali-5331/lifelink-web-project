<?php
require_once __DIR__ . '/../includes/functions.php';

// Logout must be a POST with a valid CSRF token, so other websites cannot log you out.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $_SESSION = [];                                    // 1. clear the data
    if (ini_get('session.use_cookies')) {              // 2. delete the browser cookie
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();                                 // 3. destroy the server-side session

    session_start();                                   // fresh session just to show a message
    flash('ok', 'You have been logged out.');
}
redirect('/login.php');
