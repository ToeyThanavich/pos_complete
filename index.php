<?php
session_start();
include 'connect.php';

// เมื่อมีการเข้าผ่านลิงก์ (เช่น สแกน QR Code แล้วมี ?table=1 ต่อท้าย)
if (isset($_GET['table']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $table_id = intval($_GET['table']);
    // เปลี่ยนจาก is_active เป็น status = 'available'
    $check = $conn->prepare("SELECT * FROM tables WHERE table_id = ? AND status = 'available'");
    $check->bind_param('i', $table_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['table_id'] = $table_id;
        header("Location: menu_detail.php");
        exit;
    } else {
        // เช็กว่าโต๊ะนี้มีอยู่จริงไหมแต่ถูกตั้งค่าเป็น occupied หรือ unavailable
        $exist = $conn->prepare("SELECT status FROM tables WHERE table_id = ?");
        $exist->bind_param('i', $table_id);
        $exist->execute();
        $row = $exist->get_result()->fetch_assoc();
        if ($row && $row['status'] !== 'available') {
            $error = "โต๊ะนี้ปิดให้บริการชั่วคราว หรือไม่พร้อมใช้งาน กรุณาติดต่อพนักงาน";
        }
    }
}

// เมื่อมีการกดปุ่มเลือกโต๊ะ หรือพิมพ์เลขโต๊ะแล้วกด Submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $table_id = intval($_POST['table_id']);
    // เปลี่ยนจาก is_active เป็น status = 'available'
    $check = $conn->prepare("SELECT * FROM tables WHERE table_id = ? AND status = 'available'");
    $check->bind_param('i', $table_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['table_id'] = $table_id;
        header("Location: menu_detail.php");
        exit;
    } else {
        $exist = $conn->prepare("SELECT status FROM tables WHERE table_id = ?");
        $exist->bind_param('i', $table_id);
        $exist->execute();
        $row = $exist->get_result()->fetch_assoc();
        if ($row && $row['status'] !== 'available') {
            $error = "โต๊ะนี้ปิดให้บริการชั่วคราว หรือไม่พร้อมใช้งาน กรุณาติดต่อพนักงาน";
        } else {
            $error = "ไม่พบโต๊ะนี้ในระบบ กรุณาลองใหม่";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>Black Sheep in the Garden</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
  /* CSS ทั้งหมดของเดิม (ไม่เปลี่ยนแปลง) */
  body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--choco);
    padding: 24px;
  }

  body::after {
    content: '';
    position: fixed;
    inset: 0;
    background: radial-gradient(ellipse at 30% 20%, rgba(201, 168, 76, .12) 0%, transparent 55%), radial-gradient(ellipse at 80% 80%, rgba(92, 61, 82, .15) 0%, transparent 50%);
    pointer-events: none;
  }

  .welcome-wrap {
    width: 100%;
    max-width: 380px;
    position: relative;
    z-index: 1;
  }

  .brand-block {
    text-align: center;
    margin-bottom: 36px;
  }

  .brand-emblem {
    width: 64px;
    height: 64px;
    background: var(--gold);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    box-shadow: 0 8px 30px rgba(201, 168, 76, .3);
  }

  .brand-emblem svg {
    width: 36px;
    height: 36px;
  }

  .brand-eyebrow {
    font-size: .6rem;
    font-weight: 400;
    letter-spacing: .28em;
    text-transform: uppercase;
    color: rgba(201, 168, 76, .7);
    margin-bottom: 10px;
  }

  .brand-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 2.4rem;
    font-weight: 300;
    color: var(--cream);
    line-height: 1.1;
    margin-bottom: 4px;
  }

  .brand-title em {
    font-style: italic;
    color: var(--gold);
  }

  .brand-sub {
    font-size: .72rem;
    font-weight: 300;
    letter-spacing: .12em;
    color: rgba(245, 240, 232, .4);
    text-transform: uppercase;
  }

  .card-wrap {
    background: rgba(245, 240, 232, .04);
    border: 1px solid rgba(245, 240, 232, .1);
    border-radius: var(--radius);
    padding: 28px 24px;
    backdrop-filter: blur(12px);
  }

  .section-label {
    font-size: .6rem;
    font-weight: 400;
    letter-spacing: .2em;
    text-transform: uppercase;
    color: rgba(245, 240, 232, .4);
    text-align: center;
    margin-bottom: 14px;
  }

  .table-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 24px;
  }

  .table-pick-btn {
    aspect-ratio: 1;
    background: rgba(245, 240, 232, .07);
    border: 1px solid rgba(245, 240, 232, .12);
    border-radius: var(--radius-sm);
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--cream);
    cursor: pointer;
    transition: all .2s;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .table-pick-btn:hover {
    background: var(--gold);
    color: var(--choco);
    border-color: var(--gold);
    transform: translateY(-2px);
  }

  .or-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
  }

  .or-line {
    flex: 1;
    height: 1px;
    background: rgba(245, 240, 232, .12);
  }

  .or-text {
    font-size: .6rem;
    color: rgba(245, 240, 232, .3);
    letter-spacing: .12em;
    text-transform: uppercase;
  }

  .manual-input {
    background: rgba(245, 240, 232, .07);
    border: 1px solid rgba(245, 240, 232, .15);
    border-radius: var(--radius-sm);
    padding: 14px 16px;
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.6rem;
    font-weight: 600;
    color: var(--cream);
    text-align: center;
    width: 100%;
    outline: none;
    transition: border-color .2s;
    -webkit-appearance: none;
  }

  .manual-input:focus {
    border-color: rgba(201, 168, 76, .6);
  }

  .manual-input::placeholder {
    color: rgba(245, 240, 232, .2);
    font-size: 1rem;
    font-family: 'Jost', sans-serif;
    font-weight: 300;
  }

  .confirm-btn {
    width: 100%;
    margin-top: 14px;
    background: var(--gold);
    color: var(--choco);
    border: none;
    padding: 16px;
    border-radius: 50px;
    font-family: 'Jost', sans-serif;
    font-size: .75rem;
    font-weight: 500;
    letter-spacing: .16em;
    text-transform: uppercase;
    cursor: pointer;
    transition: all .2s;
  }

  .confirm-btn:hover {
    background: #d4b056;
    transform: translateY(-1px);
  }

  .error-msg {
    background: rgba(245, 218, 218, .1);
    border: 1px solid rgba(200, 80, 80, .3);
    color: #F5A0A0;
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    font-size: .78rem;
    font-weight: 300;
    margin-bottom: 16px;
    text-align: center;
  }

  .staff-link {
    text-align: center;
    margin-top: 24px;
  }

  .staff-link a {
    font-size: .65rem;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: rgba(245, 240, 232, .25);
    transition: color .2s;
  }

  .staff-link a:hover {
    color: rgba(245, 240, 232, .5);
  }
  </style>
