<?php
require 'auth.php';
include '../connect.php';
include '../functions.php';

// POST: อัปเดตสถานะ หรือ แจ้งปัญหา
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $oid = (int)$_POST['order_id'];
        $status = $_POST['status'];
        $allowed = ['pending','cooking','serving','completed','cancelled'];
        if (in_array($status, $allowed)) {
            $conn->query("UPDATE orders SET status='$status', updated_at=NOW() WHERE order_id=$oid");
        }
    }

    if ($action === 'report_issue') {
        $oid  = (int)$_POST['order_id'];
        $msg  = $conn->real_escape_string(trim($_POST['issue_msg']));
        $uid  = (int)$_SESSION['admin_id'];
        $name = $conn->real_escape_string($_SESSION['admin_name'] ?? 'Staff');
        // เก็บเป็น note ต่อท้าย order (ใช้ updated_at + note field ถ้ามี ไม่งั้นบันทึกเข้า log table)
        // สำหรับนี้ append note เข้าไปใน order items note แรก หรือสร้าง order note
        // เราจะใช้ session flash message แทน เนื่องจาก DB อาจไม่มี notes table
        $_SESSION['issue_flash'] = "แจ้งปัญหาออเดอร์ #{$oid}: {$msg} (โดย {$name})";
        // อัปเดต status เป็น pending เพื่อดึงความสนใจ admin
        $conn->query("UPDATE orders SET status='pending', updated_at=NOW() WHERE order_id=$oid AND status NOT IN ('completed','cancelled')");
    }

    header("Location: staff_kitchen.php"); exit;
}

$issue_flash = $_SESSION['issue_flash'] ?? '';
unset($_SESSION['issue_flash']);

