<?php
require 'auth.php';
include '../connect.php';

// ── Auto-add is_active column if not exists ──────────────────────────────────
$col = $conn->query("SHOW COLUMNS FROM tables LIKE 'is_active'");
if ($col->num_rows === 0) {
    $conn->query("ALTER TABLE tables ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
}

// ── Handle POST actions ───────────────────────────────────────────────────────
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // เพิ่มโต๊ะใหม่
    if ($action === 'add') {
        $name = trim($conn->real_escape_string($_POST['table_name'] ?? ''));
        if ($name !== '') {
            // ตรวจว่าชื่อซ้ำไหม
            $dup = $conn->query("SELECT table_id FROM tables WHERE table_name='$name'");
            if ($dup->num_rows > 0) {
                $msg = "มีโต๊ะชื่อ \"$name\" อยู่ในระบบแล้ว"; $msg_type = 'error';
            } else {
                $conn->query("INSERT INTO tables (table_name, is_active) VALUES ('$name', 1)");
                $msg = "เพิ่มโต๊ะ \"$name\" สำเร็จ";
            }
        }
    }

    // เปิด/ปิดโต๊ะ
    if ($action === 'toggle') {
        $id  = (int)($_POST['table_id'] ?? 0);
        $cur = $conn->query("SELECT is_active FROM tables WHERE table_id=$id")->fetch_assoc();
        $new = $cur['is_active'] ? 0 : 1;
        $conn->query("UPDATE tables SET is_active=$new WHERE table_id=$id");
        $msg = $new ? "เปิดโต๊ะสำเร็จ" : "ปิดโต๊ะสำเร็จ";
        $msg_type = $new ? 'success' : 'warning';
    }

    // แก้ชื่อโต๊ะ
    if ($action === 'rename') {
        $id   = (int)($_POST['table_id'] ?? 0);
        $name = trim($conn->real_escape_string($_POST['table_name'] ?? ''));
        if ($id > 0 && $name !== '') {
            $conn->query("UPDATE tables SET table_name='$name' WHERE table_id=$id");
            $msg = "เปลี่ยนชื่อโต๊ะเป็น \"$name\" สำเร็จ";
        }
    }

    // ลบโต๊ะ
    if ($action === 'delete') {
        $id = (int)($_POST['table_id'] ?? 0);
        // ตรวจว่ามีออเดอร์ active อยู่ไหม
        $active = $conn->query("SELECT COUNT(*) as c FROM orders WHERE table_id=$id AND status NOT IN ('completed','cancelled')")->fetch_assoc();
        if ($active['c'] > 0) {
            $msg = "ไม่สามารถลบได้ เพราะมีออเดอร์ที่ยังดำเนินการอยู่";
            $msg_type = 'error';
        } else {
            $conn->query("DELETE FROM tables WHERE table_id=$id");
            $msg = "ลบโต๊ะสำเร็จ";
        }
    }
}

// ── Fetch all tables ──────────────────────────────────────────────────────────
$tables_res = $conn->query("SELECT * FROM tables ORDER BY is_active DESC, table_id ASC");
$tables     = [];
while ($t = $tables_res->fetch_assoc()) $tables[] = $t;

$total       = count($tables);
$active_cnt  = count(array_filter($tables, fn($t) => $t['is_active']));
$inactive_cnt = $total - $active_cnt;

