<?php
// ============================================================
//  superadmin/billing_settlement.php — Global Billing Settlement
//  Super Admin oversight of ALL club payment transactions.
//  Extra powers: force-settle, bulk-settle, flag disputes,
//  export, per-club breakdown, revenue analytics.
// ============================================================
require_once '../config.php';
requireSuperAdmin();

$activePage = 'billing_settlement';
$pageTitle  = 'Billing Settlement';

$pendingClubs     = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='pending'")->fetchColumn();
$pendingApprovals = (int) db()->query("SELECT COUNT(*) FROM escalated_approvals WHERE status='pending' LIMIT 1")->fetchColumn();

// ── AJAX Actions ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Force-settle a single transaction (superadmin override)
    if ($action === 'force_settle') {
        $txId = (int)($_POST['tx_id'] ?? 0);
        if (!$txId) jsonResponse(false, 'Invalid transaction.');
        $tx = db()->prepare("SELECT id, settlement_status, payment_status FROM payment_transactions WHERE id=? LIMIT 1");
        $tx->execute([$txId]);
        $row = $tx->fetch();
        if (!$row) jsonResponse(false, 'Transaction not found.');
        if ($row['settlement_status'] === 'Settled') jsonResponse(false, 'Already settled.');
        db()->prepare("UPDATE payment_transactions SET settlement_status='Settled', settled_at=NOW() WHERE id=?")
            ->execute([$txId]);
        logAction('FORCE_SETTLE', "Tx ID $txId force-settled by superadmin");
        jsonResponse(true, 'Transaction force-settled.');
    }

    // Bulk-settle all Paid+Pending settlements for a club
    if ($action === 'bulk_settle_club') {
        $clubId = (int)($_POST['club_id'] ?? 0);
        if (!$clubId) jsonResponse(false, 'Invalid club.');
        $stmt = db()->prepare("
            UPDATE payment_transactions
            SET settlement_status='Settled', settled_at=NOW()
            WHERE club_id=? AND payment_status='Paid' AND settlement_status='Pending'
        ");
        $stmt->execute([$clubId]);
        $count = $stmt->rowCount();
        logAction('BULK_SETTLE', "Bulk settled $count transactions for Club ID $clubId");
        jsonResponse(true, "$count transaction(s) settled for this club.");
    }

    // Bulk-settle ALL clubs
    if ($action === 'bulk_settle_all') {
        $stmt = db()->prepare("
            UPDATE payment_transactions
            SET settlement_status='Settled', settled_at=NOW()
            WHERE payment_status='Paid' AND settlement_status='Pending'
        ");
        $stmt->execute();
        $count = $stmt->rowCount();
        logAction('BULK_SETTLE_ALL', "Global bulk settle: $count transactions settled");
        jsonResponse(true, "$count transaction(s) settled globally.");
    }

    // Flag / unflag a transaction as disputed
    if ($action === 'flag_dispute') {
        $txId = (int)($_POST['tx_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        if (!$txId) jsonResponse(false, 'Invalid transaction.');
        db()->prepare("UPDATE payment_transactions SET settlement_status='Disputed', dispute_note=?, disputed_at=NOW() WHERE id=?")
            ->execute([$note ?: 'Flagged by operator', $txId]);
        logAction('FLAG_DISPUTE', "Tx ID $txId flagged as Disputed");
        jsonResponse(true, 'Transaction flagged as Disputed.');
    }

    // Resolve a disputed transaction
    if ($action === 'resolve_dispute') {
        $txId = (int)($_POST['tx_id'] ?? 0);
        if (!$txId) jsonResponse(false, 'Invalid transaction.');
        db()->prepare("UPDATE payment_transactions SET settlement_status='Settled', settled_at=NOW(), dispute_note=NULL WHERE id=?")
            ->execute([$txId]);
        logAction('RESOLVE_DISPUTE', "Tx ID $txId dispute resolved & settled");
        jsonResponse(true, 'Dispute resolved and transaction settled.');
    }

    // Reverse a settlement (set back to Pending)
    if ($action === 'reverse_settle') {
        $txId = (int)($_POST['tx_id'] ?? 0);
        if (!$txId) jsonResponse(false, 'Invalid transaction.');
        db()->prepare("UPDATE payment_transactions SET settlement_status='Pending', settled_at=NULL WHERE id=?")
            ->execute([$txId]);
        logAction('REVERSE_SETTLE', "Tx ID $txId settlement reversed by superadmin");
        jsonResponse(true, 'Settlement reversed to Pending.');
    }

    jsonResponse(false, 'Unknown action.');
}

// ── Filters ───────────────────────────────────────────────────
$filterClub     = (int)(   $_GET['club_id']   ?? 0);
$filterStatus   = trim(    $_GET['status']    ?? '');
$filterSettle   = trim(    $_GET['settle']    ?? '');
$filterEvent    = (int)(   $_GET['event_id']  ?? 0);
$filterDateFrom = trim(    $_GET['date_from'] ?? '');
$filterDateTo   = trim(    $_GET['date_to']   ?? '');
$search         = trim(    $_GET['q']         ?? '');
$tab            = in_array($_GET['tab'] ?? '', ['overview','transactions','clubs','disputes']) ? ($_GET['tab'] ?? 'overview') : 'overview';

$where  = "WHERE 1=1";
$params = [];

if ($filterClub)     { $where .= " AND pt.club_id = :club_id";         $params[':club_id']   = $filterClub; }
if ($filterStatus)   { $where .= " AND pt.payment_status = :pstatus";  $params[':pstatus']   = $filterStatus; }
if ($filterSettle)   { $where .= " AND pt.settlement_status = :sstat"; $params[':sstat']     = $filterSettle; }
if ($filterEvent)    { $where .= " AND pt.event_id = :evid";           $params[':evid']      = $filterEvent; }
if ($filterDateFrom) { $where .= " AND DATE(pt.created_at) >= :dfrom"; $params[':dfrom']     = $filterDateFrom; }
if ($filterDateTo)   { $where .= " AND DATE(pt.created_at) <= :dto";   $params[':dto']       = $filterDateTo; }
if ($search) {
    $where .= " AND (s.name LIKE :q1 OR s.matric_no LIKE :q2 OR e.title LIKE :q3 OR pt.bill_code LIKE :q4 OR pt.transaction_id LIKE :q5 OR a.name LIKE :q6)";
    for ($i=1; $i<=6; $i++) $params[":q$i"] = "%$search%";
}

// ── Main transaction query ────────────────────────────────────
$transactions = db()->prepare("
    SELECT pt.*,
           s.name        AS student_name,
           s.matric_no,
           s.email       AS student_email,
           e.title       AS event_title,
           a.name        AS club_name
    FROM payment_transactions pt
    LEFT JOIN students s ON s.id = pt.student_id
    LEFT JOIN events   e ON e.id = pt.event_id
    LEFT JOIN admins   a ON a.id = pt.club_id
    $where
    ORDER BY pt.created_at DESC
    LIMIT 1000
");
$transactions->execute($params);
$transactions = $transactions->fetchAll();

// ── Global stats ──────────────────────────────────────────────
$globalStats = db()->query("
    SELECT
        COUNT(*)                                                            AS total,
        SUM(payment_status='Paid')                                         AS paid_count,
        SUM(payment_status='Pending')                                      AS pending_count,
        SUM(payment_status='Failed')                                       AS failed_count,
        SUM(settlement_status='Settled')                                   AS settled_count,
        SUM(settlement_status='Pending' AND payment_status='Paid')         AS unsettled_paid,
        SUM(settlement_status='Disputed')                                  AS disputed_count,
        SUM(CASE WHEN payment_status='Paid' THEN amount ELSE 0 END)        AS total_revenue,
        SUM(CASE WHEN settlement_status='Settled' THEN amount ELSE 0 END)  AS settled_revenue,
        SUM(CASE WHEN settlement_status='Pending' AND payment_status='Paid' THEN amount ELSE 0 END) AS unsettled_revenue
    FROM payment_transactions
")->fetch();

// ── Per-club breakdown ────────────────────────────────────────
$clubBreakdown = db()->query("
    SELECT a.id AS club_id, a.name AS club_name,
           COUNT(pt.id)                                                           AS total_tx,
           SUM(pt.payment_status='Paid')                                          AS paid_tx,
           SUM(CASE WHEN pt.payment_status='Paid' THEN pt.amount ELSE 0 END)     AS revenue,
           SUM(pt.settlement_status='Settled')                                    AS settled_tx,
           SUM(pt.settlement_status='Pending' AND pt.payment_status='Paid')       AS unsettled_tx,
           SUM(CASE WHEN pt.settlement_status='Pending' AND pt.payment_status='Paid' THEN pt.amount ELSE 0 END) AS unsettled_amt,
           SUM(pt.settlement_status='Disputed')                                   AS disputed_tx,
           MAX(pt.created_at)                                                     AS last_tx
    FROM admins a
    LEFT JOIN payment_transactions pt ON pt.club_id = a.id
    GROUP BY a.id, a.name
    HAVING total_tx > 0
    ORDER BY revenue DESC
")->fetchAll();

// ── Disputed transactions ─────────────────────────────────────
$disputes = db()->query("
    SELECT pt.*, s.name AS student_name, s.matric_no,
           e.title AS event_title, a.name AS club_name
    FROM payment_transactions pt
    LEFT JOIN students s ON s.id = pt.student_id
    LEFT JOIN events   e ON e.id = pt.event_id
    LEFT JOIN admins   a ON a.id = pt.club_id
    WHERE pt.settlement_status = 'Disputed'
    ORDER BY pt.disputed_at DESC
")->fetchAll();

// ── Monthly revenue trend (last 6 months) ────────────────────
$trend = db()->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month,
           DATE_FORMAT(created_at,'%Y-%m') AS sort_key,
           SUM(CASE WHEN payment_status='Paid' THEN amount ELSE 0 END) AS revenue,
           COUNT(CASE WHEN payment_status='Paid' THEN 1 END)           AS tx_count
    FROM payment_transactions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month, sort_key
    ORDER BY sort_key ASC
")->fetchAll();

// ── Clubs dropdown ────────────────────────────────────────────
$clubs = db()->query("SELECT id, name FROM admins ORDER BY name")->fetchAll();

// ── Helpers ───────────────────────────────────────────────────
function receiptNo(int $id): string {
    return 'RCP-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}
function payBadge(string $s): string {
    $map = [
        'Paid'     => ['#d1fae5','#065f46','fa-circle-check'],
        'Pending'  => ['#fef3c7','#92400e','fa-clock'],
        'Failed'   => ['#fee2e2','#991b1b','fa-times-circle'],
        'Refunded' => ['#e0e7ff','#3730a3','fa-rotate-left'],
    ];
    [$bg,$c,$ico] = $map[$s] ?? ['#f3f4f6','#374151','fa-circle'];
    return "<span class=\"sbadge\" style=\"background:{$bg};color:{$c};\"><i class=\"fas {$ico} text-xs\"></i> {$s}</span>";
}
function settleBadge(string $s): string {
    $map = [
        'Settled'  => ['#d1fae5','#065f46','fa-check'],
        'Pending'  => ['#fef3c7','#92400e','fa-hourglass-half'],
        'Disputed' => ['#fee2e2','#991b1b','fa-flag'],
    ];
    [$bg,$c,$ico] = $map[$s] ?? ['#f3f4f6','#374151','fa-circle'];
    return "<span class=\"sbadge\" style=\"background:{$bg};color:{$c};\"><i class=\"fas {$ico} text-xs\"></i> {$s}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>UiVent | Billing Settlement</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
<style>
  .fi { padding:.45rem .8rem; border:1.5px solid #e5e7eb; border-radius:.5rem; font-size:.8rem; color:#374151; outline:none; background:#fff; transition:border-color .15s; }
  .fi:focus { border-color:#582C83; box-shadow:0 0 0 3px rgba(88,44,131,.1); }
  .sbadge { font-size:10.5px; font-weight:700; padding:3px 10px; border-radius:99px; display:inline-flex; align-items:center; gap:4px; border:1px solid transparent; }

  /* Tab nav */
  .tab-btn { padding:.5rem 1.1rem; border-radius:.5rem; font-size:.78rem; font-weight:600; color:#6b7280; transition:all .15s; cursor:pointer; border:1.5px solid transparent; }
  .tab-btn.active { background:#582C83; color:#fff; border-color:#582C83; }
  .tab-btn:not(.active):hover { background:#f0ebfa; color:#582C83; }
  .tab-panel { display:none; }
  .tab-panel.active { display:block; animation:fadeIn .2s ease-out; }

  /* Modal */
  .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; z-index:200; opacity:0; pointer-events:none; transition:opacity .2s; }
  .modal-overlay.open { opacity:1; pointer-events:all; }
  .modal-box { background:#fff; border-radius:20px; width:580px; max-width:96vw; max-height:90vh; overflow-y:auto; transform:translateY(14px); transition:transform .2s; box-shadow:0 24px 60px rgba(0,0,0,.22); }
  .modal-overlay.open .modal-box { transform:translateY(0); }
  .drow { display:flex; gap:8px; padding:9px 0; border-bottom:1px solid #f5f0ff; font-size:13px; }
  .drow:last-child { border-bottom:none; }
  .dk { color:#9ca3af; font-weight:600; width:155px; flex-shrink:0; font-size:11.5px; text-transform:uppercase; letter-spacing:.03em; }
  .dv { color:#111827; font-weight:600; flex:1; }

  /* Chart bars */
  .bar-wrap { display:flex; align-items:flex-end; gap:6px; height:80px; }
  .bar { flex:1; border-radius:6px 6px 0 0; background:#582C83; min-height:4px; transition:height .6s cubic-bezier(.4,0,.2,1); }
  .bar:hover { opacity:.8; }

  /* Dispute badge pulse */
  .dispute-pulse { animation:pulseAlert 2s infinite; }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 space-y-6 max-w-7xl w-full mx-auto">

  <!-- ── Header ──────────────────────────────────────────────── -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Billing Settlement</h1>
      <p class="text-sm text-gray-500 mt-0.5">Operator-level oversight of all club payment transactions.</p>
    </div>
    <div class="flex flex-wrap gap-2 items-center">
      <?php if ((int)($globalStats['disputed_count'] ?? 0) > 0): ?>
      <span class="sbadge dispute-pulse" style="background:#fee2e2;color:#991b1b;font-size:11px;">
        <i class="fas fa-flag text-xs"></i> <?= (int)$globalStats['disputed_count'] ?> Disputed
      </span>
      <?php endif; ?>
      <button onclick="openBulkAll()"
              class="btn-primary flex items-center gap-2">
        <i class="fas fa-check-double"></i> Settle All Pending
      </button>
      <div class="relative" id="exportDropWrap">
        <button onclick="document.getElementById('exportDrop').classList.toggle('hidden')"
                class="btn-secondary flex items-center gap-2">
          <i class="fas fa-download"></i> Export <i class="fas fa-chevron-down text-xs ml-1"></i>
        </button>
        <div id="exportDrop" class="hidden absolute right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden" style="min-width:190px;">
          <a href="export_settlements.php?<?= htmlspecialchars(http_build_query(array_diff_key($_GET,['tab'=>'']))) ?>&format=csv"
             class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">
            <i class="fas fa-file-csv text-green-600 w-4"></i> Download CSV
          </a>
          <a href="export_settlements.php?<?= htmlspecialchars(http_build_query(array_diff_key($_GET,['tab'=>'']))) ?>&format=pdf" target="_blank"
             class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors border-t border-gray-100">
            <i class="fas fa-file-pdf text-red-500 w-4"></i> Settlement Report PDF
          </a>
          <a href="export_settlements.php?<?= htmlspecialchars(http_build_query(array_diff_key($_GET,['tab'=>'']))) ?>&format=pdf&print=1" target="_blank"
             class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-purple-50 hover:text-purple-900 transition-colors border-t border-gray-100">
            <i class="fas fa-print text-purple-600 w-4"></i> Print Report
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Global KPI Cards ─────────────────────────────────────── -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
    <?php
    $kpis = [
      ['Total Revenue',      'RM '.number_format($globalStats['total_revenue']??0,2),     'fa-money-bill-wave',  '#059669','#d1fae5'],
      ['Settled Revenue',    'RM '.number_format($globalStats['settled_revenue']??0,2),   'fa-circle-check',     '#0284c7','#e0f2fe'],
      ['Unsettled (Paid)',   'RM '.number_format($globalStats['unsettled_revenue']??0,2), 'fa-hourglass-half',   '#d97706','#fef3c7'],
      ['Paid Transactions',  (int)($globalStats['paid_count']??0),                        'fa-receipt',          '#582C83','#f0ebfa'],
      ['Disputed',           (int)($globalStats['disputed_count']??0),                    'fa-flag',             '#dc2626','#fee2e2'],
    ];
    foreach ($kpis as [$label,$val,$icon,$col,$bg]): ?>
    <div class="stat-card bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center text-base shrink-0"
           style="background:<?= $bg ?>;color:<?= $col ?>;">
        <i class="fas <?= $icon ?>"></i>
      </div>
      <div>
        <p class="text-lg font-extrabold text-gray-900 leading-tight"><?= $val ?></p>
        <p class="text-xs font-semibold text-gray-400 leading-tight"><?= $label ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Tabs ─────────────────────────────────────────────────── -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
    <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 flex-wrap">
      <button class="tab-btn <?= $tab==='overview'?'active':'' ?>"      onclick="switchTab('overview')">
        <i class="fas fa-chart-pie mr-1.5"></i>Overview
      </button>
      <button class="tab-btn <?= $tab==='transactions'?'active':'' ?>" onclick="switchTab('transactions')">
        <i class="fas fa-receipt mr-1.5"></i>All Transactions
        <span class="ml-1.5 text-xs bg-gray-200 text-gray-600 font-bold px-1.5 py-0.5 rounded-full"><?= count($transactions) ?></span>
      </button>
      <button class="tab-btn <?= $tab==='clubs'?'active':'' ?>"         onclick="switchTab('clubs')">
        <i class="fas fa-users mr-1.5"></i>By Club
      </button>
      <button class="tab-btn <?= $tab==='disputes'?'active':'' ?>"      onclick="switchTab('disputes')">
        <i class="fas fa-flag mr-1.5"></i>Disputes
        <?php if ((int)($globalStats['disputed_count']??0)>0): ?>
        <span class="ml-1.5 text-xs bg-red-500 text-white font-bold px-1.5 py-0.5 rounded-full"><?= (int)$globalStats['disputed_count'] ?></span>
        <?php endif; ?>
      </button>
    </div>

    <!-- ══ TAB: Overview ══════════════════════════════════════ -->
    <div id="tab-overview" class="tab-panel <?= $tab==='overview'?'active':'' ?> p-6 space-y-6">

      <!-- Settlement Progress -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <?php
        $total    = (int)($globalStats['total']??0);
        $settled  = (int)($globalStats['settled_count']??0);
        $disputed = (int)($globalStats['disputed_count']??0);
        $unset    = (int)($globalStats['unsettled_paid']??0);
        $settledPct  = $total > 0 ? round($settled/$total*100) : 0;
        $unsetPct    = $total > 0 ? round($unset/$total*100)   : 0;
        $dispPct     = $total > 0 ? round($disputed/$total*100): 0;
        ?>
        <div class="bg-gray-50 rounded-xl p-5 border border-gray-100 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Settlement Rate</p>
            <span class="text-xl font-extrabold" style="color:#059669;"><?= $settledPct ?>%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2.5">
            <div class="h-2.5 rounded-full" style="width:<?= $settledPct ?>%;background:#059669;transition:width .8s;"></div>
          </div>
          <p class="text-xs text-gray-500"><?= $settled ?> of <?= $total ?> transactions settled</p>
        </div>

        <div class="bg-amber-50 rounded-xl p-5 border border-amber-100 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Unsettled Paid</p>
            <span class="text-xl font-extrabold text-amber-700"><?= $unset ?></span>
          </div>
          <div class="w-full bg-amber-100 rounded-full h-2.5">
            <div class="h-2.5 rounded-full bg-amber-500" style="width:<?= $unsetPct ?>%;transition:width .8s;"></div>
          </div>
          <p class="text-xs text-amber-700">RM <?= number_format($globalStats['unsettled_revenue']??0,2) ?> awaiting settlement</p>
        </div>

        <div class="bg-red-50 rounded-xl p-5 border border-red-100 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-xs font-bold uppercase tracking-wider text-red-700">Disputed</p>
            <span class="text-xl font-extrabold text-red-700"><?= $disputed ?></span>
          </div>
          <div class="w-full bg-red-100 rounded-full h-2.5">
            <div class="h-2.5 rounded-full bg-red-500" style="width:<?= $dispPct ?>%;transition:width .8s;"></div>
          </div>
          <p class="text-xs text-red-700"><?= $dispPct ?>% of all transactions flagged</p>
        </div>
      </div>

      <!-- Revenue Trend Chart -->
      <?php if (!empty($trend)): ?>
      <div class="bg-gray-50 rounded-xl border border-gray-100 p-5">
        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-4">Revenue Trend — Last 6 Months</p>
        <?php
        $maxRev = max(array_column($trend,'revenue') ?: [1]);
        ?>
        <div class="flex items-end gap-2" style="height:100px;">
          <?php foreach ($trend as $m):
            $h = max(8, round(($m['revenue']/$maxRev)*90));
          ?>
          <div class="flex-1 flex flex-col items-center gap-1">
            <span class="text-xs font-bold" style="color:#582C83;">RM<?= number_format($m['revenue']/1000,1) ?>k</span>
            <div class="w-full rounded-t-lg" style="height:<?= $h ?>px;background:#582C83;opacity:.85;min-height:4px;"></div>
            <span class="text-xs text-gray-400 whitespace-nowrap"><?= $m['month'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Payment Status Breakdown -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <?php
        $breakdown = [
          ['Paid',     (int)($globalStats['paid_count']??0),    '#d1fae5','#065f46'],
          ['Pending',  (int)($globalStats['pending_count']??0), '#fef3c7','#92400e'],
          ['Failed',   (int)($globalStats['failed_count']??0),  '#fee2e2','#991b1b'],
          ['Settled',  (int)($globalStats['settled_count']??0), '#e0f2fe','#0284c7'],
        ];
        foreach ($breakdown as [$lbl,$cnt,$bg,$col]): ?>
        <div class="rounded-xl p-4 border text-center" style="background:<?= $bg ?>;border-color:<?= $bg ?>;">
          <p class="text-2xl font-extrabold" style="color:<?= $col ?>;"><?= $cnt ?></p>
          <p class="text-xs font-bold mt-0.5" style="color:<?= $col ?>;"><?= $lbl ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div><!-- /tab-overview -->

    <!-- ══ TAB: All Transactions ══════════════════════════════ -->
    <div id="tab-transactions" class="tab-panel <?= $tab==='transactions'?'active':'' ?>">

      <!-- Filters -->
      <div class="px-5 py-4 border-b border-gray-100">
        <form method="GET" class="flex flex-wrap gap-2 items-end">
          <input type="hidden" name="tab" value="transactions">
          <div class="relative flex-1 min-w-[160px]">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Name, matric, bill code, club…" class="fi w-full pl-8">
          </div>
          <select name="club_id" class="fi">
            <option value="">All Clubs</option>
            <?php foreach ($clubs as $cl): ?>
              <option value="<?= $cl['id'] ?>" <?= $filterClub===$cl['id']?'selected':'' ?>><?= htmlspecialchars($cl['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="status" class="fi">
            <option value="">All Payment</option>
            <?php foreach (['Paid','Pending','Failed','Refunded'] as $s): ?>
              <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <select name="settle" class="fi">
            <option value="">All Settlement</option>
            <option value="Pending"  <?= $filterSettle==='Pending' ?'selected':'' ?>>Pending</option>
            <option value="Settled"  <?= $filterSettle==='Settled' ?'selected':'' ?>>Settled</option>
            <option value="Disputed" <?= $filterSettle==='Disputed'?'selected':'' ?>>Disputed</option>
          </select>
          <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>" class="fi" title="From">
          <input type="date" name="date_to"   value="<?= htmlspecialchars($filterDateTo) ?>"   class="fi" title="To">
          <button type="submit" class="btn-primary"><i class="fas fa-filter mr-1"></i>Filter</button>
          <?php if ($search||$filterStatus||$filterSettle||$filterClub||$filterDateFrom||$filterDateTo): ?>
            <a href="?tab=transactions" class="btn-secondary"><i class="fas fa-times text-xs mr-1"></i>Clear</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Receipt</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Club</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Student</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Event</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Settlement</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php if (empty($transactions)): ?>
            <tr><td colspan="9" class="px-6 py-16 text-center text-gray-400">
              <i class="fas fa-receipt text-4xl mb-3 block" style="color:#ddd5f5;"></i>
              <p class="font-semibold text-gray-600">No transactions found.</p>
            </td></tr>
            <?php else: foreach ($transactions as $tx): ?>
            <tr class="hover-row" id="tx-row-<?= $tx['id'] ?>">
              <td class="px-4 py-3">
                <span class="font-mono text-xs font-bold" style="color:#582C83;"><?= receiptNo((int)$tx['id']) ?></span>
              </td>
              <td class="px-4 py-3">
                <span class="text-xs font-semibold text-gray-700 truncate max-w-[110px] block"><?= htmlspecialchars($tx['club_name'] ?? '—') ?></span>
              </td>
              <td class="px-4 py-3">
                <p class="font-semibold text-gray-800 text-xs truncate"><?= htmlspecialchars($tx['student_name'] ?? '—') ?></p>
                <p class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($tx['matric_no'] ?? '') ?></p>
              </td>
              <td class="px-4 py-3 max-w-[140px]">
                <p class="truncate text-gray-700 text-xs"><?= htmlspecialchars($tx['event_title'] ?? '—') ?></p>
              </td>
              <td class="px-4 py-3 text-right font-bold text-gray-800 whitespace-nowrap">RM <?= number_format($tx['amount'],2) ?></td>
              <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                <?= date('d M Y', strtotime($tx['paid_at'] ?? $tx['created_at'])) ?>
              </td>
              <td class="px-4 py-3 text-center"><?= payBadge($tx['payment_status']) ?></td>
              <td class="px-4 py-3 text-center" id="settle-cell-<?= $tx['id'] ?>"><?= settleBadge($tx['settlement_status']) ?></td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-1 flex-wrap">
                  <!-- View Detail -->
                  <button onclick="openDetail(<?= htmlspecialchars(json_encode($tx)) ?>)"
                          class="btn-secondary py-1 px-2 text-xs" title="View details">
                    <i class="fas fa-eye"></i>
                  </button>
                  <!-- Print Receipt -->
                  <a href="receipt.php?id=<?= $tx['id'] ?>" target="_blank"
                     class="btn-secondary py-1 px-2 text-xs" title="Print receipt">
                    <i class="fas fa-print"></i>
                  </a>
                  <!-- Force Settle -->
                  <?php if ($tx['settlement_status'] === 'Pending' && $tx['payment_status'] === 'Paid'): ?>
                  <button onclick="forceSettle(<?= $tx['id'] ?>, this)"
                          class="btn-primary py-1 px-2 text-xs" title="Force settle">
                    <i class="fas fa-check"></i>
                  </button>
                  <?php endif; ?>
                  <!-- Flag Dispute -->
                  <?php if ($tx['settlement_status'] !== 'Disputed' && $tx['settlement_status'] !== 'Settled'): ?>
                  <button onclick="openDisputeModal(<?= $tx['id'] ?>)"
                          class="py-1 px-2 text-xs font-semibold rounded" style="background:#fee2e2;color:#991b1b;" title="Flag dispute">
                    <i class="fas fa-flag"></i>
                  </button>
                  <?php endif; ?>
                  <!-- Resolve Dispute -->
                  <?php if ($tx['settlement_status'] === 'Disputed'): ?>
                  <button onclick="resolveDispute(<?= $tx['id'] ?>, this)"
                          class="py-1 px-2 text-xs font-semibold rounded" style="background:#d1fae5;color:#065f46;" title="Resolve dispute">
                    <i class="fas fa-check-double"></i>
                  </button>
                  <?php endif; ?>
                  <!-- Reverse Settle -->
                  <?php if ($tx['settlement_status'] === 'Settled'): ?>
                  <button onclick="reverseSettle(<?= $tx['id'] ?>, this)"
                          class="py-1 px-2 text-xs font-semibold rounded" style="background:#fef3c7;color:#92400e;" title="Reverse settlement">
                    <i class="fas fa-rotate-left"></i>
                  </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="px-5 py-3 border-t border-gray-100 text-xs text-gray-400">
        <?= count($transactions) ?> record<?= count($transactions)!==1?'s':'' ?> shown
      </div>
    </div><!-- /tab-transactions -->

    <!-- ══ TAB: By Club ════════════════════════════════════════ -->
    <div id="tab-clubs" class="tab-panel <?= $tab==='clubs'?'active':'' ?> p-6 space-y-4">
      <?php if (empty($clubBreakdown)): ?>
      <div class="text-center py-16 text-gray-400">
        <i class="fas fa-users text-4xl mb-3"></i>
        <p class="text-sm">No club transaction data yet.</p>
      </div>
      <?php else: ?>
      <div class="overflow-x-auto rounded-xl border border-gray-100">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Club</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Revenue</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Transactions</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Settled</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Unsettled</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Unsettled Amt</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Disputed</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Last Activity</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($clubBreakdown as $cl):
              $settleRate = $cl['paid_tx'] > 0 ? round($cl['settled_tx']/$cl['paid_tx']*100) : 0;
            ?>
            <tr class="hover-row">
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                       style="background:#f0ebfa;color:#582C83;">
                    <?= strtoupper(substr($cl['club_name'],0,1)) ?>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($cl['club_name']) ?></p>
                    <div class="flex items-center gap-1 mt-0.5">
                      <div class="h-1.5 w-20 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full" style="width:<?= $settleRate ?>%"></div>
                      </div>
                      <span class="text-xs text-gray-400"><?= $settleRate ?>% settled</span>
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3.5 text-right font-bold text-gray-800">RM <?= number_format($cl['revenue'],2) ?></td>
              <td class="px-5 py-3.5 text-center text-gray-600"><?= $cl['total_tx'] ?></td>
              <td class="px-5 py-3.5 text-center">
                <span class="sbadge" style="background:#d1fae5;color:#065f46;"><?= $cl['settled_tx'] ?></span>
              </td>
              <td class="px-5 py-3.5 text-center">
                <?php if ($cl['unsettled_tx'] > 0): ?>
                  <span class="sbadge" style="background:#fef3c7;color:#92400e;"><?= $cl['unsettled_tx'] ?></span>
                <?php else: ?>
                  <span class="text-xs text-gray-400">—</span>
                <?php endif; ?>
              </td>
              <td class="px-5 py-3.5 text-right font-semibold text-amber-700">
                <?= $cl['unsettled_amt'] > 0 ? 'RM '.number_format($cl['unsettled_amt'],2) : '—' ?>
              </td>
              <td class="px-5 py-3.5 text-center">
                <?php if ($cl['disputed_tx'] > 0): ?>
                  <span class="sbadge" style="background:#fee2e2;color:#991b1b;"><?= $cl['disputed_tx'] ?></span>
                <?php else: ?>
                  <span class="text-xs text-gray-400">—</span>
                <?php endif; ?>
              </td>
              <td class="px-5 py-3.5 text-xs text-gray-500">
                <?= $cl['last_tx'] ? date('d M Y', strtotime($cl['last_tx'])) : '—' ?>
              </td>
              <td class="px-5 py-3.5">
                <div class="flex items-center justify-center gap-1.5">
                  <a href="?tab=transactions&club_id=<?= $cl['club_id'] ?>" class="btn-secondary py-1 px-2.5 text-xs">
                    <i class="fas fa-list mr-1"></i>View
                  </a>
                  <?php if ($cl['unsettled_tx'] > 0): ?>
                  <button onclick="openBulkClub(<?= $cl['club_id'] ?>, <?= json_encode($cl['club_name']) ?>, <?= $cl['unsettled_tx'] ?>, '<?= number_format($cl['unsettled_amt'],2) ?>')"
                          class="btn-primary py-1 px-2.5 text-xs">
                    <i class="fas fa-check-double mr-1"></i>Bulk Settle
                  </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div><!-- /tab-clubs -->

    <!-- ══ TAB: Disputes ══════════════════════════════════════ -->
    <div id="tab-disputes" class="tab-panel <?= $tab==='disputes'?'active':'' ?> p-6 space-y-4">
      <?php if (empty($disputes)): ?>
      <div class="text-center py-16 text-gray-400">
        <i class="fas fa-flag text-4xl mb-3"></i>
        <p class="text-sm font-medium">No disputed transactions.</p>
        <p class="text-xs mt-1">All transactions are in good standing.</p>
      </div>
      <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($disputes as $d): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-5 flex flex-col sm:flex-row gap-4 items-start">
          <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0" style="background:#fee2e2;">
            <i class="fas fa-flag text-red-600"></i>
          </div>
          <div class="flex-1 space-y-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-mono text-xs font-bold" style="color:#582C83;"><?= receiptNo((int)$d['id']) ?></span>
              <span class="text-xs text-gray-500">·</span>
              <span class="text-xs font-semibold text-gray-700"><?= htmlspecialchars($d['club_name'] ?? '—') ?></span>
              <span class="text-xs text-gray-500">·</span>
              <span class="text-xs text-gray-500">RM <?= number_format($d['amount'],2) ?></span>
            </div>
            <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($d['student_name'] ?? '—') ?> — <?= htmlspecialchars($d['event_title'] ?? '—') ?></p>
            <?php if (!empty($d['dispute_note'])): ?>
            <p class="text-xs text-red-700 bg-red-100 rounded-lg px-3 py-1.5 inline-block mt-1">
              <i class="fas fa-note-sticky mr-1"></i><?= htmlspecialchars($d['dispute_note']) ?>
            </p>
            <?php endif; ?>
            <p class="text-xs text-gray-400">
              Flagged <?= $d['disputed_at'] ? date('d M Y H:i', strtotime($d['disputed_at'])) : 'recently' ?>
            </p>
          </div>
          <div class="flex flex-col gap-2 shrink-0">
            <button onclick="resolveDispute(<?= $d['id'] ?>, this)"
                    class="btn-primary text-xs py-1.5 px-4">
              <i class="fas fa-check-double mr-1"></i>Resolve & Settle
            </button>
            <button onclick="openDetail(<?= htmlspecialchars(json_encode($d)) ?>)"
                    class="btn-secondary text-xs py-1.5 px-4">
              <i class="fas fa-eye mr-1"></i>View Detail
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div><!-- /tab-disputes -->

  </div><!-- /tabs container -->

</main>
</div><!-- /flex-1 -->

<!-- ── Detail Modal ──────────────────────────────────────────── -->
<div id="detailModal" class="modal-overlay" onclick="if(event.target===this)closeDetail()">
  <div class="modal-box">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:#f0ebfa;">
          <i class="fas fa-receipt text-sm" style="color:#582C83;"></i>
        </div>
        <h3 class="font-bold text-gray-800" id="modal-title">Transaction Detail</h3>
      </div>
      <button onclick="closeDetail()" class="text-gray-400 hover:text-gray-600">
        <i class="fas fa-times text-lg"></i>
      </button>
    </div>
    <div class="p-6 space-y-1" id="modal-body"></div>
    <div class="px-6 pb-5 flex gap-2 flex-wrap border-t border-gray-100 pt-4" id="modal-actions"></div>
  </div>
</div>

<!-- ── Bulk Club Settle Modal ────────────────────────────────── -->
<div id="bulkClubModal" class="modal-overlay" onclick="if(event.target===this)closeBulkClub()">
  <div class="modal-box p-0">
    <div class="px-6 py-5 border-b border-gray-100 flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:#fef3c7;">
        <i class="fas fa-check-double text-amber-600"></i>
      </div>
      <h3 class="font-bold text-gray-800">Bulk Settle — <span id="bc-club-name"></span></h3>
    </div>
    <div class="p-6 space-y-3">
      <p class="text-sm text-gray-600">This will mark <strong id="bc-count"></strong> unsettled paid transactions totalling <strong id="bc-amt"></strong> as <span class="sbadge" style="background:#d1fae5;color:#065f46;">Settled</span>.</p>
      <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800 flex items-start gap-2">
        <i class="fas fa-triangle-exclamation mt-0.5 shrink-0"></i>
        This action is logged in the audit trail and can be individually reversed per transaction.
      </div>
    </div>
    <div class="px-6 pb-5 flex gap-2">
      <button id="bc-confirm-btn" onclick="confirmBulkClub()" class="btn-primary flex-1 justify-center py-2.5">
        <i class="fas fa-check-double mr-1.5"></i>Confirm Bulk Settle
      </button>
      <button onclick="closeBulkClub()" class="btn-secondary flex-1 justify-center py-2.5">Cancel</button>
    </div>
  </div>
</div>

<!-- ── Dispute Flag Modal ────────────────────────────────────── -->
<div id="disputeModal" class="modal-overlay" onclick="if(event.target===this)closeDisputeModal()">
  <div class="modal-box p-0">
    <div class="px-6 py-5 border-b border-gray-100 flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:#fee2e2;">
        <i class="fas fa-flag text-red-600"></i>
      </div>
      <h3 class="font-bold text-gray-800">Flag as Disputed</h3>
    </div>
    <div class="p-6 space-y-4">
      <p class="text-sm text-gray-600">Add a note describing the dispute reason. This will be logged and visible to the club admin.</p>
      <textarea id="dispute-note" rows="3" placeholder="e.g. Student claims payment not received…"
                class="w-full fi resize-none" style="font-size:.875rem;"></textarea>
    </div>
    <div class="px-6 pb-5 flex gap-2">
      <button onclick="confirmDispute()" class="flex-1 justify-center py-2.5 text-white font-semibold text-sm rounded-lg" style="background:#dc2626;">
        <i class="fas fa-flag mr-1.5"></i>Flag as Disputed
      </button>
      <button onclick="closeDisputeModal()" class="btn-secondary flex-1 justify-center py-2.5">Cancel</button>
    </div>
  </div>
</div>

<?php include 'partials/modals_js.php'; ?>

<script>
// ── Tab switching ────────────────────────────────────────────
function switchTab(id) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  document.querySelectorAll('.tab-btn').forEach(b => {
    if (b.getAttribute('onclick') === "switchTab('" + id + "')") b.classList.add('active');
  });
}

// ── AJAX helper ──────────────────────────────────────────────
function postAction(data, onSuccess) {
  const fd = new FormData();
  Object.entries(data).forEach(([k,v]) => fd.append(k, v));
  fetch('billing_settlement.php', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      if (d.success) { showToast(d.message); if (onSuccess) onSuccess(d); }
      else { showToast(d.message, true); }
    })
    .catch(() => showToast('Network error.', true));
}

// ── Force Settle ─────────────────────────────────────────────
function forceSettle(txId, btn) {
  openConfirm('settle', 'Force Settle Transaction', 'Mark this transaction as Settled? This action is logged.', 'Force Settle', 'purple', () => {
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    postAction({action:'force_settle', tx_id:txId}, () => {
      const cell = document.getElementById('settle-cell-' + txId);
      if (cell) cell.innerHTML = '<span class="sbadge" style="background:#d1fae5;color:#065f46;"><i class="fas fa-check text-xs"></i> Settled</span>';
      btn.remove();
      // Also add reverse button
      const row = document.getElementById('tx-row-' + txId);
      if (row) {
        const actCell = row.querySelector('td:last-child div');
        if (actCell) {
          const rev = document.createElement('button');
          rev.className = 'py-1 px-2 text-xs font-semibold rounded';
          rev.style = 'background:#fef3c7;color:#92400e;';
          rev.title = 'Reverse settlement';
          rev.innerHTML = '<i class="fas fa-rotate-left"></i>';
          rev.onclick = () => reverseSettle(txId, rev);
          actCell.appendChild(rev);
        }
      }
    });
  });
}

// ── Reverse Settle ───────────────────────────────────────────
function reverseSettle(txId, btn) {
  openConfirm('reverse', 'Reverse Settlement', 'This will set the transaction back to Pending settlement. Confirm?', 'Reverse', 'amber', () => {
    btn.disabled = true;
    postAction({action:'reverse_settle', tx_id:txId}, () => {
      const cell = document.getElementById('settle-cell-' + txId);
      if (cell) cell.innerHTML = '<span class="sbadge" style="background:#fef3c7;color:#92400e;"><i class="fas fa-hourglass-half text-xs"></i> Pending</span>';
      setTimeout(() => location.reload(), 800);
    });
  });
}

// ── Dispute Modal ────────────────────────────────────────────
let _disputeTxId = null;
function openDisputeModal(txId) {
  _disputeTxId = txId;
  document.getElementById('dispute-note').value = '';
  document.getElementById('disputeModal').classList.add('open');
}
function closeDisputeModal() {
  document.getElementById('disputeModal').classList.remove('open');
  _disputeTxId = null;
}
function confirmDispute() {
  if (!_disputeTxId) return;
  const note = document.getElementById('dispute-note').value.trim();
  postAction({action:'flag_dispute', tx_id:_disputeTxId, note}, () => {
    closeDisputeModal();
    setTimeout(() => location.reload(), 600);
  });
}

// ── Resolve Dispute ──────────────────────────────────────────
function resolveDispute(txId, btn) {
  openConfirm('resolve', 'Resolve Dispute', 'This will mark the transaction as Settled and close the dispute.', 'Resolve & Settle', 'green', () => {
    btn.disabled = true;
    postAction({action:'resolve_dispute', tx_id:txId}, () => {
      setTimeout(() => location.reload(), 600);
    });
  });
}

// ── Bulk Settle All ──────────────────────────────────────────
function openBulkAll() {
  openConfirm('bulk', 'Settle All Pending', 'This will globally settle ALL paid-but-unsettled transactions across every club. This is logged in the audit trail.', 'Settle All', 'purple', () => {
    postAction({action:'bulk_settle_all'}, () => setTimeout(() => location.reload(), 800));
  });
}

// ── Bulk Settle Club ─────────────────────────────────────────
let _bulkClubId = null;
function openBulkClub(clubId, clubName, count, amt) {
  _bulkClubId = clubId;
  document.getElementById('bc-club-name').textContent = clubName;
  document.getElementById('bc-count').textContent = count + ' transaction' + (count!==1?'s':'');
  document.getElementById('bc-amt').textContent = 'RM ' + amt;
  document.getElementById('bulkClubModal').classList.add('open');
}
function closeBulkClub() {
  document.getElementById('bulkClubModal').classList.remove('open');
  _bulkClubId = null;
}
function confirmBulkClub() {
  if (!_bulkClubId) return;
  const btn = document.getElementById('bc-confirm-btn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Settling…';
  postAction({action:'bulk_settle_club', club_id:_bulkClubId}, () => {
    closeBulkClub();
    setTimeout(() => location.reload(), 700);
  });
}

// ── Detail Modal ─────────────────────────────────────────────
function receiptNo(id) { return 'RCP-' + String(id).padStart(6,'0'); }
function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric'});
}
function openDetail(tx) {
  const pColors = {Paid:['#d1fae5','#065f46'],Pending:['#fef3c7','#92400e'],Failed:['#fee2e2','#991b1b'],Refunded:['#e0e7ff','#3730a3']};
  const sColors = {Settled:['#d1fae5','#065f46'],Pending:['#fef3c7','#92400e'],Disputed:['#fee2e2','#991b1b']};
  const [pb,pc] = pColors[tx.payment_status]||['#f3f4f6','#374151'];
  const [sb,sc] = sColors[tx.settlement_status]||['#f3f4f6','#374151'];

  document.getElementById('modal-title').textContent = receiptNo(tx.id);
  const rows = [
    ['Receipt No',       receiptNo(tx.id)],
    ['Club',             tx.club_name || '—'],
    ['Transaction ID',   tx.transaction_id || '—'],
    ['Bill Code',        tx.bill_code || '—'],
    ['Student',          (tx.student_name||'—') + (tx.matric_no ? ' · ' + tx.matric_no : '')],
    ['Event',            tx.event_title || '—'],
    ['Amount',           'RM ' + parseFloat(tx.amount||0).toFixed(2)],
    ['Payment Method',   tx.payment_method || '—'],
    ['Payment Date',     fmtDate(tx.paid_at || tx.created_at)],
    ['Settled At',       tx.settled_at ? fmtDate(tx.settled_at) : '—'],
  ];
  if (tx.dispute_note) rows.push(['Dispute Note', tx.dispute_note]);

  let html = rows.map(([k,v]) => `<div class="drow"><span class="dk">${k}</span><span class="dv">${v}</span></div>`).join('');
  html += `<div class="drow"><span class="dk">Payment</span><span class="dv"><span class="sbadge" style="background:${pb};color:${pc};">${tx.payment_status}</span></span></div>`;
  html += `<div class="drow"><span class="dk">Settlement</span><span class="dv"><span class="sbadge" style="background:${sb};color:${sc};">${tx.settlement_status}</span></span></div>`;
  document.getElementById('modal-body').innerHTML = html;

  // Action buttons in modal
  let actions = `<button onclick="closeDetail()" class="btn-secondary flex-1 justify-center py-2.5">Close</button>`;
  actions = `<a href="receipt.php?id=${tx.id}" target="_blank" class="btn-secondary flex-1 justify-center py-2.5"><i class="fas fa-print mr-1"></i>Print Receipt</a>` + actions;
  if (tx.settlement_status==='Pending' && tx.payment_status==='Paid') {
    actions = `<button onclick="closeDetail();forceSettle(${tx.id},document.createElement('button'))" class="btn-primary flex-1 justify-center py-2.5"><i class="fas fa-check mr-1"></i>Force Settle</button>` + actions;
  }
  if (tx.settlement_status==='Disputed') {
    actions = `<button onclick="closeDetail();resolveDispute(${tx.id},document.createElement('button'))" class="flex-1 justify-center py-2.5 text-white font-semibold text-sm rounded-lg" style="background:#059669;"><i class="fas fa-check-double mr-1"></i>Resolve</button>` + actions;
  }
  document.getElementById('modal-actions').innerHTML = actions;
  document.getElementById('detailModal').classList.add('open');
}
function closeDetail() { document.getElementById('detailModal').classList.remove('open'); }

// Close export dropdown on outside click
document.addEventListener('click', e => {
  const wrap = document.getElementById('exportDropWrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('exportDrop').classList.add('hidden');
  }
});
</script>
</body>
</html>
