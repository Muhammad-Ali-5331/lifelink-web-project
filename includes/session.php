<?php
// Secure session start. Included by functions.php, so every page gets it.

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');     // reject unknown session IDs
    ini_set('session.cookie_httponly', '1');     // JavaScript cannot read the cookie
    ini_set('session.cookie_samesite', 'Lax');   // limits cross-site cookie sending
    // session.cookie_secure is NOT enabled: we use plain http://lifelink.local locally.
    session_start();
}
