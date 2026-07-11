<?php
// ============================================================
//  superadmin/export_settlements.php
//  Exports filtered transactions as CSV or printable PDF report.
//  format=csv  → download CSV
//  format=pdf  → printable HTML report (browser print → PDF)
// ============================================================
require_once '../config.php';
requireSuperAdmin();

$format = in_array($_GET['format'] ?? '', ['csv','pdf']) ? $_GET['format'] : 'csv';

// ── Same filters as billing_settlement.php ────────────────────
$filterClub     = (int)(trim($_GET['club_id']   ?? ''));
$filterStatus   = trim($_GET['status']    ?? '');
$filterSettle   = trim($_GET['settle']    ?? '');
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo   = trim($_GET['date_to']   ?? '');
$search         = trim($_GET['q']         ?? '');

$where  = "WHERE 1=1";
$params = [];
if ($filterClub)     { $where .= " AND pt.club_id = :club_id";         $params[':club_id'] = $filterClub; }
if ($filterStatus)   { $where .= " AND pt.payment_status = :pstatus";  $params[':pstatus'] = $filterStatus; }
if ($filterSettle)   { $where .= " AND pt.settlement_status = :sstat"; $params[':sstat']   = $filterSettle; }
if ($filterDateFrom) { $where .= " AND DATE(pt.created_at) >= :dfrom"; $params[':dfrom']   = $filterDateFrom; }
if ($filterDateTo)   { $where .= " AND DATE(pt.created_at) <= :dto";   $params[':dto']     = $filterDateTo; }
if ($search) {
    $where .= " AND (s.name LIKE :q1 OR s.matric_no LIKE :q2 OR e.title LIKE :q3 OR pt.bill_code LIKE :q4 OR a.name LIKE :q5)";
    for ($i=1; $i<=5; $i++) $params[":q$i"] = "%$search%";
}

