<?php
// ── ToyyibPay server-to-server callback ───────────────────────
// ToyyibPay calls this URL directly (server-to-server).
// DO NOT output anything before the final echo.
// Must be reachable from the internet (use ngrok on localhost).

require_once 'config.php';

// Log all incoming data for debugging
$raw = file_get_contents('php://input');
error_log('[UiVent Callback] POST: ' . json_encode($_POST));
error_log('[UiVent Callback] RAW:  ' . $raw);

// ToyyibPay POST fields:
// refno            = our external ref e.g. UV000003
// order_id         = ToyyibPay's internal transaction/order id
// billcode         = the bill code (same one we redirected to)
// status           = 1=success, 2=pending, 3=fail
$refno            = $_POST['refno']            ?? '';
$order_id         = $_POST['order_id']         ?? '';
$billcode         = $_POST['billcode']         ?? '';   // ← ToyyibPay also sends billcode
$status           = $_POST['status']           ?? '';
$reason           = $_POST['reason']           ?? '';
$amount           = $_POST['amount']           ?? '';
$transaction_time = $_POST['transaction_time'] ?? date('Y-m-d H:i:s');

// Parse payment_transactions id from external ref (UV000003 → 3)
$txnId = (int) ltrim(str_replace('UV', '', $refno), '0');

if (!$txnId) {
    error_log('[UiVent Callback] Could not parse txn id from refno: ' . $refno);
    echo 'INVALID_REF';
    exit;
}

$pdo = db();

// Fetch payment_transactions row
$stmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE id = ? LIMIT 1");
$stmt->execute([$txnId]);
$txn = $stmt->fetch();

if (!$txn) {
    error_log('[UiVent Callback] Transaction not found: ' . $txnId);
    echo 'NOT_FOUND';
    exit;
}

if ($status == '1') {
    // Payment successful
    // bill_code: prefer the billcode POST field; fall back to what's already stored
    $savedBillCode = $billcode ?: ($txn['bill_code'] ?? $order_id);

    $pdo->prepare("
        UPDATE payment_transactions
        SET payment_status = 'Paid',
            bill_code      = ?,
            transaction_id = ?,
            paid_at        = ?
        WHERE id = ? AND payment_status != 'Paid'
    ")->execute([$savedBillCode, $order_id, $transaction_time, $txnId]);

    // Ensure registration stays active
    if ($txn['registration_id']) {
        $pdo->prepare("UPDATE registrations SET status = 'registered' WHERE id = ?")
            ->execute([$txn['registration_id']]);
    }

    error_log('[UiVent Callback] Transaction #' . $txnId . ' marked PAID. bill_code=' . $savedBillCode);
    echo 'PAYMENT_SUCCESS';

} elseif ($status == '2') {
    $pdo->prepare("UPDATE payment_transactions SET payment_status = 'Pending' WHERE id = ?")
        ->execute([$txnId]);
    error_log('[UiVent Callback] Transaction #' . $txnId . ' still PENDING.');
    echo 'PAYMENT_PENDING';

} else {
    $pdo->prepare("UPDATE payment_transactions SET payment_status = 'Failed' WHERE id = ?")
        ->execute([$txnId]);
    error_log('[UiVent Callback] Transaction #' . $txnId . ' FAILED. Reason: ' . $reason);
    echo 'PAYMENT_FAILED';
}

exit;