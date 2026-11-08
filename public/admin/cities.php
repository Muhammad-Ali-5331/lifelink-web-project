<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');
$pageTitle = 'Cities';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['city_name'] ?? '');
    if ($name === '' || mb_strlen($name) > 60) {
        flash('error', 'Enter a city name (max 60 characters).');
    } else {
        try {
            $pdo->prepare('INSERT INTO cities (city_name) VALUES (?)')->execute([$name]);
            flash('ok', 'City added.');
        } catch (PDOException $e) {
            flash('error', ($e->errorInfo[1] ?? 0) == 1062 ? 'That city already exists.' : 'Could not save.');
        }
    }
    redirect('/admin/cities.php');
}

$cities = $pdo->query('SELECT c.city_name,
        (SELECT COUNT(*) FROM users u WHERE u.city_id = c.city_id) AS users,
        (SELECT COUNT(*) FROM hospitals h WHERE h.city_id = c.city_id) AS hospitals
        FROM cities c ORDER BY c.city_name')->fetchAll();
require __DIR__ . '/../../includes/header.php';
?>
<h1>Cities</h1>
<div class="card">
    <div class="table-wrap"><table>
        <tr><th>City</th><th>Users</th><th>Hospitals</th></tr>
        <?php foreach ($cities as $c): ?>
            <tr><td><?= e($c['city_name']) ?></td><td><?= (int)$c['users'] ?></td><td><?= (int)$c['hospitals'] ?></td></tr>
        <?php endforeach; ?>
    </table></div>
</div>
<div class="card narrow">
    <h2>Add city</h2>
    <form method="post"><?= csrf_field() ?>
        <label>City name <input name="city_name" required></label>
        <button class="btn">Add city</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
