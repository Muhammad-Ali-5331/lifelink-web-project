<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Home';

// Live numbers for the landing page
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM users WHERE role = 'donor' AND is_active = 1)  AS donors,
    (SELECT COUNT(*) FROM v_open_requests)                                AS open_requests,
    (SELECT COALESCE(SUM(units_fulfilled), 0) FROM blood_requests)        AS units")->fetch();

require __DIR__ . '/../includes/header.php';
?>
<section class="hero">
    <h1>Find the right blood donor, fast.</h1>
    <p>LifeLink connects patients' families with <strong>eligible, compatible donors in the same city</strong>.
       No more endless WhatsApp groups.</p>
    <?php if (!empty($_SESSION['user_id'])): ?>
        <a class="btn" href="<?= e(dashboard_url($_SESSION['role'])) ?>">Go to my dashboard</a>
    <?php else: ?>
        <a class="btn" href="/register.php">Join LifeLink</a>
        <a class="btn secondary" href="/login.php">Login</a>
    <?php endif; ?>
</section>

<section class="grid3">
    <div class="card stat"><b><?= (int)$stats['donors'] ?></b>Registered donors</div>
    <div class="card stat"><b><?= (int)$stats['open_requests'] ?></b>Open requests</div>
    <div class="card stat"><b><?= (int)$stats['units'] ?></b>Units fulfilled</div>
</section>

<section class="card">
    <h2>How it works</h2>
    <ol>
        <li><b>Requester</b> posts a request: patient, hospital, blood group, units, urgency.</li>
        <li><b>Matching donors</b> (compatible group, same city, last donated 90+ days ago) see it.</li>
        <li>A donor taps <b>"I can donate"</b>.</li>
        <li>The requester <b>accepts</b>; only then the donor's phone number is revealed.</li>
        <li>After donating, the requester <b>confirms</b>; the system updates everything safely.</li>
    </ol>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
