<?php
session_start();
$table_id = isset($_GET['table']) ? (int)$_GET['table'] : ($_SESSION['table_id'] ?? 0);
$cart = $_SESSION['cart'] ?? [];
$subtotal = 0.0;
foreach ($cart as $c) {
    $qty   = isset($c['qty']) ? (int)$c['qty'] : (int)($c['quantity'] ?? 1);
    $subtotal += (float)$c['price'] * $qty;
}
// เอา Service Charge ออกตามเล่มอ้างอิง
$total = $subtotal;
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>ตะกร้า — Black Sheep</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <link
    href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
  /* ปรับ Font Family ให้ทั้งหน้า */
  body {
    font-family: 'Prompt', sans-serif;
  }

  .serif {
    font-family: 'Kanit', sans-serif;
  }

  .page-header {
    padding: 20px 20px 0;
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
  }

  .back-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 1px solid rgba(44, 24, 16, .15);
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .15s;
  }

  .back-btn:hover {
    background: var(--cream-deep);
  }

  .back-btn svg {
    width: 16px;
    height: 16px;
    stroke: var(--choco);
    fill: none;
  }

  .page-title {
    font-size: 1.6rem;
    font-weight: 500;
    color: var(--choco);
  }

  .cart-wrap {
    padding: 0 20px;
  }

  .cart-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 0;
    border-bottom: 1px solid rgba(44, 24, 16, .08);
  }

  .item-img {
    width: 64px;
    height: 64px;
    border-radius: var(--radius-sm);
    object-fit: cover;
    background: var(--cream-deep);
    flex-shrink: 0;
  }

  .item-info {
    flex: 1;
    min-width: 0;
  }

  .item-name {
    font-family: 'Kanit', sans-serif;
    font-size: 1.05rem;
    font-weight: 500;
    color: var(--choco);
    margin-bottom: 3px;
  }

  .item-note {
    font-size: .75rem;
    font-weight: 300;
    color: var(--choco-light);
    margin-bottom: 6px;
  }

  .item-qty-row {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .qty-s {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 1px solid rgba(44, 24, 16, .18);
    background: var(--cream-deep);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: .9rem;
    color: var(--choco);
    transition: all .15s;
  }

  .qty-s:hover {
    background: var(--cream-dark);
  }

  .qty-val {
    font-family: 'Kanit', sans-serif;
    font-size: 1rem;
    font-weight: 500;
    color: var(--choco);
    min-width: 20px;
    text-align: center;
  }

  .item-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    flex-shrink: 0;
  }

  .item-price {
    font-family: 'Kanit', sans-serif;
    font-size: 1.1rem;
    font-weight: 500;
    color: var(--choco);
  }

  .item-unit {
    font-size: .7rem;
    font-weight: 300;
    color: var(--choco-light);
  }

  .remove-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 2px;
    opacity: .4;
    transition: opacity .15s;
  }

  .remove-btn:hover {
    opacity: 1;
  }

  .remove-btn svg {
    width: 15px;
    height: 15px;
    stroke: #d9534f;
    fill: none;
  }

  /* summary */
  .summary-box {
    background: var(--cream-deep);
    border-radius: 12px;
    padding: 20px;
    margin: 22px 20px 14px;
  }

  .sum-row {
    display: flex;
    justify-content: space-between;
    font-size: .9rem;
    font-weight: 300;
    color: var(--choco-light);
    margin-bottom: 8px;
  }

  .sum-row.total {
    font-size: 1.1rem;
    font-weight: 400;
    color: var(--choco);
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(44, 24, 16, .1);
  }

  .sum-row.total .v {
    font-family: 'Kanit', sans-serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--choco);
  }

  /* Order Type Selector (ทานที่ร้าน / กลับบ้าน) */
  .order-type-box {
    display: flex;
    gap: 10px;
    margin: 0 20px 20px;
  }

  .type-label {
    flex: 1;
    text-align: center;
    background: #fff;
    border: 1px solid rgba(44, 24, 16, .2);
    padding: 12px;
    border-radius: 8px;
    cursor: pointer;
    font-size: .85rem;
    color: var(--choco-light);
    transition: all .2s;
  }

  .type-label input {
    margin-right: 5px;
    cursor: pointer;
  }

  .type-label.selected {
    background: var(--cream-deep);
    border-color: var(--choco);
    color: var(--choco);
    font-weight: 500;
  }

  .checkout-area {
    padding: 0 20px 40px;
  }

  .checkout-btn {
    display: block;
    width: 100%;
    background: var(--choco);
    color: var(--cream);
    border: none;
    padding: 17px;
    border-radius: 50px;
    font-family: 'Prompt', sans-serif;
    font-size: .9rem;
    font-weight: 400;
    cursor: pointer;
    transition: background .2s;
    text-align: center;
    text-decoration: none;
  }

  .checkout-btn:hover {
    background: var(--choco-mid);
  }

  .back-menu-btn {
    display: block;
    width: 100%;
    margin-top: 10px;
    background: transparent;
    color: var(--choco);
    border: 1px solid rgba(44, 24, 16, .2);
    padding: 15px;
    border-radius: 50px;
    font-family: 'Prompt', sans-serif;
    font-size: .85rem;
    font-weight: 400;
    text-align: center;
    text-decoration: none;
    transition: all .2s;
  }

  .back-menu-btn:hover {
    background: var(--cream-deep);
  }

  /* empty */
  .empty-wrap {
    text-align: center;
    padding: 72px 20px;
  }

  .empty-title {
    font-family: 'Kanit', sans-serif;
    font-size: 1.6rem;
    font-weight: 400;
    color: var(--choco);
    margin-bottom: 8px;
  }

  .empty-sub {
    font-size: .9rem;
    font-weight: 300;
    color: var(--choco-light);
  }

  .empty-icon {
    font-size: 2.8rem;
    opacity: .2;
    margin-bottom: 16px;
  }
  </style>
