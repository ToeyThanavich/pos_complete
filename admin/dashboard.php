<?php
require 'auth.php';
include '../connect.php';
include '../functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['status'];
    // อัปเดต allowed ให้มี 'served'
    $allowed = ['pending','cooking','serving','served','completed','cancelled'];
    if (in_array($status, $allowed)) {
        $s = $conn->prepare("UPDATE orders SET status=? WHERE order_id=?");
        $s->bind_param('si', $status, $oid);
        $s->execute();
    }
    header("Location: dashboard.php"); exit;
}

$active_orders = $conn->query("
    SELECT o.*, t.table_name,
           COUNT(oi.order_item_id) as item_count,
           SUM(oi.quantity * oi.price) as total
    FROM orders o
    JOIN tables t ON o.table_id = t.table_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.status NOT IN ('completed','cancelled')
    GROUP BY o.order_id
    ORDER BY o.created_at ASC
");

$today_stats = $conn->query("
    SELECT
        COUNT(*) as total_orders,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status='cooking'   THEN 1 ELSE 0 END) as cooking,
        SUM(CASE WHEN order_type='takeaway' THEN 1 ELSE 0 END) as takeaway,
        SUM(CASE WHEN order_type='dine_in' OR order_type IS NULL THEN 1 ELSE 0 END) as dine_in
    FROM orders WHERE DATE(created_at) = CURDATE()
")->fetch_assoc();

$revenue_details = $conn->query("
    SELECT
        SUM(oi.quantity * oi.price) as total_rev,
        SUM(CASE WHEN o.order_type = 'dine_in' OR o.order_type IS NULL THEN oi.quantity * oi.price ELSE 0 END) as dine_in_rev,
        SUM(CASE WHEN o.order_type = 'takeaway' THEN oi.quantity * oi.price ELSE 0 END) as takeaway_rev
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE DATE(o.created_at) = CURDATE() AND o.status = 'completed'
")->fetch_assoc();

$status_map = [
    'pending'   => ['th'=>'รอรับออเดอร์', 'cls'=>'bs-badge-pending'],
    'cooking'   => ['th'=>'กำลังทำ',      'cls'=>'bs-badge-cooking'],
    'serving'   => ['th'=>'กำลังเสิร์ฟ', 'cls'=>'bs-badge-serving'],
    'served'    => ['th'=>'รอชำระเงิน',   'cls'=>'bs-badge-completed'], 
    'completed' => ['th'=>'จ่ายแล้ว',     'cls'=>'bs-badge-completed'],
    'cancelled' => ['th'=>'ยกเลิก',       'cls'=>'bs-badge-cancelled'],
];

$type_map = [
    'dine_in' => ['th' => 'ทานที่ร้าน', 'cls' => 'bs-badge-completed'],
    'takeaway' => ['th' => 'สั่งกลับบ้าน', 'cls' => 'bs-badge-serving']
];
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>Dashboard — Black Sheep Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link
    href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../assets/admin.css">
  <style>
  /* CSS เฉพาะหน้า Dashboard */
  .summary-box {
    background: #fff;
    border-radius: var(--radius);
    padding: 20px;
    border: 1px solid rgba(44, 24, 16, .06);
    box-shadow: var(--shadow-soft);
    margin-bottom: 26px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }

  .sum-col {
    padding: 15px;
    background: #FDFAF5;
    border-radius: 12px;
  }

  .sum-title {
    font-family: 'Kanit', sans-serif;
    font-size: 1.2rem;
    color: var(--choco);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .sum-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: .9rem;
    color: var(--choco-light);
  }

  .sum-val {
    font-family: 'Kanit', sans-serif;
    font-weight: 500;
    color: var(--choco);
    font-size: 1rem;
  }

  .sum-total {
    border-top: 1px dashed rgba(44, 24, 16, .15);
    padding-top: 10px;
    margin-top: 10px;
    font-weight: 500;
    color: var(--choco);
    font-size: 1.1rem;
  }

  .orders-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
  }

  .ord-card {
    background: #FDFAF5;
    border-radius: var(--radius);
    border: 1px solid rgba(44, 24, 16, .06);
    box-shadow: var(--shadow-soft);
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
  }

  .ord-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-card);
  }

  .ord-head {
    padding: 13px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(44, 24, 16, .07);
  }

  .ord-table {
    font-family: 'Kanit', sans-serif;
    font-size: 1.1rem;
    font-weight: 500;
  }

  .ord-code {
    font-size: .7rem;
    font-weight: 300;
    color: var(--choco-light);
    margin-top: 1px;
  }

  .ord-body {
    padding: 10px 16px;
  }

  .ord-item-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 7px 0;
    border-bottom: 1px solid rgba(44, 24, 16, .05);
    gap: 8px;
  }

  .ord-item-row:last-child {
    border-bottom: none;
  }

  .oi-nm {
    font-size: .85rem;
    font-weight: 400;
    color: var(--choco);
  }

  .oi-note {
    font-size: .7rem;
    font-weight: 300;
    color: var(--choco-light);
    margin-top: 1px;
  }

  .oi-qty {
    font-family: 'Kanit', sans-serif;
    font-size: 1rem;
    font-weight: 500;
    color: var(--choco-light);
    flex-shrink: 0;
  }

  .ord-footer {
    padding: 11px 16px;
    background: var(--cream-deep);
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .status-sel {
    flex: 1;
    background: #FDFAF5;
    border: 1px solid rgba(44, 24, 16, .12);
    border-radius: var(--radius-sm);
    padding: 7px 9px;
    font-family: 'Prompt', sans-serif;
    font-size: .75rem;
    color: var(--choco);
    outline: none;
    cursor: pointer;
    min-width: 0;
  }

  .upd-btn {
    background: var(--choco);
    color: var(--cream);
    border: none;
    padding: 7px 12px;
    border-radius: var(--radius-sm);
    font-family: 'Prompt', sans-serif;
    font-size: .7rem;
    cursor: pointer;
    transition: background .15s;
    flex-shrink: 0;
  }

  .upd-btn:hover {
    background: var(--mangosteen);
  }

  .live-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--bot-pale);
    color: var(--botanical);
    font-size: .65rem;
    font-weight: 500;
    letter-spacing: .05em;
    text-transform: uppercase;
    padding: 6px 12px;
    border-radius: 20px;
  }

  .live-dot {
    width: 6px;
    height: 6px;
    background: var(--botanical);
    border-radius: 50%;
    animation: lp 1.8s ease infinite;
  }

  @keyframes lp {

    0%,
    100% {
      opacity: 1
    }

    50% {
      opacity: .3
    }
  }
  </style>
  <script>
  setTimeout(() => location.reload(), 20000);
  </script>
