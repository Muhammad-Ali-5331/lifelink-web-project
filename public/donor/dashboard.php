<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('donor');
$pageTitle = 'Donor Dashboard';
$uid = (int)$_SESSION['user_id'];

// ---- Toggle availability (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo->prepare('UPDATE donors SET is_available = 1 - is_available WHERE user_id = ?')->execute([$uid]);
    flash('ok', 'Availability updated.');
    redirect('/donor/dashboard.php');
}

// ---- Donor profile + eligibility numbers computed by MySQL ----
$st = $pdo->prepare('SELECT d.*, u.city_id, c.city_name,
                            TIMESTAMPDIFF(YEAR, d.date_of_birth, CURDATE()) AS age,
                            DATEDIFF(CURDATE(), d.last_donation_date) AS days_since
                     FROM donors d
                     JOIN users u ON u.user_id = d.user_id
                     JOIN cities c ON c.city_id = u.city_id
                     WHERE d.user_id = ?');
$st->execute([$uid]);
$me = $st->fetch();

$daysLeft = 0;
$nextDate = null;
if ($me['last_donation_date'] !== null && $me['days_since'] < WAIT_DAYS) {
    $daysLeft = WAIT_DAYS - (int)$me['days_since'];
    $nextDate = (new DateTime($me['last_donation_date']))->modify('+' . WAIT_DAYS . ' days')->format('Y-m-d');
}

$cities = $pdo->query('SELECT city_id, city_name FROM cities ORDER BY city_name')->fetchAll();

// ---- My offers ----
$st = $pdo->prepare('SELECT dr.status, dr.responded_at, r.patient_name, r.blood_group, r.needed_by,
                            h.hospital_name, h.address, h.phone AS hospital_phone
                     FROM donor_responses dr
                     JOIN blood_requests r ON r.request_id = dr.request_id
                     JOIN hospitals h ON h.hospital_id = r.hospital_id
                     WHERE dr.donor_id = ? ORDER BY dr.responded_at DESC');
$st->execute([$uid]);
$offers = $st->fetchAll();

// ---- Donation history ----
$st = $pdo->prepare('SELECT dn.donated_on, r.patient_name, h.hospital_name
                     FROM donations dn
                     JOIN donor_responses dr ON dr.response_id = dn.response_id
                     JOIN blood_requests r ON r.request_id = dr.request_id
                     JOIN hospitals h ON h.hospital_id = r.hospital_id
                     WHERE dr.donor_id = ? ORDER BY dn.donated_on DESC');
$st->execute([$uid]);
$history = $st->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<h1>Hello, <?= e($_SESSION['user_name']) ?></h1>

<section class="grid2">
    <div class="card">
        <h2>My eligibility</h2>
        <p>Blood group: <b class="bg"><?= e($me['blood_group']) ?></b> &middot; City: <b><?= e($me['city_name']) ?></b></p>

        <?php if ($me['age'] < 18): ?>
            <div class="alert error">You must be 18 or older to donate.</div>
        <?php elseif ($daysLeft > 0): ?>
            <div class="alert warn" data-countdown="<?= e($nextDate) ?>">
                You can donate again in <?= $daysLeft ?> days.
            </div>
        <?php else: ?>
            <div class="alert ok">You are eligible to donate now.</div>
        <?php endif; ?>

        <p>Last donation: <?= $me['last_donation_date'] ? e($me['last_donation_date']) : 'never' ?></p>

        <form method="post">
            <?= csrf_field() ?>
            <p>Status: <?= $me['is_available'] ? badge('available', 'open') : badge('hidden', 'cancelled') ?></p>
            <button class="btn secondary"><?= $me['is_available'] ? 'Switch to unavailable' : 'Switch to available' ?></button>
        </form>
    </div>

    <div class="card">
        <h2>My statistics</h2>
        <p><b><?= count($history) ?></b> donation(s) completed</p>
        <p><b><?= count($offers) ?></b> offer(s) made</p>
        <p class="muted">Donors who are unavailable or still in the 90-day wait are hidden from matching.</p>
    </div>
</section>

<section class="card">
    <h2>Blood requests I can help with</h2>
    <div class="filters">
        <label>Blood group needed
            <select id="f-group">
                <option value="">All</option>
                <?php foreach (BLOOD_GROUPS as $g): ?><option><?= e($g) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>City
            <select id="f-city">
                <option value="0">All cities</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?= $c['city_id'] ?>" <?= (int)$c['city_id'] === (int)$me['city_id'] ? 'selected' : '' ?>><?= e($c['city_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <p class="muted">Only requests your blood group can legally give to are shown. Critical cases come first.</p>
    <div id="request-list" data-csrf="<?= e(csrf_token()) ?>">Loading...</div>
</section>

<section class="card">
    <h2>My offers</h2>
    <?php if (!$offers): ?><p class="muted">You have not offered to donate yet.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Patient</th><th>Group</th><th>Hospital</th><th>Needed by</th><th>Status</th></tr>
        <?php foreach ($offers as $o): ?>
        <tr>
            <td><?= e($o['patient_name']) ?></td>
            <td><?= e($o['blood_group']) ?></td>
            <td><?= e($o['hospital_name']) ?><br><small><?= e($o['address']) ?></small>
                <?php if (in_array($o['status'], ['accepted'], true) && $o['hospital_phone']): ?><br><small>&#9742; <?= e($o['hospital_phone']) ?></small><?php endif; ?></td>
            <td><?= e($o['needed_by']) ?></td>
            <td><?= badge($o['status']) ?>
                <?php if ($o['status'] === 'accepted'): ?><br><small>The requester will contact you.</small><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Donation history</h2>
    <?php if (!$history): ?><p class="muted">No donations yet.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Date</th><th>Patient</th><th>Hospital</th></tr>
        <?php foreach ($history as $h): ?>
        <tr><td><?= e($h['donated_on']) ?></td><td><?= e($h['patient_name']) ?></td><td><?= e($h['hospital_name']) ?></td></tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
