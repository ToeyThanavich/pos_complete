<?php
  // date_default_timezone_set('Asia/Bangkok');

  // $host   = "localhost";
  // $user   = "root";
  // $pass   = "";
  // $dbname = "cafe_pos";

  // $conn = mysqli_connect($host, $user, $pass, $dbname)
  //     or die('ไม่สามารถเชื่อมต่อกับ database ได้');
  // $conn->query("SET NAMES UTF8");
  // $conn->query("SET time_zone = '+07:00'");

  $host = getenv("DB_HOST");
  $port = getenv("DB_PORT");
  $user = getenv("DB_USER");
  $pass = getenv("DB_PASS");
  $db   = getenv("DB_NAME");

  $conn = mysqli_connect($host, $user, $pass, $db, $port);

  if (!$conn) {
    die("DB Connection failed: " . mysqli_connect_error());
}
?>
