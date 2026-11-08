<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Register';

if (!empty($_SESSION['user_id'])) {
    redirect(dashboard_url($_SESSION['role']));
}

$cities = $pdo->query('SELECT city_id, city_name FROM cities ORDER BY city_name')->fetchAll();
$old    = ['role' => 'donor'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = $_POST;

    $name    = trim($_POST['full_name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $cityId  = (int)($_POST['city_id'] ?? 0);
    $role    = $_POST['role'] ?? '';

    // ---- Validation (never trust the browser) ----
    if ($name === '' || mb_strlen($name) > 100)                  $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))              $errors[] = 'Invalid email address.';
    if (!preg_match('/^[0-9+\- ]{7,20}$/', $phone))              $errors[] = 'Phone must be 7-20 digits (+, - and spaces allowed).';
    if (strlen($pass) < 8)                                       $errors[] = 'Password must have at least 8 characters.';
    if ($pass !== $confirm)                                      $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['donor', 'requester'], true))          $errors[] = 'Choose a role.';  // admin can NOT be chosen here
    $st = $pdo->prepare('SELECT 1 FROM cities WHERE city_id = ?');
    $st->execute([$cityId]);
    if (!$st->fetch())                                           $errors[] = 'Choose a city.';

    if ($role === 'donor') {
        $group  = $_POST['blood_group'] ?? '';
        $dob    = $_POST['date_of_birth'] ?? '';
        $weight = $_POST['weight_kg'] ?? '';

        if (!in_array($group, BLOOD_GROUPS, true))               $errors[] = 'Choose your blood group.';
        $dobDate = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dobDate || $dobDate->format('Y-m-d') !== $dob) {
            $errors[] = 'Enter a valid date of birth.';
        } elseif ($dobDate->diff(new DateTime('today'))->y < 18) {
            $errors[] = 'Donors must be at least 18 years old.';
        }
        if (!is_numeric($weight) || $weight < 50 || $weight > 300) $errors[] = 'Weight must be at least 50 kg.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();   // users row + donors row must both exist, or neither

            $pdo->prepare('INSERT INTO users (full_name, email, phone, password_hash, role, city_id)
                           VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT), $role, $cityId]);
            $userId = (int)$pdo->lastInsertId();

            if ($role === 'donor') {
                $pdo->prepare('INSERT INTO donors (user_id, blood_group, date_of_birth, weight_kg)
                               VALUES (?, ?, ?, ?)')
                    ->execute([$userId, $group, $dob, $weight]);
            }
            $pdo->commit();

            flash('ok', 'Account created. Please log in.');
            redirect('/login.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // 1062 = duplicate key (UNIQUE email)
            $errors[] = ($e->errorInfo[1] ?? 0) == 1062 ? 'That email is already registered.'
                                                        : 'Could not create the account.';
            if (($e->errorInfo[1] ?? 0) != 1062) error_log($e->getMessage());
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="card narrow">
    <h1>Create account</h1>
    <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <label>I want to join as
            <select name="role" id="role">
                <option value="donor"     <?= ($old['role'] ?? '') === 'donor' ? 'selected' : '' ?>>Donor (I want to donate blood)</option>
                <option value="requester" <?= ($old['role'] ?? '') === 'requester' ? 'selected' : '' ?>>Requester (I need blood for a patient)</option>
            </select>
        </label>
        <label>Full name <input name="full_name" value="<?= e($old['full_name'] ?? '') ?>" required></label>
        <label>Email <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required></label>
        <label>Phone <input name="phone" value="<?= e($old['phone'] ?? '') ?>" required></label>
        <label>City
            <select name="city_id" required>
                <option value="">-- choose --</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?= $c['city_id'] ?>" <?= (int)($old['city_id'] ?? 0) === (int)$c['city_id'] ? 'selected' : '' ?>><?= e($c['city_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div id="donor-fields">
            <label>Blood group
                <select name="blood_group">
                    <option value="">-- choose --</option>
                    <?php foreach (BLOOD_GROUPS as $g): ?>
                        <option <?= ($old['blood_group'] ?? '') === $g ? 'selected' : '' ?>><?= e($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Date of birth <input type="date" name="date_of_birth" value="<?= e($old['date_of_birth'] ?? '') ?>"></label>
            <label>Weight (kg) <input type="number" step="0.1" min="50" name="weight_kg" value="<?= e($old['weight_kg'] ?? '') ?>"></label>
        </div>

        <label>Password (min 8 characters) <input type="password" name="password" required></label>
        <label>Confirm password <input type="password" name="confirm_password" required></label>
        <button class="btn">Create account</button>
    </form>
    <p class="muted">Already registered? <a href="/login.php">Login</a>.</p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
