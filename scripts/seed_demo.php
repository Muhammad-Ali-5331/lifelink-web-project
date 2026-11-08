<?php
// OPTIONAL demo data so you can test quickly. All demo passwords: Password123!
//   php /var/www/lifelink/scripts/seed_demo.php
if (PHP_SAPI !== 'cli') { die('Run this from the terminal.'); }
require_once __DIR__ . '/../config/database.php';

$hash   = password_hash('Password123!', PASSWORD_DEFAULT);
$lahore = $pdo->query("SELECT city_id FROM cities WHERE city_name = 'Lahore'")->fetchColumn();
$karachi = $pdo->query("SELECT city_id FROM cities WHERE city_name = 'Karachi'")->fetchColumn();

// name, email, role, city, group, dob, weight, last donation
$people = [
    ['Rida Requester', 'requester@demo.com', 'requester', $lahore, null, null, null, null],
    ['Ali Donor',      'ali@demo.com',   'donor', $lahore,  'O-',  '1999-03-10', 70, null],
    ['Sara Donor',     'sara@demo.com',  'donor', $lahore,  'A+',  '1998-07-21', 58, null],
    ['Bilal Donor',    'bilal@demo.com', 'donor', $lahore,  'B+',  '1995-01-15', 82, date('Y-m-d', strtotime('-30 days'))],  // still waiting
    ['Hina Donor',     'hina@demo.com',  'donor', $karachi, 'O+',  '2000-11-02', 61, null],
];

foreach ($people as [$n, $e, $role, $city, $g, $dob, $w, $last]) {
    try {
        $pdo->prepare('INSERT INTO users (full_name, email, phone, password_hash, role, city_id) VALUES (?,?,?,?,?,?)')
            ->execute([$n, $e, '0300-' . rand(1000000, 9999999), $hash, $role, $city]);
        if ($role === 'donor') {
            $pdo->prepare('INSERT INTO donors (user_id, blood_group, date_of_birth, weight_kg, last_donation_date) VALUES (?,?,?,?,?)')
                ->execute([$pdo->lastInsertId(), $g, $dob, $w, $last]);
        }
        echo "Created $e\n";
    } catch (PDOException $ex) {
        echo "Skipped $e (already exists)\n";
    }
}
echo "Done. Demo password: Password123!\n";
