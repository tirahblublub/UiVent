<?php
require_once __DIR__ . '/../config.php';
requireStudent();

$sid     = (int) $_SESSION['student_id'];
$student = $_SESSION['student'] ?? [];
$sName   = htmlspecialchars($student['name'] ?? 'Student');

$payments = []; $totalPaid = 0; $totalPending = 0;
$outstanding = [];

try {
    $pdo = db();

    // ── 1. Event registrations joined with payment_transactions ───────
    $s1 = $pdo->prepare("
        SELECT 'event'                                    AS type,
               r.id,
               e.title                                    AS item_name,
               1                                          AS quantity,
               e.registration_fee                         AS amount,
               CASE
                 WHEN e.registration_fee = 0 OR e.registration_fee IS NULL THEN 'Free'
                 WHEN pt.payment_status IN ('Paid','paid') THEN 'Paid'
                 WHEN pt.id IS NOT NULL THEN pt.payment_status
                 ELSE 'Pending'
               END                                        AS payment_status,
               pt.paid_at                                 AS paid_at,
               COALESCE(pt.id, 0)                         AS txn_id,
               COALESCE(pt.bill_code, CONCAT('EVT-', LPAD(r.id, 4, '0'))) AS ref_no
        FROM registrations r
        JOIN events e ON e.id = r.event_id
        LEFT JOIN payment_transactions pt
               ON pt.registration_id = r.id
              AND pt.student_id      = r.student_id
              AND pt.payment_status IN ('Paid','paid','Pending')
        WHERE r.student_id = ?
          AND r.status != 'cancelled'
        ORDER BY r.registered_at DESC
    ");
    $s1->execute([$sid]);
    $evRows = $s1->fetchAll(PDO::FETCH_ASSOC);

    // ── 2. Merch orders ───────────────────────────────────────────────
    $s2 = $pdo->prepare("
        SELECT 'merchandise'                              AS type,
               mo.id,
               m.name                                     AS item_name,
               mo.quantity,
               mo.total_price                             AS amount,
               mo.status                                  AS payment_status,
               COALESCE(mo.paid_at, mo.ordered_at)        AS paid_at,
               0                                          AS txn_id,
               CONCAT('MER-', LPAD(mo.id, 4, '0'))       AS ref_no
        FROM merch_orders mo
        JOIN merchandise m ON m.id = mo.merch_id
        WHERE mo.student_id = ?
          AND mo.status != 'cancelled'
        ORDER BY mo.ordered_at DESC
    ");
    $s2->execute([$sid]);
    $merchRows = $s2->fetchAll(PDO::FETCH_ASSOC);

    // ── 3. Merge & split ──────────────────────────────────────────────
    $all = array_merge($evRows, $merchRows);

    foreach ($all as $p) {
        $amount  = (float) $p['amount'];
        $isFree  = $p['type'] === 'event' && $amount == 0;
        $isPaid  = in_array($p['payment_status'], ['Paid', 'paid', 'attended', 'Free'], true) || $isFree;

        if ($isPaid) {
            $totalPaid += $amount;
            $payments[] = $p;
        } else {
            $totalPending += $amount;
            $outstanding[] = $p;
            $payments[]    = $p;
        }
    }

} catch (Throwable $e) {
    error_log('payments.php error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Payments</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; }
.sidebar-nav-btn { transition: all 0.18s; }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">

<!-- ═══ SIDEBAR ═══ -->
<aside class="w-64 bg-purple-950 text-white flex-col hidden md:flex shrink-0 shadow-xl">
  <div class="overflow-y-auto flex-1 min-h-0">
    <div class="h-16 flex items-center px-6 border-b border-purple-900 bg-purple-900/40 sticky top-0">
      <div class="bg-amber-500 text-purple-950 px-2.5 py-1 rounded-md font-extrabold text-lg tracking-wider mr-2">Ui</div>
      <span class="font-bold text-xl tracking-wide">Vent</span>
      <span class="text-xs bg-purple-800 text-purple-200 ml-2 px-1.5 py-0.5 rounded uppercase">Student</span>
    </div>
    <nav class="mt-6 px-4 space-y-1 pb-4">
      <a href="home.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg group">
        <i class="fas fa-home text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Home</span>
      </a>
      <a href="events.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg group">
        <i class="fas fa-calendar-alt text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Browse Events</span>
      </a>
      <a href="mybookings.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg group">
        <i class="fas fa-ticket-alt text-lg w-5 text-purple-400 group-hover:text-white"></i><span>My Registrations</span>
      </a>
      <a href="attendance.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg group">
        <i class="fas fa-chart-bar text-lg w-5 text-purple-400 group-hover:text-white"></i><span>My Attendance</span>
      </a>
      <a href="announcements.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg group">
        <i class="fas fa-bullhorn text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Announcements</span>
      </a>
      <a href="profile.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg group">
        <i class="fas fa-user text-lg w-5 text-purple-400 group-hover:text-white"></i><span>My Profile</span>
      </a>
      <div class="mt-4 mb-1 px-1"><p class="text-xs font-bold uppercase tracking-widest text-purple-600">More</p></div>
      <a href="merchandise.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg group">
        <i class="fas fa-tshirt text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Merchandise</span>
      </a>
      <a href="payments.php" class="sidebar-nav-btn w-full flex items-center space-x-3 bg-amber-500 text-purple-950 font-semibold px-4 py-3 rounded-lg shadow-sm">
        <i class="fas fa-credit-card text-lg w-5"></i><span>Payments</span>
      </a>
      <a href="feedback.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg group">
        <i class="fas fa-comment-dots text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Feedback</span>
      </a>
      <div class="mt-2 pt-2 border-t border-purple-900">
        <a href="logout.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-300 hover:bg-red-900/40 hover:text-red-300 px-4 py-3 rounded-lg group">
          <i class="fas fa-sign-out-alt text-lg w-5 text-purple-500 group-hover:text-red-300"></i><span>Logout</span>
        </a>
      </div>
    </nav>
  </div>
</aside>

<!-- ═══ MAIN ═══ -->
<div class="flex-1 flex flex-col h-full overflow-y-auto">

  <!-- TOPBAR -->
  <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 md:px-8 shrink-0 sticky top-0 z-10 shadow-sm">
    <div class="flex items-center space-x-4">
      <button class="text-gray-500 hover:text-gray-700 md:hidden"><i class="fas fa-bars text-xl"></i></button>
      <h2 class="text-xl font-bold text-gray-800">Payments</h2>
    </div>
    <div class="flex items-center space-x-4">
      <a href="announcements.php" class="p-2 text-gray-400 hover:text-purple-900 relative">
        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
        <i class="far fa-bell text-lg"></i>
      </a>
      <div class="h-6 w-px bg-gray-200"></div>
      <a href="profile.php" class="flex items-center space-x-3">
        <div class="text-right hidden md:block">
          <p class="text-sm font-semibold text-gray-800"><?= $sName ?></p>
          <p class="text-xs text-gray-500">Information Science Faculty &middot; Year 3</p>
        </div>
        <img class="w-9 h-9 rounded-full ring-2 ring-purple-100 object-cover" src="images/passport.jpg" alt="User">
      </a>
    </div>
  </header>

  <main class="flex-1 p-6 md:p-8 max-w-4xl w-full mx-auto space-y-6">

    <div>
      <h3 class="text-2xl font-bold text-gray-900">Payments</h3>
      <p class="text-sm text-gray-500 mt-1">Your outstanding dues and full payment history.</p>
    </div>

    <!-- ── Summary cards ── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
          <i class="fas fa-check-circle text-emerald-600 text-lg"></i>
        </div>
        <div>
          <p class="text-xs text-gray-500 font-medium">Total Paid</p>
          <p class="text-xl font-extrabold text-gray-900">RM <?= number_format($totalPaid, 2) ?></p>
        </div>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
          <i class="fas fa-clock text-amber-500 text-lg"></i>
        </div>
        <div>
          <p class="text-xs text-gray-500 font-medium">Outstanding</p>
          <p class="text-xl font-extrabold text-gray-900">RM <?= number_format($totalPending, 2) ?></p>
        </div>
      </div>
    </div>

    <!-- ── Outstanding payments ── -->
    <?php if (!empty($outstanding)): ?>
    <div class="space-y-3">
      <h4 class="font-bold text-sm text-gray-800 uppercase tracking-wider">Outstanding Payments</h4>
      <?php foreach ($outstanding as $p):
        $amount    = (float) $p['amount'];
        $isMerch   = $p['type'] === 'merchandise';
        $typeLabel = $isMerch ? 'Merchandise Order' : 'Event Fee';
        $rowId     = 'pay-row-' . $p['type'] . '-' . $p['id'];
      ?>
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col md:flex-row md:items-center gap-4" id="<?= $rowId ?>">
        <div class="flex-1 space-y-1">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-bold text-gray-900"><?= htmlspecialchars($p['item_name']) ?></span>
            <span class="text-xs font-bold px-2 py-0.5 rounded border bg-amber-50 text-amber-600 border-amber-100">Pending</span>
            <span class="text-xs text-gray-400 font-medium"><?= $typeLabel ?></span>
          </div>
          <p class="text-xs text-gray-500 font-mono">Ref: <?= htmlspecialchars($p['ref_no']) ?></p>
          <?php if ($isMerch): ?>
          <p class="text-xs text-gray-400">Qty: <?= (int)$p['quantity'] ?></p>
          <?php endif; ?>
        </div>
        <div class="flex items-center gap-4 shrink-0">
          <span class="text-xl font-extrabold text-purple-900">RM <?= number_format($amount, 2) ?></span>
          <button onclick="openPayModal(
                    '<?= addslashes(htmlspecialchars($p['item_name'])) ?>',
                    'RM <?= number_format($amount, 2) ?>',
                    '<?= addslashes($p['ref_no']) ?>',
                    '<?= $rowId ?>',
                    '<?= $p['type'] ?>',
                    <?= $p['type'] === 'event' ? (int)$p['txn_id'] : (int)$p['id'] ?>,
                    '<?= number_format($amount, 2) ?>'
                  )"
                  class="bg-purple-900 hover:bg-purple-800 text-white font-bold text-xs px-4 py-2.5 rounded-lg transition-colors">
            Pay Now
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">
      <i class="fas fa-circle-check text-4xl mb-3 block text-emerald-400"></i>
      <p class="text-sm font-semibold text-gray-700">All payments are up to date!</p>
      <p class="text-xs text-gray-400 mt-1">You have no outstanding dues.</p>
    </div>
    <?php endif; ?>

    <!-- ── Payment History ── -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="p-4 border-b border-gray-100 flex items-center justify-between">
        <h4 class="font-bold text-sm text-gray-900">Payment History</h4>
        <span class="text-xs text-gray-400"><?= count($payments) ?> record<?= count($payments) !== 1 ? 's' : '' ?></span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
              <th class="py-3 px-5">Description</th>
              <th class="py-3 px-5">Type</th>
              <th class="py-3 px-5">Ref</th>
              <th class="py-3 px-5">Date</th>
              <th class="py-3 px-5">Amount</th>
              <th class="py-3 px-5">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($payments)): ?>
            <tr>
              <td colspan="6" class="text-center py-12 text-gray-400">
                <i class="fas fa-receipt text-3xl mb-2 block" style="color:#ddd5f5;"></i>
                <p class="text-sm">No payment records yet.</p>
              </td>
            </tr>
            <?php else: foreach ($payments as $p):
              $amount    = (float) $p['amount'];
              $isMerch   = $p['type'] === 'merchandise';
              $isFree    = !$isMerch && $amount == 0;
              $isPaid    = in_array($p['payment_status'], ['Paid', 'paid', 'attended'], true) || $isFree;
              $typeLabel = $isMerch ? 'Merchandise' : 'Event Fee';
              $isFreeRow = ($p['payment_status'] === 'Free');
              $dateStr   = $isFreeRow ? date('d M Y', strtotime($p['paid_at'] ?? date('Y-m-d'))) : ($p['paid_at'] ? date('d M Y', strtotime($p['paid_at'])) : '—');

              if ($isFree) {
                $statusCls   = 'bg-blue-50 text-blue-600 border-blue-100';
                $statusLabel = 'Free';
              } elseif ($isPaid) {
                $statusCls   = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                $statusLabel = 'Paid';
              } else {
                $statusCls   = 'bg-amber-50 text-amber-700 border-amber-100';
                $statusLabel = 'Pending';
              }
            ?>
            <tr class="hover:bg-gray-50 transition-colors">
              <td class="px-5 py-3.5">
                <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($p['item_name']) ?></p>
                <?php if ($isMerch): ?>
                <p class="text-xs text-gray-400">Qty: <?= (int)$p['quantity'] ?></p>
                <?php endif; ?>
              </td>
              <td class="px-5 py-3.5 text-xs text-gray-500"><?= $typeLabel ?></td>
              <td class="px-5 py-3.5 text-xs font-mono text-gray-600"><?= htmlspecialchars($p['ref_no']) ?></td>
              <td class="px-5 py-3.5 text-xs text-gray-500"><?= $dateStr ?></td>
              <td class="px-5 py-3.5 text-sm font-bold text-gray-900">
                <?php if ($isFree): ?>
                  <span class="text-blue-500 font-semibold text-xs">FREE</span>
                <?php else: ?>
                  RM <?= number_format($amount, 2) ?>
                <?php endif; ?>
              </td>
              <td class="px-5 py-3.5">
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold border <?= $statusCls ?>"><?= $statusLabel ?></span>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<!-- TOAST -->
<div id="toast" class="fixed bottom-6 right-6 z-[100] hidden">
  <div class="bg-gray-900 text-white text-sm font-medium px-5 py-3 rounded-xl shadow-2xl flex items-center space-x-3">
    <i class="fas fa-check-circle text-emerald-400"></i>
    <span id="toastMsg">Done.</span>
  </div>
</div>

<!-- PAYMENT MODAL -->
<div id="payModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-[90] hidden flex items-center justify-center p-4">
  <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
      <h3 class="text-lg font-bold text-gray-900">Complete Payment</h3>
      <button onclick="closePayModal()" class="text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
    </div>
    <div class="p-5 space-y-4">
      <div class="bg-purple-50 border border-purple-100 rounded-xl p-4 flex items-center justify-between">
        <div>
          <p class="text-xs text-gray-500">Paying for</p>
          <p class="font-bold text-gray-900 text-sm" id="pmDesc">—</p>
          <p class="text-xs text-gray-400 font-mono mt-0.5" id="pmRef">—</p>
        </div>
        <p class="text-xl font-extrabold text-purple-900" id="pmAmount">RM 0.00</p>
      </div>
      <p class="text-xs text-gray-500">You'll be redirected to ToyyibPay's secure checkout to pay by Card, FPX, or e-Wallet.</p>
      <p id="payError" class="text-xs text-red-600 font-semibold hidden"></p>
      <form id="payForm" method="POST" action="create_bill.php">
        <input type="hidden" name="type"   id="pmType">
        <input type="hidden" name="ref_id" id="pmRefId">
        <input type="hidden" name="desc"   id="pmDescInput">
        <input type="hidden" name="amount" id="pmAmountInput">
        <button type="submit" class="w-full bg-purple-900 hover:bg-purple-800 text-white font-bold text-sm py-3 rounded-lg transition-colors flex items-center justify-center gap-2">
          <i class="fas fa-lock"></i> Continue to ToyyibPay
        </button>
      </form>
    </div>
  </div>
</div>

<script>
let toastTimer;
function showToast(msg) {
  const el = document.getElementById('toast');
  document.getElementById('toastMsg').innerText = msg;
  el.classList.remove('hidden');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.add('hidden'), 3200);
}

function openPayModal(desc, amount, ref, rowId, type, refId, amountValue) {
  document.getElementById('pmDesc').innerText        = desc;
  document.getElementById('pmAmount').innerText      = amount;
  document.getElementById('pmRef').innerText         = 'Ref: ' + ref;
  document.getElementById('pmType').value            = type || '';
  document.getElementById('pmRefId').value           = refId || '';
  document.getElementById('pmDescInput').value       = desc;
  document.getElementById('pmAmountInput').value     = amountValue || amount.replace(/[^\d.]/g, '');
  document.getElementById('payError').classList.add('hidden');
  document.getElementById('payModal').classList.remove('hidden');
}

function closePayModal() {
  document.getElementById('payModal').classList.add('hidden');
}

document.getElementById('payModal').addEventListener('click', function(e) {
  if (e.target === this) closePayModal();
});
</script>
</body>
</html>