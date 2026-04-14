<?php
require 'auth.php';
include '../connect.php';
include '../functions.php';

$msg=''; $msg_type='success';

// ฟังก์ชันสำหรับอัปโหลดรูปภาพเข้าโฟลเดอร์ uploads
function uploadImage($fileInputName) {
    if (!empty($_FILES[$fileInputName]['name']) && $_FILES[$fileInputName]['error'] == 0) {
        $ext = pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION);
        $newName = uniqid('menu_') . '.' . $ext; // สุ่มชื่อไฟล์ใหม่กันไฟล์ซ้ำ
        $target = '../uploads/' . $newName;
        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $target)) {
            return $newName;
        }
    }
    return '';
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? '';
  
  // เพิ่มเมนู
  if($action === 'add'){
    $name  = trim($conn->real_escape_string($_POST['item_name']));
    $cat   = (int)$_POST['category_id'];
    $price = (float)$_POST['price'];
    $size  = trim($conn->real_escape_string($_POST['size']??''));
    $desc  = trim($conn->real_escape_string($_POST['description']??''));
    
    $imageName = uploadImage('image'); // เรียกใช้ฟังก์ชันอัปโหลด
    
    $conn->query("INSERT INTO menu_items (category_id,item_name,size,description,price,active,image) VALUES ($cat,'$name','$size','$desc',$price,1,'$imageName')");
    $msg = "เพิ่มเมนู '{$_POST['item_name']}' สำเร็จ";
  }
  
  // แก้ไขเมนู
  if($action === 'edit'){
    $id    = (int)$_POST['item_id'];
    $name  = trim($conn->real_escape_string($_POST['item_name']));
    $cat   = (int)$_POST['category_id'];
    $price = (float)$_POST['price'];
    $size  = trim($conn->real_escape_string($_POST['size']??''));
    $desc  = trim($conn->real_escape_string($_POST['description']??''));
    
    $imageQuery = "";
    $imageName = uploadImage('image');
    if($imageName !== ''){
        $imageQuery = ", image='$imageName'"; // อัปเดตชื่อรูปเฉพาะตอนที่มีการอัปโหลดไฟล์ใหม่
    }
    
    $conn->query("UPDATE menu_items SET category_id=$cat, item_name='$name', size='$size', description='$desc', price=$price $imageQuery WHERE item_id=$id");
    $msg = "อัปเดตข้อมูลเมนู '{$_POST['item_name']}' เรียบร้อยแล้ว";
  }

  // เปิด-ปิดเมนู
  if($action === 'toggle'){ 
    $id=(int)$_POST['item_id']; 
    $conn->query("UPDATE menu_items SET active=1-active WHERE item_id=$id"); 
    header("Location: menu_manage.php"); 
    exit; 
  }
  
  // ลบเมนู
  if($action === 'delete'){ 
    $id=(int)$_POST['item_id']; 
    $conn->query("DELETE FROM menu_items WHERE item_id=$id"); 
    header("Location: menu_manage.php"); 
    exit; 
  }
}

$cats = $conn->query("SELECT * FROM categories ORDER BY sort_order");
$cat_list = []; 
while($c = $cats->fetch_assoc()) $cat_list[] = $c;

$items = $conn->query("SELECT m.*,c.category_name FROM menu_items m JOIN categories c ON m.category_id=c.category_id ORDER BY c.sort_order,m.item_name");
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>จัดการเมนู — Black Sheep</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link
    href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">

  <link rel="stylesheet" href="../assets/admin.css">
</head>

