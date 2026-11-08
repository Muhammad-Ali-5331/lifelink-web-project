<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('requester');
$pageTitle = 'Add Hospital';

$cities = $pdo->query('SELECT city_id, city_name FROM cities ORDER BY city_name')->fetchAll();
$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = $_POST;
    $name    = trim($_POST['hospital_name'] ?? '');
    $cityId  = (int)($_POST['city_id'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');

    if ($name === '' || mb_strlen($name) > 100)    $errors[] = 'Enter the hospital name.';
    if ($address === '' || mb_strlen($address) > 200) $errors[] = 'Enter the address.';
    if ($phone !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $phone)) $errors[] = 'Invalid phone number.';
    $st = $pdo->prepare('SELECT 1 FROM cities WHERE city_id = ?');
    $st->execute([$cityId]);
    if (!$st->fetch())                             $errors[] = 'Choose a city.';

    if (!$errors) {
        try {
            // is_verified defaults to 0: an admin must verify before it can be used
            $pdo->prepare('INSERT INTO hospitals (hospital_name, city_id, address, phone) VALUES (?, ?, ?, ?)')
                ->execute([$name, $cityId, $address, $phone ?: null]);
            flash('ok', 'Hospital submitted. You can use it after an admin verifies it.');
            redirect('/requester/dashboard.php');
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) == 1062) $errors[] = 'That hospital already exists in this city.';
            else { error_log($e->getMessage()); $errors[] = 'Could not save the hospital.'; }
        }
    }
}
require __DIR__ . '/../../includes/header.php';
?>
<div class="card narrow">
    <h1>Suggest a hospital</h1>
    <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label>Hospital name <input name="hospital_name" value="<?= e($old['hospital_name'] ?? '') ?>" required></label>
        <label>City
            <select name="city_id" required>
                <option value="">-- choose --</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?= $c['city_id'] ?>" <?= (int)($old['city_id'] ?? 0) === (int)$c['city_id'] ? 'selected' : '' ?>><?= e($c['city_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Address <input name="address" value="<?= e($old['address'] ?? '') ?>" required></label>
        <label>Phone (optional) <input name="phone" value="<?= e($old['phone'] ?? '') ?>"></label>
        <button class="btn">Submit for verification</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
