<?php
session_start();
include 'connect.php';
include 'functions.php';

$table_id = $_SESSION['table_id'] ?? 0;
if ($table_id <= 0) { header("Location: index.php"); exit; }

$cats = $conn->query("SELECT * FROM categories ORDER BY sort_order, category_name");
$cat_list = [];
while($c = $cats->fetch_assoc()){ $cat_list[] = $c; }

$all_fallback_imgs = [
  'uploads/ce53316bba21f1adcd2419d81d22e216.jpg', 
  'uploads/930e6a420dd193f22294fc11399f9429.jpg', 
  'uploads/937cadcd-2fed-4cca-a111-8bad7fbd54f8.jpg', 
  'uploads/brownies.jpg', 
  'uploads/558000012054201.jpg'
];
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>Menu — Black Sheep in the Garden</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/customer.css">
</head>

<body>

  <?php include 'navbar.php'; ?> <div class="hero animate-up delay-1">
    <div class="hero-eyebrow">Table <?= $table_id ?> · สแกนแล้วสั่งได้เลย</div>
    <h1 class="hero-title">Our <em>Garden</em><br>Menu</h1>
    <div class="bs-divider">
      <div class="bs-divider-line"></div>
      <div class="bs-divider-gem"></div>
      <div class="bs-divider-line"></div>
    </div>
    <p class="hero-sub">ทุกจานปรุงจากวัตถุดิบสด คัดสรรจากสวน เพื่อประสบการณ์ที่ดีที่สุด</p>
  </div>

  <div class="tab-scroll animate-up delay-2">
    <div class="tab-row">
      <button class="tab-btn active" onclick="filterCat('all',this)">ทั้งหมด</button>
      <?php foreach($cat_list as $c): ?>
      <button class="tab-btn"
        onclick="filterCat('<?= $c['category_id'] ?>',this)"><?= htmlspecialchars($c['category_name']) ?></button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="menu-section animate-up delay-3" id="menuGrid">
    <?php
$item_idx = 0;
foreach($cat_list as $ci => $cat):
  $cid = (int)$cat['category_id'];
  $stmt = $conn->prepare("SELECT * FROM menu_items WHERE active=1 AND category_id=? ORDER BY item_name");
  $stmt->bind_param('i',$cid);
  $stmt->execute();
  $items = $stmt->get_result();
  $items_arr = [];
  while($m = $items->fetch_assoc()) $items_arr[] = $m;
  if(empty($items_arr)) continue;
