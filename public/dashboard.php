<?php
// Tiny router: sends each logged-in user to the dashboard of their role.
require_once __DIR__ . '/../includes/auth.php';
require_login();
redirect(dashboard_url($_SESSION['role']));
