<?php
require 'auth.php';
include '../connect.php';
include '../functions.php';

$page = max(1,(int)($_GET['p']??1));
$per_page = 20;
$offset = ($page-1)*$per_page;
$filter_status = $_GET['status'] ?? '';
$where = $filter_status ? "WHERE o.status='".mysqli_real_escape_string($conn,$filter_status)."'" : '';
$total_rows  = $conn->query("SELECT COUNT(*) as c FROM orders o $where")->fetch_assoc()['c'];
$total_pages = ceil($total_rows/$per_page);
$orders = $conn->query("SELECT o.*,t.table_name,SUM(oi.quantity*oi.price) as total FROM orders o JOIN tables t ON o.table_id=t.table_id LEFT JOIN order_items oi ON o.order_id=oi.order_id $where GROUP BY o.order_id ORDER BY o.created_at DESC LIMIT $per_page OFFSET $offset");

$status_map=['pending'=>['th'=>'รอรับ','cls'=>'bs-badge-pending'],'cooking'=>['th'=>'กำลังทำ','cls'=>'bs-badge-cooking'],'serving'=>['th'=>'เสิร์ฟ','cls'=>'bs-badge-serving'],'completed'=>['th'=>'เสร็จ','cls'=>'bs-badge-completed'],'cancelled'=>['th'=>'ยกเลิก','cls'=>'bs-badge-cancelled']];
$filter_opts=[''=>'ทั้งหมด','pending'=>'รอรับ','cooking'=>'กำลังทำ','serving'=>'เสิร์ฟ','completed'=>'เสร็จ','cancelled'=>'ยกเลิก'];
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>Order History — Black Sheep</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link
    href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../assets/admin.css">
  <style>
  /* CSS เฉพาะหน้า Orders */
  .filter-row {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
  }

  .filter-btn {
    font-size: .75rem;
    padding: 7px 16px;
    border-radius: 30px;
    border: 1px solid rgba(44, 24, 16, .15);
    background: transparent;
    color: var(--choco);
    text-decoration: none;
  }

  .filter-btn.active {
    background: var(--choco);
    color: var(--cream);
  }

  .pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    padding: 18px 0 4px;
  }

  .pg-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 1px solid rgba(44, 24, 16, .15);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: var(--choco);
  }

  .pg-btn.active {
    background: var(--choco);
    color: var(--cream);
  }
  </style>
</head>

<body>

  <?php include 'sidebar.php'; ?>

  <div class="main">
    <div class="topbar">
      <div class="topbar-title serif">ประวัติการสั่งอาหาร</div>
    </div>
    <div class="page-content">
      <div class="filter-row">
        <?php foreach ($filter_opts as $v=>$l): ?>
        <a href="?status=<?= $v ?>" class="filter-btn <?= $filter_status===$v?'active':'' ?>"><?= $l ?></a>
        <?php endforeach; ?>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>รหัสออเดอร์</th>
              <th>โต๊ะ</th>
              <th>ประเภท</th>
              <th>ยอดรวม</th>
              <th>สถานะ</th>
              <th>วันที่</th>
            </tr>
          </thead>
          <tbody>
            <?php while($o=$orders->fetch_assoc()):
            $s=$status_map[$o['status']]??['th'=>$o['status'],'cls'=>'bs-badge-pending'];
            $otype = (isset($o['order_type']) && $o['order_type'] === 'takeaway') ? 'สั่งกลับบ้าน' : 'ทานที่ร้าน';
          ?>
            <tr>
              <td style="color:var(--choco-light);"><?= $o['order_id'] ?></td>
              <td style="font-family:'Kanit',sans-serif;font-weight:500;"><?= htmlspecialchars($o['order_code']) ?></td>
              <td><?= htmlspecialchars($o['table_name']) ?></td>
              <td><?= $otype ?></td>
              <td style="font-family:'Kanit',sans-serif;font-weight:500;">฿<?= number_format($o['total']??0,0) ?></td>
              <td><span class="bs-badge <?= $s['cls'] ?>"><?= $s['th'] ?></span></td>
              <td style="color:var(--choco-light);"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php if($total_pages>1): ?>
        <div class="pagination">
          <?php for($i=1;$i<=$total_pages;$i++): ?>
          <a href="?status=<?= $filter_status ?>&p=<?= $i ?>" class="pg-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>