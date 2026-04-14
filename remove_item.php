<?php
session_start();
$i      = (int)($_GET['i'] ?? -1);
$table  = (int)($_GET['table'] ?? 0);
$action = $_GET['action'] ?? 'remove';

if (isset($_SESSION['cart'][$i])) {
    if ($action === 'inc') {
        $_SESSION['cart'][$i]['qty'] = (int)($_SESSION['cart'][$i]['qty'] ?? $_SESSION['cart'][$i]['quantity'] ?? 1) + 1;
    } elseif ($action === 'dec') {
        $qty = (int)($_SESSION['cart'][$i]['qty'] ?? $_SESSION['cart'][$i]['quantity'] ?? 1) - 1;
        if ($qty <= 0) {
            array_splice($_SESSION['cart'], $i, 1);
        } else {
            $_SESSION['cart'][$i]['qty'] = $qty;
        }
    } else {
        array_splice($_SESSION['cart'], $i, 1);
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']);
}
header("Location: cart.php?table=$table");
exit;
