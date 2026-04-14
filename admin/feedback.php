<?php
require 'auth.php';
include '../connect.php';
include '../functions.php';

// Ensure table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS customer_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        table_id INT DEFAULT NULL,
        order_code VARCHAR(20) DEFAULT NULL,
        feedback_type ENUM('complaint','suggestion','compliment') DEFAULT 'complaint',
        message TEXT NOT NULL,
        status ENUM('unread','read','resolved') DEFAULT 'unread',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// POST: update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $fid    = (int)$_POST['feedback_id'];
    $status = in_array($_POST['status'], ['unread','read','resolved']) ? $_POST['status'] : 'read';
    $conn->query("UPDATE customer_feedback SET status='$status' WHERE id=$fid");
    header("Location: feedback.php"); exit;
}

// POST: delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_feedback'])) {
    $fid = (int)$_POST['feedback_id'];
    $conn->query("DELETE FROM customer_feedback WHERE id=$fid");
    header("Location: feedback.php"); exit;
}

// Filter
$filter = in_array($_GET['filter'] ?? 'all', ['all','unread','read','resolved','complaint','suggestion','compliment'])
          ? ($_GET['filter'] ?? 'all') : 'all';

$where = '';
if ($filter === 'unread')    $where = "WHERE status='unread'";
elseif ($filter === 'read')  $where = "WHERE status='read'";
elseif ($filter === 'resolved') $where = "WHERE status='resolved'";
elseif (in_array($filter, ['complaint','suggestion','compliment']))
    $where = "WHERE feedback_type='$filter'";

$feedbacks = $conn->query("SELECT * FROM customer_feedback $where ORDER BY created_at DESC")
    ->fetch_all(MYSQLI_ASSOC);

$counts = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(status='unread') as unread,
        SUM(feedback_type='complaint') as complaints,
        SUM(feedback_type='suggestion') as suggestions,
        SUM(feedback_type='compliment') as compliments
    FROM customer_feedback
")->fetch_assoc();