</head>

<body>
  <nav class="bs-navbar">
    <div class="bs-navbar-brand">
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
        <div class="bs-navbar-sub" style="font-family: 'Prompt', sans-serif;">in the Garden</div>
      </div>
    </div>
  </nav>

  <div class="page-header animate-up">
    <a href="menu_detail.php" class="back-btn">
      <svg viewBox="0 0 24 24" stroke-width="2">
        <polyline points="15 18 9 12 15 6" />
      </svg>
    </a>
    <h1 class="page-title serif">รายการอาหารของคุณ</h1>
  </div>

  <?php if (empty($cart)): ?>
  <div class="empty-wrap animate-up delay-1">
    <div class="empty-icon">🌿</div>
    <div class="empty-title">ตะกร้าว่างเปล่า</div>
    <div class="empty-sub">ยังไม่ได้เพิ่มเมนูใด กลับไปเลือกได้เลย</div>
    <a href="menu_detail.php" class="back-menu-btn" style="max-width:280px;margin:24px auto 0;">ดูเมนู →</a>
  </div>

  <?php else: ?>
  <div class="cart-wrap animate-up delay-1">
    <?php foreach ($cart as $i => $c):
    $qty   = isset($c['qty']) ? (int)$c['qty'] : (int)($c['quantity'] ?? 1);
    $price = (float)$c['price'];
    $sum   = $price * $qty;
  ?>
    <div class="cart-item">
      <img class="item-img" src="https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=200&q=70" alt="">
      <div class="item-info">
        <div class="item-name"><?= htmlspecialchars($c['name']) ?></div>
        <?php if (!empty($c['note'])): ?><div class="item-note">📝 <?= htmlspecialchars($c['note']) ?></div>
        <?php endif; ?>
        <div class="item-qty-row">
          <a href="remove_item.php?i=<?= $i ?>&table=<?= $table_id ?>&action=dec" class="qty-s" title="ลด">−</a>
          <span class="qty-val"><?= $qty ?></span>
          <a href="remove_item.php?i=<?= $i ?>&table=<?= $table_id ?>&action=inc" class="qty-s" title="เพิ่ม">+</a>
        </div>
      </div>
      <div class="item-right">
        <div class="item-price">฿<?= number_format($sum,0) ?></div>
        <div class="item-unit">฿<?= number_format($price,0) ?> / ชิ้น</div>
        <a href="remove_item.php?i=<?= $i ?>&table=<?= $table_id ?>" class="remove-btn" title="ลบ"
          onclick="return confirm('ลบรายการนี้?')">
          <svg viewBox="0 0 24 24" stroke-width="2">
            <polyline points="3 6 5 6 21 6" />
            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
          </svg>
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="summary-box animate-up delay-2">
    <div class="sum-row"><span>ยอดรวม (Subtotal)</span><span>฿<?= number_format($subtotal,2) ?></span></div>
    <div class="sum-row total"><span>ยอดสุทธิ (Total)</span><span class="v">฿<?= number_format($total,2) ?></span></div>
  </div>

  <form method="post" action="checkout.php?table=<?= $table_id ?>">

    <div class="order-type-box animate-up delay-3">
      <label class="type-label selected" id="lbl-dine-in">
        <input type="radio" name="order_type" value="dine_in" checked onclick="toggleType('dine_in')"> ทานที่ร้าน
      </label>
      <label class="type-label" id="lbl-takeaway">
        <input type="radio" name="order_type" value="takeaway" onclick="toggleType('takeaway')"> สั่งกลับบ้าน
      </label>
    </div>

    <div class="checkout-area animate-up delay-3">
      <button type="submit" class="checkout-btn">ยืนยันการสั่งอาหาร →</button>
      <a href="menu_detail.php" class="back-menu-btn">← สั่งเมนูเพิ่ม</a>
    </div>
  </form>

  <script>
  // สคริปต์สลับสีปุ่ม ทานร้าน/กลับบ้าน
  function toggleType(type) {
    document.getElementById('lbl-dine-in').classList.remove('selected');
    document.getElementById('lbl-takeaway').classList.remove('selected');
    if (type === 'dine_in') {
      document.getElementById('lbl-dine-in').classList.add('selected');
    } else {
      document.getElementById('lbl-takeaway').classList.add('selected');
    }
  }
  </script>
  <?php endif; ?>
</body>

</html>