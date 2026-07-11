<?php
// ============================================================
//  admin/payments.php — Payment Settlement (Club Admin view)
//  Club admins see ONLY transactions belonging to their club.
// ============================================================
require_once '../config.php';
requireAdmin();

$activePage = 'payments';
$pageTitle  = 'Payment Settlement';
$adminData  = currentClubAdmin();
$adminId    = (int)$_SESSION['admin_id'];

// Resolve this admin's club_id
$adminRow = db()->prepare("SELECT club_id, name FROM admins WHERE id = ? LIMIT 1");
$adminRow->execute([$adminId]);
$adminRow = $adminRow->fetch();
$clubId   = (int)($adminRow['club_id'] ?? $adminId); // fallback: use admin id as club id

// ── AJAX: Mark settlement ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'settle') {
    $txId = (int)($_POST['tx_id'] ?? 0);
    // Verify ownership
    $chk = db()->prepare("SELECT id FROM payment_transactions WHERE id=? AND club_id=? LIMIT 1");
    $chk->execute([$txId, $clubId]);
    if (!$chk->fetch()) jsonResponse(false, 'Unauthorised or not found.');
    db()->prepare("UPDATE payment_transactions SET settlement_status='Settled', settled_at=NOW() WHERE id=? AND club_id=?")
        ->execute([$txId, $clubId]);
    jsonResponse(true, 'Transaction marked as Settled.');
}

// ── Filters ───────────────────────────────────────────────────────────────
$filterStatus   = trim($_GET['status']   ?? '');
$filterSettle   = trim($_GET['settle']   ?? '');
$filterEvent    = (int)($_GET['event_id'] ?? 0);
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo   = trim($_GET['date_to']   ?? '');
$search         = trim($_GET['q']         ?? '');

$where  = "WHERE pt.club_id = :club_id";
$params = [':club_id' => $clubId];

if ($filterStatus)   { $where .= " AND pt.payment_status = :pstatus";    $params[':pstatus']   = $filterStatus; }
if ($filterSettle)   { $where .= " AND pt.settlement_status = :sstatus"; $params[':sstatus']   = $filterSettle; }
if ($filterEvent)    { $where .= " AND pt.event_id = :evid";             $params[':evid']      = $filterEvent; }
if ($filterDateFrom) { $where .= " AND DATE(pt.created_at) >= :dfrom";   $params[':dfrom']     = $filterDateFrom; }
if ($filterDateTo)   { $where .= " AND DATE(pt.created_at) <= :dto";     $params[':dto']       = $filterDateTo; }
if ($search) {
    $where .= " AND (s.name LIKE :q1 OR s.matric_no LIKE :q2 OR e.title LIKE :q3 OR pt.bill_code LIKE :q4 OR pt.transaction_id LIKE :q5)";
    $params[':q1'] = "%$search%"; $params[':q2'] = "%$search%"; $params[':q3'] = "%$search%";
    $params[':q4'] = "%$search%"; $params[':q5'] = "%$search%";
}

$sql = "
    SELECT pt.*,
           s.name        AS student_name,
           s.matric_no,
           s.email       AS student_email,
           e.title       AS event_title
    FROM payment_transactions pt
    LEFT JOIN students s ON s.id = pt.student_id
    LEFT JOIN events   e ON e.id = pt.event_id
    $where
    ORDER BY pt.created_at DESC
    LIMIT 500
