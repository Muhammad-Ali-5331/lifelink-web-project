<?php
// Interactive CLI script: creates an admin account
// Run via terminal: php script_name.php

if (PHP_SAPI !== 'cli') {
    die("Error: This script must be run from the command line terminal.\n");
}

require_once __DIR__ . '/../config/database.php';

// ANSI color constants for clean CLI feedback
$green = "\033[32m";
$red   = "\033[31m";
$yellow= "\033[33m";
$cyan  = "\033[36m";
$bold  = "\033[1m";
$reset = "\033[0m";

echo "==========================================" . PHP_EOL;
echo "   {$bold}LIFELINK - ADMIN ACCOUNT CREATION{$reset}" . PHP_EOL;
echo "==========================================" . PHP_EOL . PHP_EOL;

// 1. Prompt for input
echo "{$cyan}?> Enter full name:{$reset} ";
$name = trim(fgets(STDIN));

echo "{$cyan}?> Enter email address:{$reset} ";
$email = trim(fgets(STDIN));

echo "{$cyan}?> Enter phone number (press Enter for default):{$reset} ";
$phone = trim(fgets(STDIN));

echo "{$cyan}?> Enter password (min 8 characters):{$reset} ";
system('stty -echo');
$pass = trim(fgets(STDIN));
system('stty echo');
echo PHP_EOL . PHP_EOL;

// 2. Validate input logic
if ($name === '') {
    die("{$red}[x] Error: Full name cannot be empty. Nothing created.{$reset}" . PHP_EOL);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("{$red}[x] Error: Invalid email format. Nothing created.{$reset}" . PHP_EOL);
}

if (strlen($pass) < 8) {
    die("{$red}[x] Error: Password must be at least 8 characters long. Nothing created.{$reset}" . PHP_EOL);
}

// 3. Database operations
try {
    echo "{$yellow}[i] Verifying city database records...{$reset}" . PHP_EOL;
    $cityId = $pdo->query('SELECT MIN(city_id) FROM cities')->fetchColumn();

    if (!$cityId) {
        die("{$red}[x] Error: No cities found. Please run the seed file first (database/seed.sql).{$reset}" . PHP_EOL);
    }

    echo "{$yellow}[i] Creating admin account...{$reset}" . PHP_EOL;
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, email, phone, password_hash, role, city_id)
        VALUES (?, ?, ?, ?, 'admin', ?)
    ");
    
    $finalPhone = ($phone !== '') ? $phone : '0000000000';
    $hashedPass = password_hash($pass, PASSWORD_DEFAULT);

    $stmt->execute([$name, $email, $finalPhone, $hashedPass, $cityId]);

    // 4. Output results cleanly
    echo PHP_EOL;
    echo "{$green}+--------------------------------------------------+{$reset}" . PHP_EOL;
    echo "{$green}|      ADMIN USER ACCOUNT CREATED SUCCESSFULLY     |{$reset}" . PHP_EOL;
    echo "{$green}+--------------------------------------------------+{$reset}" . PHP_EOL;
    echo "{$bold}  Name:{$reset}      $name" . PHP_EOL;
    echo "{$bold}  Email:{$reset}     $email" . PHP_EOL;
    echo "{$bold}  Phone:{$reset}     $finalPhone" . PHP_EOL;
    echo "{$bold}  City ID:{$reset}   $cityId" . PHP_EOL;
    echo "{$green}----------------------------------------------------{$reset}" . PHP_EOL;
    echo "{$cyan}You can now log in at /login.php with {$bold}$email{$reset}" . PHP_EOL . PHP_EOL;

} catch (PDOException $e) {
    echo "{$red}[x] Database Error: " . $e->getMessage() . "{$reset}" . PHP_EOL;
}