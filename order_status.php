<?php
include 'connect.php';
include 'functions.php';

$order_code = isset($_GET['order']) ? trim($_GET['order']) : '';
if (!$order_code){ die('ไม่พบรหัสออเดอร์'); }

$stmt = $conn->prepare("SELECT o.*, t.table_name FROM orders o JOIN tables t ON o.table_id=t.table_id WHERE o.order_code=?");
$stmt->bind_param('s',$order_code);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order){ die('ไม่พบออเดอร์นี้'); }

// ============================================================
// BUG FIX: If this order is completed/cancelled, check whether
// the same table has a NEW active order. If yes, redirect to it.
// If no new order exists, show a "bill cleared" screen instead
// of displaying stale items from the old paid bill.
// ============================================================
$bill_cleared = false;

if (in_array($order['status'], ['completed', 'cancelled'])) {
    $table_id = (int)$order['table_id'];
    $new_stmt = $conn->prepare(
        "SELECT order_code FROM orders
         WHERE table_id=? AND status NOT IN ('completed','cancelled')
         ORDER BY created_at DESC LIMIT 1"
    );
    $new_stmt->bind_param('i', $table_id);
    $new_stmt->execute();
    $new_row = $new_stmt->get_result()->fetch_assoc();
    if ($new_row) {
        // Redirect to the new active order seamlessly
        header("Location: order_status.php?order=" . urlencode($new_row['order_code']));
        exit;
    } else {
        $bill_cleared = true;
    }
}

// Only fetch items for live (non-cleared) orders
$items_result = null;
if (!$bill_cleared) {
    $items = $conn->prepare("SELECT oi.*, m.item_name FROM order_items oi JOIN menu_items m ON oi.item_id=m.item_id WHERE oi.order_id=?");
    $oid = (int)$order['order_id'];
    $items->bind_param('i',$oid);
    $items->execute();
    $items_result = $items->get_result();
}

$steps = ['pending'=>0,'cooking'=>1,'serving'=>2,'completed'=>3];
$cur_step = $steps[$order['status']] ?? 0;
$is_cancelled = $order['status'] === 'cancelled';

