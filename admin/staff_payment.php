<?php
require 'auth.php';
include '../connect.php';
include '../functions.php';

// POST: บันทึกการชำระเงิน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_order'])) {
    $oid    = (int)$_POST['order_id'];
    $method = $conn->real_escape_string($_POST['payment_method']);
    
    // หา payment_method_id
    $pm = $conn->query("SELECT id FROM payment_methods WHERE method_name='$method' LIMIT 1")->fetch_assoc();
    if (!$pm) {
        $conn->query("INSERT INTO payment_methods (method_name) VALUES ('$method')");
        $pm_id = $conn->insert_id;
    } else { $pm_id = $pm['id']; }

    $conn->query("UPDATE orders SET status='completed', payment_method_id=$pm_id WHERE order_id=$oid");
    header("Location: staff_payment.php?paid=$oid"); exit;
}

// POST: ยกเลิกเมนูบางรายการ (ใหม่)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_item'])) {
    $oi_id = (int)$_POST['order_item_id'];
    $oid   = (int)$_POST['order_id'];
    
    // ลบรายการอาหารนั้นออกจาก Database
    $conn->query("DELETE FROM order_items WHERE order_item_id=$oi_id");

    // เช็กว่าออเดอร์นี้ยังมีรายการอาหารเหลืออยู่ไหม?
    $remain = $conn->query("SELECT COUNT(*) as c FROM order_items WHERE order_id=$oid")->fetch_assoc()['c'];
    if ($remain == 0) {
        // ถ้าลบจนหมดตะกร้า ให้ยกเลิกบิลนี้ไปเลย
        $conn->query("UPDATE orders SET status='cancelled' WHERE order_id=$oid");
    }
    header("Location: staff_payment.php"); exit;
}

