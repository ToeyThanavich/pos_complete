<?php
// admin/auth.php — Role-based Authentication Guard
session_start();

if (empty($_SESSION['admin_id'])) {
    header("Location: ../login.php"); exit;
}

function is_admin()     { return ($_SESSION['admin_role'] ?? '') === 'admin'; }
function is_staff()     { return in_array($_SESSION['admin_role'] ?? '', ['admin','staff']); }
function current_role() { return $_SESSION['admin_role'] ?? 'staff'; }
function require_admin() {
    if (!is_admin()) { header("Location: dashboard.php?err=forbidden"); exit; }
}

// Admin-only pages
$admin_only = ['menu_manage.php','qr_manager.php','report.php'];
if (in_array(basename($_SERVER['PHP_SELF']), $admin_only) && !is_admin()) {
    header("Location: dashboard.php?err=forbidden"); exit;
}
