<?php
session_start();
include 'connect.php';
include 'functions.php';

$order_id = isset($_GET['order']) ? (int)$_GET['order'] : 0;
if ($order_id <= 0) { header("Location: index.php"); exit; }

$stmt = $conn->prepare("SELECT o.*, t.table_name FROM orders o JOIN tables t ON o.table_id=t.table_id WHERE o.order_id=?");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) { header("Location: index.php"); exit; }

$items_stmt = $conn->prepare("SELECT oi.*, m.item_name FROM order_items oi JOIN menu_items m ON oi.item_id=m.item_id WHERE oi.order_id=?");
$items_stmt->bind_param('i', $order_id);
$items_stmt->execute();
$items_rs = $items_stmt->get_result();
$total = 0; $items_list = [];
while ($row = $items_rs->fetch_assoc()) {
    $row['subtotal'] = $row['price'] * $row['quantity'];
    $total += $row['subtotal'];
    $items_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>สั่งสำเร็จ — Black Sheep</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; }
    .success-wrap { width: 100%; max-width: 400px; text-align: center; }
    .check-circle {
      width: 72px; height: 72px; border-radius: 50%; background: var(--bot-pale);
      display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;
    }
    .check-circle svg { width: 32px; height: 32px; stroke: var(--botanical); fill: none; stroke-width: 2; }
    .s-eyebrow { font-size: .6rem; letter-spacing: .24em; text-transform: uppercase; color: var(--botanical); margin-bottom: 8px; }
    .s-title { font-size: 2.2rem; font-weight: 300; line-height: 1.1; margin-bottom: 8px; }
    .s-sub { font-size: .82rem; font-weight: 300; color: var(--choco-light); line-height: 1.7; margin-bottom: 28px; }
    .order-ref-card {
      background: #FDFAF5; border: 1px solid rgba(44,24,16,.08); border-radius: var(--radius);
      padding: 20px 24px; margin-bottom: 20px; box-shadow: var(--shadow-soft); text-align: left;
    }
    .ref-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; }
    .ref-label { font-size: .58rem; letter-spacing: .18em; text-transform: uppercase; color: var(--choco-light); margin-bottom: 4px; }
    .ref-value { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; font-weight: 600; color: var(--choco); }
    .status-chip { display: inline-flex; align-items: center; gap: 6px; background: var(--gold-pale); color: #7A5010; font-size: .65rem; font-weight: 500; letter-spacing: .08em; text-transform: uppercase; padding: 6px 14px; border-radius: 20px; }
    .pulse-dot { width: 6px; height: 6px; background: var(--gold); border-radius: 50%; animation: pd 1.6s ease infinite; }
    @keyframes pd { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.3;transform:scale(.7)} }
    .items-list { text-align: left; width: 100%; margin-bottom: 24px; }
    .order-item-row { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid rgba(44,24,16,.07); font-size: .82rem; }
    .order-item-row:last-child { border-bottom: none; }
    .oi-name { color: var(--choco); font-weight: 400; }
    .oi-price { font-family: 'Cormorant Garamond', serif; font-size: .95rem; font-weight: 600; }
    .total-row { display: flex; justify-content: space-between; padding: 12px 0 0; font-size: .85rem; font-weight: 400; color: var(--choco); border-top: 1px solid rgba(44,24,16,.1); margin-top: 4px; }
    .total-row .v { font-family: 'Cormorant Garamond', serif; font-size: 1.4rem; font-weight: 600; }
    .actions { display: flex; flex-direction: column; gap: 10px; }
    .act-btn-primary { display: block; width: 100%; background: var(--choco); color: var(--cream); border: none; padding: 16px; border-radius: 50px; font-family: 'Jost', sans-serif; font-size: .75rem; font-weight: 400; letter-spacing: .14em; text-transform: uppercase; cursor: pointer; text-align: center; text-decoration: none; transition: background .2s; }
    .act-btn-primary:hover { background: var(--choco-mid); }
    .act-btn-outline { display: block; width: 100%; background: transparent; color: var(--choco); border: 1px solid rgba(44,24,16,.2); padding: 15px; border-radius: 50px; font-family: 'Jost', sans-serif; font-size: .72rem; font-weight: 400; letter-spacing: .12em; text-transform: uppercase; text-align: center; text-decoration: none; transition: all .2s; }
    .act-btn-outline:hover { background: var(--cream-deep); }
  </style>
</head>
<body>
<div class="success-wrap animate-up">
  <div class="check-circle animate-up"><svg viewBox="0 0 24 24" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>
  <div class="s-eyebrow animate-up delay-1">Order Confirmed</div>
  <h1 class="s-title serif animate-up delay-1">ส่งออเดอร์แล้ว!</h1>
  <p class="s-sub animate-up delay-2">ทางครัวได้รับออเดอร์ของคุณแล้ว<br>กรุณารอสักครู่ เราจะนำมาเสิร์ฟที่โต๊ะ</p>

  <div class="order-ref-card animate-up delay-2">
    <div class="ref-row">
      <div>
        <div class="ref-label">Order Reference</div>
        <div class="ref-value"><?= htmlspecialchars($order['order_code']) ?></div>
      </div>
      <div style="text-align:right;">
        <div class="ref-label">โต๊ะ</div>
        <div class="ref-value"><?= htmlspecialchars($order['table_name']) ?></div>
      </div>
    </div>
    <div class="status-chip"><div class="pulse-dot"></div>กำลังเตรียม</div>
  </div>

  <div class="items-list animate-up delay-3">
    <?php foreach ($items_list as $item): ?>
    <div class="order-item-row">
      <span class="oi-name"><?= htmlspecialchars($item['item_name']) ?> ×<?= $item['quantity'] ?></span>
      <span class="oi-price">฿<?= number_format($item['subtotal'],0) ?></span>
    </div>
    <?php endforeach; ?>
    <div class="total-row"><span>รวมทั้งหมด</span><span class="v">฿<?= number_format($total,0) ?></span></div>
  </div>

  <div class="actions animate-up delay-4">
    <a href="order_status.php?order=<?= htmlspecialchars($order['order_code']) ?>" class="act-btn-primary">ติดตามสถานะออเดอร์ →</a>
    <a href="menu_detail.php" class="act-btn-outline">สั่งเพิ่มเติม</a>
  </div>
</div>
</body>
</html>
