<?php
require 'auth.php';
include '../connect.php';
include '../functions.php';

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $tid = (int)$_POST['table_id'];
        $conn->query("UPDATE tables SET is_active=1-is_active WHERE table_id=$tid");
    }
    if ($action === 'add_table') {
        $name = $conn->real_escape_string(trim($_POST['table_name']));
        if ($name) $conn->query("INSERT INTO tables (table_name, is_active) VALUES ('$name', 1)");
    }
    if ($action === 'move_order') {
        $oid = (int)$_POST['order_id'];
        $new_tid = (int)$_POST['new_table_id'];
        if ($oid && $new_tid) {
            $conn->query("UPDATE orders SET table_id=$new_tid WHERE order_id=$oid");
        }
    }
    header("Location: staff_tables.php"); exit;
}

// ดึงข้อมูลโต๊ะพร้อมออเดอร์ที่ active
$tables = $conn->query("
    SELECT t.*,
           COUNT(o.order_id) as active_orders,
           SUM(CASE WHEN o.status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) as pending_count
    FROM tables t
    LEFT JOIN orders o ON t.table_id=o.table_id AND o.status NOT IN ('completed','cancelled')
    GROUP BY t.table_id
    ORDER BY t.table_id
");
$table_list = [];
while ($t = $tables->fetch_assoc()) $table_list[] = $t;

// ออเดอร์ active ทั้งหมด (สำหรับ move)
$active_orders = $conn->query("
    SELECT o.order_id, o.order_code, o.status, t.table_name, t.table_id,
           SUM(oi.quantity) as item_count
    FROM orders o
    JOIN tables t ON o.table_id=t.table_id
    LEFT JOIN order_items oi ON o.order_id=oi.order_id
    WHERE o.status NOT IN ('completed','cancelled')
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
");
$order_list = [];
while ($o = $active_orders->fetch_assoc()) $order_list[] = $o;

$status_map = ['pending'=>'รอรับออเดอร์','cooking'=>'กำลังทำ','serving'=>'กำลังเสิร์ฟ','completed'=>'เสร็จ','cancelled'=>'ยกเลิก'];
$status_cls  = ['pending'=>'bs-badge-pending','cooking'=>'bs-badge-cooking','serving'=>'bs-badge-serving','completed'=>'bs-badge-completed','cancelled'=>'bs-badge-cancelled'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>จัดการโต๊ะ — Black Sheep</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../assets/admin.css">
  <style>
  .table-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:14px; margin-bottom:28px; }
  .tbl-card { border-radius:14px; border:1px solid rgba(44,24,16,.1); padding:18px 16px; text-align:center; position:relative; transition:all .2s; }
  .tbl-card.open  { background:#FDFAF5; }
  .tbl-card.busy  { background:linear-gradient(145deg,#FFF8F0,#FDF2E4); border-color:rgba(201,168,76,.3); }
  .tbl-card.closed{ background:var(--cream-deep); opacity:.55; }
  .tbl-num  { font-family:'Cormorant Garamond',serif; font-size:2rem; font-weight:400; color:var(--choco); line-height:1; }
  .tbl-name { font-size:.72rem; color:var(--choco-light); margin-top:2px; margin-bottom:10px; }
  .tbl-status-dot { width:8px; height:8px; border-radius:50%; margin:0 auto 8px; }
  .dot-open   { background:#4CAF50; }
  .dot-busy   { background:var(--gold); }
  .dot-closed { background:#9E9E9E; }
  .tbl-orders { font-size:.72rem; color:var(--choco-light); margin-bottom:10px; }
  .tbl-actions { display:flex; gap:6px; justify-content:center; flex-wrap:wrap; }

  .section-title { font-family:'Kanit',sans-serif; font-size:1rem; font-weight:500; color:var(--choco); margin-bottom:14px; display:flex; align-items:center; gap:8px; }
  .order-row { background:#FDFAF5; border:1px solid rgba(44,24,16,.07); border-radius:12px; padding:14px 16px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:8px; }
  .order-code { font-size:.8rem; font-weight:500; color:var(--choco); }
  .order-table { font-size:.72rem; color:var(--choco-light); }
  .order-items { font-size:.72rem; color:var(--choco-light); }
  </style>
</head>
<body>

  <?php include 'sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">🪑 จัดการโต๊ะ</div>
      <button class="bs-btn bs-btn-primary bs-btn-sm" onclick="document.getElementById('addTableModal').classList.add('open')">
        + เพิ่มโต๊ะ
      </button>
    </div>

    <div class="page-content">

      <!-- ─── Table Grid ─── -->
      <div class="section-title">🗺️ สถานะโต๊ะทั้งหมด</div>
      <div class="table-grid">
        <?php foreach ($table_list as $t):
          $is_busy   = $t['pending_count'] > 0;
          $is_active = $t['is_active'] == 1;
          $card_cls  = !$is_active ? 'closed' : ($is_busy ? 'busy' : 'open');
          $dot_cls   = !$is_active ? 'dot-closed' : ($is_busy ? 'dot-busy' : 'dot-open');
          $status_label = !$is_active ? 'ปิด' : ($is_busy ? "มีออเดอร์ {$t['pending_count']} รายการ" : 'ว่าง');
        ?>
        <div class="tbl-card <?= $card_cls ?>">
          <div class="tbl-status-dot <?= $dot_cls ?>"></div>
          <div class="tbl-num"><?= $t['table_id'] ?></div>
          <div class="tbl-name"><?= htmlspecialchars($t['table_name']) ?></div>
          <div class="tbl-orders"><?= $status_label ?></div>
          <div class="tbl-actions">
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="table_id" value="<?= $t['table_id'] ?>">
              <button type="submit" class="bs-btn bs-btn-outline bs-btn-sm">
                <?= $is_active ? '🔒 ปิดโต๊ะ' : '🔓 เปิดโต๊ะ' ?>
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- ─── Active Orders + Move ─── -->
      <div class="section-title">
        🔄 ย้ายโต๊ะ
        <span style="font-size:.72rem;font-weight:300;color:var(--choco-light);">ออเดอร์ที่กำลังดำเนินการ</span>
      </div>

      <?php if (empty($order_list)): ?>
      <div style="text-align:center;padding:40px;color:var(--choco-light);opacity:.5;font-size:.85rem;">ไม่มีออเดอร์ที่กำลังดำเนินการ</div>
      <?php else: ?>
      <?php foreach ($order_list as $o): ?>
      <div class="order-row">
        <div style="flex:1;">
          <div class="order-code"><?= htmlspecialchars($o['order_code']) ?></div>
          <div class="order-table">📍 <?= htmlspecialchars($o['table_name']) ?> · <?= $o['item_count'] ?> รายการ</div>
        </div>
        <span class="bs-badge <?= $status_cls[$o['status']] ?? 'bs-badge-pending' ?>"><?= $status_map[$o['status']] ?? $o['status'] ?></span>
        <form method="post" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <input type="hidden" name="action" value="move_order">
          <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
          <select name="new_table_id" class="bs-select" style="width:140px;padding:6px 10px;font-size:.78rem;">
            <option value="">ย้ายไปโต๊ะ…</option>
            <?php foreach ($table_list as $t): if ($t['table_id']==$o['table_id']) continue; ?>
            <option value="<?= $t['table_id'] ?>"><?= htmlspecialchars($t['table_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="bs-btn bs-btn-outline bs-btn-sm">ย้าย</button>
        </form>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Add Table Modal -->
<div class="modal-bg" id="addTableModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box" style="max-width:360px;">
    <div class="modal-hdr">
      <div><div class="modal-eyebrow">จัดการโต๊ะ</div><div class="modal-title">เพิ่มโต๊ะใหม่</div></div>
      <button class="modal-close" onclick="document.getElementById('addTableModal').classList.remove('open')">✕</button>
    </div>
    <form method="post" style="padding:20px 24px 24px;">
      <input type="hidden" name="action" value="add_table">
      <label class="bs-label">ชื่อโต๊ะ</label>
      <input type="text" name="table_name" class="bs-input" placeholder="เช่น โต๊ะ 8, VIP Table" required style="margin-bottom:16px;">
      <button type="submit" class="bs-btn bs-btn-primary" style="width:100%;">+ เพิ่มโต๊ะ</button>
    </form>
  </div>
</div>
</body>
</html>