<body>

  <?php include 'sidebar.php'; ?>

  <div class="main">
    <div class="topbar">
      <div class="topbar-title serif">Menu Management</div>
    </div>
    <div class="page-content animate-up">
      <?php if($msg): ?><div class="bs-alert bs-alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <div class="sec-hdr">
        <div>
          <div class="sec-eyebrow">Catalogue</div>
          <div class="sec-title serif">รายการอาหาร</div>
        </div>
        <button class="add-btn-top" onclick="document.getElementById('addModal').classList.add('open')">
          <svg viewBox="0 0 24 24" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
          </svg>
          เพิ่มเมนู
        </button>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>รูป</th>
              <th>ชื่อเมนู</th>
              <th>หมวด</th>
              <th>ขนาด</th>
              <th>ราคา</th>
              <th>สถานะ</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php while($item=$items->fetch_assoc()):
            $cat_key=strtolower($item['category_name']);
            $cc='cc-other';
            if(strpos($cat_key,'อาหาร')!==false||strpos($cat_key,'food')!==false) $cc='cc-food';
            elseif(strpos($cat_key,'ดื่ม')!==false||strpos($cat_key,'drink')!==false) $cc='cc-drink';
            elseif(strpos($cat_key,'หวาน')!==false||strpos($cat_key,'dessert')!==false) $cc='cc-dessert';
          ?>
            <tr class="<?= !$item['active']?'inactive':'' ?>">
              <td>
                <?php if(!empty($item['image'])): ?>
                <img class="thumb" src="../uploads/<?= htmlspecialchars($item['image']) ?>" alt="">
                <?php else: ?>
                <div class="thumb-placeholder">🌿</div>
                <?php endif; ?>
              </td>
              <td>
                <div class="mn"><?= htmlspecialchars($item['item_name']) ?></div>
                <?php if($item['description']): ?><div class="md">
                  <?= htmlspecialchars(substr($item['description'],0,55)).(strlen($item['description'])>55?'…':'') ?>
                </div><?php endif; ?>
              </td>
              <td><span class="cat-chip <?= $cc ?>"><?= htmlspecialchars($item['category_name']) ?></span></td>
              <td style="font-size:.8rem;color:var(--choco-light)"><?= htmlspecialchars($item['size']??'-') ?></td>
              <td class="price-val">฿<?= number_format($item['price'],0) ?></td>
              <td>
                <span class="act-dot">
                  <span class="dot <?= $item['active']?'dot-on':'dot-off' ?>"></span>
                  <span style="font-size:.8rem;color:var(--choco-light)"><?= $item['active']?'เปิด':'ปิด' ?></span>
                </span>
              </td>
              <td>
                <button class="act-btn edit" onclick='openEditModal(<?= json_encode([
                  "id" => $item["item_id"],
                  "name" => $item["item_name"],
                  "cat" => $item["category_id"],
                  "size" => $item["size"],
                  "price" => $item["price"],
                  "desc" => $item["description"]
              ]) ?>)'>แก้ไข</button>

                <form method="post" style="display:inline">
                  <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                  <button name="action" value="toggle" class="act-btn"><?= $item['active']?'ปิด':'เปิด' ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('ลบเมนูนี้?')">
                  <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                  <button name="action" value="delete" class="act-btn danger">ลบ</button>
                </form>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="modal-bg" id="addModal">
    <div class="modal-box">
      <div class="modal-hdr">
        <div>
          <div class="modal-eyebrow">Catalogue</div>
          <div class="modal-title">เพิ่มเมนูใหม่</div>
        </div>
        <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')"><svg
            viewBox="0 0 24 24" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg></button>
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add">
        <div class="modal-body">
          <div class="fg">
            <label class="bs-label">ชื่อเมนู *</label>
            <input type="text" name="item_name" class="bs-input" required>
          </div>
          <div class="fg3">
            <div>
              <label class="bs-label">หมวดหมู่ *</label>
              <select name="category_id" class="bs-select" required>
                <?php foreach($cat_list as $c): ?><option value="<?= $c['category_id'] ?>">
                  <?= htmlspecialchars($c['category_name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="bs-label">ขนาด</label>
              <input type="text" name="size" class="bs-input" placeholder="เช่น 16oz">
            </div>
            <div>
              <label class="bs-label">ราคา (฿) *</label>
              <input type="number" name="price" class="bs-input" step="0.01" min="0" required>
            </div>
          </div>
          <div class="fg">
            <label class="bs-label">รูปภาพเมนู</label>
            <input type="file" name="image" class="bs-input" accept="image/*">
          </div>
          <div class="fg">
            <label class="bs-label">คำอธิบาย</label>
            <textarea name="description" class="bs-textarea" rows="3"
              style="resize:vertical;min-height:72px;"></textarea>
          </div>
        </div>
        <div class="modal-ftr">
          <button type="button" class="cancel-btn"
            onclick="document.getElementById('addModal').classList.remove('open')">ยกเลิก</button>
          <button type="submit" class="submit-btn">เพิ่มเมนู →</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-bg" id="editModal">
    <div class="modal-box">
      <div class="modal-hdr">
        <div>
          <div class="modal-eyebrow">Edit</div>
          <div class="modal-title">แก้ไขเมนู</div>
        </div>
        <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')"><svg
            viewBox="0 0 24 24" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg></button>
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="item_id" id="edit_id">
        <div class="modal-body">
          <div class="fg">
            <label class="bs-label">ชื่อเมนู *</label>
            <input type="text" name="item_name" id="edit_name" class="bs-input" required>
          </div>
          <div class="fg3">
            <div>
              <label class="bs-label">หมวดหมู่ *</label>
              <select name="category_id" id="edit_cat" class="bs-select" required>
                <?php foreach($cat_list as $c): ?><option value="<?= $c['category_id'] ?>">
                  <?= htmlspecialchars($c['category_name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="bs-label">ขนาด</label>
              <input type="text" name="size" id="edit_size" class="bs-input" placeholder="เช่น 16oz">
            </div>
            <div>
              <label class="bs-label">ราคา (฿) *</label>
              <input type="number" name="price" id="edit_price" class="bs-input" step="0.01" min="0" required>
            </div>
          </div>
          <div class="fg">
            <label class="bs-label">เปลี่ยนรูปภาพ (เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)</label>
            <input type="file" name="image" class="bs-input" accept="image/*">
          </div>
          <div class="fg">
            <label class="bs-label">คำอธิบาย</label>
            <textarea name="description" id="edit_desc" class="bs-textarea" rows="3"
              style="resize:vertical;min-height:72px;"></textarea>
          </div>
        </div>
        <div class="modal-ftr">
          <button type="button" class="cancel-btn"
            onclick="document.getElementById('editModal').classList.remove('open')">ยกเลิก</button>
          <button type="submit" class="submit-btn">บันทึกการแก้ไข →</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  // ปิด Modal เมื่อคลิกพื้นที่ว่าง
  document.querySelectorAll('.modal-bg').forEach(bg => {
    bg.addEventListener('click', function(e) {
      if (e.target === this) this.classList.remove('open');
    });
  });

  // ฟังก์ชันเปิด Modal แก้ไขและดึงข้อมูลเดิมมาแสดง
  function openEditModal(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_name').value = item.name;
    document.getElementById('edit_cat').value = item.cat;
    document.getElementById('edit_size').value = item.size ? item.size : '';
    document.getElementById('edit_price').value = item.price;
    document.getElementById('edit_desc').value = item.desc ? item.desc : '';

    document.getElementById('editModal').classList.add('open');
  }
  </script>
</body>

</html>