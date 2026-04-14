<?php
include 'phpqrcode/qrlib.php';
$dir = "qrcodes/";
if (!file_exists($dir)) mkdir($dir);

$total_tables = 1;

for ($i=1; $i<=$total_tables; $i++) {
    $url = " https://waltzlike-savanna-silklike.ngrok-free.dev/projectpos/index.php"; 
    $file = $dir."table_$i.png";
    QRcode::png($url, $file, QR_ECLEVEL_L, 6);
    echo "✅ สร้าง QR โต๊ะ $i (ลูกค้ากรอกเอง) → $file<br>";
}
?>