$timeline = [
  ['key'=>'pending',  'th'=>'ได้รับออเดอร์',    'en'=>'Order Received',   'icon'=>'M9 12l2 2 4-4'],
  ['key'=>'cooking',  'th'=>'กำลังปรุงอาหาร',   'en'=>'Being Prepared',    'icon'=>'M12 2a10 10 0 1 0 0 20'],
  ['key'=>'serving',  'th'=>'กำลังนำมาเสิร์ฟ', 'en'=>'On Its Way',        'icon'=>'M5 12h14M12 5l7 7-7 7'],
  ['key'=>'completed','th'=>'เสิร์ฟแล้ว',       'en'=>'Served at Table',   'icon'=>'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>สถานะออเดอร์ — Black Sheep</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    body { min-height: 100vh; padding-bottom: 40px; }
    .page-header { padding: 20px 20px 0; display: flex; align-items: center; gap: 14px; margin-bottom: 28px; }
    .back-btn { width: 40px; height: 40px; border-radius: 50%; border: 1px solid rgba(44,24,16,.15); background: transparent; display: flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; transition: all .15s; }
    .back-btn:hover { background: var(--cream-deep); }
    .back-btn svg { width: 16px; height: 16px; stroke: var(--choco); fill: none; }
    .page-title { font-size: 1.8rem; font-weight: 300; }
    .content { padding: 0 20px; }
    .ref-card { background: #FDFAF5; border-radius: var(--radius); padding: 18px 20px; border: 1px solid rgba(44,24,16,.08); box-shadow: var(--shadow-soft); margin-bottom: 24px; }
    .ref-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
    .ref-label { font-size: .58rem; letter-spacing: .18em; text-transform: uppercase; color: var(--choco-light); margin-bottom: 3px; }
    .ref-code { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; font-weight: 600; color: var(--choco); }
    .ref-table { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; font-weight: 600; text-align: right; }
    .ref-time { font-size: .68rem; font-weight: 300; color: var(--choco-light); }
    .timeline { position: relative; margin: 0 0 28px; }
    .tl-item { display: flex; gap: 16px; padding-bottom: 24px; position: relative; }
    .tl-item:last-child { padding-bottom: 0; }
    .tl-left { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; width: 30px; }
    .tl-dot { width: 30px; height: 30px; border-radius: 50%; background: var(--cream-dark); border: 2px solid var(--cream-dark); display: flex; align-items: center; justify-content: center; z-index: 1; flex-shrink: 0; }
    .tl-dot.done { background: var(--botanical); border-color: var(--botanical); }
    .tl-dot.active { background: var(--cream); border-color: var(--gold); box-shadow: 0 0 0 5px rgba(201,168,76,.18); }
    .tl-dot svg { width: 13px; height: 13px; stroke: var(--cream); fill: none; stroke-width: 2.5; }
    .tl-dot.active svg { stroke: var(--gold); }
    .tl-line { flex: 1; width: 2px; background: var(--cream-dark); margin-top: 2px; }
    .tl-line.done { background: var(--bot-pale); }
    .tl-content { flex: 1; padding-top: 5px; }
    .tl-title { font-family: 'Cormorant Garamond', serif; font-size: 1.1rem; font-weight: 600; color: var(--choco); margin-bottom: 2px; }
    .tl-title.muted { color: var(--choco-light); font-weight: 400; }
    .tl-sub { font-size: .68rem; font-weight: 300; color: var(--choco-light); }
    .items-card { background: #FDFAF5; border-radius: var(--radius); border: 1px solid rgba(44,24,16,.08); box-shadow: var(--shadow-soft); overflow: hidden; margin-bottom: 20px; }
    .items-card-head { padding: 12px 18px; border-bottom: 1px solid rgba(44,24,16,.07); font-size: .62rem; letter-spacing: .16em; text-transform: uppercase; color: var(--choco-light); }
    .order-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 12px 18px; border-bottom: 1px solid rgba(44,24,16,.05); }
    .order-item:last-child { border-bottom: none; }
    .oi-name { font-size: .85rem; font-weight: 400; color: var(--choco); }
    .oi-note { font-size: .7rem; font-weight: 300; font-style: italic; color: var(--choco-light); margin-top: 2px; }
    .oi-right { font-family: 'Cormorant Garamond', serif; font-size: 1rem; font-weight: 600; color: var(--choco-light); white-space: nowrap; }
    .refresh-note { text-align: center; font-size: .65rem; font-weight: 300; color: var(--choco-light); opacity: .6; letter-spacing: .08em; margin-bottom: 20px; }
    .back-btn-full { display: block; width: 100%; background: transparent; color: var(--choco); border: 1px solid rgba(44,24,16,.2); padding: 15px; border-radius: 50px; font-family: 'Jost', sans-serif; font-size: .72rem; font-weight: 400; letter-spacing: .12em; text-transform: uppercase; text-align: center; text-decoration: none; transition: all .2s; }
    .back-btn-full:hover { background: var(--cream-deep); }
    .cancelled-banner { background: #F5DADA; border: 1px solid rgba(144,32,32,.2); color: #902020; padding: 12px 16px; border-radius: var(--radius-sm); font-size: .82rem; font-weight: 300; text-align: center; margin-bottom: 20px; }
    /* Bill cleared screen */
    .cleared-wrap { text-align: center; padding: 40px 0 20px; }
    .cleared-icon { width: 72px; height: 72px; border-radius: 50%; background: #e8f3e8; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
    .cleared-icon svg { width: 32px; height: 32px; stroke: var(--botanical, #4A5E3A); fill: none; stroke-width: 2; }
    .cleared-title { font-family: 'Cormorant Garamond', serif; font-size: 1.8rem; font-weight: 300; margin-bottom: 10px; }
    .cleared-sub { font-size: .82rem; font-weight: 300; color: var(--choco-light); line-height: 1.7; margin-bottom: 32px; }
  </style>
  <?php if (!$bill_cleared): ?>
  <script>setTimeout(()=>location.reload(), 15000);</script>
  <?php endif; ?>
</head>
<body>
<nav class="bs-navbar">
  <div class="bs-navbar-brand">
    <div class="bs-navbar-logo">
      <svg viewBox="0 0 40 40" fill="none">
        <circle cx="20" cy="14" r="8" fill="#F5F0E8" opacity=".9"/>
        <circle cx="13" cy="17" r="5" fill="#F5F0E8" opacity=".9"/>
        <circle cx="27" cy="17" r="5" fill="#F5F0E8" opacity=".9"/>
        <circle cx="20" cy="20" r="6.5" fill="#F5F0E8" opacity=".9"/>
        <ellipse cx="16.5" cy="29" rx="2" ry="4" fill="#F5F0E8" opacity=".7"/>
        <ellipse cx="23.5" cy="29" rx="2" ry="4" fill="#F5F0E8" opacity=".7"/>
      </svg>
    </div>
    <div><div class="bs-navbar-name">Black Sheep</div><div class="bs-navbar-sub">in the Garden</div></div>
  </div>
</nav>

<div class="page-header animate-up">
  <a href="menu_detail.php" class="back-btn">
    <svg viewBox="0 0 24 24" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
  </a>
  <h1 class="page-title serif">Order Status</h1>
</div>

<div class="content animate-up delay-1">

<?php if ($bill_cleared): ?>
  <!-- Bill was paid and no new order: show "thank you" instead of stale data -->
  <div class="cleared-wrap animate-up">
    <div class="cleared-icon">
      <svg viewBox="0 0 24 24" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div class="cleared-title serif">ชำระเงินเรียบร้อยแล้ว</div>
    <p class="cleared-sub">บิลของโต๊ะนี้ถูกชำระเงินเรียบร้อยแล้ว<br>ขอบคุณที่มาใช้บริการ ยินดีต้อนรับกลับมาเสมอ 🙏</p>
  </div>
  <a href="menu_detail.php" class="back-btn-full">← สั่งอาหารใหม่</a>

<?php else: ?>

  <div class="ref-card">
    <div class="ref-top">
      <div>
        <div class="ref-label">Order</div>
        <div class="ref-code"><?= htmlspecialchars($order['order_code']) ?></div>
      </div>
      <div>
        <div class="ref-label">โต๊ะ</div>
        <div class="ref-table"><?= htmlspecialchars($order['table_name']) ?></div>
      </div>
    </div>
    <div class="ref-time">สั่งเมื่อ <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?> น.</div>
  </div>

  <?php if ($is_cancelled): ?>
  <div class="cancelled-banner">ออเดอร์นี้ถูกยกเลิกแล้ว กรุณาติดต่อพนักงาน</div>
  <?php else: ?>
  <div class="timeline animate-up delay-2">
    <?php foreach ($timeline as $ti => $step):
      $step_n = $steps[$step['key']];
      $is_done   = $step_n < $cur_step;
      $is_active = $step_n === $cur_step;
      $has_line  = $ti < count($timeline)-1;
    ?>
    <div class="tl-item">
      <div class="tl-left">
        <div class="tl-dot <?= $is_done?'done':($is_active?'active':'') ?>">
          <svg viewBox="0 0 24 24" stroke-width="2.5">
            <?php if($is_done): ?>
            <polyline points="20 6 9 17 4 12"/>
            <?php else: ?>
            <path d="<?= $step['icon'] ?>"/>
            <?php endif; ?>
          </svg>
        </div>
        <?php if($has_line): ?>
        <div class="tl-line <?= $is_done?'done':'' ?>"></div>
        <?php endif; ?>
      </div>
      <div class="tl-content">
        <div class="tl-title <?= (!$is_done && !$is_active)?'muted':'' ?>"><?= $step['en'] ?></div>
        <div class="tl-sub"><?= $step['th'] ?><?= $is_active?' · กำลังดำเนินการ…':($is_done?' · เสร็จแล้ว':'') ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="items-card animate-up delay-3">
    <div class="items-card-head">รายการอาหาร</div>
    <?php if ($items_result): while($row=$items_result->fetch_assoc()): ?>
    <div class="order-item">
      <div>
        <div class="oi-name"><?= htmlspecialchars($row['item_name']) ?></div>
        <?php if(!empty($row['note'])): ?><div class="oi-note"><?= htmlspecialchars($row['note']) ?></div><?php endif; ?>
      </div>
      <div class="oi-right">×<?= (int)$row['quantity'] ?></div>
    </div>
    <?php endwhile; endif; ?>
  </div>

  <div class="refresh-note">รีเฟรชอัตโนมัติทุก 15 วินาที</div>
  <a href="menu_detail.php" class="back-btn-full">← กลับสั่งเพิ่ม</a>

<?php endif; ?>
</div>
</body>
</html>
