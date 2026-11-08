<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');
$pageTitle = 'Admin Statistics';

$s = $pdo->query("SELECT
    (SELECT COUNT(*) FROM users WHERE role = 'donor')                    AS donors,
    (SELECT COUNT(*) FROM users WHERE role = 'requester')                AS requesters,
    (SELECT COUNT(*) FROM v_open_requests)                               AS open_requests,
    (SELECT COALESCE(SUM(units_fulfilled), 0) FROM blood_requests)       AS units,
    (SELECT COUNT(*) FROM hospitals WHERE is_verified = 0)               AS unverified")->fetch();

// Donors per blood group (fill missing groups with 0)
$per = array_fill_keys(BLOOD_GROUPS, 0);
foreach ($pdo->query("SELECT d.blood_group, COUNT(*) AS n FROM donors d
                      JOIN users u ON u.user_id = d.user_id WHERE u.is_active = 1
                      GROUP BY d.blood_group") as $row) {
    $per[$row['blood_group']] = (int)$row['n'];
}
$max = max(1, max($per));

require __DIR__ . '/../../includes/header.php';
?>
<h1>Admin statistics</h1>
<section class="grid3">
    <div class="card stat"><b><?= (int)$s['donors'] ?></b>Donors</div>
    <div class="card stat"><b><?= (int)$s['requesters'] ?></b>Requesters</div>
    <div class="card stat"><b><?= (int)$s['open_requests'] ?></b>Open requests</div>
    <div class="card stat"><b><?= (int)$s['units'] ?></b>Units fulfilled</div>
    <div class="card stat"><b><?= (int)$s['unverified'] ?></b>Hospitals awaiting verification
        <?php if ($s['unverified']): ?><br><a href="/admin/hospitals.php">Review</a><?php endif; ?></div>
</section>

<div class="card">
    <h2>Active donors per blood group</h2>
    <?php foreach ($per as $group => $n): ?>
        <div class="bar-row"><span class="bar-label"><?= e($group) ?></span>
            <div class="progress"><span style="width: <?= (int)($n / $max * 100) ?>%"></span></div>
            <span class="bar-n"><?= $n ?></span></div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
