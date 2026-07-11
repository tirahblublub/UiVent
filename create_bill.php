<?php
require_once '../config.php';
requireStudent();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: payments.php');
    exit;
}

require_once 'toyyib_config.php';

$sid    = (int) $_SESSION['student_id'];
$type   = $_POST['type']   ?? '';
$ref_id = (int) ($_POST['ref_id'] ?? 0);
$desc   = trim($_POST['desc']   ?? 'UiVent Payment');
$amount = (float) ($_POST['amount'] ?? 0);

if ($amount <= 0 || !in_array($type, ['event', 'merchandise'])) {
    die('Invalid payment request.');
}

// Fetch student info
$stmt = db()->prepare("SELECT id, name, email FROM students WHERE id = ? LIMIT 1");
$stmt->execute([$sid]);
$student = $stmt->fetch();

if (!$student) {
    die('Student not found. Please log in again.');
}

$external_ref = 'UV' . str_pad($ref_id, 6, '0', STR_PAD_LEFT);

$categoryCode = ($type === 'event')
    ? TOYYIB_CATEGORY_CODE_EVENT
    : TOYYIB_CATEGORY_CODE_MERCHANDISE;

// Call ToyyibPay createBill API
$postFields = [
    'userSecretKey'           => TOYYIB_SECRET_KEY,
    'categoryCode'            => $categoryCode,
    'billName'                => substr($desc, 0, 30),
    'billDescription'         => substr($desc, 0, 100),
    'billPriceSetting'        => 1,
    'billPayorInfo'           => 1,
    'billAmount'              => (string) round($amount * 100),
    'billReturnUrl'           => TOYYIB_RETURN_URL,
    'billCallbackUrl'         => TOYYIB_CALLBACK_URL,
    'billExternalReferenceNo' => $external_ref,
    'billTo'                  => $student['name'],
    'billEmail'               => $student['email'],
    'billPhone'               => '0100000000',
    'billSplitPayment'        => 0,
    'billPaymentChannel'      => 2,
    'billContentEmail'        => 'Thank you for your payment to UiVent.',
    'billChargeToCustomer'    => 1,
];

$ch = curl_init(TOYYIB_BASE_URL . '/index.php/api/createBill');
curl_setopt($ch, CURLOPT_POST,           true);
curl_setopt($ch, CURLOPT_POSTFIELDS,     http_build_query($postFields));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT,        15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response  = curl_exec($ch);
$curlErrno = curl_errno($ch);
$curlError = curl_error($ch);
curl_close($ch);

// ── cURL failure ──────────────────────────────────────────────────────
if ($curlErrno) {
    error_log('[UiVent] ToyyibPay cURL error: ' . $curlError);

    if ($type === 'event' && $ref_id) {
        db()->prepare("UPDATE payment_transactions SET payment_status='Failed' WHERE id=? AND student_id=?")
            ->execute([$ref_id, $sid]);
    }
    if ($type === 'merchandise') {
        $ids = $_SESSION['pending_merch_order_ids'] ?? [$ref_id];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        db()->prepare("UPDATE merch_orders SET status='cancelled' WHERE id IN ($placeholders) AND student_id=?")
            ->execute(array_merge($ids, [$sid]));
        unset($_SESSION['pending_merch_order_ids']);
    }
    ?>
    <!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Payment Error — UiVent</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
      <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md w-full text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-wifi text-red-500 text-2xl"></i>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Cannot Connect to ToyyibPay</h1>
        <p class="text-sm text-gray-500 mb-4">Unable to reach the payment gateway. Please try again.</p>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-left mb-6 text-sm">
          <p class="font-bold text-amber-800 mb-2"><i class="fas fa-terminal mr-1"></i> Fix for localhost:</p>
          <ol class="text-amber-700 space-y-1 list-decimal list-inside">
            <li>Install <a href="https://ngrok.com" target="_blank" class="underline font-semibold">ngrok</a></li>
            <li>Run: <code class="bg-amber-100 px-1 rounded">ngrok http 80</code></li>
            <li>Copy the <code class="bg-amber-100 px-1 rounded">https://xxxx.ngrok-free.app</code> URL</li>
            <li>Paste as <code class="bg-amber-100 px-1 rounded">APP_BASE_URL</code> in <code class="bg-amber-100 px-1 rounded">toyyib_config.php</code></li>
          </ol>
        </div>
        <p class="text-xs text-gray-400 mb-5">Error: <?= htmlspecialchars($curlError) ?></p>
        <a href="<?= $type === 'merchandise' ? 'merchandise.php' : 'payments.php' ?>" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white" style="background:#582C83;">
          <i class="fas fa-arrow-left mr-1"></i> Go Back
        </a>
      </div>
    </body></html>
    <?php
    exit;
}

// ── Bad response ──────────────────────────────────────────────────────
$data = json_decode($response, true);
if (!is_array($data) || empty($data[0]['BillCode'])) {
    error_log('[UiVent] ToyyibPay unexpected response: ' . $response);

    if ($type === 'event' && $ref_id) {
        db()->prepare("UPDATE payment_transactions SET payment_status='Failed' WHERE id=? AND student_id=?")
            ->execute([$ref_id, $sid]);
    }
    if ($type === 'merchandise') {
        $ids = $_SESSION['pending_merch_order_ids'] ?? [$ref_id];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        db()->prepare("UPDATE merch_orders SET status='cancelled' WHERE id IN ($placeholders) AND student_id=?")
            ->execute(array_merge($ids, [$sid]));
        unset($_SESSION['pending_merch_order_ids']);
    }
    ?>
    <!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Payment Error — UiVent</title>
    <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
      <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md w-full text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-circle-exclamation text-red-500 text-2xl"></i>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Payment Gateway Error</h1>
        <p class="text-sm text-gray-500 mb-4">ToyyibPay returned an unexpected response. Check your Secret Key and Category Code in <code>toyyib_config.php</code>.</p>
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-left mb-4 text-xs font-mono text-gray-600 overflow-x-auto">
          <?= htmlspecialchars($response ?: '(empty response)') ?>
        </div>
        <a href="<?= $type === 'merchandise' ? 'merchandise.php' : 'payments.php' ?>" class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-bold text-white" style="background:#582C83;">
          <i class="fas fa-arrow-left mr-1"></i> Go Back
        </a>
      </div>
    </body></html>
    <?php
    exit;
}

// ── Success — save bill_code and redirect to ToyyibPay ────────────────
$billCode = $data[0]['BillCode'];

if ($type === 'event' && $ref_id) {
    db()->prepare("UPDATE payment_transactions SET bill_code=? WHERE id=? AND student_id=?")
        ->execute([$billCode, $ref_id, $sid]);
}

if ($type === 'merchandise') {
    // Save bill_code to all orders in this checkout session
    $ids = $_SESSION['pending_merch_order_ids'] ?? [$ref_id];
    foreach ($ids as $oid) {
        db()->prepare("UPDATE merch_orders SET status='pending' WHERE id=? AND student_id=?")
            ->execute([$oid, $sid]);
    }
    // Store bill_code on primary order for reference
    db()->prepare("UPDATE merch_orders SET notes=? WHERE id=? AND student_id=?")
        ->execute(['bill_code:' . $billCode, $ref_id, $sid]);
    unset($_SESSION['pending_merch_order_ids']);
}

header('Location: ' . TOYYIB_BASE_URL . '/' . $billCode);
exit;