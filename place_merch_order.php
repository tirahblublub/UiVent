<?php
require_once __DIR__ . '/../config.php';
requireStudent();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: merchandise.php');
    exit;
}

$sid   = (int) $_SESSION['student_id'];
$items = json_decode($_POST['cart_json'] ?? '[]', true);

if (empty($items) || !is_array($items)) {
    header('Location: merchandise.php?error=empty_cart');
    exit;
}

$pdo = db();

// Fetch admin_id for merchandise (use the merch item's admin_id)
// We'll look up each item from the DB to get real price + admin_id
$orderIds  = [];
$totalAll  = 0;
$descParts = [];

try {
    $pdo->beginTransaction();

    foreach ($items as $item) {
        $merchId  = (int) ($item['merch_id'] ?? 0);
        $quantity = max(1, (int) ($item['qty'] ?? 1));

        if ($merchId <= 0) continue;

        // Fetch real price and admin_id from DB
        $s = $pdo->prepare("SELECT id, name, price, admin_id, stock FROM merchandise WHERE id = ? AND is_active = 1 LIMIT 1");
        $s->execute([$merchId]);
        $merch = $s->fetch(PDO::FETCH_ASSOC);

        if (!$merch) continue;

        // Check stock
        if ($merch['stock'] < $quantity) {
            $pdo->rollBack();
            header('Location: merchandise.php?error=out_of_stock&item=' . urlencode($merch['name']));
            exit;
        }

        $unitPrice  = (float) $merch['price'];
        $totalPrice = $unitPrice * $quantity;
        $totalAll  += $totalPrice;
        $descParts[] = $merch['name'] . ' x' . $quantity;

        // Insert into merch_orders
        $ins = $pdo->prepare("
            INSERT INTO merch_orders (merch_id, student_id, admin_id, quantity, unit_price, total_price, status, ordered_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $ins->execute([$merchId, $sid, $merch['admin_id'], $quantity, $unitPrice, $totalPrice]);
        $orderIds[] = $pdo->lastInsertId();

        // Decrement stock
        $pdo->prepare("UPDATE merchandise SET stock = stock - ? WHERE id = ?")->execute([$quantity, $merchId]);
    }

    $pdo->commit();

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('place_merch_order.php error: ' . $e->getMessage());
    header('Location: merchandise.php?error=server');
    exit;
}

if (empty($orderIds)) {
    header('Location: merchandise.php?error=empty_cart');
    exit;
}

// If multiple orders, we bill the first one (or you could create one combined order)
// For simplicity: bill the first order_id; pass all order IDs in session for callback to mark paid
$_SESSION['pending_merch_order_ids'] = $orderIds;

$desc    = implode(', ', $descParts);
$refId   = $orderIds[0]; // primary ref for create_bill

// POST to create_bill.php
$fields = [
    'type'   => 'merchandise',
    'ref_id' => $refId,
    'desc'   => $desc,
    'amount' => $totalAll,
];

// Use a self-submitting form to POST to create_bill.php
?>
<!DOCTYPE html>
<html>
<head><title>Redirecting to payment...</title></head>
<body onload="document.getElementById('payform').submit();">
  <form id="payform" method="POST" action="create_bill.php">
    <input type="hidden" name="type"   value="<?= htmlspecialchars($fields['type']) ?>">
    <input type="hidden" name="ref_id" value="<?= (int)$fields['ref_id'] ?>">
    <input type="hidden" name="desc"   value="<?= htmlspecialchars($fields['desc']) ?>">
    <input type="hidden" name="amount" value="<?= number_format($fields['amount'], 2, '.', '') ?>">
  </form>
  <p style="font-family:sans-serif;text-align:center;margin-top:40px;color:#555;">Redirecting to payment gateway...</p>
</body>
</html>