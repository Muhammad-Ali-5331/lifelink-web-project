<?php
// Guards for protected pages. Include this INSTEAD of functions.php, then call
// require_login() or require_role('donor') at the top of the page.

require_once __DIR__ . '/functions.php';

function require_login(): void
{
    global $pdo;

    if (empty($_SESSION['user_id'])) {
        flash('error', 'Please log in first.');
        redirect('/login.php');
    }

    // Re-check the database on every request, so an admin who deactivates an
    // account locks that person out immediately (not only at next login).
    $st = $pdo->prepare('SELECT is_active FROM users WHERE user_id = ?');
    $st->execute([$_SESSION['user_id']]);
    $row = $st->fetch();

    if (!$row || !$row['is_active']) {
        $_SESSION = [];
        flash('error', 'Your account is not active.');
        redirect('/login.php');
    }

    expire_requests($pdo);   // keep statuses fresh
}

function require_role(string $role): void
{
    require_login();
    if ($_SESSION['role'] !== $role) {
        http_response_code(403);
        die('Access denied: this page is for ' . e($role) . 's only.');
    }
}
