<?php
// JSON endpoint for the donor dashboard's live filtering.
//   GET /api/requests.php?blood_group=A%2B&city_id=1
// Returns ONLY open requests that this donor's blood group can legally give to.
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'donor') {
    http_response_code(403);
    echo json_encode(['error' => 'Donors only.']);
    exit;
}
require_login();   // also re-checks is_active and runs auto-expiry

$uid = (int)$_SESSION['user_id'];

// Donor's own group + whether they may donate right now
$st = $pdo->prepare('SELECT d.blood_group, u.city_id,
                            (SELECT COUNT(*) FROM v_eligible_donors e WHERE e.user_id = d.user_id) AS eligible
                     FROM donors d JOIN users u ON u.user_id = d.user_id
                     WHERE d.user_id = ?');
$st->execute([$uid]);
$me = $st->fetch();

// Build the query; every filter uses its own named placeholder (never string concatenation of input).
$sql = "SELECT r.request_id, r.patient_name, r.blood_group, r.urgency, r.needed_by,
               r.units_remaining, r.hospital_name, r.hospital_address, r.city_id, r.city_name,
               (SELECT COUNT(*) FROM donor_responses dr
                 WHERE dr.request_id = r.request_id AND dr.donor_id = :uid) AS already_offered
        FROM v_open_requests r
        JOIN blood_compatibility bc
          ON bc.recipient_group = r.blood_group AND bc.donor_group = :mygroup
        WHERE r.units_remaining > 0";
$params = [':uid' => $uid, ':mygroup' => $me['blood_group']];

$bg = $_GET['blood_group'] ?? '';
if ($bg !== '' && in_array($bg, BLOOD_GROUPS, true)) {
    $sql .= ' AND r.blood_group = :bg';
    $params[':bg'] = $bg;
}
$city = (int)($_GET['city_id'] ?? 0);
if ($city > 0) {
    $sql .= ' AND r.city_id = :city';
    $params[':city'] = $city;
}
// Critical first, then the most urgent date
$sql .= " ORDER BY FIELD(r.urgency, 'critical', 'urgent', 'normal'), r.needed_by";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

foreach ($rows as &$r) {
    // The Offer button is allowed only if: donor eligible, same city, not offered yet
    $r['can_offer'] = $me['eligible'] > 0
                      && (int)$r['city_id'] === (int)$me['city_id']
                      && (int)$r['already_offered'] === 0;
}
echo json_encode($rows);
