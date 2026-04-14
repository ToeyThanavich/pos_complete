<?php
  date_default_timezone_set('Asia/Bangkok');

  $host   = "localhost";
  $user   = "root";
  $pass   = "";
  $dbname = "cafe_pos";

  $conn = mysqli_connect($host, $user, $pass, $dbname)
      or die('ไม่สามารถเชื่อมต่อกับ database ได้');
  $conn->query("SET NAMES UTF8");
  $conn->query("SET time_zone = '+07:00'");
?>
