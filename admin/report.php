<?php
require 'auth.php';
include '../connect.php';  // timezone Bangkok set here

// ====== Date range filter ======
$end_date   = isset($_GET['end_date'])   ? $_GET['end_date']   : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));

// Ensure order_type column exists
$col = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_type'");
if ($col->num_rows === 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN order_type VARCHAR(20) DEFAULT 'dine_in' AFTER status");
}

// ====== 1. สรุปจำนวนออเดอร์ตามช่วงเวลา ======
$q1 = $conn->prepare("
    SELECT
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_type='takeaway' THEN 1 ELSE 0 END) as takeaway,
        SUM(CASE WHEN order_type='dine_in' OR order_type IS NULL THEN 1 ELSE 0 END) as dine_in,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'
");
$q1->bind_param('ss', $start_date, $end_date);
$q1->execute();
$range_stats = $q1->get_result()->fetch_assoc();

// ====== 2. สรุปยอดขายตามช่วงเวลา ======
$q2 = $conn->prepare("
    SELECT
        COALESCE(SUM(oi.quantity * oi.price), 0) as total_rev,
        COALESCE(SUM(CASE WHEN o.order_type='dine_in' OR o.order_type IS NULL THEN oi.quantity * oi.price ELSE 0 END), 0) as dine_in_rev,
        COALESCE(SUM(CASE WHEN o.order_type='takeaway' THEN oi.quantity * oi.price ELSE 0 END), 0) as takeaway_rev
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status = 'completed'
");
$q2->bind_param('ss', $start_date, $end_date);
$q2->execute();
$revenue_details = $q2->get_result()->fetch_assoc();

// ====== 3. กราฟยอดขายรายวัน ======
$q3 = $conn->prepare("
    SELECT
        DATE(o.created_at) as date_val,
        COALESCE(SUM(oi.quantity * oi.price), 0) as daily_rev,
        COALESCE(SUM(CASE WHEN o.order_type='dine_in' OR o.order_type IS NULL THEN oi.quantity * oi.price ELSE 0 END), 0) as dine_in_rev,
        COALESCE(SUM(CASE WHEN o.order_type='takeaway' THEN oi.quantity * oi.price ELSE 0 END), 0) as takeaway_rev
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status = 'completed'
    GROUP BY DATE(o.created_at)
    ORDER BY date_val ASC
");
$q3->bind_param('ss', $start_date, $end_date);
$q3->execute();
$chart_res = $q3->get_result();

$chart_labels    = [];
$chart_dine_in   = [];
$chart_takeaway  = [];
while ($row = $chart_res->fetch_assoc()) {
    $chart_labels[]   = date('d/m', strtotime($row['date_val']));
    $chart_dine_in[]  = (float)$row['dine_in_rev'];
    $chart_takeaway[] = (float)$row['takeaway_rev'];
}

// ====== 4. สถิติเมนูขายดี (Top Menu) ======
$q4 = $conn->prepare("
    SELECT
        m.item_name,
        c.category_name,
        SUM(oi.quantity) as total_qty,
        SUM(oi.quantity * oi.price) as total_revenue,
        COUNT(DISTINCT oi.order_id) as order_count
    FROM order_items oi
    JOIN menu_items m ON oi.item_id = m.item_id
    JOIN categories c ON m.category_id = c.category_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status = 'completed'
    GROUP BY oi.item_id, m.item_name, c.category_name
    ORDER BY total_qty DESC
    LIMIT 10
");
$q4->bind_param('ss', $start_date, $end_date);
$q4->execute();
$top_menu_res = $q4->get_result();
$top_menus    = [];
while ($row = $top_menu_res->fetch_assoc()) {
    $top_menus[] = $row;
}

// สำหรับ chart เมนูขายดี
$menu_labels   = [];
$menu_qty_data = [];
$menu_rev_data = [];
foreach ($top_menus as $m) {
    $menu_labels[]   = $m['item_name'];
    $menu_qty_data[] = (int)$m['total_qty'];
    $menu_rev_data[] = (float)$m['total_revenue'];
}

// ====== 5. สถิติแยกตามหมวดหมู่ ======
$q5 = $conn->prepare("
    SELECT
        c.category_name,
        SUM(oi.quantity) as total_qty,
        COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
    FROM order_items oi
    JOIN menu_items m ON oi.item_id = m.item_id
    JOIN categories c ON m.category_id = c.category_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status = 'completed'
    GROUP BY c.category_id, c.category_name
    ORDER BY total_revenue DESC
");
$q5->bind_param('ss', $start_date, $end_date);
$q5->execute();
$cat_stats = $q5->get_result()->fetch_all(MYSQLI_ASSOC);

// ====== 6. สถิติแยกตามช่วงเวลา (ช่วง peak hour) ======
$q6 = $conn->prepare("
    SELECT
        HOUR(created_at) as hr,
        COUNT(*) as order_count,
        COALESCE(SUM(oi.quantity * oi.price), 0) as revenue
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status = 'completed'
    GROUP BY HOUR(o.created_at)
    ORDER BY hr ASC
");
$q6->bind_param('ss', $start_date, $end_date);
$q6->execute();
$hour_res = $q6->get_result();
$hour_labels = [];
$hour_orders = [];
while ($row = $hour_res->fetch_assoc()) {
    $hour_labels[] = sprintf('%02d:00', $row['hr']);
    $hour_orders[] = (int)$row['order_count'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>รายงาน — Black Sheep Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../assets/admin.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
  .filter-card {
    background: #fff;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid rgba(44,24,16,.06);
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
  }
  .filter-card label { font-size:.85rem; color:var(--choco-light); }
  .filter-card input[type="date"] {
    border: 1px solid rgba(44,24,16,.2);
    padding: 8px 12px;
    border-radius: 8px;
    font-family: 'Prompt', sans-serif;
    font-size: .85rem;
    outline: none;
    color: var(--choco);
  }
  .btn-filter {
    background: var(--choco);
    color: var(--cream);
    border: none;
    padding: 9px 22px;
    border-radius: 8px;
    font-family: 'Prompt', sans-serif;
    font-size: .85rem;
    cursor: pointer;
    transition: background .2s;
  }
  .btn-filter:hover { background: var(--mangosteen); }

  /* Summary cards */
  .summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 24px;
  }
  .sum-card {
    background: #FDFAF5;
    border-radius: var(--radius);
    padding: 18px 20px;
    border: 1px solid rgba(44,24,16,.06);
    box-shadow: var(--shadow-soft);
    position: relative;
    overflow: hidden;
  }
  .sum-card::after {
    content: '';
    position: absolute;
    top: -16px; right: -16px;
    width: 60px; height: 60px;
    border-radius: 50%;
    opacity: .08;
  }
  .sum-card.c1::after { background: var(--gold); }
  .sum-card.c2::after { background: var(--mangosteen); }
  .sum-card.c3::after { background: var(--botanical); }
  .sum-card.c4::after { background: var(--choco); }
  .sum-lbl { font-size: .6rem; letter-spacing: .14em; text-transform: uppercase; color: var(--choco-light); margin-bottom: 7px; }
  .sum-val { font-family: 'Kanit', sans-serif; font-size: 2rem; font-weight: 600; color: var(--choco); line-height: 1; margin-bottom: 4px; }
  .sum-sub { font-size: .72rem; color: var(--choco-light); }

  /* Section dividers */
  .report-section {
    background: #fff;
    border-radius: var(--radius);
    border: 1px solid rgba(44,24,16,.06);
    box-shadow: var(--shadow-soft);
    padding: 22px 24px;
    margin-bottom: 22px;
  }
  .report-sec-title {
    font-family: 'Kanit', sans-serif;
    font-size: 1.1rem;
    font-weight: 500;
    color: var(--choco);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(44,24,16,.07);
  }

  /* Top Menu Table */
  .menu-rank-table { width: 100%; border-collapse: collapse; }
  .menu-rank-table th {
    font-size: .62rem;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--choco-light);
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid rgba(44,24,16,.1);
    font-family: 'Prompt', sans-serif;
    font-weight: 400;
  }
  .menu-rank-table td {
    padding: 10px 12px;
    font-size: .85rem;
    color: var(--choco);
    border-bottom: 1px solid rgba(44,24,16,.05);
    vertical-align: middle;
  }
  .menu-rank-table tr:last-child td { border-bottom: none; }
  .menu-rank-table tr:hover td { background: var(--cream-deep); }
  .rank-num {
    width: 28px; height: 28px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Kanit', sans-serif;
    font-size: .8rem;
    font-weight: 600;
  }
  .rank-1 { background: #FFD700; color: #5A4000; }
  .rank-2 { background: #C0C0C0; color: #404040; }
  .rank-3 { background: #CD7F32; color: #fff; }
  .rank-other { background: var(--cream-deep); color: var(--choco-light); }

  .bar-wrap { display: flex; align-items: center; gap: 8px; }
  .bar-bg { flex: 1; height: 6px; background: var(--cream-dark); border-radius: 3px; overflow: hidden; }
  .bar-fill { height: 100%; border-radius: 3px; background: var(--choco); transition: width .5s; }

  /* Category pills */
  .cat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
  .cat-card {
    background: var(--cream-deep);
    border-radius: var(--radius-sm);
    padding: 14px 16px;
    position: relative;
    overflow: hidden;
  }
  .cat-card::before {
    content: '';
    position: absolute; top: 0; left: 0; bottom: 0;
    width: 3px; border-radius: 3px 0 0 3px;
  }
  .cat-card:nth-child(1)::before { background: var(--gold); }
  .cat-card:nth-child(2)::before { background: var(--mangosteen); }
  .cat-card:nth-child(3)::before { background: var(--botanical); }
  .cat-card:nth-child(4)::before { background: var(--choco-light); }
  .cat-name { font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: var(--choco-light); margin-bottom: 4px; }
  .cat-val { font-family: 'Kanit', sans-serif; font-size: 1.4rem; font-weight: 600; color: var(--choco); }
  .cat-sub { font-size: .7rem; color: var(--choco-light); margin-top: 2px; }

  /* Two-col layout */
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; margin-bottom: 22px; }
  .two-col .report-section { margin-bottom: 0; }

  /* No-data */
  .no-data { text-align: center; padding: 40px; color: var(--choco-light); font-size: .9rem; }
  </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-title serif">รายงานและสถิติยอดขาย</div>
    <div style="font-size:.78rem;color:var(--choco-light);"><?= date('d/m/Y H:i') ?> น. (Bangkok)</div>
  </div>

  <div class="page-content animate-up">

    <!-- Filter -->
    <form method="get" class="filter-card">
      <label>ตั้งแต่ :</label>
      <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
      <label>ถึง :</label>
      <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
      <button type="submit" class="btn-filter">🔍 ดูรายงาน</button>
      <span style="font-size:.78rem;color:var(--choco-light);">
        ข้อมูลวันที่ <?= date('d/m/Y', strtotime($start_date)) ?> — <?= date('d/m/Y', strtotime($end_date)) ?>
      </span>
    </form>

    <!-- Summary KPI Cards -->
    <div class="summary-grid animate-up delay-1">
      <div class="sum-card c1">
        <div class="sum-lbl">ออเดอร์ทั้งหมด</div>
        <div class="sum-val"><?= $range_stats['total_orders'] ?? 0 ?></div>
        <div class="sum-sub">รายการ (ไม่รวมยกเลิก)</div>
      </div>
      <div class="sum-card c2">
        <div class="sum-lbl">ทานที่ร้าน</div>
        <div class="sum-val"><?= $range_stats['dine_in'] ?? 0 ?></div>
        <div class="sum-sub">Dine In</div>
      </div>
      <div class="sum-card c3">
        <div class="sum-lbl">สั่งกลับบ้าน</div>
        <div class="sum-val"><?= $range_stats['takeaway'] ?? 0 ?></div>
        <div class="sum-sub">Take Away</div>
      </div>
      <div class="sum-card c4">
        <div class="sum-lbl">ยอดขายรวม</div>
        <div class="sum-val" style="font-size:1.5rem;">฿<?= number_format($revenue_details['total_rev'] ?? 0, 0) ?></div>
        <div class="sum-sub">จากออเดอร์ที่เสร็จแล้ว</div>
      </div>
    </div>

    <!-- Revenue breakdown -->
    <div class="report-section animate-up delay-1">
      <div class="report-sec-title">💰 สรุปยอดขาย แยกประเภทการรับอาหาร</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div>
          <?php
          $total_rev = (float)($revenue_details['total_rev'] ?? 0);
          $dine_rev  = (float)($revenue_details['dine_in_rev'] ?? 0);
          $take_rev  = (float)($revenue_details['takeaway_rev'] ?? 0);
          $dine_pct  = $total_rev > 0 ? round($dine_rev / $total_rev * 100) : 0;
          $take_pct  = $total_rev > 0 ? round($take_rev / $total_rev * 100) : 0;
          ?>
          <div style="margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:.85rem;">
              <span>🍽️ ทานที่ร้าน (Dine In)</span>
              <strong>฿<?= number_format($dine_rev, 0) ?> <span style="color:var(--choco-light);font-weight:300;">(<?= $dine_pct ?>%)</span></strong>
            </div>
            <div class="bar-bg" style="height:8px;"><div class="bar-fill" style="width:<?= $dine_pct ?>%;background:var(--mangosteen);"></div></div>
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:.85rem;">
              <span>🛍️ สั่งกลับบ้าน (Take Away)</span>
              <strong>฿<?= number_format($take_rev, 0) ?> <span style="color:var(--choco-light);font-weight:300;">(<?= $take_pct ?>%)</span></strong>
            </div>
            <div class="bar-bg" style="height:8px;"><div class="bar-fill" style="width:<?= $take_pct ?>%;background:var(--botanical);"></div></div>
          </div>
          <div style="border-top:1px dashed rgba(44,24,16,.15);margin-top:16px;padding-top:12px;display:flex;justify-content:space-between;font-family:'Kanit',sans-serif;font-weight:500;">
            <span>ยอดรวมสุทธิ</span>
            <span style="color:var(--mangosteen);font-size:1.1rem;">฿<?= number_format($total_rev, 0) ?></span>
          </div>
        </div>
        <div>
          <canvas id="typeChart" height="160"></canvas>
        </div>
      </div>
    </div>

    <!-- Daily Sales Chart -->
    <div class="report-section animate-up delay-2">
      <div class="report-sec-title">📈 กราฟยอดขายรายวัน แยกตามช่วงเวลา</div>
      <?php if (empty($chart_labels)): ?>
      <div class="no-data">ไม่มีข้อมูลยอดขายในช่วงเวลานี้</div>
      <?php else: ?>
      <canvas id="salesChart" height="90"></canvas>
      <?php endif; ?>
    </div>

    <!-- Peak Hour Chart -->
    <div class="two-col animate-up delay-2">
      <div class="report-section">
        <div class="report-sec-title">⏰ ช่วงเวลาที่มีคนสั่งมากที่สุด (Peak Hours)</div>
        <?php if (empty($hour_labels)): ?>
        <div class="no-data">ไม่มีข้อมูล</div>
        <?php else: ?>
        <canvas id="hourChart" height="180"></canvas>
        <?php endif; ?>
      </div>

      <!-- Category Stats -->
      <div class="report-section">
        <div class="report-sec-title">🗂️ ยอดขายแยกตามหมวดหมู่</div>
        <?php if (empty($cat_stats)): ?>
        <div class="no-data">ไม่มีข้อมูล</div>
        <?php else: ?>
        <?php
        $max_cat_rev = max(array_column($cat_stats, 'total_revenue'));
        ?>
        <div class="cat-grid">
          <?php foreach ($cat_stats as $cat): ?>
          <div class="cat-card">
            <div class="cat-name"><?= htmlspecialchars($cat['category_name']) ?></div>
            <div class="cat-val">฿<?= number_format($cat['total_revenue'], 0) ?></div>
            <div class="cat-sub"><?= number_format($cat['total_qty'], 0) ?> ชิ้น</div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Top Menu Ranking -->
    <div class="report-section animate-up delay-3">
      <div class="report-sec-title">🏆 เมนูขายดี Top 10 (แยกตามจำนวนชิ้นที่ขายได้)</div>
      <?php if (empty($top_menus)): ?>
      <div class="no-data">ไม่มีข้อมูลเมนูในช่วงเวลานี้</div>
      <?php else: ?>
      <?php $max_qty = max(array_column($top_menus, 'total_qty')); ?>
      <table class="menu-rank-table">
        <thead>
          <tr>
            <th style="width:50px;">อันดับ</th>
            <th>ชื่อเมนู</th>
            <th>หมวดหมู่</th>
            <th style="width:120px;">จำนวนที่ขาย</th>
            <th style="width:160px;">สัดส่วน</th>
            <th style="width:130px;">ยอดขาย (฿)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top_menus as $i => $m):
            $rank = $i + 1;
            $pct  = $max_qty > 0 ? round($m['total_qty'] / $max_qty * 100) : 0;
            $rank_cls = match($rank) { 1=>'rank-1', 2=>'rank-2', 3=>'rank-3', default=>'rank-other' };
          ?>
          <tr>
            <td><div class="rank-num <?= $rank_cls ?>"><?= $rank ?></div></td>
            <td style="font-family:'Kanit',sans-serif;font-weight:500;"><?= htmlspecialchars($m['item_name']) ?></td>
            <td style="font-size:.78rem;color:var(--choco-light);"><?= htmlspecialchars($m['category_name']) ?></td>
            <td style="font-family:'Kanit',sans-serif;font-weight:600;font-size:1.05rem;"><?= number_format($m['total_qty'], 0) ?></td>
            <td>
              <div class="bar-wrap">
                <div class="bar-bg"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
                <span style="font-size:.72rem;color:var(--choco-light);width:30px;text-align:right;"><?= $pct ?>%</span>
              </div>
            </td>
            <td style="font-family:'Kanit',sans-serif;font-weight:500;">฿<?= number_format($m['total_revenue'], 0) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

  </div><!-- /page-content -->
</div><!-- /main -->

<script>
// ---- Chart 1: Dine In vs Takeaway pie ----
<?php if ($total_rev > 0): ?>
new Chart(document.getElementById('typeChart'), {
  type: 'doughnut',
  data: {
    labels: ['ทานที่ร้าน (Dine In)', 'สั่งกลับบ้าน (Take Away)'],
    datasets: [{
      data: [<?= $dine_rev ?>, <?= $take_rev ?>],
      backgroundColor: ['#5C3D52', '#4A5E3A'],
      borderWidth: 0,
      hoverOffset: 6
    }]
  },
  options: {
    responsive: true,
    cutout: '65%',
    plugins: {
      legend: { position: 'bottom', labels: { font: { family: 'Prompt', size: 12 } } },
      tooltip: { callbacks: { label: ctx => ctx.label + ': ฿' + ctx.parsed.toLocaleString() } }
    }
  }
});
<?php endif; ?>

// ---- Chart 2: Daily Sales ----
<?php if (!empty($chart_labels)): ?>
new Chart(document.getElementById('salesChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($chart_labels) ?>,
    datasets: [
      {
        label: 'ทานที่ร้าน',
        data: <?= json_encode($chart_dine_in) ?>,
        backgroundColor: '#2C1810',
        borderRadius: 4
      },
      {
        label: 'สั่งกลับบ้าน',
        data: <?= json_encode($chart_takeaway) ?>,
        backgroundColor: '#C9A84C',
        borderRadius: 4
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'top', labels: { font: { family: 'Prompt', size: 12 } } },
      tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ฿' + ctx.parsed.y.toLocaleString() } }
    },
    scales: {
      x: { stacked: false },
      y: { beginAtZero: true, ticks: { callback: v => '฿' + v.toLocaleString() } }
    }
  }
});
<?php endif; ?>

// ---- Chart 3: Peak Hours ----
<?php if (!empty($hour_labels)): ?>
new Chart(document.getElementById('hourChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($hour_labels) ?>,
    datasets: [{
      label: 'จำนวนออเดอร์',
      data: <?= json_encode($hour_orders) ?>,
      borderColor: '#5C3D52',
      backgroundColor: 'rgba(92,61,82,.1)',
      fill: true,
      tension: 0.4,
      pointBackgroundColor: '#5C3D52',
      pointRadius: 4
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => 'ออเดอร์: ' + ctx.parsed.y + ' รายการ' } }
    },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1 } }
    }
  }
});
<?php endif; ?>
</script>
</body>
</html>
