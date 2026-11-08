<?php
// One request: see offers, accept/decline, confirm donations, cancel.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/donation.php';
require_role('requester');

$me        = (int)$_SESSION['user_id'];
$requestId = (int)($_GET['id'] ?? $_POST['request_id'] ?? 0);
$self      = '/requester/request.php?id=' . $requestId;

// ---- Actions (all POST + CSRF) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action     = $_POST['action'] ?? '';
    $responseId = (int)($_POST['response_id'] ?? 0);

    if ($action === 'accept') {
        [$ok, $msg] = accept_offer($pdo, $requestId, $responseId, $me);
        flash($ok ? 'ok' : 'error', $msg);

    } elseif ($action === 'confirm') {
        [$ok, $msg] = confirm_donation($pdo, $requestId, $responseId, $me);   // the big transaction
        flash($ok ? 'ok' : 'error', $msg);

    } elseif ($action === 'decline') {
        $pdo->prepare("UPDATE donor_responses dr
                       JOIN blood_requests r ON r.request_id = dr.request_id
                       SET dr.status = 'declined'
                       WHERE dr.response_id = ? AND r.request_id = ? AND r.requester_id = ?
                         AND dr.status IN ('offered','accepted')")
            ->execute([$responseId, $requestId, $me]);
        flash('ok', 'Offer declined.');

    } elseif ($action === 'cancel') {
        $pdo->beginTransaction();
        $st = $pdo->prepare("UPDATE blood_requests SET status = 'cancelled'
                             WHERE request_id = ? AND requester_id = ? AND status = 'open'");
        $st->execute([$requestId, $me]);
        if ($st->rowCount()) {
            $pdo->prepare("UPDATE donor_responses SET status = 'declined'
                           WHERE request_id = ? AND status IN ('offered','accepted')")->execute([$requestId]);
            flash('ok', 'Request cancelled.');
        }
        $pdo->commit();
    }
    redirect($self);
}

// ---- Load the request (must belong to this requester) ----
$st = $pdo->prepare('SELECT r.*, h.hospital_name, h.address, h.city_id, c.city_name
                     FROM blood_requests r
                     JOIN hospitals h ON h.hospital_id = r.hospital_id
                     JOIN cities c ON c.city_id = h.city_id
                     WHERE r.request_id = ? AND r.requester_id = ?');
$st->execute([$requestId, $me]);
$req = $st->fetch();
if (!$req) { http_response_code(404); die('Request not found.'); }

// ---- Offers. PRIVACY: the phone is selected ONLY when status is accepted/completed ----
$st = $pdo->prepare("SELECT dr.response_id, dr.status, dr.responded_at, u.full_name, d.blood_group,
                            CASE WHEN dr.status IN ('accepted','completed') THEN u.phone END AS phone
                     FROM donor_responses dr
                     JOIN donors d ON d.user_id = dr.donor_id
                     JOIN users  u ON u.user_id = dr.donor_id
                     WHERE dr.request_id = ? ORDER BY dr.responded_at");
$st->execute([$requestId]);
$offers = $st->fetchAll();

// ---- Smart matching: eligible + compatible + same city (names only, no contact info) ----
$st = $pdo->prepare('SELECT e.full_name, e.blood_group
                     FROM v_eligible_donors e
                     JOIN blood_compatibility bc ON bc.donor_group = e.blood_group AND bc.recipient_group = ?
                     WHERE e.city_id = ? ORDER BY e.full_name');
$st->execute([$req['blood_group'], $req['city_id']]);
$matches = $st->fetchAll();

$isOpen = $req['status'] === 'open';
$pageTitle = 'Request #' . $requestId;
require __DIR__ . '/../../includes/header.php';
?>
<p><a href="/requester/dashboard.php">&larr; All requests</a></p>

<div class="card request <?= e($req['urgency']) ?>">
    <div class="row between">
        <h1><?= e($req['patient_name']) ?> <span class="bg"><?= e($req['blood_group']) ?></span></h1>
        <div><?= badge($req['urgency']) ?> <?= badge($req['status']) ?></div>
    </div>
    <p><?= e($req['hospital_name']) ?>, <?= e($req['address']) ?> (<?= e($req['city_name']) ?>)<br>
       Needed by <b><?= e($req['needed_by']) ?></b></p>
    <div class="progress"><span style="width: <?= (int)($req['units_fulfilled'] / $req['units_needed'] * 100) ?>%"></span></div>
    <p><?= (int)$req['units_fulfilled'] ?> of <?= (int)$req['units_needed'] ?> units received</p>

    <?php if ($isOpen): ?>
    <form method="post" data-confirm="Cancel this request? Pending offers will be declined.">
        <?= csrf_field() ?><input type="hidden" name="request_id" value="<?= $requestId ?>">
        <input type="hidden" name="action" value="cancel">
        <button class="btn danger">Cancel request</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Offers from donors (<?= count($offers) ?>)</h2>
    <?php if (!$offers): ?><p class="muted">No offers yet. Matching donors have been notified on their dashboards.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Donor</th><th>Group</th><th>Status</th><th>Contact</th><th>Action</th></tr>
        <?php foreach ($offers as $o): ?>
        <tr>
            <td><?= e($o['full_name']) ?></td>
            <td><?= e($o['blood_group']) ?></td>
            <td><?= badge($o['status']) ?></td>
            <td><?= $o['phone'] ? '&#9742; ' . e($o['phone']) : '<span class="muted">hidden until accepted</span>' ?></td>
            <td class="actions">
                <?php if ($isOpen && $o['status'] === 'offered'): ?>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="request_id" value="<?= $requestId ?>">
                        <input type="hidden" name="response_id" value="<?= (int)$o['response_id'] ?>">
                        <button name="action" value="accept" class="btn">Accept</button>
                        <button name="action" value="decline" class="btn secondary">Decline</button>
                    </form>
                <?php elseif ($isOpen && $o['status'] === 'accepted'): ?>
                    <form method="post" data-confirm="Confirm that <?= e($o['full_name']) ?> has donated blood?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="request_id" value="<?= $requestId ?>">
                        <input type="hidden" name="response_id" value="<?= (int)$o['response_id'] ?>">
                        <input type="hidden" name="action" value="confirm">
                        <button class="btn">Confirm donation</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<?php if ($isOpen): ?>
<div class="card">
    <h2>Matching donors in <?= e($req['city_name']) ?> (<?= count($matches) ?>)</h2>
    <p class="muted">Eligible, compatible with <?= e($req['blood_group']) ?>, and available. Contact details stay private until you accept their offer.</p>
    <p><?php foreach ($matches as $m): ?><span class="chip"><?= e($m['full_name']) ?> &middot; <?= e($m['blood_group']) ?></span> <?php endforeach; ?>
       <?php if (!$matches): ?><span class="muted">No matching donors right now.</span><?php endif; ?></p>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