</head>

<body>
  <div class="welcome-wrap animate-up">
    <div class="brand-block">
      <div class="brand-emblem">
        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="20" cy="14" r="8" fill="#2C1810" opacity=".9" />
          <circle cx="13" cy="17" r="5" fill="#2C1810" opacity=".9" />
          <circle cx="27" cy="17" r="5" fill="#2C1810" opacity=".9" />
          <circle cx="20" cy="20" r="6.5" fill="#2C1810" opacity=".9" />
          <ellipse cx="16.5" cy="29" rx="2.2" ry="4.5" fill="#2C1810" opacity=".7" />
          <ellipse cx="23.5" cy="29" rx="2.2" ry="4.5" fill="#2C1810" opacity=".7" />
        </svg>
      </div>
      <div class="brand-eyebrow">Welcome to</div>
      <h1 class="brand-title">Black <em>Sheep</em><br>in the Garden</h1>
      <div class="brand-sub">QR Ordering · กรุณาเลือกโต๊ะ</div>
    </div>

    <div class="card-wrap">
      <?php if (!empty($error)): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
    // เปลี่ยนจาก is_active เป็น status = 'available'
    $tables_q = $conn->query("SELECT * FROM tables WHERE status = 'available' ORDER BY table_id");
    $tables_arr = [];
    while ($t = $tables_q->fetch_assoc()) $tables_arr[] = $t;
    ?>

      <?php if (!empty($tables_arr)): ?>
      <div class="section-label">เลือกโต๊ะของคุณ</div>
      <div class="table-grid">
        <?php foreach ($tables_arr as $t): ?>
        <form method="post" style="display:contents;">
          <input type="hidden" name="table_id" value="<?= $t['table_id'] ?>">
          <button type="submit" class="table-pick-btn"><?= htmlspecialchars($t['table_name']) ?></button>
        </form>
        <?php endforeach; ?>
      </div>
      <div class="or-divider">
        <div class="or-line"></div>
        <div class="or-text">หรือกรอกเลขโต๊ะ</div>
        <div class="or-line"></div>
      </div>
      <?php endif; ?>

      <form method="post">
        <input type="number" name="table_id" class="manual-input" placeholder="หมายเลขโต๊ะ" min="1" required>
        <button type="submit" class="confirm-btn">ยืนยันโต๊ะ →</button>
      </form>
    </div>

    <div class="staff-link">
      <a href="login.php">Staff Login</a>
    </div>
  </div>
</body>

</html>