";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// ── Summary stats ─────────────────────────────────────────────────────────
$statsQ = db()->prepare("
    SELECT
        COUNT(*)                                                           AS total,
        SUM(pt.payment_status = 'Paid')                                   AS paid_count,
        SUM(pt.payment_status = 'Pending')                                AS pending_count,
        SUM(CASE WHEN pt.payment_status='Paid' THEN pt.amount ELSE 0 END) AS revenue,
        COUNT(DISTINCT CASE WHEN pt.payment_status='Paid' THEN pt.student_id END) AS paid_students
    FROM payment_transactions pt
    WHERE pt.club_id = ?
");
$statsQ->execute([$clubId]);
$stats = $statsQ->fetch();

// ── Events dropdown for filter ────────────────────────────────────────────
$evList = db()->prepare("
    SELECT DISTINCT e.id, e.title
    FROM events e
    WHERE e.club_id = ? OR e.created_by = ?
    ORDER BY e.start_date DESC
");
$evList->execute([$clubId, $adminId]);
$evList = $evList->fetchAll();

// ── Receipt number helper ─────────────────────────────────────────────────
function receiptNo(int $id): string {
    return 'RCP-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

function statusBadge(string $s): string {
    $map = [
        'Paid'     => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'fa-circle-check'],
        'Pending'  => ['bg'=>'#fef3c7','color'=>'#92400e','icon'=>'fa-clock'],
        'Failed'   => ['bg'=>'#fee2e2','color'=>'#991b1b','icon'=>'fa-times-circle'],
        'Refunded' => ['bg'=>'#e0e7ff','color'=>'#3730a3','icon'=>'fa-rotate-left'],
    ];
    $c = $map[$s] ?? ['bg'=>'#f3f4f6','color'=>'#374151','icon'=>'fa-circle'];
    return "<span class=\"status-badge\" style=\"background:{$c['bg']};color:{$c['color']};border:1px solid {$c['bg']};\">
        <i class=\"fas {$c['icon']} text-xs\"></i> {$s}</span>";
}
function settleBadge(string $s): string {
    if ($s === 'Settled')
        return '<span class="status-badge" style="background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;"><i class="fas fa-check text-xs"></i> Settled</span>';
    return '<span class="status-badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;"><i class="fas fa-hourglass-half text-xs"></i> Pending</span>';
}
// ── CSV Export ────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Receipt No','Student Name','Matric No','Event','Amount (RM)','Method','Date','Payment Status','Settlement']);
    foreach ($transactions as $tx) {
        fputcsv($out, [
            receiptNo((int)$tx['id']),
            $tx['student_name'] ?? '',
            $tx['matric_no'] ?? '',
            $tx['event_title'] ?? '',
            number_format((float)$tx['amount'], 2),
            $tx['payment_method'] ?? '',
            $tx['paid_at'] ? date('d M Y', strtotime($tx['paid_at'])) : date('d M Y', strtotime($tx['created_at'])),
            $tx['payment_status'],
            $tx['settlement_status'],
        ]);
    }
    fclose($out);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>UiVent | Payment Settlement</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
<style>
  .fi { padding:.5rem .875rem; border:1.5px solid #e5e7eb; border-radius:.5rem; font-size:.875rem; color:#374151; outline:none; background:#fff; transition:border-color .15s; }
  .fi:focus { border-color:#582C83; box-shadow:0 0 0 3px rgba(88,44,131,.1); }
  .status-badge { font-size:10.5px; font-weight:700; padding:3px 10px; border-radius:99px; display:inline-flex; align-items:center; gap:4px; }
  .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; z-index:200; opacity:0; pointer-events:none; transition:opacity .2s; }
  .modal-overlay.open { opacity:1; pointer-events:all; }
  .modal-box { background:#fff; border-radius:20px; width:560px; max-width:96vw; max-height:92vh; overflow-y:auto; transform:translateY(14px); transition:transform .2s; box-shadow:0 24px 60px rgba(0,0,0,.22); }
  .modal-overlay.open .modal-box { transform:translateY(0); }
  .detail-row { display:flex; gap:8px; padding:9px 0; border-bottom:1px solid #f5f0ff; font-size:13px; }
  .detail-row:last-child { border-bottom:none; }
  .detail-k { color:#9ca3af; font-weight:600; width:150px; flex-shrink:0; font-size:12px; }
  .detail-v { color:#111827; font-weight:600; }

  @media print {
    body { background: #fff !important; }
    nav, aside, .toolbar, form, .modal-overlay,
    [class*="sidebar"], [class*="topbar"],
    button, a.btn-primary, a.btn-secondary { display: none !important; }
    main { padding: 0 !important; }
    .stat-card { box-shadow: none !important; border: 1px solid #e5e7eb !important; }
    thead { background: #f9fafb !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page { margin: 1.5cm; }
  }
</style>
</head>
<body class="bg-gray-100 font-sans flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 space-y-6 max-w-7xl w-full mx-auto">

  <!-- Header -->
  <div class="flex items-center justify-between flex-wrap gap-4">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Payment Settlement</h1>
      <p class="text-sm text-gray-500 mt-0.5">Transaction records for your club events.</p>
    </div>
    <div class="flex gap-2">
      <a href="?<?= htmlspecialchars(http_build_query($_GET)) ?>&export=csv"
         class="btn-secondary"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
      <button onclick="window.print()" class="btn-primary"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
  </div>

  <!-- Summary cards -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <?php $cards = [
      ['label'=>'Total Revenue',      'val'=>'RM '.number_format($stats['revenue']??0,2), 'icon'=>'fa-money-bill-wave','color'=>'#059669','bg'=>'#d1fae5'],
      ['label'=>'Paid Transactions',  'val'=>(int)($stats['paid_count']??0),              'icon'=>'fa-circle-check',  'color'=>'#0284c7','bg'=>'#e0f2fe'],
      ['label'=>'Pending Payments',   'val'=>(int)($stats['pending_count']??0),           'icon'=>'fa-clock',         'color'=>'#d97706','bg'=>'#fef3c7'],
      ['label'=>'Paid Participants',  'val'=>(int)($stats['paid_students']??0),           'icon'=>'fa-users',         'color'=>'#582C83','bg'=>'#f0ebfa'],
    ];
    foreach ($cards as $c): ?>
    <div class="stat-card bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;">
        <i class="fas <?= $c['icon'] ?>"></i>
      </div>
      <div>
        <p class="text-xl font-extrabold text-gray-900"><?= $c['val'] ?></p>
        <p class="text-xs font-semibold text-gray-400 leading-tight"><?= $c['label'] ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
      <div class="relative flex-1 min-w-[160px]">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
               placeholder="Name, matric, bill code…" class="fi w-full pl-8">
      </div>
      <select name="event_id" class="fi">
        <option value="">All Events</option>
        <?php foreach ($evList as $ev): ?>
          <option value="<?= $ev['id'] ?>" <?= $filterEvent===$ev['id']?'selected':'' ?>><?= htmlspecialchars($ev['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="fi">
        <option value="">All Payment Status</option>
        <?php foreach (['Paid','Pending','Failed','Refunded'] as $s): ?>
          <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <select name="settle" class="fi">
        <option value="">All Settlement</option>
        <option value="Pending" <?= $filterSettle==='Pending'?'selected':'' ?>>Pending</option>
        <option value="Settled" <?= $filterSettle==='Settled'?'selected':'' ?>>Settled</option>
      </select>
      <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>" class="fi" title="From date">
      <input type="date" name="date_to"   value="<?= htmlspecialchars($filterDateTo) ?>"   class="fi" title="To date">
      <button type="submit" class="btn-primary"><i class="fas fa-filter mr-1"></i> Filter</button>
      <?php if ($search||$filterStatus||$filterSettle||$filterEvent||$filterDateFrom||$filterDateTo): ?>
        <a href="payments.php" class="btn-secondary"><i class="fas fa-times text-xs mr-1"></i> Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Transactions table -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 class="font-bold text-gray-800 text-sm">Transaction Records</h2>
      <span class="text-xs text-gray-400"><?= count($transactions) ?> record<?= count($transactions)!==1?'s':'' ?></span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Receipt No</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Student</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Matric</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Event</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Method</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Settlement</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($transactions)): ?>
          <tr><td colspan="10" class="px-6 py-16 text-center text-gray-400">
            <i class="fas fa-receipt text-4xl mb-3 block" style="color:#ddd5f5;"></i>
            <p class="font-semibold text-gray-600">No transactions found.</p>
            <p class="text-xs mt-1">Transactions will appear once students complete payment.</p>
          </td></tr>
          <?php else: foreach ($transactions as $tx): ?>
          <tr class="hover-row" id="tx-row-<?= $tx['id'] ?>">
            <td class="px-4 py-3">
              <span class="font-mono text-xs font-bold text-purple-700"><?= receiptNo((int)$tx['id']) ?></span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                     style="background:#f0ebfa;color:#582C83;">
                  <?= strtoupper(substr($tx['student_name'] ?? 'U', 0, 1)) ?>
                </div>
                <span class="font-semibold text-gray-800"><?= htmlspecialchars($tx['student_name'] ?? '—') ?></span>
              </div>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500 font-mono"><?= htmlspecialchars($tx['matric_no'] ?? '—') ?></td>
            <td class="px-4 py-3 max-w-[160px]">
              <p class="truncate text-gray-700 font-medium text-xs"><?= htmlspecialchars($tx['event_title'] ?? '—') ?></p>
            </td>
            <td class="px-4 py-3 text-right font-bold text-gray-800">RM <?= number_format($tx['amount'], 2) ?></td>
            <td class="px-4 py-3 text-xs text-gray-500"><?= htmlspecialchars($tx['payment_method'] ?? '—') ?></td>
            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
              <?= $tx['paid_at'] ? date('d M Y', strtotime($tx['paid_at'])) : date('d M Y', strtotime($tx['created_at'])) ?>
            </td>
            <td class="px-4 py-3 text-center"><?= statusBadge($tx['payment_status']) ?></td>
            <td class="px-4 py-3 text-center" id="settle-cell-<?= $tx['id'] ?>"><?= settleBadge($tx['settlement_status']) ?></td>
            <td class="px-4 py-3 text-center">
              <div class="flex items-center justify-center gap-1 flex-wrap">
                <button onclick="openDetail(<?= htmlspecialchars(json_encode($tx)) ?>)"
                        class="btn-secondary py-1 px-2 text-xs">
                  <i class="fas fa-eye"></i>
                </button>
                <a href="payment_details.php?id=<?= $tx['id'] ?>" target="_blank"
                   class="btn-secondary py-1 px-2 text-xs">
                  <i class="fas fa-print"></i>
                </a>
                <?php if ($tx['settlement_status'] === 'Pending' && $tx['payment_status'] === 'Paid'): ?>
                <button onclick="settleNow(<?= $tx['id'] ?>, this)"
                        class="btn-primary py-1 px-2 text-xs">
                  <i class="fas fa-check mr-1"></i> Settle
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</div><!-- end flex-1 -->

<!-- ── Detail Modal ──────────────────────────────────────────────────────── -->
<div id="detailModal" class="modal-overlay" onclick="if(event.target===this)closeDetail()">
  <div class="modal-box p-0">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h3 class="font-bold text-gray-800" id="modal-receipt-title">Transaction Detail</h3>
      <button onclick="closeDetail()" class="text-gray-400 hover:text-gray-600">
        <i class="fas fa-times text-lg"></i>
      </button>
    </div>
    <div class="p-6 space-y-1" id="modal-body"></div>
    <div class="px-6 pb-6 flex gap-3 flex-wrap">
      <a id="modal-print-btn" href="#" target="_blank" class="btn-primary flex-1 justify-center">
        <i class="fas fa-print mr-1"></i> Print Receipt
      </a>
      <button onclick="closeDetail()" class="btn-secondary flex-1 justify-center">Close</button>
    </div>
  </div>
</div>

<script>
function receiptNo(id) {
  return 'RCP-' + String(id).padStart(6,'0');
}
function fmtDate(d) {
  if (!d) return '—';
  const dt = new Date(d);
  return dt.toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric'});
}
function openDetail(tx) {
  const statusColors = {
    Paid:     {bg:'#d1fae5',c:'#065f46'},
    Pending:  {bg:'#fef3c7',c:'#92400e'},
    Failed:   {bg:'#fee2e2',c:'#991b1b'},
    Refunded: {bg:'#e0e7ff',c:'#3730a3'},
  };
  const sc = statusColors[tx.payment_status] || {bg:'#f3f4f6',c:'#374151'};
  const ssc = tx.settlement_status==='Settled' ? {bg:'#d1fae5',c:'#065f46'} : {bg:'#fef3c7',c:'#92400e'};

  document.getElementById('modal-receipt-title').textContent = receiptNo(tx.id);
  document.getElementById('modal-print-btn').href = 'payment_details.php?id=' + tx.id;

  const rows = [
    ['Receipt No',         receiptNo(tx.id)],
    ['Transaction ID',     tx.transaction_id || '—'],
    ['Bill Code',          tx.bill_code || '—'],
    ['Student Name',       tx.student_name || '—'],
    ['Matric No',          tx.matric_no || '—'],
    ['Event',              tx.event_title || '—'],
    ['Amount',             'RM ' + parseFloat(tx.amount||0).toFixed(2)],
    ['Payment Method',     tx.payment_method || '—'],
    ['Payment Date',       fmtDate(tx.paid_at || tx.created_at)],
  ];

  let html = '';
  rows.forEach(([k,v]) => {
    html += `<div class="detail-row"><span class="detail-k">${k}</span><span class="detail-v">${v}</span></div>`;
  });
  html += `<div class="detail-row"><span class="detail-k">Payment Status</span>
    <span class="detail-v"><span class="status-badge" style="background:${sc.bg};color:${sc.c};border:1px solid ${sc.bg};">${tx.payment_status}</span></span></div>`;
  html += `<div class="detail-row"><span class="detail-k">Settlement</span>
    <span class="detail-v"><span class="status-badge" style="background:${ssc.bg};color:${ssc.c};border:1px solid ${ssc.bg};">${tx.settlement_status}</span></span></div>`;
  if (tx.settled_at) html += `<div class="detail-row"><span class="detail-k">Settled At</span><span class="detail-v">${fmtDate(tx.settled_at)}</span></div>`;

  document.getElementById('modal-body').innerHTML = html;
  document.getElementById('detailModal').classList.add('open');
}
function closeDetail() {
  document.getElementById('detailModal').classList.remove('open');
}
function settleNow(txId, btn) {
  if (!confirm('Mark this transaction as Settled?')) return;
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  const fd = new FormData();
  fd.append('action','settle'); fd.append('tx_id', txId);
  fetch('payments.php', {method:'POST', body:fd})
    .then(r=>r.json()).then(d=>{
      if (d.success) {
        const cell = document.getElementById('settle-cell-'+txId);
        if (cell) cell.innerHTML = '<span class="status-badge" style="background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;"><i class="fas fa-check text-xs"></i> Settled</span>';
        btn.remove();
      } else { alert(d.message); btn.disabled=false; btn.innerHTML='<i class="fas fa-check mr-1"></i> Settle'; }
    }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="fas fa-check mr-1"></i> Settle'; });
}
</script>
</body>
</html>