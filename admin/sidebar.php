<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['admin_role'] ?? 'staff';
$is_admin = ($role === 'admin');

function sb_item($href, $label, $icon_path, $current) {
    $active = basename($href) === $current ? 'active' : '';
    echo "<a href='$href' class='sb-item $active'><svg viewBox='0 0 24 24' stroke-width='1.8' fill='none' stroke='currentColor'>$icon_path</svg>$label</a>";
}
?>
<aside class="sidebar">
  <div class="sb-brand">
    <div class="sb-logo-row">
      <div class="sb-dot"><svg viewBox="0 0 40 40" fill="none"><circle cx="20" cy="14" r="8" fill="#2C1810" opacity=".9"/><circle cx="13" cy="17" r="5" fill="#2C1810" opacity=".9"/><circle cx="27" cy="17" r="5" fill="#2C1810" opacity=".9"/><circle cx="20" cy="20" r="6.5" fill="#2C1810" opacity=".9"/><ellipse cx="16.5" cy="29" rx="2" ry="4" fill="#2C1810" opacity=".7"/><ellipse cx="23.5" cy="29" rx="2" ry="4" fill="#2C1810" opacity=".7"/></svg></div>
      <div class="sb-name">Black Sheep</div>
    </div>
    <div class="sb-sub">in the Garden · <span style="color:var(--gold);text-transform:capitalize"><?= htmlspecialchars($role) ?></span></div>
  </div>

  <nav class="sb-nav">
    <div class="sb-sec">Kitchen</div>
    <?php sb_item('dashboard.php','Dashboard','<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',$current_page) ?>
    <?php sb_item('orders.php','Order History','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',$current_page) ?>

    <div class="sb-sec">Staff Tools</div>
    <?php sb_item('staff_pos.php','POS รับออเดอร์','<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',$current_page) ?>
    <?php sb_item('staff_tables.php','จัดการโต๊ะ','<path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>',$current_page) ?>
    <?php sb_item('staff_payment.php','บันทึกชำระเงิน','<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>',$current_page) ?>
    <?php sb_item('staff_kitchen.php','สถานะครัว','<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>',$current_page) ?>

    <?php if ($is_admin): ?>
    <div class="sb-sec">Management</div>
    <?php sb_item('report.php','รายงานยอดขาย','<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',$current_page) ?>
    <?php sb_item('menu_manage.php','จัดการเมนู','<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',$current_page) ?>
    <?php sb_item('qr_manager.php','QR Manager','<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',$current_page) ?>
    <?php
    // Show unread feedback count badge
    $fb_unread = $conn->query("SELECT COUNT(*) as c FROM customer_feedback WHERE status='unread'")->fetch_assoc()['c'] ?? 0;
    $fb_label = 'ความคิดเห็น' . ($fb_unread > 0 ? " <span style=\"background:#c0392b;color:white;border-radius:10px;padding:1px 6px;font-size:.6rem;margin-left:4px;\">$fb_unread</span>" : '');
    ?>
    <a href='feedback.php' class='sb-item <?= basename($_SERVER['PHP_SELF'])==="feedback.php"?"active":"" ?>'>
      <svg viewBox='0 0 24 24' stroke-width='1.8' fill='none' stroke='currentColor'><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <?= $fb_label ?>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sb-footer">
    <div class="sb-staff">
      <div class="sb-avatar"><?= strtoupper(substr($_SESSION['admin_name']??'S',0,1)) ?></div>
      <div><div class="sb-sname"><?= htmlspecialchars($_SESSION['admin_name']??'Staff') ?></div><div class="sb-srole"><?= htmlspecialchars($role) ?></div></div>
      <a href="../logout.php" class="sb-logout" title="ออกจากระบบ"><svg viewBox="0 0 24 24" stroke-width="1.8" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    </div>
  </div>
</aside>