// Base URL for QR
$base_url   = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$script_dir = dirname(dirname($_SERVER['SCRIPT_NAME']));
$app_url    = $base_url . $script_dir;
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>QR Manager — Black Sheep Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../assets/admin.css">
  <style>
  /* ── Stats row ── */
  .stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 24px;
  }
  .stat-sm {
    background: #FDFAF5;
    border-radius: var(--radius);
    padding: 16px 20px;
    border: 1px solid rgba(44,24,16,.06);
    box-shadow: var(--shadow-soft);
    display: flex;
    align-items: center;
    gap: 14px;
  }
  .stat-sm-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: 1.2rem;
  }
  .stat-sm-icon.all  { background: var(--cream-deep); }
  .stat-sm-icon.open { background: var(--bot-pale); }
  .stat-sm-icon.closed { background: #F5DADA; }
  .stat-sm-val {
    font-family: 'Kanit', sans-serif;
    font-size: 1.8rem;
    font-weight: 600;
    color: var(--choco);
    line-height: 1;
  }
  .stat-sm-lbl { font-size: .72rem; color: var(--choco-light); margin-top: 2px; }

  /* ── Add table card ── */
  .add-card {
    background: #FDFAF5;
    border: 2px dashed rgba(44,24,16,.15);
    border-radius: var(--radius);
    padding: 20px 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
  }
  .add-card label { font-size: .75rem; letter-spacing: .1em; text-transform: uppercase; color: var(--choco-light); }
  .add-input {
    flex: 1;
    min-width: 180px;
    background: var(--cream-deep);
    border: 1px solid rgba(44,24,16,.15);
    border-radius: var(--radius-sm);
    padding: 10px 14px;
    font-family: 'Prompt', sans-serif;
    font-size: .9rem;
    color: var(--choco);
    outline: none;
    transition: border-color .2s;
  }
  .add-input:focus { border-color: var(--choco-light); }
  .add-input::placeholder { color: var(--choco-light); opacity: .5; }
  .btn-add {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--choco); color: var(--cream);
    border: none; padding: 11px 22px; border-radius: 30px;
    font-family: 'Prompt', sans-serif; font-size: .82rem;
    cursor: pointer; transition: background .2s; white-space: nowrap;
  }
  .btn-add:hover { background: var(--choco-mid); }
  .btn-add svg { width: 15px; height: 15px; stroke: currentColor; fill: none; }

  /* ── Filter tabs ── */
  .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
  .ftab {
    font-size: .72rem; letter-spacing: .1em; text-transform: uppercase;
    padding: 7px 16px; border-radius: 30px;
    border: 1px solid rgba(44,24,16,.15); background: transparent;
    color: var(--choco-light); cursor: pointer; transition: all .15s;
  }
  .ftab:hover { background: var(--cream-deep); }
  .ftab.active { background: var(--choco); color: var(--cream); border-color: var(--choco); }

  /* ── QR Grid ── */
  .qr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 18px; }

  .qr-card {
    background: #FDFAF5;
    border-radius: var(--radius);
    border: 1px solid rgba(44,24,16,.08);
    box-shadow: var(--shadow-soft);
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
    position: relative;
  }
  .qr-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-card); }
  .qr-card.inactive { opacity: .55; }
  .qr-card.inactive:hover { transform: none; }

  /* Closed overlay badge */
  .closed-badge {
    position: absolute; top: 12px; right: 12px; z-index: 2;
    background: #E53030; color: #fff;
    font-size: .6rem; font-weight: 500; letter-spacing: .1em;
    text-transform: uppercase; padding: 4px 10px; border-radius: 20px;
  }

  .qr-card-body { padding: 20px 18px 16px; text-align: center; }
  .qr-tnum { font-size: .6rem; letter-spacing: .18em; text-transform: uppercase; color: var(--choco-light); margin-bottom: 2px; }
  .qr-tname {
    font-family: 'Kanit', sans-serif; font-size: 1.5rem; font-weight: 500;
    color: var(--choco); margin-bottom: 16px;
  }

  .qr-box {
    width: 140px; height: 140px;
    margin: 0 auto 14px;
    background: var(--cream-deep);
    border: 1px solid rgba(44,24,16,.1);
    border-radius: 12px; padding: 8px;
    position: relative;
  }
  .qr-box img { width: 100%; height: 100%; display: block; }
  .qr-box .qr-blur {
    position: absolute; inset: 0; border-radius: 12px;
    background: rgba(245,240,232,.85); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    font-size: .75rem; color: #E53030; font-family: 'Prompt', sans-serif;
    letter-spacing: .04em;
  }

  .qr-url {
    font-size: .6rem; color: var(--choco-light); word-break: break-all;
    margin-bottom: 14px; background: var(--cream-deep);
    padding: 6px 10px; border-radius: 6px; line-height: 1.5;
  }

  /* Action buttons */
  .qr-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; padding: 0 2px 2px; }
  .qa-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 5px;
    padding: 8px; border-radius: 8px; font-family: 'Prompt', sans-serif;
    font-size: .72rem; cursor: pointer; transition: all .15s;
    border: 1px solid transparent; text-decoration: none;
  }
  .qa-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; }
  .qa-download { background: var(--choco); color: var(--cream); }
  .qa-download:hover { background: var(--choco-mid); }
  .qa-test { background: transparent; border-color: rgba(44,24,16,.15); color: var(--choco); }
  .qa-test:hover { background: var(--cream-deep); }
  .qa-toggle-on  { background: #FDE8CC; color: #9A4010; border-color: rgba(154,64,16,.2); }
  .qa-toggle-on:hover { background: #FACACC; color: #802020; }
  .qa-toggle-off { background: var(--bot-pale); color: var(--botanical); border-color: rgba(74,94,58,.2); }
  .qa-toggle-off:hover { background: #B8D4B0; }
  .qa-rename { background: transparent; border-color: rgba(44,24,16,.15); color: var(--choco); }
  .qa-rename:hover { background: var(--cream-deep); }
  .qa-delete { background: transparent; border-color: rgba(200,50,50,.2); color: #C03030; }
  .qa-delete:hover { background: #FDE8E8; }

  .qa-btn:disabled { opacity: .4; cursor: not-allowed; }

  /* Inline rename form (hidden by default) */
  .rename-form {
    display: none; padding: 0 2px 10px;
    animation: fadeIn .2s ease;
  }
  .rename-form.open { display: flex; gap: 6px; }
  .rename-input {
    flex: 1; background: var(--cream-deep); border: 1px solid rgba(44,24,16,.2);
    border-radius: 8px; padding: 8px 10px; font-family: 'Prompt', sans-serif;
    font-size: .82rem; color: var(--choco); outline: none;
  }
  .rename-input:focus { border-color: var(--choco-light); }
  .rename-confirm {
    background: var(--choco); color: var(--cream); border: none;
    border-radius: 8px; padding: 8px 12px; font-size: .78rem;
    font-family: 'Prompt', sans-serif; cursor: pointer;
  }
  .rename-cancel {
    background: transparent; border: 1px solid rgba(44,24,16,.15);
    border-radius: 8px; padding: 8px 10px; font-size: .78rem;
    font-family: 'Prompt', sans-serif; cursor: pointer; color: var(--choco-light);
  }

  /* ── Toast / Alert ── */
  .flash-msg {
    padding: 12px 18px; border-radius: var(--radius-sm); margin-bottom: 18px;
    font-size: .85rem; display: flex; align-items: center; gap: 10px;
  }
  .flash-success { background: var(--bot-pale); color: var(--botanical); border: 1px solid rgba(74,94,58,.2); }
  .flash-error   { background: #F5DADA; color: #802020; border: 1px solid rgba(128,32,32,.2); }
  .flash-warning { background: var(--gold-pale); color: #7A5010; border: 1px solid rgba(201,168,76,.3); }

  /* ── Print ── */
  @media print {
    .sidebar, .topbar, .stats-row, .add-card, .filter-tabs, .qa-toggle-on,
    .qa-toggle-off, .qa-rename, .qa-delete, .qa-test, .flash-msg { display: none !important; }
    .main { margin-left: 0 !important; }
    .qr-grid { grid-template-columns: repeat(3, 1fr); gap: 12px; }
    .qr-card { break-inside: avoid; box-shadow: none; border: 1px solid #ddd; }
    .qr-card.inactive { display: none; }
  }

  @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-title serif">จัดการ QR Code ประจำโต๊ะ</div>
    <div style="display:flex;gap:10px;">
      <button onclick="window.print()" style="display:inline-flex;align-items:center;gap:6px;background:transparent;border:1px solid rgba(44,24,16,.2);padding:8px 16px;border-radius:30px;font-family:'Prompt',sans-serif;font-size:.78rem;cursor:pointer;color:var(--choco);">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="1.8"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        พิมพ์ทั้งหมด
      </button>
    </div>
  </div>

  <div class="page-content">

    <!-- Flash message -->
    <?php if ($msg): ?>
    <div class="flash-msg flash-<?= $msg_type ?> animate-up">
      <?php if ($msg_type === 'success'): ?>✅<?php elseif ($msg_type === 'error'): ?>❌<?php else: ?>⚠️<?php endif; ?>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row animate-up">
      <div class="stat-sm">
        <div class="stat-sm-icon all">🪑</div>
        <div>
          <div class="stat-sm-val"><?= $total ?></div>
          <div class="stat-sm-lbl">โต๊ะทั้งหมด</div>
        </div>
      </div>
      <div class="stat-sm">
        <div class="stat-sm-icon open">✅</div>
        <div>
          <div class="stat-sm-val" style="color:var(--botanical);"><?= $active_cnt ?></div>
          <div class="stat-sm-lbl">โต๊ะที่เปิดใช้งาน</div>
        </div>
      </div>
      <div class="stat-sm">
        <div class="stat-sm-icon closed">🚫</div>
        <div>
          <div class="stat-sm-val" style="color:#C03030;"><?= $inactive_cnt ?></div>
          <div class="stat-sm-lbl">โต๊ะที่ปิดใช้งาน</div>
        </div>
      </div>
    </div>

    <!-- Add new table -->
    <div class="add-card animate-up delay-1">
      <svg viewBox="0 0 24 24" width="22" height="22" stroke="var(--choco-light)" fill="none" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      <label>เพิ่มโต๊ะใหม่</label>
      <form method="post" style="display:contents;">
        <input type="hidden" name="action" value="add">
        <input type="text" name="table_name" class="add-input" placeholder='เช่น "โต๊ะ 4", "VIP 1", "ระเบียง A"' required>
        <button type="submit" class="btn-add">
          <svg viewBox="0 0 24 24" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          เพิ่มโต๊ะ
        </button>
      </form>
    </div>

    <!-- Filter tabs -->
    <div class="filter-tabs animate-up delay-1">
      <button class="ftab active" onclick="filterTables('all', this)">ทั้งหมด (<?= $total ?>)</button>
      <button class="ftab" onclick="filterTables('open', this)">เปิดอยู่ (<?= $active_cnt ?>)</button>
      <button class="ftab" onclick="filterTables('closed', this)">ปิดอยู่ (<?= $inactive_cnt ?>)</button>
    </div>

    <!-- QR Grid -->
    <div class="qr-grid animate-up delay-2" id="qrGrid">
      <?php foreach ($tables as $t):
        $table_url = $app_url . '/index.php?table=' . $t['table_id'];
        $qr_url    = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&color=2C1810&bgcolor=FDFAF5&data=' . urlencode($table_url);
        $is_active = (int)$t['is_active'];
      ?>
      <div class="qr-card <?= $is_active ? '' : 'inactive' ?>"
           data-status="<?= $is_active ? 'open' : 'closed' ?>">

        <?php if (!$is_active): ?>
        <div class="closed-badge">ปิดใช้งาน</div>
        <?php endif; ?>

        <div class="qr-card-body">
          <div class="qr-tnum">Table</div>
          <div class="qr-tname" id="tname-<?= $t['table_id'] ?>">
            <?= htmlspecialchars($t['table_name']) ?>
          </div>

          <div class="qr-box">
            <?php if ($is_active): ?>
            <img src="<?= $qr_url ?>" alt="QR <?= htmlspecialchars($t['table_name']) ?>" loading="lazy">
            <?php else: ?>
            <img src="<?= $qr_url ?>" alt="" loading="lazy" style="filter:grayscale(1);opacity:.4;">
            <div class="qr-blur">🚫 ปิดชั่วคราว</div>
            <?php endif; ?>
          </div>

          <div class="qr-url"><?= htmlspecialchars($table_url) ?></div>

          <!-- Rename form (hidden by default) -->
          <form method="post" class="rename-form" id="renameForm-<?= $t['table_id'] ?>">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="table_id" value="<?= $t['table_id'] ?>">
            <input type="text" name="table_name" class="rename-input"
                   value="<?= htmlspecialchars($t['table_name']) ?>" required>
            <button type="submit" class="rename-confirm">✓</button>
            <button type="button" class="rename-cancel"
                    onclick="closeRename(<?= $t['table_id'] ?>)">✕</button>
          </form>

          <!-- Action buttons -->
          <div class="qr-actions">
            <?php if ($is_active): ?>
            <a href="<?= $qr_url ?>" download="qr_<?= $t['table_id'] ?>.png" class="qa-btn qa-download">
              <svg viewBox="0 0 24 24" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              ดาวน์โหลด
            </a>
            <a href="<?= htmlspecialchars($table_url) ?>" target="_blank" class="qa-btn qa-test">
              <svg viewBox="0 0 24 24" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
              ทดสอบ
            </a>
            <?php else: ?>
            <span class="qa-btn qa-download" style="opacity:.35;cursor:not-allowed;">
              <svg viewBox="0 0 24 24" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              ดาวน์โหลด
            </span>
            <span class="qa-btn qa-test" style="opacity:.35;cursor:not-allowed;">ทดสอบ</span>
            <?php endif; ?>

            <!-- Toggle open/close -->
            <form method="post" style="display:contents;">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="table_id" value="<?= $t['table_id'] ?>">
              <button type="submit" class="qa-btn <?= $is_active ? 'qa-toggle-on' : 'qa-toggle-off' ?>">
                <?php if ($is_active): ?>
                <svg viewBox="0 0 24 24" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                ปิดโต๊ะ
                <?php else: ?>
                <svg viewBox="0 0 24 24" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                เปิดโต๊ะ
                <?php endif; ?>
              </button>
            </form>

            <!-- Rename -->
            <button type="button" class="qa-btn qa-rename"
                    onclick="openRename(<?= $t['table_id'] ?>)">
              <svg viewBox="0 0 24 24" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              แก้ชื่อ
            </button>

            <!-- Delete -->
            <form method="post" style="display:contents;"
                  onsubmit="return confirm('ลบโต๊ะ \"<?= htmlspecialchars($t['table_name']) ?>\" ?\n\n(ออเดอร์เก่าจะยังคงอยู่ในระบบ)');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="table_id" value="<?= $t['table_id'] ?>">
              <button type="submit" class="qa-btn qa-delete">
                <svg viewBox="0 0 24 24" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                ลบโต๊ะ
              </button>
            </form>
          </div><!-- /qr-actions -->
        </div><!-- /qr-card-body -->
      </div><!-- /qr-card -->
      <?php endforeach; ?>
    </div><!-- /qr-grid -->

    <?php if (empty($tables)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--choco-light);">
      <div style="font-size:2rem;opacity:.25;margin-bottom:12px;">🪑</div>
      <div style="font-size:1rem;">ยังไม่มีโต๊ะในระบบ เพิ่มโต๊ะแรกได้เลย</div>
    </div>
    <?php endif; ?>

  </div><!-- /page-content -->
</div><!-- /main -->

<script>
// ── Filter tabs ──────────────────────────────────────────────────────────────
function filterTables(type, btn) {
  document.querySelectorAll('.ftab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.qr-card').forEach(card => {
    if (type === 'all') {
      card.style.display = '';
    } else if (type === 'open') {
      card.style.display = card.dataset.status === 'open' ? '' : 'none';
    } else {
      card.style.display = card.dataset.status === 'closed' ? '' : 'none';
    }
  });
}

// ── Inline rename ────────────────────────────────────────────────────────────
function openRename(id) {
  document.getElementById('renameForm-' + id).classList.add('open');
}
function closeRename(id) {
  document.getElementById('renameForm-' + id).classList.remove('open');
}
</script>
</body>
</html>