// ดึง active orders
$orders = $conn->query("
    SELECT o.*, t.table_name,
           SUM(oi.quantity * oi.price) as total,
           TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as minutes_ago
    FROM orders o
    JOIN tables t ON o.table_id=t.table_id
    LEFT JOIN order_items oi ON o.order_id=oi.order_id
    WHERE o.status NOT IN ('completed','cancelled')
    GROUP BY o.order_id
    ORDER BY FIELD(o.status,'pending','cooking','serving'), o.created_at ASC
");
$order_list = [];
while ($o = $orders->fetch_assoc()) $order_list[] = $o;

$status_map = ['pending'=>'รอรับออเดอร์','cooking'=>'กำลังทำ','serving'=>'พร้อมเสิร์ฟ'];
$status_cls = ['pending'=>'bs-badge-pending','cooking'=>'bs-badge-cooking','serving'=>'bs-badge-serving'];
$next_status = ['pending'=>'cooking','cooking'=>'serving','serving'=>'completed'];
$next_label  = ['pending'=>'🍳 เริ่มทำ','cooking'=>'🛎️ พร้อมเสิร์ฟ','serving'=>'✓ เสิร์ฟแล้ว'];
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>สถานะครัว — Black Sheep</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link
    href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../assets/style.css">
  <style>
  .kitchen-col-wrap {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
  }

  @media(max-width:900px) {
    .kitchen-col-wrap {
      grid-template-columns: 1fr;
    }
  }

  .kitchen-col {
    background: rgba(44, 24, 16, .03);
    border-radius: 14px;
    padding: 14px;
    min-height: 200px;
  }

  .kitchen-col-title {
    font-family: 'Kanit', sans-serif;
    font-size: .85rem;
    font-weight: 500;
    letter-spacing: .1em;
    text-transform: uppercase;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .col-count {
    background: var(--choco);
    color: var(--cream);
    border-radius: 50%;
    width: 22px;
    height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .65rem;
  }

  .ord-mini {
    background: #FDFAF5;
    border: 1px solid rgba(44, 24, 16, .08);
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 8px;
  }

  .ord-mini.urgent {
    border-color: rgba(192, 57, 43, .4);
    background: #FFF5F5;
    animation: urgentPulse 2s ease-in-out infinite;
  }

  @keyframes urgentPulse {

    0%,
    100% {
      border-color: rgba(192, 57, 43, .3)
    }

    50% {
      border-color: rgba(192, 57, 43, .7)
    }
  }

  .ord-mini-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
  }

  .ord-mini-code {
    font-size: .78rem;
    font-weight: 500;
    color: var(--choco);
  }

  .ord-mini-table {
    font-size: .7rem;
    color: var(--choco-light);
  }

  .ord-mini-time {
    font-size: .68rem;
    color: var(--choco-light);
  }

  .ord-mini-time.overdue {
    color: #c0392b;
    font-weight: 500;
  }

  .ord-mini-items {
    margin-bottom: 10px;
  }

  .ord-mini-item {
    font-size: .75rem;
    color: var(--choco);
    padding: 2px 0;
    display: flex;
    justify-content: space-between;
  }

  .ord-mini-note {
    font-size: .68rem;
    color: var(--choco-light);
    font-style: italic;
  }

  .ord-mini-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
  }

  .ord-mini-btn {
    flex: 1;
    padding: 7px 10px;
    border-radius: 8px;
    border: none;
    font-size: .7rem;
    font-weight: 500;
    cursor: pointer;
    transition: all .15s;
  }

  .btn-advance {
    background: var(--choco);
    color: var(--cream);
  }

  .btn-advance:hover {
    background: var(--choco-mid);
  }

  .btn-issue {
    background: rgba(192, 57, 43, .1);
    color: #c0392b;
    border: 1px solid rgba(192, 57, 43, .2);
  }

  .btn-issue:hover {
    background: rgba(192, 57, 43, .18);
  }

  .auto-badge {
    font-size: .62rem;
    background: #e8f3e8;
    color: var(--botanical, #4A5E3A);
    border-radius: 20px;
    padding: 3px 8px;
  }

  .flash-msg {
    background: var(--botanical, #4A5E3A);
    color: white;
    padding: 12px 18px;
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: .82rem;
  }

  /* Issue modal */
  .issue-modal {
    position: fixed;
    inset: 0;
    background: rgba(44, 24, 16, .5);
    z-index: 300;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .issue-modal.open {
    display: flex;
  }

  .issue-box {
    background: var(--cream);
    border-radius: 16px;
    padding: 24px;
    width: 100%;
    max-width: 400px;
  }

  .issue-title {
    font-family: 'Kanit', sans-serif;
    font-size: 1rem;
    font-weight: 500;
    margin-bottom: 14px;
  }
  </style>
</head>

<body>
  <div class="admin-wrap">
    <?php include 'sidebar.php'; ?>
    <div class="admin-main">
      <div class="topbar">
        <div class="topbar-title">🍳 สถานะครัว</div>
        <div style="display:flex;align-items:center;gap:12px;">
          <span class="auto-badge">🔄 รีเฟรชทุก 20 วิ</span>
          <button onclick="location.reload()" class="bs-btn bs-btn-outline bs-btn-sm">รีเฟรช</button>
        </div>
      </div>

      <div class="main-pad">
        <?php if ($issue_flash): ?>
        <div class="flash-msg">⚠️ <?= htmlspecialchars($issue_flash) ?></div>
        <?php endif; ?>

        <?php
      $by_status = ['pending'=>[], 'cooking'=>[], 'serving'=>[]];
      foreach ($order_list as $o) {
          if (isset($by_status[$o['status']])) $by_status[$o['status']][] = $o;
      }
      $col_titles = ['pending'=>'รอรับออเดอร์','cooking'=>'กำลังทำ','serving'=>'พร้อมเสิร์ฟ'];
      $col_colors = ['pending'=>'var(--gold)','cooking'=>'#E8914A','serving'=>'var(--botanical,#4A5E3A)'];
      ?>

        <div class="kitchen-col-wrap">
          <?php foreach ($by_status as $status => $col_orders): ?>
          <div class="kitchen-col">
            <div class="kitchen-col-title" style="color:<?= $col_colors[$status] ?>">
              <?= $col_titles[$status] ?>
              <span class="col-count"><?= count($col_orders) ?></span>
            </div>

            <?php if (empty($col_orders)): ?>
            <div style="text-align:center;padding:30px 0;font-size:.78rem;color:var(--choco-light);opacity:.4;">
              ไม่มีรายการ</div>
            <?php endif; ?>

            <?php foreach ($col_orders as $o):
            $is_urgent = ($o['minutes_ago'] >= 20 && $status !== 'serving');
            $stmt = $conn->prepare("SELECT m.item_name, oi.quantity, oi.note FROM order_items oi JOIN menu_items m ON oi.item_id=m.item_id WHERE oi.order_id=?");
            $stmt->bind_param('i',$o['order_id']); $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
          ?>
            <div class="ord-mini <?= $is_urgent ? 'urgent' : '' ?>">
              <div class="ord-mini-header">
                <div>
                  <div class="ord-mini-code"><?= htmlspecialchars($o['order_code']) ?></div>
                  <div class="ord-mini-table">🪑 <?= htmlspecialchars($o['table_name']) ?></div>
                </div>
                <div class="ord-mini-time <?= $is_urgent ? 'overdue' : '' ?>">
                  <?= $is_urgent ? '⚠️ ' : '' ?><?= $o['minutes_ago'] ?> นาที
                </div>
              </div>

              <div class="ord-mini-items">
                <?php foreach ($items as $it): ?>
                <div class="ord-mini-item">
                  <span><?= htmlspecialchars($it['item_name']) ?> ×<?= $it['quantity'] ?></span>
                </div>
                <?php if ($it['note']): ?><div class="ord-mini-note">→ <?= htmlspecialchars($it['note']) ?></div>
                <?php endif; ?>
                <?php endforeach; ?>
              </div>

              <div class="ord-mini-actions">
                <?php if (isset($next_status[$status])): ?>
                <form method="post" style="flex:1;display:flex;">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                  <input type="hidden" name="status" value="<?= $next_status[$status] ?>">
                  <button type="submit" class="ord-mini-btn btn-advance" style="width:100%;">
                    <?= $next_label[$status] ?>
                  </button>
                </form>
                <?php endif; ?>
                <button class="ord-mini-btn btn-issue"
                  onclick="openIssue(<?= $o['order_id'] ?>, '<?= htmlspecialchars($o['order_code']) ?>')">
                  ⚠️ แจ้งปัญหา
                </button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Issue Report Modal -->
  <div class="issue-modal" id="issueModal" onclick="if(event.target===this)closeIssue()">
    <div class="issue-box">
      <div class="issue-title">⚠️ แจ้งปัญหาออเดอร์ <span id="issueName" style="color:var(--choco-light)"></span></div>
      <form method="post" id="issueForm">
        <input type="hidden" name="action" value="report_issue">
        <input type="hidden" name="order_id" id="issueOrderId">
        <label class="bs-label">รายละเอียดปัญหา</label>
        <textarea name="issue_msg" class="bs-input bs-textarea" rows="3" style="margin-bottom:14px;"
          placeholder="เช่น วัตถุดิบหมด, เมนูทำไม่ได้, ลูกค้าขอยกเลิก…" required></textarea>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <button type="button" class="bs-btn bs-btn-outline" onclick="closeIssue()">ยกเลิก</button>
          <button type="submit" class="bs-btn" style="background:#c0392b;color:white;">ส่งแจ้ง Admin</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function openIssue(oid, code) {
    document.getElementById('issueOrderId').value = oid;
    document.getElementById('issueName').textContent = code;
    document.getElementById('issueModal').classList.add('open');
  }

  function closeIssue() {
    document.getElementById('issueModal').classList.remove('open');
  }
  // Auto-refresh every 20 seconds
  setInterval(() => location.reload(), 20000);
  </script>
</body>

</html>