$stmt = db()->prepare("
    SELECT pt.id, pt.bill_code, pt.transaction_id,
           pt.amount, pt.payment_method,
           pt.payment_status, pt.settlement_status,
           pt.paid_at, pt.settled_at, pt.created_at,
           pt.dispute_note,
           s.name       AS student_name,
           s.matric_no,
           s.email      AS student_email,
           e.title      AS event_title,
           e.venue      AS event_venue,
           a.name       AS club_name
    FROM payment_transactions pt
    LEFT JOIN students s ON s.id = pt.student_id
    LEFT JOIN events   e ON e.id = pt.event_id
    LEFT JOIN admins   a ON a.id = pt.club_id
    $where
    ORDER BY pt.created_at DESC
    LIMIT 5000
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

function receiptNo(int $id): string {
    return 'RCP-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

$exportDate = date('d M Y H:i');
$filename   = 'UiVent_Settlements_' . date('Ymd_His');

// ══════════════════════════════════════════════════════════════
//  CSV EXPORT
// ══════════════════════════════════════════════════════════════
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');

    // BOM for Excel UTF-8
    fputs($out, "\xEF\xBB\xBF");

    // Header row
    fputcsv($out, [
        'Receipt No', 'Bill Code', 'Transaction ID',
        'Club', 'Student Name', 'Matric No', 'Student Email',
        'Event', 'Venue',
        'Amount (RM)', 'Payment Method',
        'Payment Status', 'Settlement Status',
        'Payment Date', 'Settled Date', 'Created At',
        'Dispute Note'
    ]);

    foreach ($rows as $r) {
        fputcsv($out, [
            receiptNo((int)$r['id']),
            $r['bill_code'] ?? '',
            $r['transaction_id'] ?? '',
            $r['club_name'] ?? '',
            $r['student_name'] ?? '',
            $r['matric_no'] ?? '',
            $r['student_email'] ?? '',
            $r['event_title'] ?? '',
            $r['event_venue'] ?? '',
            number_format($r['amount'], 2),
            $r['payment_method'] ?? '',
            $r['payment_status'],
            $r['settlement_status'],
            $r['paid_at']    ? date('d/m/Y H:i', strtotime($r['paid_at']))    : '',
            $r['settled_at'] ? date('d/m/Y H:i', strtotime($r['settled_at'])) : '',
            date('d/m/Y H:i', strtotime($r['created_at'])),
            $r['dispute_note'] ?? '',
        ]);
    }

    fclose($out);
    exit;
}

// ══════════════════════════════════════════════════════════════
//  PDF / PRINT REPORT
// ══════════════════════════════════════════════════════════════

// Summary stats
$totalRevenue   = array_sum(array_map(fn($r) => $r['payment_status']==='Paid' ? $r['amount'] : 0, $rows));
$paidCount      = count(array_filter($rows, fn($r) => $r['payment_status']==='Paid'));
$settledCount   = count(array_filter($rows, fn($r) => $r['settlement_status']==='Settled'));
$pendingCount   = count(array_filter($rows, fn($r) => $r['settlement_status']==='Pending' && $r['payment_status']==='Paid'));
$disputedCount  = count(array_filter($rows, fn($r) => $r['settlement_status']==='Disputed'));

$pStatusColor = ['Paid'=>'#059669','Pending'=>'#d97706','Failed'=>'#dc2626','Refunded'=>'#4f46e5'];
$sStatusColor = ['Settled'=>'#059669','Pending'=>'#d97706','Disputed'=>'#dc2626'];

$filterLabel = [];
if ($filterClub)     $filterLabel[] = 'Club ID: ' . $filterClub;
if ($filterStatus)   $filterLabel[] = 'Payment: ' . $filterStatus;
if ($filterSettle)   $filterLabel[] = 'Settlement: ' . $filterSettle;
if ($filterDateFrom) $filterLabel[] = 'From: ' . $filterDateFrom;
if ($filterDateTo)   $filterLabel[] = 'To: ' . $filterDateTo;
if ($search)         $filterLabel[] = 'Search: "' . htmlspecialchars($search) . '"';
$filterStr = $filterLabel ? implode('  ·  ', $filterLabel) : 'All transactions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Settlement Report — <?= $exportDate ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size:11px; color:#1f2937; background:#f9fafb; }

  .no-print { }
  @media print {
    .no-print { display:none !important; }
    body { background:#fff; font-size:10px; }
    .page { box-shadow:none !important; }
    table { page-break-inside:auto; }
    tr { page-break-inside:avoid; page-break-after:auto; }
  }

  /* Action bar */
  .action-bar { background:#27134A; color:#fff; padding:12px 24px; display:flex; align-items:center; justify-content:space-between; gap:12px; position:sticky; top:0; z-index:10; }
  .action-bar h2 { font-size:14px; font-weight:700; color:#F9A51B; }
  .btn { padding:.4rem 1rem; border-radius:.4rem; font-size:.75rem; font-weight:700; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:5px; text-decoration:none; }
  .btn-print { background:#F9A51B; color:#27134A; }
  .btn-back  { background:rgba(255,255,255,.1); color:#fff; }

  /* Report */
  .page { max-width:960px; margin:20px auto; background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,.08); overflow:hidden; }

  /* Report header */
  .report-header { background:linear-gradient(135deg,#27134A,#582C83); color:#fff; padding:28px 32px 20px; }
  .report-header .brand { display:flex; align-items:center; gap:8px; margin-bottom:16px; }
  .brand-badge { background:#F9A51B; color:#27134A; font-weight:800; padding:3px 8px; border-radius:5px; font-size:14px; }
  .brand-name  { font-size:17px; font-weight:700; }
  .report-header h1 { font-size:20px; font-weight:800; }
  .report-header .meta { font-size:10.5px; color:rgba(255,255,255,.6); margin-top:4px; }
  .report-header .filters { font-size:10px; color:#F9A51B; margin-top:6px; font-weight:600; }

  /* KPI strip */
  .kpi-strip { display:flex; gap:0; border-bottom:1px solid #f3f4f6; }
  .kpi { flex:1; padding:14px 18px; border-right:1px solid #f3f4f6; text-align:center; }
  .kpi:last-child { border-right:none; }
  .kpi .val { font-size:18px; font-weight:900; color:#27134A; }
  .kpi .lbl { font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#9ca3af; margin-top:2px; }

  /* Table */
  .table-wrap { overflow-x:auto; }
  table { width:100%; border-collapse:collapse; }
  thead tr { background:#f8f5ff; }
  th { padding:9px 10px; text-align:left; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; color:#582C83; border-bottom:2px solid #e9e3ff; white-space:nowrap; }
  td { padding:8px 10px; border-bottom:1px solid #f3f4f6; vertical-align:top; }
  tr:hover td { background:#faf8ff; }
  tr:last-child td { border-bottom:none; }

  .mono { font-family:monospace; font-size:10.5px; color:#582C83; font-weight:700; }
  .badge { display:inline-block; padding:2px 8px; border-radius:99px; font-size:9.5px; font-weight:700; }

  /* Footer */
  .report-footer { background:#f9fafb; border-top:1px solid #f3f4f6; padding:14px 32px; display:flex; justify-content:space-between; font-size:10px; color:#9ca3af; }
</style>
</head>
<body>

<div class="action-bar no-print">
  <h2>Settlement Report</h2>
  <div style="display:flex;gap:8px;">
    <a href="billing_settlement.php?tab=transactions" class="btn btn-back">← Back</a>
    <a href="export_settlements.php?<?= htmlspecialchars(http_build_query(array_merge($_GET,['format'=>'csv']))) ?>" class="btn btn-back">⬇ Download CSV</a>
    <button class="btn btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
  </div>
</div>

<div class="page">

  <!-- Header -->
  <div class="report-header">
    <div class="brand">
      <span class="brand-badge">Ui</span>
      <span class="brand-name">Vent</span>
      <span style="font-size:10px;color:rgba(249,165,27,.7);font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-left:4px;">Payment Settlement Report</span>
    </div>
    <h1>Settlement Report</h1>
    <div class="meta">Generated: <?= $exportDate ?> &nbsp;·&nbsp; <?= count($rows) ?> record<?= count($rows)!==1?'s':'' ?></div>
    <div class="filters"><?= $filterStr ?></div>
  </div>

  <!-- KPI strip -->
  <div class="kpi-strip">
    <div class="kpi"><div class="val">RM <?= number_format($totalRevenue, 2) ?></div><div class="lbl">Total Revenue</div></div>
    <div class="kpi"><div class="val"><?= $paidCount ?></div><div class="lbl">Paid Transactions</div></div>
    <div class="kpi"><div class="val"><?= $settledCount ?></div><div class="lbl">Settled</div></div>
    <div class="kpi"><div class="val" style="color:#d97706;"><?= $pendingCount ?></div><div class="lbl">Unsettled (Paid)</div></div>
    <div class="kpi"><div class="val" style="color:#dc2626;"><?= $disputedCount ?></div><div class="lbl">Disputed</div></div>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Receipt</th>
          <th>Club</th>
          <th>Student</th>
          <th>Matric</th>
          <th>Event</th>
          <th style="text-align:right;">Amount</th>
          <th>Method</th>
          <th>Payment Date</th>
          <th>Payment</th>
          <th>Settlement</th>
          <th>Settled Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="11" style="text-align:center;padding:30px;color:#9ca3af;">No records match the selected filters.</td></tr>
        <?php else: foreach ($rows as $r):
          $pc = $pStatusColor[$r['payment_status']] ?? '#374151';
          $sc = $sStatusColor[$r['settlement_status']] ?? '#374151';
        ?>
        <tr>
          <td><span class="mono"><?= receiptNo((int)$r['id']) ?></span></td>
          <td style="font-size:10.5px;max-width:100px;"><?= htmlspecialchars($r['club_name'] ?? '—') ?></td>
          <td style="max-width:120px;">
            <div style="font-weight:600;font-size:11px;"><?= htmlspecialchars($r['student_name'] ?? '—') ?></div>
          </td>
          <td><span style="font-family:monospace;font-size:10px;color:#6b7280;"><?= htmlspecialchars($r['matric_no'] ?? '—') ?></span></td>
          <td style="max-width:140px;font-size:10.5px;"><?= htmlspecialchars($r['event_title'] ?? '—') ?></td>
          <td style="text-align:right;font-weight:700;">RM <?= number_format($r['amount'], 2) ?></td>
          <td style="font-size:10px;color:#6b7280;"><?= htmlspecialchars($r['payment_method'] ?? '—') ?></td>
          <td style="font-size:10px;white-space:nowrap;color:#6b7280;">
            <?= $r['paid_at'] ? date('d M Y', strtotime($r['paid_at'])) : date('d M Y', strtotime($r['created_at'])) ?>
          </td>
          <td>
            <span class="badge" style="background:<?= $pc ?>22;color:<?= $pc ?>;"><?= $r['payment_status'] ?></span>
          </td>
          <td>
            <span class="badge" style="background:<?= $sc ?>22;color:<?= $sc ?>;"><?= $r['settlement_status'] ?></span>
            <?php if (!empty($r['dispute_note'])): ?>
            <div style="font-size:9px;color:#dc2626;margin-top:2px;" title="<?= htmlspecialchars($r['dispute_note']) ?>">⚑ Disputed</div>
            <?php endif; ?>
          </td>
          <td style="font-size:10px;white-space:nowrap;color:#6b7280;">
            <?= $r['settled_at'] ? date('d M Y', strtotime($r['settled_at'])) : '—' ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Footer -->
  <div class="report-footer">
    <span>UiVent — UiTM Event Management System &nbsp;·&nbsp; Operator Report</span>
    <span>Page 1 &nbsp;·&nbsp; <?= $exportDate ?></span>
  </div>

</div>

<script>
if (new URLSearchParams(location.search).get('print') === '1') {
  window.onload = () => setTimeout(() => window.print(), 400);
}
</script>
</body>
</html>
