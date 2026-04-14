<?php
$table_id = $_SESSION['table_id'] ?? 0;
$cart_count = !empty($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'],'quantity')) : 0;
// Fallback กรณีใช้ 'qty'
if (!empty($_SESSION['cart']) && $cart_count == 0) {
    $cart_count = array_sum(array_column($_SESSION['cart'],'qty'));
}

$active_order_code = '';
if (isset($conn) && $table_id > 0) {
    $res = $conn->query("SELECT order_code FROM orders WHERE table_id=$table_id AND status NOT IN ('completed', 'cancelled') ORDER BY created_at DESC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) { 
        $active_order_code = $row['order_code']; 
    }
}
?>
<nav class="bs-navbar animate-up">
  <a href="menu_detail.php" class="bs-navbar-brand">
    <div class="bs-navbar-logo">
      <svg viewBox="0 0 40 40" fill="none">
        <circle cx="20" cy="14" r="8" fill="#F5F0E8" opacity=".9" />
        <circle cx="13" cy="17" r="5" fill="#F5F0E8" opacity=".9" />
        <circle cx="27" cy="17" r="5" fill="#F5F0E8" opacity=".9" />
        <circle cx="20" cy="20" r="6.5" fill="#F5F0E8" opacity=".9" />
        <ellipse cx="16.5" cy="29" rx="2" ry="4" fill="#F5F0E8" opacity=".7" />
        <ellipse cx="23.5" cy="29" rx="2" ry="4" fill="#F5F0E8" opacity=".7" />
      </svg>
    </div>
    <div>
      <div class="bs-navbar-name">Black Sheep</div>
      <div class="bs-navbar-sub">in the Garden</div>
    </div>
  </a>
  <div class="nav-right">
    <div class="table-chip">Table <?= $table_id ?></div>

    <?php if (!empty($active_order_code)): ?>
    <a class="track-link-btn" href="order_status.php?order=<?= $active_order_code ?>">
      <svg viewBox="0 0 24 24" stroke-width="2.5">
        <circle cx="12" cy="12" r="10" />
        <polyline points="12 6 12 12 16 14" />
      </svg>
      สถานะอาหาร
    </a>
    <?php endif; ?>

    <a class="cart-btn" href="cart.php?table=<?= $table_id ?>">
      <svg viewBox="0 0 24 24" stroke-width="1.8">
        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
        <line x1="3" y1="6" x2="21" y2="6" />
        <path d="M16 10a4 4 0 0 1-8 0" />
      </svg>
      <?php if ($cart_count > 0): ?>
      <div class="cart-badge"><?= $cart_count ?></div>
      <?php endif; ?>
    </a>

    <a class="track-link-btn" href="feedback.php"
      style="background:rgb(250, 22, 22);border-radius:20px;padding:5px 10px;font-size:.65rem;display:flex;align-items:center;gap:5px;text-decoration:none;color:var(--cream);">
      <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" width="14" height="14">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
      </svg>
      แจ้งปัญหา
    </a>
  </div>
</nav>