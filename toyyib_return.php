<?php
require_once 'config.php';

$billCode = $_GET['billcode']  ?? $_POST['billcode']  ?? '';
$status   = $_GET['status_id'] ?? $_POST['status_id'] ?? '';
$reason   = $_GET['reason']    ?? $_POST['reason']    ?? '';
$order_id = $_GET['order_id']  ?? $_POST['order_id']  ?? '';
$refno    = $_GET['refno']     ?? $_POST['refno']     ?? '';  // e.g. UV000003

error_log('[UiVent Return] billcode=' . $billCode . ' status=' . $status . ' order_id=' . $order_id . ' refno=' . $refno);

$txn = null;
$pdo = db();

// 1) Try to find transaction by bill_code
if ($billCode) {
    $stmt = $pdo->prepare("
        SELECT pt.*, s.name, s.email
        FROM payment_transactions pt
        JOIN students s ON s.id = pt.student_id
        WHERE pt.bill_code = ?
        LIMIT 1
    ");
    $stmt->execute([$billCode]);
    $txn = $stmt->fetch();
}

// 2) Fallback: find by external ref (UV000003 -> id=3)
//    Needed on localhost where callback URL can't be reached by ToyyibPay
if (!$txn && $refno) {
    $txnId = (int) ltrim(str_replace('UV', '', $refno), '0');
    if ($txnId) {
        $stmt = $pdo->prepare("
            SELECT pt.*, s.name, s.email
            FROM payment_transactions pt
            JOIN students s ON s.id = pt.student_id
            WHERE pt.id = ?
            LIMIT 1
        ");
        $stmt->execute([$txnId]);
        $txn = $stmt->fetch();
    }
}

// 3) If ToyyibPay says paid (status=1) but DB is still Pending,
//    force-update right here. This covers the case where the server-to-server
//    callback hasn't fired yet (common on localhost without ngrok).
if ($status == '1' && $txn && $txn['payment_status'] !== 'Paid') {
    $pdo->prepare("
        UPDATE payment_transactions
        SET payment_status = 'Paid',
            bill_code      = COALESCE(NULLIF(bill_code, ''), ?),
            transaction_id = ?,
            paid_at        = NOW()
        WHERE id = ? AND payment_status != 'Paid'
    ")->execute([$billCode ?: $order_id, $order_id, $txn['id']]);

    if ($txn['registration_id']) {
        $pdo->prepare("UPDATE registrations SET status = 'registered' WHERE id = ?")
            ->execute([$txn['registration_id']]);
    }

    // Re-fetch so the receipt page shows updated data
    $stmt = $pdo->prepare("
        SELECT pt.*, s.name, s.email
        FROM payment_transactions pt
        JOIN students s ON s.id = pt.student_id
        WHERE pt.id = ?
        LIMIT 1
    ");
    $stmt->execute([$txn['id']]);
    $txn = $stmt->fetch();

    error_log('[UiVent Return] Force-updated txn #' . ($txn['id'] ?? '?') . ' to Paid (callback not yet received).');
}

// DB is source of truth; ToyyibPay status_id is fallback
$isSuccess = ($txn && $txn['payment_status'] === 'Paid') || $status == '1';
$isPending = !$isSuccess && ($status == '2' || ($txn && $txn['payment_status'] === 'Pending'));
$isFailed  = !$isSuccess && !$isPending;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $isSuccess ? 'Payment Successful' : ($isPending ? 'Payment Pending' : 'Payment Failed') ?> — UiVent</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  @keyframes bounceIn { 0%{transform:scale(.5);opacity:0} 60%{transform:scale(1.1)} 100%{transform:scale(1);opacity:1} }
  .icon-animate { animation:bounceIn .5s cubic-bezier(.4,0,.2,1) forwards; }
  @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
  .fade-up   { animation:fadeUp .4s ease-out forwards; }
  .fade-up-2 { animation:fadeUp .4s .15s ease-out both; }
  .fade-up-3 { animation:fadeUp .4s .3s  ease-out both; }
</style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6" style="font-family:'Inter',sans-serif;">
<div class="bg-white rounded-3xl shadow-xl max-w-md w-full overflow-hidden">

<?php if ($isSuccess): ?>
  <div class="p-8 text-center" style="background:linear-gradient(135deg,#27134A,#582C83);">
    <div class="icon-animate w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
         style="background:rgba(249,165,27,0.2);border:3px solid #F9A51B;">
      <i class="fas fa-check text-3xl" style="color:#F9A51B;"></i>
    </div>
    <h1 class="text-2xl font-extrabold text-white mb-1 fade-up">Payment Successful!</h1>
    <p class="text-purple-200 text-sm fade-up-2">Your payment has been received.</p>
  </div>
  <div class="p-8 space-y-4 fade-up-3">
    <?php if ($txn): ?>
    <div class="bg-gray-50 rounded-2xl p-4 space-y-3 text-sm">
      <div class="flex justify-between">
        <span class="text-gray-500 font-medium">Name</span>
        <span class="font-semibold text-gray-800"><?= htmlspecialchars($txn['name']) ?></span>
      </div>
      <div class="flex justify-between">
        <span class="text-gray-500 font-medium">Amount</span>
        <span class="text-xl font-extrabold" style="color:#27134A;">RM <?= number_format($txn['amount'], 2) ?></span>
      </div>
      <div class="flex justify-between">
        <span class="text-gray-500 font-medium">Bill Code</span>
        <span class="font-mono text-xs font-semibold text-gray-600"><?= htmlspecialchars($txn['bill_code'] ?? $billCode) ?></span>
      </div>
      <div class="flex justify-between">
        <span class="text-gray-500 font-medium">Status</span>
        <span class="text-emerald-600 font-bold">Paid ✓</span>
      </div>
    </div>
    <?php endif; ?>
    <div class="flex gap-3">
      <a href="users/payments.php" class="flex-1 py-3 rounded-xl text-sm font-bold text-white text-center" style="background:#582C83;">
        <i class="fas fa-receipt mr-1"></i> My Payments
      </a>
      <a href="users/mybookings.php" class="flex-1 py-3 rounded-xl text-sm font-bold text-center" style="background:#f0ebfa;color:#582C83;">
        <i class="fas fa-ticket mr-1"></i> My Bookings
      </a>
    </div>
    <a href="users/events.php" class="block text-center text-xs text-gray-400 hover:text-gray-600 mt-2">← Back to Browse Events</a>
  </div>

<?php elseif ($isPending): ?>
  <div class="p-8 text-center" style="background:linear-gradient(135deg,#78350f,#d97706);">
    <div class="icon-animate w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
         style="background:rgba(255,255,255,0.15);border:3px solid rgba(255,255,255,0.4);">
      <i class="fas fa-clock text-3xl text-white"></i>
    </div>
    <h1 class="text-2xl font-extrabold text-white mb-1 fade-up">Payment Pending</h1>
    <p class="text-amber-100 text-sm fade-up-2">Waiting for confirmation from payment gateway.</p>
  </div>
  <div class="p-8 space-y-4 fade-up-3">
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-sm text-amber-800">
      <p class="font-bold mb-1"><i class="fas fa-circle-info mr-1"></i> What happens next?</p>
      <ul class="space-y-1 list-disc list-inside text-amber-700">
        <li>Payment usually confirms within a few minutes.</li>
        <li>Check "My Payments" to see the updated status.</li>
      </ul>
    </div>
    <?php if ($billCode): ?>
    <p class="text-xs text-gray-400 text-center">Bill Code: <span class="font-mono font-semibold"><?= htmlspecialchars($billCode) ?></span></p>
    <?php endif; ?>
    <a href="users/payments.php" class="block w-full py-3 rounded-xl text-sm font-bold text-white text-center" style="background:#d97706;">
      <i class="fas fa-receipt mr-1"></i> Check Payment Status
    </a>
    <a href="users/events.php" class="block text-center text-xs text-gray-400 hover:text-gray-600">← Back to Browse Events</a>
  </div>

<?php else: ?>
  <div class="p-8 text-center" style="background:linear-gradient(135deg,#7f1d1d,#dc2626);">
    <div class="icon-animate w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
         style="background:rgba(255,255,255,0.15);border:3px solid rgba(255,255,255,0.4);">
      <i class="fas fa-times text-3xl text-white"></i>
    </div>
    <h1 class="text-2xl font-extrabold text-white mb-1 fade-up">Payment Failed</h1>
    <p class="text-red-100 text-sm fade-up-2">Your payment could not be processed.</p>
  </div>
  <div class="p-8 space-y-4 fade-up-3">
    <?php if ($reason): ?>
    <div class="bg-red-50 border border-red-200 rounded-2xl p-4 text-sm text-red-700">
      <p class="font-bold mb-1"><i class="fas fa-circle-exclamation mr-1"></i> Reason</p>
      <p><?= htmlspecialchars($reason) ?></p>
    </div>
    <?php endif; ?>
    <div class="flex gap-3">
      <a href="users/events.php" class="flex-1 py-3 rounded-xl text-sm font-bold text-white text-center" style="background:#582C83;">
        <i class="fas fa-rotate-right mr-1"></i> Try Again
      </a>
      <a href="users/home.php" class="flex-1 py-3 rounded-xl text-sm font-bold text-center" style="background:#f3f4f6;color:#374151;">
        Back to Home
      </a>
    </div>
  </div>
<?php endif; ?>

</div>
</body>
</html>