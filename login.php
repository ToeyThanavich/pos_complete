<?php
session_start();
include 'connect.php';
if (isset($_SESSION['admin_id'])) { header("Location: admin/dashboard.php"); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $conn->prepare("SELECT id, fullname, role, password FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $valid = $user && ($user['password'] === $password || (strlen($user['password']) > 20 && password_verify($password, $user['password'])));
    if ($valid) {
        $_SESSION['admin_id']   = $user['id'];
        $_SESSION['admin_name'] = $user['fullname'];
        $_SESSION['admin_role'] = $user['role'];
        header("Location: admin/dashboard.php"); exit;
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>Staff Login — Black Sheep</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    body {
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
      background: var(--choco); padding: 24px;
    }
    body::after {
      content: ''; position: fixed; inset: 0;
      background: radial-gradient(ellipse at 70% 30%, rgba(92,61,82,.18) 0%, transparent 55%),
                  radial-gradient(ellipse at 20% 80%, rgba(74,94,58,.1) 0%, transparent 50%);
      pointer-events: none;
    }
    .login-wrap { width: 100%; max-width: 360px; position: relative; z-index: 1; }
    .login-header { text-align: center; margin-bottom: 32px; }
    .lock-icon {
      width: 52px; height: 52px; border-radius: 50%;
      background: rgba(245,240,232,.08); border: 1px solid rgba(245,240,232,.15);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 18px;
    }
    .lock-icon svg { width: 22px; height: 22px; stroke: rgba(245,240,232,.6); fill: none; }
    .login-title { font-family: 'Cormorant Garamond', serif; font-size: 2rem; font-weight: 300; color: var(--cream); margin-bottom: 4px; }
    .login-sub { font-size: .7rem; letter-spacing: .14em; text-transform: uppercase; color: rgba(245,240,232,.3); }
    .login-card {
      background: rgba(245,240,232,.04); border: 1px solid rgba(245,240,232,.1);
      border-radius: var(--radius); padding: 28px 24px; backdrop-filter: blur(12px);
    }
    .field-wrap { margin-bottom: 18px; }
    .field-label { font-size: .62rem; letter-spacing: .16em; text-transform: uppercase; color: rgba(245,240,232,.4); margin-bottom: 7px; display: block; }
    .field-input {
      width: 100%; background: rgba(245,240,232,.07); border: 1px solid rgba(245,240,232,.15);
      border-radius: var(--radius-sm); padding: 13px 16px; font-family: 'Jost', sans-serif;
      font-size: .9rem; font-weight: 300; color: var(--cream); outline: none; transition: border-color .2s;
    }
    .field-input:focus { border-color: rgba(201,168,76,.5); }
    .field-input::placeholder { color: rgba(245,240,232,.2); }
    .login-btn {
      width: 100%; margin-top: 8px; background: var(--gold); color: var(--choco);
      border: none; padding: 16px; border-radius: 50px; font-family: 'Jost', sans-serif;
      font-size: .75rem; font-weight: 500; letter-spacing: .16em; text-transform: uppercase;
      cursor: pointer; transition: all .2s;
    }
    .login-btn:hover { background: #d4b056; transform: translateY(-1px); }
    .err-msg {
      background: rgba(245,218,218,.1); border: 1px solid rgba(200,80,80,.3);
      color: #F5A0A0; padding: 10px 14px; border-radius: var(--radius-sm);
      font-size: .78rem; margin-bottom: 16px; text-align: center;
    }
    .back-link { text-align: center; margin-top: 20px; }
    .back-link a { font-size: .65rem; letter-spacing: .12em; text-transform: uppercase; color: rgba(245,240,232,.25); transition: color .2s; }
    .back-link a:hover { color: rgba(245,240,232,.5); }
  </style>
</head>
<body>
<div class="login-wrap animate-up">
  <div class="login-header">
    <div class="lock-icon">
      <svg viewBox="0 0 24 24" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
    <div class="login-title">Staff Login</div>
    <div class="login-sub">Black Sheep in the Garden</div>
  </div>

  <div class="login-card">
    <?php if ($error): ?>
    <div class="err-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="field-wrap">
        <label class="field-label">ชื่อผู้ใช้</label>
        <input type="text" name="username" class="field-input" placeholder="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
      </div>
      <div class="field-wrap">
        <label class="field-label">รหัสผ่าน</label>
        <input type="password" name="password" class="field-input" placeholder="••••••••" required>
      </div>
      <button type="submit" class="login-btn">เข้าสู่ระบบ →</button>
    </form>
  </div>
  <div class="back-link"><a href="index.php">← กลับหน้าลูกค้า</a></div>
</div>
</body>
</html>
