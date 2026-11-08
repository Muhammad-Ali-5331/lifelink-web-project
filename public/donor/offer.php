<?php
// Handles the "I can donate" button (POST only). Creates a donor_responses row.
require_once __DIR__ . '/../../includes/auth.php';
require_role('donor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/donor/dashboard.php');
csrf_check();

$uid       = (int)$_SESSION['user_id'];
$requestId = (int)($_POST['request_id'] ?? 0);

try {
    // Rule 1: donor must be eligible right now (view checks age, 90 days, availability, active)
    $st = $pdo->prepare('SELECT blood_group, city_id FROM v_eligible_donors WHERE user_id = ?');
    $st->execute([$uid]);
    $me = $st->fetch();
    if (!$me) throw new RuntimeException('You are not eligible to donate right now.');

    // Rule 2: request must be open, with units still needed
    $st = $pdo->prepare('SELECT blood_group, city_id FROM v_open_requests
                         WHERE request_id = ? AND units_remaining > 0');
    $st->execute([$requestId]);
    $req = $st->fetch();
    if (!$req) throw new RuntimeException('That request is no longer open.');

    // Rule 3: same city
    if ((int)$req['city_id'] !== (int)$me['city_id']) throw new RuntimeException('This request is in a different city.');

    // Rule 4: blood groups must be compatible
    $st = $pdo->prepare('SELECT 1 FROM blood_compatibility WHERE donor_group = ? AND recipient_group = ?');
    $st->execute([$me['blood_group'], $req['blood_group']]);
    if (!$st->fetch()) throw new RuntimeException('Your blood group is not compatible with this patient.');

    $pdo->prepare('INSERT INTO donor_responses (request_id, donor_id) VALUES (?, ?)')
        ->execute([$requestId, $uid]);
    flash('ok', 'Thank you! Your offer was sent to the requester.');

} catch (RuntimeException $e) {
    flash('error', $e->getMessage());
} catch (PDOException $e) {
    // 1062 = UNIQUE (request_id, donor_id) violated -> already offered
    if (($e->errorInfo[1] ?? 0) == 1062) {
        flash('error', 'You have already offered for this request.');
    } else {
        error_log($e->getMessage());
        flash('error', 'Could not save your offer.');
    }
}
redirect('/donor/dashboard.php');