?>
    <div data-cat="<?= $cid ?>" class="cat-section">
      <div class="cat-eyebrow"><?= htmlspecialchars($cat['category_name']) ?></div>
      <div class="cat-title">
        <?php
    $titles = ['อาหาร'=>'Signature <em>Food</em>','เครื่องดื่ม'=>'Artisan <em>Drinks</em>','ของหวาน'=>'Sweet <em>Endings</em>'];
    echo $titles[$cat['category_name']] ?? htmlspecialchars($cat['category_name']);
    ?>
      </div>

      <?php foreach($items_arr as $m):
    $cat_name = strtolower($cat['category_name']);
    $img = '';
    
    if (!empty($m['image'])) {
        $img = 'uploads/' . htmlspecialchars($m['image']);
    } else {
        if (strpos($cat_name, 'อาหาร') !== false || strpos($cat_name, 'food') !== false) {
            $img = 'uploads/ce53316bba21f1adcd2419d81d22e216.jpg';
        } elseif (strpos($cat_name, 'ดื่ม') !== false || strpos($cat_name, 'drink') !== false) {
            $drinks = ['uploads/930e6a420dd193f22294fc11399f9429.jpg', 'uploads/937cadcd-2fed-4cca-a111-8bad7fbd54f8.jpg'];
            $img = $drinks[$item_idx % 2];
        } elseif (strpos($cat_name, 'หวาน') !== false || strpos($cat_name, 'dessert') !== false) {
            $desserts = ['uploads/brownies.jpg', 'uploads/558000012054201.jpg'];
            $img = $desserts[$item_idx % 2];
        } else {
            $img = $all_fallback_imgs[$item_idx % count($all_fallback_imgs)];
        }
    }
    $item_idx++;
  ?>
      <div class="menu-card" onclick="openModal(<?= $m['item_id'] ?>)">
        <img class="card-img" src="<?= $img ?>" alt="<?= htmlspecialchars($m['item_name']) ?>" loading="lazy">
        <div class="card-body">
          <div>
            <div class="card-name">
              <?= htmlspecialchars($m['item_name']) ?><?php if($m['size']) echo ' <span style="font-size:.75rem;font-weight:300;color:var(--choco-light);">('. htmlspecialchars($m['size']).')</span>'; ?>
            </div>
            <div class="card-desc"><?= htmlspecialchars($m['description']) ?></div>
          </div>
          <div class="card-footer">
            <span class="card-price"><sup>฿</sup><?= number_format($m['price'],0) ?></span>
            <button class="add-btn" onclick="event.stopPropagation();openModal(<?= $m['item_id'] ?>)">
              <svg viewBox="0 0 24 24" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
              </svg>
            </button>
          </div>
        </div>
      </div>
      <script>
      window._items = window._items || {};
      window._items[<?= $m['item_id'] ?>] = {
        name: <?= json_encode($m['item_name']) ?>,
        size: <?= json_encode($m['size'] ?? '') ?>,
        desc: <?= json_encode($m['description'] ?? '') ?>,
        price: <?= $m['price'] ?>,
        cat: <?= json_encode($cat['category_name']) ?>,
        img: <?= json_encode($img) ?>
      };
      </script>
      <?php endforeach; ?>

      <?php if($ci < count($cat_list)-1): ?>
      <div class="bs-divider">
        <div class="bs-divider-line"></div>
        <div class="bs-divider-gem"></div>
        <div class="bs-divider-line"></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <a class="cart-bar <?= (array_sum(array_column($_SESSION['cart'] ?? [],'quantity')) > 0 || array_sum(array_column($_SESSION['cart'] ?? [],'qty')) > 0) ? 'visible' : '' ?>"
    href="cart.php?table=<?= $table_id ?>" id="cartBar">
    <div class="cart-bar-left">
      <div class="cart-pill" id="cartCount">
        <?= array_sum(array_column($_SESSION['cart'] ?? [],'quantity')) ?: array_sum(array_column($_SESSION['cart'] ?? [],'qty')) ?>
      </div>
      <div class="cart-bar-label">ดูตะกร้า</div>
    </div>
    <div class="cart-bar-total">
      ฿<?php $t = 0; if(!empty($_SESSION['cart'])) foreach($_SESSION['cart'] as $ci) $t += ($ci['price']??0)*($ci['qty']??$ci['quantity']??1); echo number_format($t,0); ?>
    </div>
  </a>

  <div class="modal-overlay" id="addModal" onclick="closeModal(event)">
    <div class="modal-sheet">
      <div class="modal-handle"></div>
      <img class="modal-food-img" id="modalImg" src="" alt="">
      <div class="modal-body">
        <div class="modal-cat" id="modalCat"></div>
        <div class="modal-title" id="modalTitle"></div>
        <div class="modal-size" id="modalSize"></div>
        <div class="modal-desc" id="modalDesc"></div>
        <div class="modal-row">
          <div class="modal-price" id="modalPrice"></div>
          <div class="qty-ctrl">
            <button type="button" class="qty-btn" onclick="chQty(-1)">−</button>
            <div class="qty-num" id="qtyNum">1</div>
            <button type="button" class="qty-btn" onclick="chQty(1)">+</button>
          </div>
        </div>
        <div class="note-label">หมายเหตุพิเศษ</div>
        <input type="text" class="note-input" id="noteInput" placeholder="เช่น หวานน้อย, ไม่เผ็ด, เพิ่มช็อต…">
        <form method="post" action="add_to_cart.php" id="addForm">
          <input type="hidden" name="id" id="hiddenId">
          <input type="hidden" name="table" value="<?= $table_id ?>">
          <input type="hidden" name="qty" id="hiddenQty" value="1">
          <input type="hidden" name="note" id="hiddenNote">
          <button type="submit" class="add-to-order-btn">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19" />
              <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            เพิ่มลงตะกร้า · <span id="modalTotal"></span>
          </button>
        </form>
      </div>
    </div>
  </div>

  <script>
  let curItem = null,
    curQty = 1;

  function openModal(id) {
    const item = window._items[id];
    if (!item) return;
    curItem = {
      ...item,
      id
    };
    curQty = 1;
    document.getElementById('modalCat').textContent = item.cat;
    document.getElementById('modalTitle').textContent = item.name;
    document.getElementById('modalSize').textContent = item.size ? '(' + item.size + ')' : '';
    document.getElementById('modalDesc').textContent = item.desc || 'เมนูแนะนำของทางร้าน';
    document.getElementById('modalPrice').textContent = '฿' + parseInt(item.price).toLocaleString();
    document.getElementById('modalImg').src = item.img;
    document.getElementById('qtyNum').textContent = '1';
    document.getElementById('hiddenId').value = id;
    document.getElementById('hiddenQty').value = '1';
    document.getElementById('noteInput').value = '';
    document.getElementById('modalTotal').textContent = '฿' + parseInt(item.price).toLocaleString();
    document.getElementById('addModal').classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeModal(e) {
    if (e.target === document.getElementById('addModal')) {
      document.getElementById('addModal').classList.remove('open');
      document.body.style.overflow = '';
    }
  }

  function chQty(d) {
    curQty = Math.max(1, curQty + d);
    document.getElementById('qtyNum').textContent = curQty;
    document.getElementById('hiddenQty').value = curQty;
    document.getElementById('modalTotal').textContent = '฿' + (parseInt(curItem.price) * curQty).toLocaleString();
  }
  document.getElementById('addForm').addEventListener('submit', function() {
    document.getElementById('hiddenNote').value = document.getElementById('noteInput').value;
  });

  function filterCat(cat, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.cat-section').forEach(s => {
      s.style.display = (cat === 'all' || s.dataset.cat === cat) ? 'block' : 'none';
    });
  }
  </script>
</body>

</html>