<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('requester');
$pageTitle = 'New Request';

// Only VERIFIED hospitals can be used -> reduces fake requests
$hospitals = $pdo->query('SELECT h.hospital_id, h.hospital_name, c.city_name
                          FROM hospitals h JOIN cities c ON c.city_id = h.city_id
                          WHERE h.is_verified = 1 ORDER BY c.city_name, h.hospital_name')->fetchAll();
$errors = [];
$old = ['urgency' => 'normal', 'units_needed' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = $_POST;

    $hospitalId = (int)($_POST['hospital_id'] ?? 0);
    $patient    = trim($_POST['patient_name'] ?? '');
    $group      = $_POST['blood_group'] ?? '';
    $units      = (int)($_POST['units_needed'] ?? 0);
    $urgency    = $_POST['urgency'] ?? '';
    $neededBy   = $_POST['needed_by'] ?? '';

    $st = $pdo->prepare('SELECT 1 FROM hospitals WHERE hospital_id = ? AND is_verified = 1');
    $st->execute([$hospitalId]);
    if (!$st->fetch())                                          $errors[] = 'Choose a verified hospital.';
    if ($patient === '' || mb_strlen($patient) > 100)           $errors[] = 'Enter the patient name.';
    if (!in_array($group, BLOOD_GROUPS, true))                  $errors[] = 'Choose the blood group.';
    if ($units < 1 || $units > 10)                              $errors[] = 'Units must be between 1 and 10.';
    if (!in_array($urgency, ['normal', 'urgent', 'critical'], true)) $errors[] = 'Choose the urgency.';
    $d = DateTime::createFromFormat('Y-m-d', $neededBy);
    if (!$d || $d->format('Y-m-d') !== $neededBy || $neededBy < date('Y-m-d')) $errors[] = 'Needed-by date cannot be in the past.';

    if (!$errors) {
        $pdo->prepare('INSERT INTO blood_requests
                       (requester_id, hospital_id, patient_name, blood_group, units_needed, urgency, needed_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$_SESSION['user_id'], $hospitalId, $patient, $group, $units, $urgency, $neededBy]);
        flash('ok', 'Request posted. Matching donors can now see it.');
        redirect('/requester/request.php?id=' . $pdo->lastInsertId());
    }
}
require __DIR__ . '/../../includes/header.php';
?>
<div class="card narrow">
    <h1>Post a blood request</h1>
    <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label>Patient name <input name="patient_name" value="<?= e($old['patient_name'] ?? '') ?>" required></label>
        <label>Hospital (verified only)
            <select name="hospital_id" required>
                <option value="">-- choose --</option>
                <?php foreach ($hospitals as $h): ?>
                    <option value="<?= $h['hospital_id'] ?>" <?= (int)($old['hospital_id'] ?? 0) === (int)$h['hospital_id'] ? 'selected' : '' ?>>
                        <?= e($h['hospital_name']) ?> (<?= e($h['city_name']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="muted">Hospital not listed? <a href="/requester/add_hospital.php">Suggest it</a>; an admin must verify it first.</p>
        <label>Blood group needed
            <select name="blood_group" required>
                <option value="">-- choose --</option>
                <?php foreach (BLOOD_GROUPS as $g): ?><option <?= ($old['blood_group'] ?? '') === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Units needed (1-10) <input type="number" name="units_needed" min="1" max="10" value="<?= e($old['units_needed'] ?? 1) ?>" required></label>
        <label>Urgency
            <select name="urgency">
                <?php foreach (['normal', 'urgent', 'critical'] as $u): ?>
                    <option value="<?= $u ?>" <?= ($old['urgency'] ?? '') === $u ? 'selected' : '' ?>><?= ucfirst($u) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Needed by <input type="date" name="needed_by" min="<?= date('Y-m-d') ?>" value="<?= e($old['needed_by'] ?? '') ?>" required></label>
        <button class="btn">Post request</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