</head>

<body>

  <?php include 'sidebar.php'; ?>

  <div class="main">
    <div class="topbar">
      <div class="topbar-title serif">ภาพรวมระบบ (Dashboard)</div>
      <div class="topbar-right" style="display:flex; align-items:center; gap:12px;">
        <div class="live-pill">
          <div class="live-dot"></div>อัปเดตอัตโนมัติ 20s
        </div>
        <div style="font-size:.8rem;color:var(--choco-light);"><?= date('d/m/Y H:i:s') ?> น.</div>
      </div>
    </div>

    <div class="page-content">
      <div class="summary-box animate-up">
        <div class="sum-col">
          <div class="sum-title">📊 สรุปจำนวนออเดอร์วันนี้</div>
          <div class="sum-row"><span>ออเดอร์ทานที่ร้าน</span> <span class="sum-val"><?= $today_stats['dine_in'] ?? 0 ?>
              รายการ</span></div>
          <div class="sum-row"><span>ออเดอร์สั่งกลับบ้าน</span> <span
              class="sum-val"><?= $today_stats['takeaway'] ?? 0 ?> รายการ</span></div>
          <div class="sum-row sum-total"><span>รวมทั้งหมด</span> <span
              class="sum-val"><?= $today_stats['total_orders'] ?? 0 ?> รายการ</span></div>
        </div>
        <div class="sum-col" style="background: #FDF4E6;">
          <div class="sum-title">💰 สรุปยอดขายวันนี้</div>
          <div class="sum-row"><span>ยอดขายทานที่ร้าน</span> <span
              class="sum-val">฿<?= number_format($revenue_details['dine_in_rev'] ?? 0, 0) ?></span></div>
          <div class="sum-row"><span>ยอดขายสั่งกลับบ้าน</span> <span
              class="sum-val">฿<?= number_format($revenue_details['takeaway_rev'] ?? 0, 0) ?></span></div>
          <div class="sum-row sum-total"><span>ยอดขายรวมสุทธิ</span> <span class="sum-val"
              style="color:var(--mangosteen);">฿<?= number_format($revenue_details['total_rev'] ?? 0, 0) ?></span></div>
        </div>
      </div>

      <div class="sec-hdr animate-up delay-1">
        <div>
          <div class="sec-eyebrow">Kitchen Queue</div>
          <div class="sec-title serif">ออเดอร์ที่กำลังดำเนินการ</div>
        </div>
      </div>

      <?php
    $orders = [];
    while ($o = $active_orders->fetch_assoc()) $orders[] = $o;
    ?>

      <?php if (empty($orders)): ?>
      <div style="text-align:center; padding: 40px; color: var(--choco-light);">ไม่มีออเดอร์ที่รอดำเนินการ</div>
      <?php else: ?>
      <div class="orders-grid animate-up delay-2">
        <?php foreach ($orders as $order):
        $s = $status_map[$order['status']] ?? ['th'=>$order['status'],'cls'=>'bs-badge-pending'];
        $otype = !empty($order['order_type']) ? $order['order_type'] : 'dine_in';
        $t_info = $type_map[$otype];
        
        $items_q = $conn->prepare("SELECT oi.quantity, oi.note, m.item_name FROM order_items oi JOIN menu_items m ON oi.item_id=m.item_id WHERE oi.order_id=?");
        $items_q->bind_param('i', $order['order_id']);
        $items_q->execute();
        $items_rs = $items_q->get_result();
      ?>
        <div class="ord-card">
          <div class="ord-head">
            <div>
              <div class="ord-table"><?= htmlspecialchars($order['table_name']) ?></div>
              <div class="ord-code"><?= htmlspecialchars($order['order_code']) ?></div>
            </div>
            <div style="text-align:right;">
              <span class="bs-badge <?= $s['cls'] ?>" style="display:block;margin-bottom:4px;"><?= $s['th'] ?></span>
              <span class="bs-badge <?= $t_info['cls'] ?>"><?= $t_info['th'] ?></span>
            </div>
          </div>
          <div class="ord-body">
            <?php while ($item = $items_rs->fetch_assoc()): ?>
            <div class="ord-item-row">
              <div>
                <div class="oi-nm"><?= htmlspecialchars($item['item_name']) ?></div>
                <?php if(!empty($item['note'])): ?><div class="oi-note"><?= htmlspecialchars($item['note']) ?></div>
                <?php endif; ?>
              </div>
              <div class="oi-qty">×<?= $item['quantity'] ?></div>
            </div>
            <?php endwhile; ?>
          </div>
          <div class="ord-footer">
            <form method="post" style="display:flex;align-items:center;gap:6px;flex:1;">
              <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
              <select name="status" class="status-sel">
                <?php foreach ($status_map as $val => $info): ?>
                <option value="<?= $val ?>" <?= $order['status']===$val?'selected':'' ?>><?= $info['th'] ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" name="update_status" class="upd-btn">อัปเดต</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>