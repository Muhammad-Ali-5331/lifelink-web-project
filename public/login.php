<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Login';

if (!empty($_SESSION['user_id'])) {
    redirect(dashboard_url($_SESSION['role']));
}

$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $st = $pdo->prepare('SELECT user_id, full_name, role, city_id, password_hash, is_active
                         FROM users WHERE email = ?');
    $st->execute([$email]);
    $user = $st->fetch();

    // One generic message for every failure: do not reveal whether the email exists.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash('error', 'Invalid email or password.');
    } elseif (!$user['is_active']) {
        flash('error', 'This account has been deactivated by an administrator.');
    } else {
        session_regenerate_id(true);   // new session ID after login (prevents session fixation)
        $_SESSION['user_id']   = (int)$user['user_id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['city_id']   = (int)$user['city_id'];
        redirect(dashboard_url($user['role']));
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="card narrow">
    <h1>Login</h1>
    <form method="post">
        <?= csrf_field() ?>
        <label>Email <input type="email" name="email" value="<?= e($email) ?>" required></label>
        <label>Password <input type="password" name="password" required></label>
        <button class="btn">Login</button>
    </form>
    <p class="muted">No account? <a href="/register.php">Register here</a>.</p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
