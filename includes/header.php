<?php
// Common top of every page. Set $pageTitle before including this file.
require_once __DIR__ . '/functions.php';
$pageTitle = $pageTitle ?? 'LifeLink';
$role = $_SESSION['role'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | LifeLink</title>
    <link rel="stylesheet" href="/assets/style.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<header class="topbar">
    <a class="brand" href="/">&#129656; LifeLink</a>
    <nav>
        <?php if ($role === 'donor'): ?>
            <a href="/donor/dashboard.php">Dashboard</a>
        <?php elseif ($role === 'requester'): ?>
            <a href="/requester/dashboard.php">My Requests</a>
            <a href="/requester/new_request.php">New Request</a>
            <a href="/requester/add_hospital.php">Add Hospital</a>
        <?php elseif ($role === 'admin'): ?>
            <a href="/admin/dashboard.php">Statistics</a>
            <a href="/admin/hospitals.php">Hospitals</a>
            <a href="/admin/cities.php">Cities</a>
            <a href="/admin/users.php">Users</a>
        <?php else: ?>
            <a href="/login.php">Login</a>
            <a href="/register.php">Register</a>
        <?php endif; ?>

        <button type="button" id="theme-toggle" title="Dark / light mode">&#9680;</button>

        <?php if ($role): ?>
            <form method="post" action="/logout.php" class="inline">
                <?= csrf_field() ?>
                <button class="link-btn">Logout (<?= e($_SESSION['user_name']) ?>)</button>
            </form>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
<?= flash_render() ?>
