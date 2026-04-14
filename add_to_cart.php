<?php
session_start();
include 'connect.php';

$item_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$table_id = isset($_POST['table']) ? intval($_POST['table']) : 0;
$qty     = isset($_POST['qty']) ? max(1,intval($_POST['qty'])) : 1;
$note    = isset($_POST['note']) ? trim($_POST['note']) : '';

if ($item_id <= 0) { header("Location: index.php?table=$table_id"); exit; }

$stmt = $conn->prepare("SELECT item_id,item_name,price FROM menu_items WHERE item_id=? AND active=1");
$stmt->bind_param('i',$item_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows==0) { header("Location: index.php?table=$table_id"); exit; }
$menu = $res->fetch_assoc();

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

/** กติกา: รวมรายการถ้า item_id + note ตรงกัน */
$found = false;
foreach ($_SESSION['cart'] as &$line){
  if ($line['id'] == $menu['item_id'] && $line['note'] === $note){
    $line['qty'] += $qty;
    $found = true; break;
  }
}
unset($line);

if (!$found){
  $_SESSION['cart'][] = [
    'id' => $menu['item_id'],
    'name' => $menu['item_name'],
    'price' => (float)$menu['price'],
    'qty' => $qty,
    'note' => $note
  ];
}

header("Location: cart.php?table=$table_id");
exit;