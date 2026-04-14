<?php
session_start();
include 'connect.php';

// Ensure the customer_feedback table exists
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

$table_id = $_SESSION['table_id'] ?? 0;
$success = false;
$error = '';

// Find current active order_code for this table (to attach to feedback)
$order_code = '';
if ($table_id > 0) {
    $r = $conn->query("SELECT order_code FROM orders WHERE table_id=$table_id AND status NOT IN ('completed','cancelled') ORDER BY created_at DESC LIMIT 1");
    if ($r && $r->num_rows > 0) $order_code = $r->fetch_assoc()['order_code'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type    = in_array($_POST['feedback_type'] ?? '', ['complaint','suggestion','compliment'])
               ? $_POST['feedback_type'] : 'complaint';
    $message = trim($_POST['message'] ?? '');
    if (strlen($message) < 5) {
        $error = 'กรุณากรอกข้อความอย่างน้อย 5 ตัวอักษร';
    } else {
        $msg_esc = $conn->real_escape_string($message);
        $code_esc = $conn->real_escape_string($order_code);
        $conn->query("INSERT INTO customer_feedback (table_id, order_code, feedback_type, message)
                      VALUES ($table_id, '$code_esc', '$type', '$msg_esc')");
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>แจ้งปัญหา / ความคิดเห็น — Black Sheep</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    body { min-height:100vh; padding-bottom:40px; }
    .page-header { padding:20px 20px 0; display:flex; align-items:center; gap:14px; margin-bottom:28px; }
    .back-btn { width:40px; height:40px; border-radius:50%; border:1px solid rgba(44,24,16,.15); background:transparent; display:flex; align-items:center; justify-content:center; cursor:pointer; text-decoration:none; transition:all .15s; }
    .back-btn:hover { background:var(--cream-deep); }
    .back-btn svg { width:16px; height:16px; stroke:var(--choco); fill:none; }
    .page-title { font-size:1.8rem; font-weight:300; }
    .content { padding:0 20px; }
    .fb-card { background:#FDFAF5; border-radius:var(--radius); padding:22px; border:1px solid rgba(44,24,16,.08); box-shadow:var(--shadow-soft); margin-bottom:20px; }
    .fb-type-row { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
    .fb-type-btn { flex:1; min-width:90px; padding:10px 8px; border:1px solid rgba(44,24,16,.14); border-radius:10px; background:transparent; font-family:'Jost',sans-serif; font-size:.72rem; font-weight:400; cursor:pointer; text-align:center; transition:all .15s; color:var(--choco-light); }
    .fb-type-btn.selected { border-color:var(--choco); background:var(--choco); color:var(--cream); }
    .fb-label { font-size:.65rem; letter-spacing:.14em; text-transform:uppercase; color:var(--choco-light); margin-bottom:8px; display:block; }
    .fb-textarea { width:100%; border:1px solid rgba(44,24,16,.14); border-radius:10px; padding:12px 14px; font-family:'Jost',sans-serif; font-size:.85rem; background:white; outline:none; resize:vertical; min-height:120px; color:var(--choco); box-sizing:border-box; }
    .fb-textarea:focus { border-color:var(--choco); }
    .fb-submit { width:100%; background:var(--choco); color:var(--cream); border:none; padding:15px; border-radius:50px; font-family:'Jost',sans-serif; font-size:.75rem; font-weight:400; letter-spacing:.14em; text-transform:uppercase; cursor:pointer; margin-top:14px; transition:all .2s; }
    .fb-submit:hover { background:var(--choco-mid,#4a2e20); }
    .error-msg { background:#fde8e8; border:1px solid rgba(192,57,43,.2); color:#c0392b; padding:10px 14px; border-radius:8px; font-size:.8rem; margin-bottom:14px; }
    /* success */
    .success-wrap { text-align:center; padding:40px 0 20px; }
    .success-icon { width:72px; height:72px; border-radius:50%; background:#e8f3e8; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; }
    .success-icon svg { width:32px; height:32px; stroke:var(--botanical,#4A5E3A); fill:none; stroke-width:2; }
    .success-title { font-family:'Cormorant Garamond',serif; font-size:1.8rem; font-weight:300; margin-bottom:10px; }
    .success-sub { font-size:.82rem; font-weight:300; color:var(--choco-light); line-height:1.7; margin-bottom:32px; }
    .back-btn-full { display:block; width:100%; background:transparent; color:var(--choco); border:1px solid rgba(44,24,16,.2); padding:15px; border-radius:50px; font-family:'Jost',sans-serif; font-size:.72rem; font-weight:400; letter-spacing:.12em; text-transform:uppercase; text-align:center; text-decoration:none; transition:all .2s; }
    .back-btn-full:hover { background:var(--cream-deep); }
  </style>
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
  <h1 class="page-title serif">แจ้งปัญหา</h1>
</div>

<div class="content animate-up delay-1">

<?php if ($success): ?>
  <div class="success-wrap animate-up">
    <div class="success-icon">
      <svg viewBox="0 0 24 24" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div class="success-title serif">ขอบคุณสำหรับความคิดเห็น</div>
    <p class="success-sub">ทีมงานได้รับข้อความของคุณแล้ว<br>เราจะปรับปรุงการบริการให้ดียิ่งขึ้น 🙏</p>
  </div>
  <a href="menu_detail.php" class="back-btn-full">← กลับหน้าเมนู</a>

<?php else: ?>

  <p style="font-size:.82rem;font-weight:300;color:var(--choco-light);line-height:1.7;margin-bottom:20px;">
    มีปัญหาหรือข้อเสนอแนะอะไรหรือไม่? ส่งข้อความหาเราได้เลย ทีมงานจะรีบดำเนินการโดยเร็ว
  </p>

  <?php if ($error): ?>
  <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="fb-card">
    <form method="post">
      <span class="fb-label">ประเภท</span>
      <div class="fb-type-row" id="typeRow">
        <button type="button" class="fb-type-btn <?= (!isset($_POST['feedback_type']) || $_POST['feedback_type']==='complaint')?'selected':'' ?>"
          data-val="complaint" onclick="selType(this)">⚠️ แจ้งปัญหา</button>
        <button type="button" class="fb-type-btn <?= (($_POST['feedback_type']??'')==='suggestion')?'selected':'' ?>"
          data-val="suggestion" onclick="selType(this)">💡 ข้อเสนอแนะ</button>
        <button type="button" class="fb-type-btn <?= (($_POST['feedback_type']??'')==='compliment')?'selected':'' ?>"
          data-val="compliment" onclick="selType(this)">⭐ ชมเชย</button>
      </div>
      <input type="hidden" name="feedback_type" id="fbType"
        value="<?= htmlspecialchars($_POST['feedback_type'] ?? 'complaint') ?>">

      <span class="fb-label" style="margin-top:4px;">ข้อความ</span>
      <textarea name="message" class="fb-textarea"
        placeholder="เช่น อาหารช้ามาก, พนักงานไม่สุภาพ, ห้องน้ำไม่สะอาด, ชอบบรรยากาศมาก…"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>

      <?php if ($table_id > 0): ?>
      <div style="font-size:.65rem;color:var(--choco-light);margin-top:8px;opacity:.6;">โต๊ะ <?= (int)$table_id ?><?= $order_code ? ' · ' . htmlspecialchars($order_code) : '' ?></div>
      <?php endif; ?>

      <button type="submit" class="fb-submit">ส่งข้อความ →</button>
    </form>
  </div>

<?php endif; ?>
</div>

<script>
function selType(el) {
  document.querySelectorAll('.fb-type-btn').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('fbType').value = el.dataset.val;
}
</script>
</body>
</html>
