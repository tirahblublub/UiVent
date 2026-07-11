<?php
// ============================================================
//  admin/payment_details.php — Payment Receipt / Print View
// ============================================================
require_once '../config.php';
requireAdmin();

$adminId = (int)$_SESSION['admin_id'];
$txId    = (int)($_GET['id'] ?? 0);

if (!$txId) {
    http_response_code(400);
    die('Invalid transaction ID.');
}

// Resolve this admin's club_id for ownership check
$adminRow = db()->prepare("SELECT club_id, name FROM admins WHERE id = ? LIMIT 1");
$adminRow->execute([$adminId]);
$adminRow = $adminRow->fetch();
$clubId   = (int)($adminRow['club_id'] ?? $adminId);

// Fetch transaction — must belong to this admin's club
$stmt = db()->prepare("
    SELECT pt.*,
           s.name       AS student_name,
           s.matric_no,
           s.email      AS student_email,
           s.phone      AS student_phone,
           e.title      AS event_title,
           e.start_date AS event_date,
           c.name       AS club_name
    FROM payment_transactions pt
    LEFT JOIN students s ON s.id = pt.student_id
    LEFT JOIN events   e ON e.id = pt.event_id
    LEFT JOIN clubs    c ON c.id = pt.club_id
    WHERE pt.id = ? AND pt.club_id = ?
    LIMIT 1
");
$stmt->execute([$txId, $clubId]);
$tx = $stmt->fetch();

if (!$tx) {
    http_response_code(404);
    die('Receipt not found or access denied.');
}

function receiptNo(int $id): string {
    return 'RCP-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

$statusColors = [
    'Paid'     => ['bg' => '#d1fae5', 'color' => '#065f46'],
    'Pending'  => ['bg' => '#fef3c7', 'color' => '#92400e'],
    'Failed'   => ['bg' => '#fee2e2', 'color' => '#991b1b'],
    'Refunded' => ['bg' => '#e0e7ff', 'color' => '#3730a3'],
];
$sc  = $statusColors[$tx['payment_status']]  ?? ['bg' => '#f3f4f6', 'color' => '#374151'];
$ssc = $tx['settlement_status'] === 'Settled'
     ? ['bg' => '#d1fae5', 'color' => '#065f46']
     : ['bg' => '#fef3c7', 'color' => '#92400e'];

$payDate = $tx['paid_at'] ?: $tx['created_at'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Receipt <?= receiptNo($txId) ?> | UiVent</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f3f4f6;
    color: #111827;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 32px 16px;
  }

  /* ── Print toolbar (hidden when printing) ── */
  .toolbar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    width: 100%;
    max-width: 680px;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
  }
  .btn-primary   { background: #582C83; color: #fff; }
  .btn-secondary { background: #fff; color: #374151; border: 1.5px solid #e5e7eb; }
  .btn:hover { opacity: .88; }

  /* ── Receipt card ── */
  .receipt {
    background: #fff;
    width: 100%;
    max-width: 680px;
    border-radius: 18px;
    box-shadow: 0 8px 40px rgba(88,44,131,.10);
    overflow: hidden;
  }

  /* Header band */
  .receipt-header {
    background: linear-gradient(135deg, #582C83 0%, #7c3aed 100%);
    color: #fff;
    padding: 28px 32px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
  .receipt-header .logo-area h2 {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -.5px;
  }
  .receipt-header .logo-area p {
    font-size: 12.5px;
    opacity: .8;
    margin-top: 2px;
  }
  .receipt-no {
    font-size: 13px;
    font-weight: 700;
    background: rgba(255,255,255,.18);
    border-radius: 8px;
    padding: 6px 14px;
    letter-spacing: .5px;
    white-space: nowrap;
  }

  /* Status banner */
  .status-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    font-weight: 700;
    font-size: 14px;
  }

  /* Body */
  .receipt-body { padding: 24px 32px 28px; }

  /* Amount hero */
  .amount-hero {
    text-align: center;
    padding: 20px 0 24px;
    border-bottom: 2px dashed #ede9fe;
    margin-bottom: 24px;
  }
  .amount-hero .label { font-size: 12px; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: .8px; }
  .amount-hero .amount { font-size: 40px; font-weight: 800; color: #582C83; line-height: 1.1; margin-top: 4px; }

  /* Detail grid */
  .detail-grid { display: flex; flex-direction: column; gap: 0; }
  .detail-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 10px 0;
    border-bottom: 1px solid #f5f0ff;
    font-size: 13.5px;
  }
  .detail-row:last-child { border-bottom: none; }
  .detail-k { color: #9ca3af; font-weight: 600; font-size: 12px; min-width: 140px; padding-top: 1px; }
  .detail-v { color: #111827; font-weight: 600; text-align: right; word-break: break-word; }

  .badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 99px;
  }

  /* Divider */
  .section-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: #a78bfa;
    margin: 20px 0 8px;
  }

  /* Footer */
  .receipt-footer {
    border-top: 1px solid #f3f4f6;
    padding: 16px 32px;
    text-align: center;
    font-size: 11.5px;
    color: #9ca3af;
    line-height: 1.6;
  }

  /* ── Print styles ── */
  @media print {
    body { background: #fff; padding: 0; }
    .toolbar { display: none !important; }
    .receipt {
      box-shadow: none;
      border-radius: 0;
      max-width: 100%;
    }
    @page { margin: 1cm; }
  }
</style>
</head>
<body>

<!-- Toolbar (screen only) -->
<div class="toolbar">
  <a href="payments.php" class="btn btn-secondary">
    <i class="fas fa-arrow-left"></i> Back
  </a>
  <button onclick="window.print()" class="btn btn-primary">
    <i class="fas fa-print"></i> Print Receipt
  </button>
</div>

<!-- Receipt card -->
<div class="receipt">

  <!-- Header -->
  <div class="receipt-header">
    <div class="logo-area">
      <h2>UiVent</h2>
      <p><?= htmlspecialchars($tx['club_name'] ?? 'Club Event Management') ?></p>
    </div>
    <div class="receipt-no"><?= receiptNo($txId) ?></div>
  </div>

  <!-- Status banner -->
  <div class="status-banner"
       style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;">
    <?php if ($tx['payment_status'] === 'Paid'): ?>
      <i class="fas fa-circle-check"></i> Payment Confirmed
    <?php elseif ($tx['payment_status'] === 'Pending'): ?>
      <i class="fas fa-clock"></i> Payment Pending
    <?php elseif ($tx['payment_status'] === 'Failed'): ?>
      <i class="fas fa-times-circle"></i> Payment Failed
    <?php else: ?>
      <i class="fas fa-rotate-left"></i> Payment <?= htmlspecialchars($tx['payment_status']) ?>
    <?php endif; ?>
  </div>

  <div class="receipt-body">

    <!-- Amount hero -->
    <div class="amount-hero">
      <div class="label">Total Amount</div>
      <div class="amount">RM <?= number_format((float)$tx['amount'], 2) ?></div>
    </div>

    <!-- Transaction info -->
    <p class="section-title">Transaction Info</p>
    <div class="detail-grid">
      <div class="detail-row">
        <span class="detail-k">Receipt No</span>
        <span class="detail-v" style="font-family:monospace;"><?= receiptNo($txId) ?></span>
      </div>
      <?php if ($tx['transaction_id']): ?>
      <div class="detail-row">
        <span class="detail-k">Transaction ID</span>
        <span class="detail-v" style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($tx['transaction_id']) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($tx['bill_code']): ?>
      <div class="detail-row">
        <span class="detail-k">Bill Code</span>
        <span class="detail-v" style="font-family:monospace;"><?= htmlspecialchars($tx['bill_code']) ?></span>
      </div>
      <?php endif; ?>
      <div class="detail-row">
        <span class="detail-k">Payment Method</span>
        <span class="detail-v"><?= htmlspecialchars($tx['payment_method'] ?? '—') ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-k">Payment Date</span>
        <span class="detail-v"><?= $payDate ? date('d M Y, h:i A', strtotime($payDate)) : '—' ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-k">Payment Status</span>
        <span class="detail-v">
          <span class="badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;border:1px solid <?= $sc['bg'] ?>;">
            <?= htmlspecialchars($tx['payment_status']) ?>
          </span>
        </span>
      </div>
      <div class="detail-row">
        <span class="detail-k">Settlement</span>
        <span class="detail-v">
          <span class="badge" style="background:<?= $ssc['bg'] ?>;color:<?= $ssc['color'] ?>;border:1px solid <?= $ssc['bg'] ?>;">
            <?= htmlspecialchars($tx['settlement_status']) ?>
          </span>
        </span>
      </div>
      <?php if ($tx['settled_at']): ?>
      <div class="detail-row">
        <span class="detail-k">Settled At</span>
        <span class="detail-v"><?= date('d M Y, h:i A', strtotime($tx['settled_at'])) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Student info -->
    <p class="section-title">Student Info</p>
    <div class="detail-grid">
      <div class="detail-row">
        <span class="detail-k">Name</span>
        <span class="detail-v"><?= htmlspecialchars($tx['student_name'] ?? '—') ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-k">Matric No</span>
        <span class="detail-v" style="font-family:monospace;"><?= htmlspecialchars($tx['matric_no'] ?? '—') ?></span>
      </div>
      <?php if ($tx['student_email']): ?>
      <div class="detail-row">
        <span class="detail-k">Email</span>
        <span class="detail-v"><?= htmlspecialchars($tx['student_email']) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($tx['student_phone']): ?>
      <div class="detail-row">
        <span class="detail-k">Phone</span>
        <span class="detail-v"><?= htmlspecialchars($tx['student_phone']) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Event info -->
    <p class="section-title">Event Info</p>
    <div class="detail-grid">
      <div class="detail-row">
        <span class="detail-k">Event</span>
        <span class="detail-v"><?= htmlspecialchars($tx['event_title'] ?? '—') ?></span>
      </div>
      <?php if ($tx['event_date']): ?>
      <div class="detail-row">
        <span class="detail-k">Event Date</span>
        <span class="detail-v"><?= date('d M Y', strtotime($tx['event_date'])) ?></span>
      </div>
      <?php endif; ?>
      <div class="detail-row">
        <span class="detail-k">Organiser</span>
        <span class="detail-v"><?= htmlspecialchars($tx['club_name'] ?? '—') ?></span>
      </div>
    </div>

  </div><!-- /receipt-body -->

  <div class="receipt-footer">
    This receipt was generated by UiVent &bull; <?= date('d M Y, h:i A') ?><br>
    For enquiries, please contact your club administrator.
  </div>

</div><!-- /receipt -->

</body>
</html>