$type_icon  = ['complaint'=>'⚠️','suggestion'=>'💡','compliment'=>'⭐'];
$type_label = ['complaint'=>'แจ้งปัญหา','suggestion'=>'ข้อเสนอแนะ','compliment'=>'ชมเชย'];
$status_cls = ['unread'=>'bs-badge-pending','read'=>'bs-badge-cooking','resolved'=>'bs-badge-serving'];
$status_label = ['unread'=>'ยังไม่อ่าน','read'=>'อ่านแล้ว','resolved'=>'แก้ไขแล้ว'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ความคิดเห็นลูกค้า — Black Sheep</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../assets/admin.css">
  <style>
  .fb-stats { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; }
  .fb-stat-card { flex:1; min-width:120px; background:#FDFAF5; border:1px solid rgba(44,24,16,.08); border-radius:12px; padding:14px 16px; }
  .fb-stat-label { font-size:.65rem; letter-spacing:.1em; text-transform:uppercase; color:var(--choco-light); margin-bottom:4px; }
  .fb-stat-val { font-family:'Cormorant Garamond',serif; font-size:1.6rem; color:var(--choco); }
  .fb-stat-val.alert { color:#c0392b; }

  .filter-bar { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
  .filter-btn { padding:6px 14px; border-radius:20px; border:1px solid rgba(44,24,16,.15); background:transparent; font-size:.7rem; font-weight:500; cursor:pointer; transition:all .15s; color:var(--choco-light); text-decoration:none; }
  .filter-btn.active, .filter-btn:hover { background:var(--choco); color:var(--cream); border-color:var(--choco); }

  .fb-list { display:flex; flex-direction:column; gap:10px; }
  .fb-card { background:#FDFAF5; border:1px solid rgba(44,24,16,.08); border-radius:14px; padding:16px 18px; }
  .fb-card.unread { border-left:3px solid var(--gold); }
  .fb-card.resolved { opacity:.65; }
  .fb-card-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; gap:10px; }
  .fb-card-meta { font-size:.65rem; color:var(--choco-light); margin-top:3px; }
  .fb-card-msg { font-size:.85rem; color:var(--choco); line-height:1.6; margin-bottom:12px; white-space:pre-wrap; word-break:break-word; }
  .fb-card-actions { display:flex; gap:6px; flex-wrap:wrap; }
  .fb-action-btn { padding:5px 12px; border-radius:8px; border:1px solid rgba(44,24,16,.14); background:transparent; font-size:.68rem; cursor:pointer; transition:all .12s; color:var(--choco-light); }
  .fb-action-btn:hover { background:var(--cream-deep); color:var(--choco); }
  .fb-action-btn.resolve { border-color:var(--botanical,#4A5E3A); color:var(--botanical,#4A5E3A); }
  .fb-action-btn.resolve:hover { background:var(--botanical,#4A5E3A); color:white; }
  .fb-action-btn.del { border-color:rgba(192,57,43,.3); color:#c0392b; }
  .fb-action-btn.del:hover { background:#c0392b; color:white; }
  .empty-state { text-align:center; padding:60px; color:var(--choco-light); opacity:.4; font-size:.85rem; }
  </style>
</head>
<body>
  <?php include 'sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">💬 ความคิดเห็นลูกค้า</div>
      <div style="font-size:.78rem;color:var(--choco-light);"><?= date('d/m/Y') ?></div>
    </div>

    <div class="page-content">

      <!-- Stats -->
      <div class="fb-stats">
        <div class="fb-stat-card">
          <div class="fb-stat-label">ทั้งหมด</div>
          <div class="fb-stat-val"><?= (int)($counts['total'] ?? 0) ?></div>
        </div>
        <div class="fb-stat-card">
          <div class="fb-stat-label">ยังไม่อ่าน</div>
          <div class="fb-stat-val <?= ($counts['unread'] ?? 0) > 0 ? 'alert' : '' ?>"><?= (int)($counts['unread'] ?? 0) ?></div>
        </div>
        <div class="fb-stat-card">
          <div class="fb-stat-label">⚠️ ปัญหา</div>
          <div class="fb-stat-val"><?= (int)($counts['complaints'] ?? 0) ?></div>
        </div>
        <div class="fb-stat-card">
          <div class="fb-stat-label">💡 ข้อเสนอแนะ</div>
          <div class="fb-stat-val"><?= (int)($counts['suggestions'] ?? 0) ?></div>
        </div>
        <div class="fb-stat-card">
          <div class="fb-stat-label">⭐ ชมเชย</div>
          <div class="fb-stat-val"><?= (int)($counts['compliments'] ?? 0) ?></div>
        </div>
      </div>

      <!-- Filter -->
      <div class="filter-bar">
        <?php
        $filters = [
          'all'         => 'ทั้งหมด',
          'unread'      => 'ยังไม่อ่าน',
          'read'        => 'อ่านแล้ว',
          'resolved'    => 'แก้ไขแล้ว',
          'complaint'   => '⚠️ ปัญหา',
          'suggestion'  => '💡 ข้อเสนอแนะ',
          'compliment'  => '⭐ ชมเชย',
        ];
        foreach ($filters as $k => $label):
        ?>
        <a href="?filter=<?= $k ?>" class="filter-btn <?= $filter===$k?'active':'' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>

      <!-- List -->
      <div class="fb-list">
        <?php if (empty($feedbacks)): ?>
        <div class="empty-state">✓ ไม่มีข้อความในตัวกรองนี้</div>
        <?php else: ?>
        <?php foreach ($feedbacks as $fb): ?>
        <div class="fb-card <?= $fb['status'] ?>">
          <div class="fb-card-top">
            <div>
              <span style="font-weight:500;font-size:.85rem;">
                <?= $type_icon[$fb['feedback_type']] ?? '' ?> <?= $type_label[$fb['feedback_type']] ?? $fb['feedback_type'] ?>
              </span>
              <div class="fb-card-meta">
                <?= $fb['order_code'] ? 'ออเดอร์ ' . htmlspecialchars($fb['order_code']) . ' · ' : '' ?>
                <?= $fb['table_id'] ? 'โต๊ะ ' . (int)$fb['table_id'] . ' · ' : '' ?>
                <?= date('d/m/Y H:i', strtotime($fb['created_at'])) ?>
              </div>
            </div>
            <span class="bs-badge <?= $status_cls[$fb['status']] ?? '' ?>"><?= $status_label[$fb['status']] ?? $fb['status'] ?></span>
          </div>

          <div class="fb-card-msg"><?= htmlspecialchars($fb['message']) ?></div>

          <div class="fb-card-actions">
            <?php if ($fb['status'] === 'unread'): ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
              <input type="hidden" name="status" value="read">
              <button type="submit" name="update_status" class="fb-action-btn">✓ ทำเครื่องหมายว่าอ่านแล้ว</button>
            </form>
            <?php endif; ?>
            <?php if ($fb['status'] !== 'resolved'): ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
              <input type="hidden" name="status" value="resolved">
              <button type="submit" name="update_status" class="fb-action-btn resolve">✓ แก้ไขแล้ว</button>
            </form>
            <?php endif; ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('ลบข้อความนี้?')">
              <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
              <button type="submit" name="delete_feedback" class="fb-action-btn del">🗑 ลบ</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</body>
</html>
