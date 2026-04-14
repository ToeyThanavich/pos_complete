<?php
/**
 * order_status_ajax.php - Secure AJAX endpoint for polling order status
 * Used by order_status.php auto-refresh (replaces the insecure debug file)
 */
session_start();
include 'connect.php';

header('Content-Type: application/json');

$order_code = isset($_GET['order']) ? trim($_GET['order']) : '';
if (!$order_code) {
    echo json_encode(['error' => 'missing order code']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT o.order_id, o.status, o.updated_at, t.table_name
     FROM orders o
     JOIN tables t ON o.table_id = t.table_id
     WHERE o.order_code = ? LIMIT 1"
);
$stmt->bind_param('s', $order_code);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['error' => 'order not found']);
    exit;
}

$items_stmt = $conn->prepare(
    "SELECT m.item_name, oi.quantity, oi.note
     FROM order_items oi
     JOIN menu_items m ON oi.item_id = m.item_id
     WHERE oi.order_id = ?"
);
$items_stmt->bind_param('i', $order['order_id']);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'status'     => $order['status'],
    'table_name' => $order['table_name'],
    'updated_at' => $order['updated_at'],
    'items'      => $items,
]);
