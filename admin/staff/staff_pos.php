<?php
require 'auth.php';
include '../connect.php';
include '../functions.php';

// POST: สร้างออเดอร์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $table_id = (int)$_POST['table_id'];
    $order_type = $_POST['order_type'] ?? 'dine_in';
    $items = json_decode($_POST['items_json'] ?? '[]', true);
    $note  = $conn->real_escape_string(trim($_POST['order_note'] ?? ''));

    if ($table_id && !empty($items)) {
        $order_code = 'ORD' . date('YmdHis') . rand(100,999);
        $uid = (int)$_SESSION['admin_id'];
        $conn->query("INSERT INTO orders (order_code, table_id, status, user_id, order_time, created_at)
                      VALUES ('$order_code', $table_id, 'pending', $uid, NOW(), NOW())");
        $oid = $conn->insert_id;

        foreach ($items as $it) {
            $iid   = (int)$it['id'];
            $qty   = (int)$it['qty'];
            $price = (float)$it['price'];
            $inote = $conn->real_escape_string($it['note'] ?? '');
            $conn->query("INSERT INTO order_items (order_id, item_id, quantity, price, note)
                          VALUES ($oid, $iid, $qty, $price, '$inote')");
        }
        header("Location: staff_pos.php?success=$order_code"); exit;
    }
}

// ดึงข้อมูล
$cats  = $conn->query("SELECT * FROM categories ORDER BY sort_order, category_name");
$cat_list = [];
while ($c = $cats->fetch_assoc()) $cat_list[] = $c;

$tables = $conn->query("SELECT * FROM tables WHERE is_active=1 ORDER BY table_id");
$table_list = [];
while ($t = $tables->fetch_assoc()) $table_list[] = $t;

$success_code = htmlspecialchars($_GET['success'] ?? '');
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>POS รับออเดอร์ — Black Sheep</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../assets/admin.css">
  <style>
  .pos-wrap { display: grid; grid-template-columns: 1fr 360px; gap: 20px; height: calc(100vh - 100px); overflow: hidden; }
  @media(max-width:900px){ .pos-wrap{grid-template-columns:1fr;height:auto;overflow:visible;} }

  /* Left: Menu */
  .pos-menu { overflow-y: auto; padding-right: 4px; }
  .pos-cat-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
  .pos-cat-btn { font-size:.7rem; font-weight:500; letter-spacing:.1em; text-transform:uppercase; padding:6px 14px; border-radius:20px; border:1px solid rgba(44,24,16,.15); background:transparent; color:var(--choco-light); cursor:pointer; transition:all .15s; }
  .pos-cat-btn.active { background:var(--choco); color:var(--cream); border-color:var(--choco); }
  .pos-menu-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; }
  .pos-item-card { background:#FDFAF5; border:1px solid rgba(44,24,16,.08); border-radius:12px; padding:12px; cursor:pointer; transition:all .15s; text-align:left; }
  .pos-item-card:hover { border-color:var(--choco-light); transform:translateY(-2px); box-shadow:0 4px 16px rgba(44,24,16,.1); }
  .pos-item-card:active { transform:scale(.97); }
  .pos-item-img { width:100%; aspect-ratio:4/3; object-fit:cover; border-radius:8px; margin-bottom:8px; background:var(--cream-deep); }
  .pos-item-placeholder { width:100%; aspect-ratio:4/3; border-radius:8px; margin-bottom:8px; background:var(--cream-deep); display:flex; align-items:center; justify-content:center; font-size:1.6rem; }
  .pos-item-name { font-family:'Kanit',sans-serif; font-size:.82rem; font-weight:400; color:var(--choco); line-height:1.3; margin-bottom:4px; }
  .pos-item-price { font-size:.78rem; font-weight:500; color:var(--botanical); }

  /* Right: Cart */
  .pos-cart { background:#FDFAF5; border-radius:16px; border:1px solid rgba(44,24,16,.08); display:flex; flex-direction:column; overflow:hidden; box-shadow:0 4px 24px rgba(44,24,16,.08); }
  .pos-cart-header { padding:16px 18px 12px; border-bottom:1px solid rgba(44,24,16,.07); }
  .pos-cart-title { font-family:'Kanit',sans-serif; font-size:1rem; font-weight:500; color:var(--choco); margin-bottom:8px; }
  .pos-table-select { width:100%; background:white; border:1px solid rgba(44,24,16,.15); border-radius:8px; padding:8px 12px; font-family:'Prompt',sans-serif; font-size:.82rem; color:var(--choco); outline:none; margin-bottom:8px; }
  .pos-type-row { display:flex; gap:6px; }
  .pos-type-btn { flex:1; padding:6px; border-radius:8px; border:1px solid rgba(44,24,16,.12); background:transparent; font-size:.68rem; font-weight:400; letter-spacing:.06em; color:var(--choco-light); cursor:pointer; transition:all .15s; }
  .pos-type-btn.active { background:var(--choco); color:var(--cream); border-color:var(--choco); }

  .pos-cart-items { flex:1; overflow-y:auto; padding:10px 18px; }
  .pos-cart-empty { text-align:center; padding:40px 20px; color:var(--choco-light); opacity:.5; font-size:.82rem; }
  .pos-cart-row { display:flex; align-items:flex-start; gap:10px; padding:8px 0; border-bottom:1px solid rgba(44,24,16,.06); }
  .pos-cart-row:last-child { border-bottom:none; }
  .pos-cart-qty { background:var(--choco); color:var(--cream); border-radius:6px; min-width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:500; flex-shrink:0; }
  .pos-cart-name { flex:1; font-size:.82rem; color:var(--choco); line-height:1.3; }
  .pos-cart-name-note { font-size:.68rem; color:var(--choco-light); font-style:italic; margin-top:1px; }
  .pos-cart-price { font-size:.82rem; font-weight:500; color:var(--choco); white-space:nowrap; }
  .pos-cart-del { background:none; border:none; color:var(--choco-light); opacity:.35; cursor:pointer; font-size:1rem; padding:0 2px; transition:opacity .15s; flex-shrink:0; }
  .pos-cart-del:hover { opacity:.8; color:#c0392b; }

  .pos-cart-footer { padding:14px 18px; border-top:1px solid rgba(44,24,16,.07); }
  .pos-total-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
  .pos-total-label { font-size:.72rem; letter-spacing:.1em; text-transform:uppercase; color:var(--choco-light); }
  .pos-total-amount { font-family:'Cormorant Garamond',serif; font-size:1.5rem; font-weight:500; color:var(--choco); }
  .pos-note-input { width:100%; background:white; border:1px solid rgba(44,24,16,.12); border-radius:8px; padding:8px 12px; font-size:.8rem; font-family:'Prompt',sans-serif; color:var(--choco); outline:none; margin-bottom:12px; resize:none; }
  .pos-note-input:focus { border-color:var(--choco-light); }
  .pos-submit-btn { width:100%; background:var(--choco); color:var(--cream); border:none; padding:14px; border-radius:12px; font-family:'Kanit',sans-serif; font-size:.85rem; font-weight:400; letter-spacing:.1em; cursor:pointer; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:8px; }
  .pos-submit-btn:hover { background:var(--choco-mid); transform:translateY(-1px); }
  .pos-submit-btn:disabled { opacity:.4; cursor:not-allowed; transform:none; }
  .pos-clear-btn { width:100%; background:transparent; color:var(--choco-light); border:1px solid rgba(44,24,16,.12); padding:9px; border-radius:12px; font-size:.72rem; cursor:pointer; margin-top:6px; transition:all .15s; }
  .pos-clear-btn:hover { background:rgba(44,24,16,.05); }

  .toast-success { position:fixed; top:20px; right:24px; background:var(--botanical); color:white; padding:12px 20px; border-radius:12px; font-size:.82rem; z-index:999; box-shadow:0 4px 20px rgba(0,0,0,.2); animation:slideIn .3s ease; }
  @keyframes slideIn { from{transform:translateX(120%);opacity:0} to{transform:translateX(0);opacity:1} }

  /* Quick-note modal */
  .qnote-overlay { position:fixed; inset:0; background:rgba(44,24,16,.45); backdrop-filter:blur(4px); z-index:300; display:none; align-items:flex-end; justify-content:center; }
  .qnote-overlay.open { display:flex; }
  .qnote-box { background:var(--cream); border-radius:20px 20px 0 0; padding:24px 20px 32px; width:100%; max-width:440px; }
  .qnote-title { font-family:'Kanit',sans-serif; font-size:1rem; font-weight:500; margin-bottom:6px; }
  .qnote-sub { font-size:.75rem; color:var(--choco-light); margin-bottom:14px; }
  .qnote-qty { display:flex; align-items:center; gap:0; background:var(--cream-deep); border-radius:30px; overflow:hidden; width:fit-content; margin-bottom:14px; }
  .qnote-qty button { background:none; border:none; width:36px; height:36px; font-size:1.1rem; cursor:pointer; color:var(--choco); }
  .qnote-qty span { width:40px; text-align:center; font-size:1rem; }
  .qnote-input { width:100%; border:1px solid rgba(44,24,16,.12); border-radius:10px; padding:10px 13px; font-size:.85rem; font-family:'Prompt',sans-serif; background:white; outline:none; margin-bottom:14px; }
  .qnote-input:focus { border-color:var(--choco-light); }
  .qnote-actions { display:grid; grid-template-columns:1fr 2fr; gap:8px; }
  </style>
</head>
<body>
<div class="admin-wrap">
  <?php include 'sidebar.php'; ?>
  <div class="admin-main">
    <div class="topbar">
      <div class="topbar-title">🖥️ POS รับออเดอร์</div>
      <div style="font-size:.78rem; color:var(--choco-light);"><?= date('d/m/Y H:i') ?></div>
    </div>

    <?php if ($success_code): ?>
    <div class="toast-success" id="toastSuccess">✓ ออเดอร์ <?= $success_code ?> ส่งครัวแล้ว!</div>
    <script>setTimeout(()=>document.getElementById('toastSuccess')?.remove(), 4000)</script>
    <?php endif; ?>

    <div class="main-pad">
      <div class="pos-wrap">

        <!-- ─── LEFT: Menu ─── -->
        <div class="pos-menu">
          <div class="pos-cat-bar" id="catBar">
            <button class="pos-cat-btn active" data-cat="all">ทั้งหมด</button>
            <?php foreach ($cat_list as $c): ?>
            <button class="pos-cat-btn" data-cat="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></button>
            <?php endforeach; ?>
          </div>

          <div class="pos-menu-grid" id="menuGrid">
            <?php foreach ($cat_list as $c):
              $cid = (int)$c['category_id'];
              $items = $conn->query("SELECT * FROM menu_items WHERE active=1 AND category_id=$cid ORDER BY item_name");
              while ($m = $items->fetch_assoc()):
            ?>
            <button class="pos-item-card" data-cat="<?= $cid ?>"
              onclick="promptAdd(<?= htmlspecialchars(json_encode($m)) ?>)">
              <?php if ($m['image']): ?>
              <img src="../uploads/<?= htmlspecialchars($m['image']) ?>" class="pos-item-img" alt="">
              <?php else: ?><div class="pos-item-placeholder">☕</div><?php endif; ?>
              <div class="pos-item-name"><?= htmlspecialchars($m['item_name']) ?><?= $m['size']?' ('.$m['size'].')':'' ?></div>
              <div class="pos-item-price"><?= number_format($m['price'],0) ?> ฿</div>
            </button>
            <?php endwhile; endforeach; ?>
          </div>
        </div>

        <!-- ─── RIGHT: Cart ─── -->
        <div class="pos-cart">
          <div class="pos-cart-header">
            <div class="pos-cart-title">📋 ออเดอร์ใหม่</div>
            <select class="pos-table-select" id="tableSelect">
              <option value="">— เลือกโต๊ะ —</option>
              <?php foreach ($table_list as $t): ?>
              <option value="<?= $t['table_id'] ?>"><?= htmlspecialchars($t['table_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="pos-type-row">
              <button class="pos-type-btn active" data-type="dine_in" onclick="setType('dine_in',this)">🪑 ทานที่ร้าน</button>
              <button class="pos-type-btn" data-type="takeaway" onclick="setType('takeaway',this)">🛍️ สั่งกลับ</button>
            </div>
          </div>

          <div class="pos-cart-items" id="cartItems">
            <div class="pos-cart-empty">ยังไม่มีรายการ<br><small>กดเมนูด้านซ้ายเพื่อเพิ่ม</small></div>
          </div>

          <div class="pos-cart-footer">
            <div class="pos-total-row">
              <span class="pos-total-label">รวมทั้งหมด</span>
              <span class="pos-total-amount" id="totalAmt">฿ 0</span>
            </div>
            <textarea class="pos-note-input" id="orderNote" rows="2" placeholder="หมายเหตุถึงครัว (ไม่บังคับ)…"></textarea>
            <form method="post" id="posForm">
              <input type="hidden" name="place_order" value="1">
              <input type="hidden" name="table_id" id="fTableId">
              <input type="hidden" name="order_type" id="fOrderType" value="dine_in">
              <input type="hidden" name="order_note" id="fNote">
              <input type="hidden" name="items_json" id="fItems">
              <button type="button" class="pos-submit-btn" id="submitBtn" onclick="submitOrder()" disabled>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                ส่งออเดอร์ไปครัว
              </button>
              <button type="button" class="pos-clear-btn" onclick="clearCart()">ล้างตะกร้า</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Note Modal -->
<div class="qnote-overlay" id="qnoteOverlay" onclick="if(event.target===this)closeNote()">
  <div class="qnote-box">
    <div class="qnote-title" id="qnoteTitle">เพิ่มรายการ</div>
    <div class="qnote-sub" id="qnotePrice">฿0</div>
    <div class="qnote-qty">
      <button onclick="chQty(-1)">−</button>
      <span id="qnoteQty">1</span>
      <button onclick="chQty(1)">+</button>
    </div>
    <input type="text" class="qnote-input" id="qnoteNote" placeholder="หมายเหตุ เช่น หวานน้อย, ไม่ใส่น้ำแข็ง…">
    <div class="qnote-actions">
      <button class="bs-btn bs-btn-outline" onclick="closeNote()">ยกเลิก</button>
      <button class="bs-btn bs-btn-primary" onclick="confirmAdd()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        เพิ่มลงออเดอร์
      </button>
    </div>
  </div>
</div>

<script>
let cart = [], currentItem = null, qQty = 1, orderType = 'dine_in';

// Category filter
document.querySelectorAll('.pos-cat-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.pos-cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const cat = btn.dataset.cat;
    document.querySelectorAll('.pos-item-card').forEach(card => {
      card.style.display = (cat==='all' || card.dataset.cat===cat) ? '' : 'none';
    });
  });
});

function setType(type, el) {
  orderType = type;
  document.querySelectorAll('.pos-type-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('fOrderType').value = type;
}

// Quick note modal
function promptAdd(item) {
  currentItem = item; qQty = 1;
  document.getElementById('qnoteTitle').textContent = item.item_name + (item.size ? ` (${item.size})` : '');
  document.getElementById('qnotePrice').textContent = '฿' + parseFloat(item.price).toLocaleString('th-TH');
  document.getElementById('qnoteQty').textContent = '1';
  document.getElementById('qnoteNote').value = '';
  document.getElementById('qnoteOverlay').classList.add('open');
  setTimeout(() => document.getElementById('qnoteNote').focus(), 100);
}
function closeNote() { document.getElementById('qnoteOverlay').classList.remove('open'); }
function chQty(d) { qQty = Math.max(1, qQty+d); document.getElementById('qnoteQty').textContent = qQty; }
function confirmAdd() {
  if (!currentItem) return;
  const note = document.getElementById('qnoteNote').value.trim();
  const key = currentItem.item_id + '|' + note;
  const existing = cart.find(c => c.key === key);
  if (existing) { existing.qty += qQty; }
  else { cart.push({ key, id: currentItem.item_id, name: currentItem.item_name, size: currentItem.size, price: parseFloat(currentItem.price), qty: qQty, note }); }
  renderCart(); closeNote();
}

function renderCart() {
  const el = document.getElementById('cartItems');
  if (!cart.length) { el.innerHTML = '<div class="pos-cart-empty">ยังไม่มีรายการ<br><small>กดเมนูด้านซ้ายเพื่อเพิ่ม</small></div>'; updateTotal(); return; }
  el.innerHTML = cart.map((c,i) => `
    <div class="pos-cart-row">
      <div class="pos-cart-qty">${c.qty}</div>
      <div class="pos-cart-name">
        ${c.name}${c.size?` <span style="font-size:.68rem;opacity:.6">(${c.size})</span>`:''}
        ${c.note?`<div class="pos-cart-name-note">${c.note}</div>`:''}
      </div>
      <div class="pos-cart-price">${(c.price*c.qty).toLocaleString('th-TH',{minimumFractionDigits:0})} ฿</div>
      <button class="pos-cart-del" onclick="removeItem(${i})">✕</button>
    </div>`).join('');
  updateTotal();
}

function removeItem(i) { cart.splice(i,1); renderCart(); }
function clearCart() { if(cart.length && !confirm('ล้างตะกร้าทั้งหมด?')) return; cart=[]; renderCart(); }

function updateTotal() {
  const total = cart.reduce((s,c) => s + c.price*c.qty, 0);
  document.getElementById('totalAmt').textContent = '฿ ' + total.toLocaleString('th-TH',{minimumFractionDigits:0});
  const tableOk = document.getElementById('tableSelect').value !== '';
  document.getElementById('submitBtn').disabled = (!cart.length || !tableOk);
}

document.getElementById('tableSelect').addEventListener('change', updateTotal);

function submitOrder() {
  const tableId = document.getElementById('tableSelect').value;
  if (!tableId) { alert('กรุณาเลือกโต๊ะก่อน'); return; }
  if (!cart.length) { alert('กรุณาเพิ่มรายการอาหารก่อน'); return; }
  document.getElementById('fTableId').value = tableId;
  document.getElementById('fNote').value = document.getElementById('orderNote').value;
  document.getElementById('fItems').value = JSON.stringify(cart);
  document.getElementById('submitBtn').disabled = true;
  document.getElementById('submitBtn').textContent = 'กำลังส่ง…';
  document.getElementById('posForm').submit();
}

document.addEventListener('keydown', e => { if(e.key==='Escape') closeNote(); });
</script>
</body>
</html>
