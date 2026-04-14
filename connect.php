<?php
date_default_timezone_set('Asia/Bangkok');

$host   = "localhost";
$user   = "if0_41661118";
$pass   = "0VQznpHBk9Ts1yA";
$dbname = "if0_41661118_cafe_pos";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("DB connection failed: " . mysqli_connect_error());
}

$conn->query("SET NAMES utf8mb4");
$conn->query("SET time_zone = '+07:00'");
?>
