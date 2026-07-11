<?php
require_once __DIR__ . '/../config.php';
requireUser();

$userId   = (int) $_SESSION['user_id'];
$cartJson = $_POST['cart_json'] ?? '[]';
$items    = json_decode($cartJson, true);

if (!is_array($items) || empty($items)) {
    header('Location: merchandise.php');
    exit;
}

// Verify products exist and have stock
$pdo   = db();
$total = 0.0;
$rows  = [];

foreach ($items as $item) {
    $pid = (int) ($item['id'] ?? 0);
    $qty = max(1, (int) ($item['qty'] ?? 1));
    $stmt = $pdo->prepare("SELECT product_id, price, stock FROM merchandise WHERE product_id=? AND stock > 0 LIMIT 1");
    $stmt->execute([$pid]);
    $product = $stmt->fetch();
    if (!$product) continue;
    $qty    = min($qty, $product['stock']);
    $rows[] = ['id' => $pid, 'qty' => $qty, 'price' => (float)$product['price']];
    $total += $qty * (float)$product['price'];
}

if (empty($rows)) {
    header('Location: merchandise.php?error=out_of_stock');
    exit;
}

// Create order
$pdo->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?,?,'Pending Payment')")
    ->execute([$userId, $total]);
$orderId = (int) $pdo->lastInsertId();

// Insert items and reduce stock
$insStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)");
$updStmt = $pdo->prepare("UPDATE merchandise SET stock = stock - ? WHERE product_id = ?");
foreach ($rows as $row) {
    $insStmt->execute([$orderId, $row['id'], $row['qty'], $row['price']]);
    $updStmt->execute([$row['qty'], $row['id']]);
}

// Redirect to payments to pay
header("Location: payments.php?new_order={$orderId}");
exit;