// ออเดอร์ที่รอชำระ (เพิ่ม 'served' เข้าไปในเงื่อนไข IN)
$pending_orders = $conn->query("
    SELECT o.*, t.table_name,
           SUM(oi.quantity * oi.price) as subtotal,
           COUNT(oi.order_item_id) as item_count
    FROM orders o
    JOIN tables t ON o.table_id=t.table_id
    LEFT JOIN order_items oi ON o.order_id=oi.order_id
    WHERE o.status IN ('pending','cooking','serving','served')
    GROUP BY o.order_id
    ORDER BY o.created_at ASC
");
$order_list = [];
while ($o = $pending_orders->fetch_assoc()) {
    if ($o['item_count'] > 0) {
        $order_list[] = $o;
    }
}

// รายงานวันนี้... (ปล่อยเหมือนเดิม)

$paid_id = (int)($_GET['paid'] ?? 0);
// อัปเดต Map ให้รองรับคำว่า served
$status_map = ['pending'=>'รอรับออเดอร์','cooking'=>'กำลังทำ','serving'=>'กำลังเสิร์ฟ','served'=>'รอชำระเงิน'];
$status_cls = ['pending'=>'bs-badge-pending','cooking'=>'bs-badge-cooking','serving'=>'bs-badge-serving','served'=>'bs-badge-completed'];
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>บันทึกชำระเงิน — Black Sheep</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link
    href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../assets/admin.css">
  <style>
  .pay-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 14px;
  }

  .pay-card {
    background: #FDFAF5;
    border: 1px solid rgba(44, 24, 16, .08);
    border-radius: 14px;
    overflow: hidden;
  }

  .pay-card-header {
    padding: 14px 16px 10px;
    border-bottom: 1px solid rgba(44, 24, 16, .06);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .pay-order-code {
    font-size: .78rem;
    font-weight: 500;
    color: var(--choco);
  }

  .pay-table {
    font-size: .72rem;
    color: var(--choco-light);
    margin-top: 2px;
  }

  .pay-items {
    padding: 10px 16px;
    border-bottom: 1px solid rgba(44, 24, 16, .06);
  }

  .pay-item-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    font-size: .78rem;
    color: var(--choco);
    padding: 4px 0;
  }

  .pay-subtotal {
    padding: 10px 16px 14px;
  }

  .pay-subtotal-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
  }

  .pay-total {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.5rem;
    color: var(--choco);
  }

  .pay-method-row {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
    flex-wrap: wrap;
  }

  .pay-method-btn {
    flex: 1;
    min-width: 80px;
    padding: 8px 10px;
    border: 1px solid rgba(44, 24, 16, .14);
    border-radius: 10px;
    background: transparent;
    font-size: .72rem;
    font-weight: 500;
    cursor: pointer;
    transition: all .15s;
    text-align: center;
    color: var(--choco-light);
  }

  .pay-method-btn.selected {
    border-color: var(--choco);
    background: var(--choco);
    color: var(--cream);
  }

  .change-display {
    background: var(--botanical-mist, #e8f0e4);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: .8rem;
    color: var(--botanical);
    margin-bottom: 10px;
    display: none;
  }

  .pay-confirm-btn {
    width: 100%;
    background: var(--botanical, #4A5E3A);
    color: white;
    border: none;
    padding: 12px;
    border-radius: 10px;
    font-family: 'Kanit', sans-serif;
    font-size: .82rem;
    cursor: pointer;
    transition: all .2s;
  }

  .pay-confirm-btn:hover {
    opacity: .9;
    transform: translateY(-1px);
  }

  .pay-confirm-btn:disabled {
    opacity: .35;
    cursor: not-allowed;
    transform: none;
  }

  .summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    background: #FDFAF5;
    border-radius: 10px;
    border: 1px solid rgba(44, 24, 16, .07);
    margin-bottom: 8px;
  }

  .toast-paid {
    position: fixed;
    top: 20px;
    right: 24px;
    background: var(--botanical, #4A5E3A);
    color: white;
    padding: 12px 20px;
    border-radius: 12px;
    font-size: .82rem;
    z-index: 999;
    box-shadow: 0 4px 20px rgba(0, 0, 0, .2);
    animation: slideIn .3s ease;
  }

  @keyframes slideIn {
    from {
      transform: translateX(120%);
      opacity: 0
    }

    to {
      transform: translateX(0);
      opacity: 1
    }
  }

  .cancel-item-btn {
    background: none;
    border: none;
    color: #e74c3c;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    padding: 0;
    display: inline-flex;
    transition: transform .1s;
  }

  .cancel-item-btn:hover {
    transform: scale(1.2);
  }
  </style>
</head>

<body>

  <?php include 'sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">💳 บันทึกชำระเงิน</div>
      <div style="font-size:.78rem; color:var(--choco-light);"><?= date('d/m/Y') ?></div>
    </div>

    <?php if ($paid_id): ?>
    <div class="toast-paid" id="toastPaid">✓ ชำระเงินออเดอร์ #<?= $paid_id ?> สำเร็จ</div>
    <script>
    setTimeout(() => document.getElementById('toastPaid')?.remove(), 4000)
    </script>
    <?php endif; ?>

    <div class="page-content">

      <?php if (!empty($today_summary)): ?>
      <div class="ord-section-title" style="margin-bottom:12px;">📊 ยอดชำระวันนี้</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px;">
        <?php
        $grand = 0;
        foreach ($today_summary as $s): $grand += $s['total']; ?>
        <div class="summary-row" style="flex:1;min-width:160px;">
          <div>
            <div style="font-size:.7rem;color:var(--choco-light);letter-spacing:.08em;">
              <?= htmlspecialchars($s['method_name'] ?? 'ไม่ระบุ') ?></div>
            <div style="font-size:.9rem;font-weight:500;"><?= $s['cnt'] ?> รายการ</div>
          </div>
          <div style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;"><?= number_format($s['total'],0) ?> ฿
          </div>
        </div>
        <?php endforeach; ?>
        <div class="summary-row" style="flex:1;min-width:160px;background:var(--choco);border-color:var(--choco);">
          <div style="color:rgba(245,240,232,.7);font-size:.72rem;letter-spacing:.08em;">รวมทั้งหมด</div>
          <div style="font-family:'Cormorant Garamond',serif;font-size:1.3rem;color:var(--cream);">
            <?= number_format($grand,0) ?> ฿</div>
        </div>
      </div>
      <?php endif; ?>

      <div class="ord-section-title" style="margin-bottom:16px;">🧾 รอชำระเงิน (<?= count($order_list) ?> รายการ)</div>

      <?php if (empty($order_list)): ?>
      <div style="text-align:center;padding:60px;color:var(--choco-light);opacity:.4;font-size:.85rem;">
        ✓ ไม่มีออเดอร์ที่รอชำระเงิน
      </div>
      <?php else: ?>
      <div class="pay-grid">
        <?php foreach ($order_list as $o):
          // ดึง order_item_id มาด้วยเพื่อใช้ตอนลบ
          $stmt = $conn->prepare("SELECT oi.order_item_id, m.item_name, oi.quantity, oi.price, oi.note FROM order_items oi JOIN menu_items m ON oi.item_id=m.item_id WHERE oi.order_id=?");
          $stmt->bind_param('i', $o['order_id']); $stmt->execute();
          $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>
        <div class="pay-card" id="payCard<?= $o['order_id'] ?>">
          <div class="pay-card-header">
            <div>
              <div class="pay-order-code"><?= htmlspecialchars($o['order_code']) ?></div>
              <div class="pay-table">🪑 <?= htmlspecialchars($o['table_name']) ?> · <?= $o['item_count'] ?> รายการ</div>
            </div>
            <span
              class="bs-badge <?= $status_cls[$o['status']] ?? 'bs-badge-pending' ?>"><?= $status_map[$o['status']] ?? $o['status'] ?></span>
          </div>
          <div class="pay-items">
            <?php foreach ($items as $it): ?>
            <div class="pay-item-row">
              <span style="display:flex; align-items:flex-start; gap:8px;">
                <form method="post" style="margin:0;" onsubmit="return confirm('ต้องการยกเลิกเมนูนี้ใช่หรือไม่?')">
                  <input type="hidden" name="cancel_item" value="1">
                  <input type="hidden" name="order_item_id" value="<?= $it['order_item_id'] ?>">
                  <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                  <button type="submit" class="cancel-item-btn" title="ยกเลิกเมนูนี้">×</button>
                </form>
                <span>
                  <?= htmlspecialchars($it['item_name']) ?> ×<?= $it['quantity'] ?>
                  <?= $it['note'] ? "<br><span style='font-style:italic;opacity:.6;font-size:.7rem'>({$it['note']})</span>" : '' ?>
                </span>
              </span>
              <span><?= number_format($it['price'] * $it['quantity'], 0) ?> ฿</span>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="pay-subtotal">
            <div class="pay-subtotal-row">
              <span
                style="font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--choco-light);">ยอดรวม</span>
              <span class="pay-total"><?= number_format($o['subtotal'],0) ?> <small
                  style="font-size:.75rem;font-weight:300">฿</small></span>
            </div>

            <form method="post" onsubmit="return validatePay(this, <?= $o['subtotal'] ?>)">
              <input type="hidden" name="pay_order" value="1">
              <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
              <input type="hidden" name="amount_paid" id="amtPaid<?= $o['order_id'] ?>" value="<?= $o['subtotal'] ?>">
              <input type="hidden" name="payment_method" id="pmMethod<?= $o['order_id'] ?>" value="">

              <div class="pay-method-row">
                <button type="button" class="pay-method-btn" onclick="selMethod(<?= $o['order_id'] ?>,'เงินสด',this)">💵
                  เงินสด</button>
                <button type="button" class="pay-method-btn"
                  onclick="selMethod(<?= $o['order_id'] ?>,'QR Payment',this)">📱 QR</button>
                <button type="button" class="pay-method-btn"
                  onclick="selMethod(<?= $o['order_id'] ?>,'บัตรเครดิต',this)">💳 บัตร</button>
              </div>

              <div id="cashWrap<?= $o['order_id'] ?>" style="display:none;margin-bottom:10px;">
                <label class="bs-label">รับเงินมา (฿)</label>
                <input type="number" class="bs-input" id="cashIn<?= $o['order_id'] ?>"
                  oninput="calcChange(<?= $o['order_id'] ?>, <?= $o['subtotal'] ?>)" placeholder="ระบุจำนวนเงินที่รับ"
                  step="1" min="<?= ceil($o['subtotal']) ?>">
                <div class="change-display" id="changeDsp<?= $o['order_id'] ?>"></div>
              </div>

              <button type="submit" class="pay-confirm-btn" id="payBtn<?= $o['order_id'] ?>" disabled>
                ✓ ยืนยันชำระเงิน
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  </div>

  <script>
  function selMethod(oid, method, el) {
    // highlight
    el.closest('.pay-method-row').querySelectorAll('.pay-method-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('pmMethod' + oid).value = method;
    // show/hide cash input
    const cw = document.getElementById('cashWrap' + oid);
    cw.style.display = (method === 'เงินสด') ? 'block' : 'none';
    if (method !== 'เงินสด') document.getElementById('changeDsp' + oid).style.display = 'none';
    // enable pay button (non-cash always ready, cash needs amount)
    document.getElementById('payBtn' + oid).disabled = (method === 'เงินสด');
  }

  function calcChange(oid, subtotal) {
    const cashIn = parseFloat(document.getElementById('cashIn' + oid).value) || 0;
    const change = cashIn - subtotal;
    const dsp = document.getElementById('changeDsp' + oid);
    if (cashIn >= subtotal) {
      dsp.style.display = 'block';
      dsp.textContent = `เงินทอน: ${change.toLocaleString('th-TH',{minimumFractionDigits:0})} ฿`;
      document.getElementById('amtPaid' + oid).value = cashIn;
      document.getElementById('payBtn' + oid).disabled = false;
    } else {
      dsp.style.display = change < 0 ? 'block' : 'none';
      dsp.textContent = `ไม่พอ: ขาด ${Math.abs(change).toLocaleString('th-TH',{minimumFractionDigits:0})} ฿`;
      dsp.style.background = '#fde8e8';
      dsp.style.color = '#c0392b';
      document.getElementById('payBtn' + oid).disabled = true;
    }
  }

  function validatePay(form, subtotal) {
    const method = form.querySelector('[name=payment_method]').value;
    if (!method) {
      alert('กรุณาเลือกวิธีชำระเงิน');
      return false;
    }
    if (method === 'เงินสด') {
      const oid = form.querySelector('[name=order_id]').value;
      const cashIn = parseFloat(document.getElementById('cashIn' + oid).value) || 0;
      if (cashIn < subtotal) {
        alert('จำนวนเงินไม่พอ');
        return false;
      }
    }
    return confirm(`ยืนยันการชำระเงินด้วย ${method}?`);
  }
  </script>
</body>

</html>