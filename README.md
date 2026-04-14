# ☕ Café POS — QR Scan & Ordering System

> ระบบ POS สำหรับร้านคาเฟ่ รองรับการสั่งอาหารผ่าน QR Code พร้อม Admin Dashboard จัดการออเดอร์  
> A PHP-based POS system for cafés — QR Code table ordering + real-time Admin Dashboard.

---

## 📸 Features / ฟีเจอร์

| ฝั่งลูกค้า (Customer) | ฝั่ง Staff/Admin |
|---|---|
| สแกน QR Code ที่โต๊ะ | Login สำหรับพนักงาน |
| เลือกเมนูตามหมวดหมู่ | Dashboard แสดงออเดอร์ live |
| เพิ่มหมายเหตุพิเศษ | เปลี่ยนสถานะออเดอร์ |
| ตรวจสอบตะกร้าก่อนยืนยัน | ดูประวัติออเดอร์ทั้งหมด |
| ติดตามสถานะออเดอร์ realtime | จัดการเมนู (เพิ่ม/ปิด) |
| | สร้าง QR Code สำหรับแต่ละโต๊ะ |

---

## 🛠️ Tech Stack

- **Backend:** PHP 8.x + MySQLi
- **Database:** MySQL / MariaDB
- **Frontend:** Bootstrap 5.3
- **QR Code:** API (qrserver.com) + phpqrcode library
- **Session:** PHP native sessions

---

## 📁 Project Structure / โครงสร้างไฟล์

```
projectpos/
├── index.php             # หน้าเลือก/กรอกโต๊ะ (Customer landing)
├── menu_detail.php       # หน้าแสดงเมนูทั้งหมด
├── add_to_cart.php       # เพิ่มสินค้าลงตะกร้า
├── cart.php              # หน้าตะกร้าสินค้า
├── checkout.php          # บันทึกออเดอร์ลง DB
├── success.php           # หน้ายืนยันการสั่ง
├── order_status.php      # ติดตามสถานะออเดอร์ (realtime)
├── remove_item.php       # ลบสินค้าออกจากตะกร้า
├── track.php             # Redirect ไปหน้าติดตาม
├── login.php             # หน้า Login สำหรับ Staff
├── logout.php            # ออกจากระบบ
├── connect.php           # การเชื่อมต่อ Database
├── functions.php         # Helper functions
├── generate_qr.php       # สร้าง QR Code (CLI/legacy)
├── admin/
│   ├── auth.php          # Middleware ตรวจสอบ session
│   ├── dashboard.php     # Dashboard หลักจัดการออเดอร์
│   ├── orders.php        # ประวัติออเดอร์ทั้งหมด
│   ├── menu_manage.php   # จัดการเมนูอาหาร
│   └── qr_manager.php    # จัดการ QR Code ทุกโต๊ะ
├── database/
│   └── cafe_pos.sql      # SQL สำหรับสร้าง Database
└── assets/
    └── style.css         # Custom CSS
```

---

## 🚀 Installation / วิธีติดตั้ง

### Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.4+
- Apache/Nginx with mod_rewrite
- XAMPP / WAMP / Laragon (local)

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/yourusername/cafe-pos.git

# 2. Move to web server directory
cp -r cafe-pos /var/www/html/projectpos
# หรือใน XAMPP: C:\xampp\htdocs\projectpos

# 3. Import database
# เปิด phpMyAdmin → สร้าง database ชื่อ cafe_pos → Import ไฟล์ database/cafe_pos.sql

# 4. Configure database connection
# แก้ไขไฟล์ connect.php
```

```php
// connect.php
$host   = "localhost";
$user   = "root";
$pass   = "";          // ← ใส่ password ของคุณ
$dbname = "cafe_pos";
```

### Default Admin Account
```
Username: admin
Password: 1234
```
> ⚠️ **เปลี่ยนรหัสผ่านก่อนใช้งานจริงทุกครั้ง**

---

## 🔄 Order Flow / การทำงานของระบบ

```
ลูกค้าสแกน QR Code
        ↓
หน้าเมนู (menu_detail.php)
        ↓
เพิ่มลงตะกร้า + หมายเหตุ
        ↓
ยืนยันการสั่ง (checkout.php)
        ↓
บันทึก DB → หน้า success.php
        ↓
ติดตามสถานะ (order_status.php) ← Auto-refresh 7s
        ↑
Admin เปลี่ยนสถานะใน Dashboard ← Auto-refresh 15s
```

### Order Status Flow
`pending` → `cooking` → `serving` → `completed`  
หรือ `cancelled` (ยกเลิกได้ทุกขั้นตอน)

---

## 📱 QR Code Setup / การตั้งค่า QR Code

1. เข้า Admin → **QR Code Manager**
2. ดาวน์โหลด QR Code ของแต่ละโต๊ะ
3. พิมพ์แล้ววางบนโต๊ะ
4. ลูกค้าสแกนแล้วระบุโต๊ะอัตโนมัติ

---

## 🔐 Security Notes

- รหัสผ่านใน DB ปัจจุบันเป็น plain text — ควร migrate เป็น `password_hash()` ก่อน production
- ระบบรองรับทั้ง plain text (legacy) และ `password_hash` อัตโนมัติ
- ใช้ Prepared Statements ในส่วนหลักๆ เพื่อป้องกัน SQL Injection

---

## 📄 License

MIT License — Free to use and modify.

---

*Developed as a cooperative education (สหกิจ) project — PHP + MySQL Café POS System*
