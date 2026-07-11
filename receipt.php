<?php
// ============================================================
//  superadmin/receipt.php — Payment Receipt PDF
//  Generates a printable receipt for a single transaction.
//  Works WITHOUT any composer library — pure HTML print view.
//  Also supports ?format=pdf via mPDF if installed.
// ============================================================
require_once '../config.php';
requireSuperAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); die('Missing transaction ID.'); }

// Fetch transaction with all joined data
$stmt = db()->prepare("
    SELECT pt.*,
           s.name       AS student_name,
           s.matric_no,
           s.email      AS student_email,
           s.faculty,
           s.year,
           e.title      AS event_title,
           e.venue      AS event_venue,
           DATE_FORMAT(e.start_date,'%d %b %Y') AS event_date,
           a.name       AS club_name,
           a.email      AS club_email
    FROM payment_transactions pt
    LEFT JOIN students s ON s.id = pt.student_id
    LEFT JOIN events   e ON e.id = pt.event_id
    LEFT JOIN admins   a ON a.id = pt.club_id
    WHERE pt.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$tx = $stmt->fetch();

if (!$tx) { http_response_code(404); die('Transaction not found.'); }

function receiptNo(int $id): string {
    return 'RCP-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

$rno       = receiptNo((int)$tx['id']);
$amount    = 'RM ' . number_format($tx['amount'], 2);
$payDate   = $tx['paid_at']   ? date('d M Y, h:i A', strtotime($tx['paid_at']))   : date('d M Y, h:i A', strtotime($tx['created_at']));
$settleDate = $tx['settled_at'] ? date('d M Y, h:i A', strtotime($tx['settled_at'])) : '—';
$printDate = date('d M Y, h:i A');

$pStatusColor = match($tx['payment_status']) {
    'Paid'     => '#059669',
    'Pending'  => '#d97706',
    'Failed'   => '#dc2626',
    'Refunded' => '#4f46e5',
    default    => '#374151',
};
$sStatusColor = match($tx['settlement_status']) {
    'Settled'  => '#059669',
    'Disputed' => '#dc2626',
    default    => '#d97706',
};

// ── mPDF support (optional — falls back to print-CSS if not installed) ──
$useMpdf = isset($_GET['format']) && $_GET['format'] === 'pdf' && class_exists('Mpdf\Mpdf');
if ($useMpdf) {
    ob_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Receipt <?= $rno ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background:#f3f4f6; color:#1f2937; font-size:13px; }

  .page { max-width:680px; margin:30px auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.10); }

  /* Header */
  .receipt-header { background:linear-gradient(135deg,#27134A 0%,#582C83 100%); color:#fff; padding:32px 36px 24px; }
  .brand { display:flex; align-items:center; gap:10px; margin-bottom:20px; }
  .brand-badge { background:#F9A51B; color:#27134A; font-weight:800; font-size:16px; padding:4px 10px; border-radius:6px; letter-spacing:.5px; }
  .brand-name { font-size:20px; font-weight:700; letter-spacing:.5px; }
  .brand-sub { font-size:11px; color:rgba(249,165,27,.8); font-weight:600; letter-spacing:.08em; text-transform:uppercase; }
  .receipt-title { display:flex; justify-content:space-between; align-items:flex-end; }
  .receipt-title h1 { font-size:22px; font-weight:800; letter-spacing:.5px; }
  .receipt-title .rno { font-size:13px; color:#F9A51B; font-weight:700; letter-spacing:.08em; font-family:monospace; }

  /* Status ribbon */
  .status-ribbon { background:rgba(255,255,255,.07); padding:10px 36px; display:flex; gap:24px; border-bottom:1px solid rgba(255,255,255,.08); }
  .status-item { display:flex; flex-direction:column; gap:2px; }
  .status-label { font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:rgba(255,255,255,.55); }
  .status-val { font-size:12px; font-weight:700; }
  .badge { display:inline-block; padding:2px 10px; border-radius:99px; font-size:10.5px; font-weight:700; }

  /* Body */
  .receipt-body { padding:28px 36px; }
  .section { margin-bottom:22px; }
  .section-title { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.1em; color:#9ca3af; margin-bottom:10px; padding-bottom:6px; border-bottom:1.5px solid #f3f4f6; }
  .row { display:flex; justify-content:space-between; align-items:flex-start; padding:5px 0; gap:12px; }
  .row-label { color:#6b7280; font-size:12px; flex-shrink:0; }
  .row-val { color:#111827; font-weight:600; font-size:12px; text-align:right; word-break:break-word; max-width:60%; }

  /* Amount box */
  .amount-box { background:linear-gradient(135deg,#f0ebfa,#e8dfff); border-radius:12px; padding:20px 24px; text-align:center; margin:20px 0; }
  .amount-box .label { font-size:11px; color:#582C83; font-weight:700; text-transform:uppercase; letter-spacing:.08em; margin-bottom:6px; }
  .amount-box .amount { font-size:34px; font-weight:900; color:#27134A; letter-spacing:.5px; }
  .amount-box .method { font-size:11px; color:#6b7280; margin-top:4px; }

  /* Footer */
  .receipt-footer { background:#fafafa; border-top:1.5px dashed #e5e7eb; padding:18px 36px; display:flex; justify-content:space-between; align-items:center; }
  .footer-note { font-size:10.5px; color:#9ca3af; }
  .footer-print { font-size:10px; color:#d1d5db; }

  /* QR placeholder */
  .verify-box { border:1.5px solid #e5e7eb; border-radius:10px; padding:14px 18px; text-align:center; }
  .verify-code { font-family:monospace; font-size:14px; font-weight:800; color:#582C83; letter-spacing:.12em; }
  .verify-label { font-size:10px; color:#9ca3af; margin-top:3px; }

  /* Print styles */
  @media print {
    body { background:#fff; }
    .page { box-shadow:none; margin:0; border-radius:0; max-width:100%; }
    .no-print { display:none !important; }
  }

  /* Action bar */
  .action-bar { background:#fff; padding:14px 36px; display:flex; gap:10px; justify-content:flex-end; border-top:1px solid #f3f4f6; }
  .btn { padding:.5rem 1.2rem; border-radius:.5rem; font-size:.8rem; font-weight:700; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:6px; text-decoration:none; }
  .btn-print { background:#582C83; color:#fff; }
  .btn-print:hover { background:#27134A; }
  .btn-close { background:#f3f4f6; color:#374151; }
  .btn-close:hover { background:#e5e7eb; }
</style>
</head>
<body>

<!-- Action bar (hidden when printing) -->
<div class="action-bar no-print">
  <a href="billing_settlement.php?tab=transactions" class="btn btn-close">
    ← Back
  </a>
  <a href="receipt.php?id=<?= $id ?>&format=pdf" class="btn btn-close" <?= $useMpdf ? 'style="display:none"' : '' ?>>
    ⬇ Download PDF
  </a>
  <button class="btn btn-print" onclick="window.print()">
    🖨 Print Receipt
  </button>
</div>

<div class="page">

  <!-- Header -->
  <div class="receipt-header">
    <div class="brand">
      <span class="brand-badge">Ui</span>
      <div>
        <div class="brand-name">Vent</div>
        <div class="brand-sub">UiTM Event Management System</div>
      </div>
    </div>
    <div class="receipt-title">
      <h1>Payment Receipt</h1>
      <div class="rno"><?= $rno ?></div>
    </div>
  </div>

  <!-- Status ribbon -->
  <div class="receipt-header status-ribbon" style="padding-top:12px;padding-bottom:12px;">
    <div class="status-item">
      <span class="status-label">Payment</span>
      <span class="badge" style="background:<?= $pStatusColor ?>22;color:<?= $pStatusColor ?>;">
        <?= htmlspecialchars($tx['payment_status']) ?>
      </span>
    </div>
    <div class="status-item">
      <span class="status-label">Settlement</span>
      <span class="badge" style="background:<?= $sStatusColor ?>22;color:<?= $sStatusColor ?>;">
        <?= htmlspecialchars($tx['settlement_status']) ?>
      </span>
    </div>
    <div class="status-item">
      <span class="status-label">Payment Date</span>
      <span class="status-val" style="color:#fff;"><?= $payDate ?></span>
    </div>
    <div class="status-item">
      <span class="status-label">Settled On</span>
      <span class="status-val" style="color:#fff;"><?= $settleDate ?></span>
    </div>
  </div>

  <div class="receipt-body">

    <!-- Amount -->
    <div class="amount-box">
      <div class="label">Total Amount Paid</div>
      <div class="amount"><?= $amount ?></div>
      <div class="method"><?= htmlspecialchars($tx['payment_method'] ?? 'Online Banking') ?></div>
    </div>

    <!-- Transaction Info -->
    <div class="section">
      <div class="section-title">Transaction Details</div>
      <div class="row"><span class="row-label">Receipt No</span><span class="row-val" style="font-family:monospace;color:#582C83;"><?= $rno ?></span></div>
      <?php if (!empty($tx['transaction_id'])): ?>
      <div class="row"><span class="row-label">Transaction ID</span><span class="row-val" style="font-family:monospace;"><?= htmlspecialchars($tx['transaction_id']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($tx['bill_code'])): ?>
      <div class="row"><span class="row-label">Bill Code</span><span class="row-val" style="font-family:monospace;"><?= htmlspecialchars($tx['bill_code']) ?></span></div>
      <?php endif; ?>
      <div class="row"><span class="row-label">Payment Method</span><span class="row-val"><?= htmlspecialchars($tx['payment_method'] ?? 'Online Banking') ?></span></div>
      <div class="row"><span class="row-label">Payment Date</span><span class="row-val"><?= $payDate ?></span></div>
      <?php if ($tx['settled_at']): ?>
      <div class="row"><span class="row-label">Settled On</span><span class="row-val"><?= $settleDate ?></span></div>
      <?php endif; ?>
    </div>

    <!-- Student Info -->
    <div class="section">
      <div class="section-title">Student Information</div>
      <div class="row"><span class="row-label">Full Name</span><span class="row-val"><?= htmlspecialchars($tx['student_name'] ?? '—') ?></span></div>
      <div class="row"><span class="row-label">Matric No</span><span class="row-val" style="font-family:monospace;"><?= htmlspecialchars($tx['matric_no'] ?? '—') ?></span></div>
      <?php if (!empty($tx['student_email'])): ?>
      <div class="row"><span class="row-label">Email</span><span class="row-val"><?= htmlspecialchars($tx['student_email']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($tx['faculty'])): ?>
      <div class="row"><span class="row-label">Faculty</span><span class="row-val"><?= htmlspecialchars($tx['faculty']) ?></span></div>
      <?php endif; ?>
    </div>

    <!-- Event Info -->
    <?php if (!empty($tx['event_title'])): ?>
    <div class="section">
      <div class="section-title">Event Details</div>
      <div class="row"><span class="row-label">Event Name</span><span class="row-val"><?= htmlspecialchars($tx['event_title']) ?></span></div>
      <?php if (!empty($tx['event_venue'])): ?>
      <div class="row"><span class="row-label">Venue</span><span class="row-val"><?= htmlspecialchars($tx['event_venue']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($tx['event_date'])): ?>
      <div class="row"><span class="row-label">Date</span><span class="row-val"><?= htmlspecialchars($tx['event_date']) ?></span></div>
      <?php endif; ?>
      <div class="row"><span class="row-label">Organiser</span><span class="row-val"><?= htmlspecialchars($tx['club_name'] ?? '—') ?></span></div>
    </div>
    <?php endif; ?>

    <!-- Dispute note if any -->
    <?php if (!empty($tx['dispute_note'])): ?>
    <div class="section">
      <div class="section-title" style="color:#dc2626;">Dispute Record</div>
      <div style="background:#fff5f5;border:1px solid #fecaca;border-radius:8px;padding:12px 14px;font-size:12px;color:#991b1b;">
        <?= htmlspecialchars($tx['dispute_note']) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Verification code -->
    <div class="verify-box">
      <div class="verify-code"><?= $rno ?>-<?= strtoupper(substr(md5($tx['id'] . $tx['amount'] . $tx['created_at']), 0, 8)) ?></div>
      <div class="verify-label">Verification Code — present this to verify authenticity</div>
    </div>

  </div>

  <!-- Footer -->
  <div class="receipt-footer">
    <div class="footer-note">
      This is an official UiVent payment receipt.<br>
      For enquiries: <?= htmlspecialchars($tx['club_email'] ?? 'support@uitm.edu.my') ?>
    </div>
    <div class="footer-print">Printed: <?= $printDate ?></div>
  </div>

</div>

<?php if (!$useMpdf): ?>
<script>
// Auto-trigger print if ?print=1
if (new URLSearchParams(location.search).get('print') === '1') {
  window.onload = () => setTimeout(() => window.print(), 300);
}
</script>
<?php endif; ?>

</body>
</html>
<?php
// ── mPDF output ───────────────────────────────────────────────
if ($useMpdf) {
    $html = ob_get_clean();
    $mpdf = new \Mpdf\Mpdf([
        'margin_top'    => 0,
        'margin_bottom' => 0,
        'margin_left'   => 0,
        'margin_right'  => 0,
        'format'        => 'A4',
    ]);
    $mpdf->WriteHTML($html);
    $mpdf->Output('Receipt_' . $rno . '.pdf', 'D');
}
