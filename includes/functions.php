<?php
// Small helper functions used everywhere.

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/database.php';   // gives us $pdo

const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const WAIT_DAYS    = 90;   // days a donor must wait between donations

// Escape output (prevents XSS). Use e() every time you print user data.
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url)
{
    header('Location: ' . $url);
    exit;
}

// ---- Flash messages: shown once on the next page ------------------------
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];   // type: ok | error
}

function flash_render(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $html .= '<div class="alert ' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// ---- CSRF protection: every POST form carries a secret token -------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        die('Invalid form token. Go back, refresh the page and try again.');
    }
}

// ---- Auto-expiry: open requests whose date has passed become 'expired' ---
function expire_requests(PDO $pdo): void
{
    $pdo->exec("UPDATE blood_requests SET status = 'expired'
                WHERE status = 'open' AND needed_by < CURDATE()");
}

// Where each role lands after login
function dashboard_url(string $role): string
{
    return match ($role) {
        'admin'     => '/admin/dashboard.php',
        'donor'     => '/donor/dashboard.php',
        default     => '/requester/dashboard.php',
    };
}

// Small HTML badge for urgency / status
function badge(string $text, string $class = ''): string
{
    return '<span class="badge ' . e($class ?: $text) . '">' . e(ucfirst($text)) . '</span>';
}
