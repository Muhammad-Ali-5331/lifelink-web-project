<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('requester');
$pageTitle = 'My Requests';

// Open requests first, then critical -> urgent -> normal, then nearest date
$st = $pdo->prepare("SELECT r.*, h.hospital_name,
        (SELECT COUNT(*) FROM donor_responses d
          WHERE d.request_id = r.request_id AND d.status IN ('offered','accepted')) AS pending_offers
    FROM blood_requests r
    JOIN hospitals h ON h.hospital_id = r.hospital_id
    WHERE r.requester_id = ?
    ORDER BY (r.status = 'open') DESC, FIELD(r.urgency,'critical','urgent','normal'), r.needed_by");
$st->execute([$_SESSION['user_id']]);
$requests = $st->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="row between">
    <h1>My blood requests</h1>
    <a class="btn" href="/requester/new_request.php">+ New request</a>
</div>

<?php if (!$requests): ?>
    <div class="card"><p>You have not posted any request yet.</p></div>
<?php endif; ?>

<?php foreach ($requests as $r): ?>
<div class="card request <?= e($r['urgency']) ?>">
    <div class="row between">
        <h3><?= e($r['patient_name']) ?> <span class="bg"><?= e($r['blood_group']) ?></span></h3>
        <div><?= badge($r['urgency']) ?> <?= badge($r['status']) ?></div>
    </div>
    <p><?= e($r['hospital_name']) ?> &middot; needed by <b><?= e($r['needed_by']) ?></b></p>
    <div class="progress"><span style="width: <?= (int)($r['units_fulfilled'] / $r['units_needed'] * 100) ?>%"></span></div>
    <p><?= (int)$r['units_fulfilled'] ?> of <?= (int)$r['units_needed'] ?> units received
       &middot; <?= (int)$r['pending_offers'] ?> active offer(s)</p>
    <a class="btn secondary" href="/requester/request.php?id=<?= (int)$r['request_id'] ?>">Manage</a>
</div>
<?php endforeach; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
