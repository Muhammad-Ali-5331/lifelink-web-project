<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');
$pageTitle = 'Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // Admins cannot be deactivated (this also protects you from locking yourself out)
    $st = $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE user_id = ? AND role <> 'admin'");
    $st->execute([(int)($_POST['user_id'] ?? 0)]);
    flash($st->rowCount() ? 'ok' : 'error', $st->rowCount() ? 'User updated.' : 'That user cannot be changed.');
    redirect('/admin/users.php');
}

$users = $pdo->query('SELECT u.user_id, u.full_name, u.email, u.role, u.is_active, u.created_at,
                             c.city_name, d.blood_group
                      FROM users u JOIN cities c ON c.city_id = u.city_id
                      LEFT JOIN donors d ON d.user_id = u.user_id
                      ORDER BY u.role, u.full_name')->fetchAll();
require __DIR__ . '/../../includes/header.php';
?>
<h1>Users</h1>
<div class="card">
    <div class="table-wrap"><table>
        <tr><th>Name</th><th>Email</th><th>Role</th><th>City</th><th>Group</th><th>Status</th><th></th></tr>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= e($u['full_name']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['role']) ?></td>
            <td><?= e($u['city_name']) ?></td><td><?= e($u['blood_group'] ?? '-') ?></td>
            <td><?= $u['is_active'] ? badge('active', 'open') : badge('deactivated', 'cancelled') ?></td>
            <td><?php if ($u['role'] !== 'admin'): ?>
                <form method="post" data-confirm="Change the active status of <?= e($u['full_name']) ?>?">
                    <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                    <button class="btn secondary"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
