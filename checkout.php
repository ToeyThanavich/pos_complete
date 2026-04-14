<?php
session_start();
include 'connect.php';

// เช็กว่ามีตะกร้าสินค้าหรือไม่ ถ้าไม่มีให้เด้งกลับ
if (empty($_SESSION['cart'])) {
    header("Location: menu_detail.php");
    exit;
}

// รับค่าเลขโต๊ะ
$table_id = isset($_GET['table']) ? (int)$_GET['table'] : ($_SESSION['table_id'] ?? 0);
$order_type = $_POST['order_type'] ?? 'dine_in'; // ทานที่ร้าน หรือ สั่งกลับบ้าน

if ($table_id > 0) {
    // =========================================================
    // 1. ลอจิกสำคัญ: ค้นหาว่าโต๊ะนี้มีบิลที่ยังไม่จ่ายเงินค้างอยู่หรือไม่?
    // (สถานะต้องไม่ใช่ completed หรือ cancelled)
    // =========================================================
    $check_sql = "SELECT order_id FROM orders WHERE table_id = $table_id AND status NOT IN ('completed', 'cancelled') LIMIT 1";
    $result = $conn->query($check_sql);

    if ($result && $result->num_rows > 0) {
        // ---> กรณีที่ 1: มีลูกค้าเก่านั่งอยู่ (บิลเก่ายังไม่เช็คบิล) <---
        $row = $result->fetch_assoc();
        $order_id = $row['order_id'];

        // อัปเดตสถานะบิลกลับเป็น 'pending' เพื่อให้ในครัวรู้ว่าโต๊ะนี้มีการสั่งอาหารเพิ่ม!
        $conn->query("UPDATE orders SET status = 'pending', updated_at = NOW() WHERE order_id = $order_id");

    } else {
        // ---> กรณีที่ 2: ลูกค้าใหม่เพิ่งมานั่ง (หรือลูกค้าเก่าจ่ายเงิน/ลุกไปแล้ว) <---
        $order_code = 'ORD' . date('YmdHis') . rand(100, 999);
        
        // สร้างบิลใหม่ (Generate Order ID ใหม่)
        $insert_order = "INSERT INTO orders (order_code, table_id, status, created_at, order_time) 
                         VALUES ('$order_code', $table_id, 'pending', NOW(), NOW())";
        $conn->query($insert_order);
        $order_id = $conn->insert_id; // ได้เลข ID บิลใหม่มาใช้งาน
    }

    // =========================================================
    // 2. นำรายการอาหารในตะกร้า บันทึกลงตาราง order_items (ผูกกับ $order_id)
    // =========================================================
    foreach ($_SESSION['cart'] as $item) {
        $item_id = (int)$item['id'];
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];
        $note = $conn->real_escape_string($item['note'] ?? '');

        $conn->query("INSERT INTO order_items (order_id, item_id, quantity, price, note) 
                      VALUES ($order_id, $item_id, $qty, $price, '$note')");
    }

    // =========================================================
    // 3. สั่งสำเร็จแล้ว เคลียร์ของในตะกร้าทิ้ง
    // =========================================================
    unset($_SESSION['cart']);

    // 4. ส่งลูกค้ากลับไปหน้าเมนู (พร้อมแสดงข้อความสั่งอาหารสำเร็จ)
    // (ถ้าคุณมีหน้า order_success.php ก็เปลี่ยนตรงนี้ได้เลยครับ)
    header("Location: menu_detail.php?success=1");
    exit;
    
} else {
    // กรณีหาเลขโต๊ะไม่เจอ ให้เด้งไปหน้าแรก
    header("Location: index.php");
    exit;
}
?>