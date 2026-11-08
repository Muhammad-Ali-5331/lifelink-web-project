<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');
$pageTitle = 'Hospitals';

$cities = $pdo->query('SELECT city_id, city_name FROM cities ORDER BY city_name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {                       // verify / un-verify
        $pdo->prepare('UPDATE hospitals SET is_verified = 1 - is_verified WHERE hospital_id = ?')
            ->execute([(int)($_POST['hospital_id'] ?? 0)]);
        flash('ok', 'Hospital updated.');

    } elseif ($action === 'add') {
        $name = trim($_POST['hospital_name'] ?? '');
        $cityId = (int)($_POST['city_id'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '' || $address === '' || $cityId < 1) {
            flash('error', 'Name, city and address are required.');
        } else {
            try {
                $pdo->prepare('INSERT INTO hospitals (hospital_name, city_id, address, phone, is_verified) VALUES (?, ?, ?, ?, 1)')
                    ->execute([$name, $cityId, $address, $phone ?: null]);
                flash('ok', 'Verified hospital added.');
            } catch (PDOException $e) {
                flash('error', ($e->errorInfo[1] ?? 0) == 1062 ? 'That hospital already exists in this city.' : 'Could not save.');
            }
        }
    }
    redirect('/admin/hospitals.php');
}

$hospitals = $pdo->query('SELECT h.*, c.city_name FROM hospitals h
                          JOIN cities c ON c.city_id = h.city_id
                          ORDER BY h.is_verified, c.city_name, h.hospital_name')->fetchAll();
require __DIR__ . '/../../includes/header.php';
?>
<h1>Hospitals</h1>
<div class="card">
    <div class="table-wrap"><table>
        <tr><th>Hospital</th><th>City</th><th>Address</th><th>Status</th><th></th></tr>
        <?php foreach ($hospitals as $h): ?>
        <tr>
            <td><?= e($h['hospital_name']) ?></td><td><?= e($h['city_name']) ?></td><td><?= e($h['address']) ?></td>
            <td><?= $h['is_verified'] ? badge('verified', 'open') : badge('unverified', 'urgent') ?></td>
            <td><form method="post"><?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="hospital_id" value="<?= (int)$h['hospital_id'] ?>">
                <button class="btn secondary"><?= $h['is_verified'] ? 'Un-verify' : 'Verify' ?></button></form></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>

<div class="card narrow">
    <h2>Add a verified hospital</h2>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label>Name <input name="hospital_name" required></label>
        <label>City <select name="city_id" required>
            <option value="">-- choose --</option>
            <?php foreach ($cities as $c): ?><option value="<?= $c['city_id'] ?>"><?= e($c['city_name']) ?></option><?php endforeach; ?>
        </select></label>
        <label>Address <input name="address" required></label>
        <label>Phone (optional) <input name="phone"></label>
        <button class="btn">Add hospital</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
