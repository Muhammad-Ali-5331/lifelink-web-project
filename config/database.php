<?php
// Creates the $pdo database connection used by every page.
// It lives outside public/ so the browser can never download it.

$dbHost = 'localhost';
$dbName = 'LifeLink';
$dbUser = 'lifelinkuser';
$dbPass = 'YOUR_PASSWORD_HERE';   // must match the password in schema.sql

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // errors become exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // rows as ['column' => value]
            PDO::ATTR_EMULATE_PREPARES   => false,                   // real prepared statements
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());   // details go to the log only
    http_response_code(500);
    die('Database connection failed.');
}
