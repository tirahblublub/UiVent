<?php
require_once '../config.php';
requireAdmin();

$activePage = 'reports';
$pageTitle  = 'Reports';
$adminId    = $_SESSION['admin_id'];
$admin      = currentClubAdmin();

$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$eventId  = (int)($_GET['event_id'] ?? 0);

// Events for dropdown
$evList = db()->prepare("SELECT id, title, start_date FROM events WHERE created_by=? ORDER BY start_date DESC");
$evList->execute([$adminId]);
$evList = $evList->fetchAll();

// Selected event
$selectedEvent = null;
if ($eventId) {
    $ev = db()->prepare("SELECT * FROM events WHERE id=? AND created_by=?");
    $ev->execute([$eventId, $adminId]);
    $selectedEvent = $ev->fetch();
}

// Stats
if ($selectedEvent) {
    $s = db()->prepare("SELECT COUNT(*) FROM registrations WHERE event_id=?"); $s->execute([$eventId]); $totalRegs=(int)$s->fetchColumn();
    $s = db()->prepare("SELECT COUNT(*) FROM registrations WHERE event_id=? AND attended_at IS NOT NULL"); $s->execute([$eventId]); $totalAttended=(int)$s->fetchColumn();
    $totalEvents = 1;
    $uniqueStudents = $totalRegs;
    $attRate = $totalRegs > 0 ? round($totalAttended/$totalRegs*100) : 0;

    $participants = db()->prepare("
        SELECT s.name, s.matric_no, s.email, r.attended_at, r.registered_at
        FROM registrations r JOIN students s ON s.id=r.student_id
        WHERE r.event_id=? ORDER BY r.attended_at IS NULL ASC, s.name ASC
    ");
    $participants->execute([$eventId]);
    $participants = $participants->fetchAll();
    $eventsData   = [];
} else {
    $s = db()->prepare("SELECT COUNT(*) FROM events WHERE created_by=? AND DATE(start_date) BETWEEN ? AND ?"); $s->execute([$adminId,$dateFrom,$dateTo]); $totalEvents=(int)$s->fetchColumn();
    $s = db()->prepare("SELECT COUNT(*) FROM registrations r JOIN events e ON e.id=r.event_id WHERE e.created_by=? AND DATE(e.start_date) BETWEEN ? AND ?"); $s->execute([$adminId,$dateFrom,$dateTo]); $totalRegs=(int)$s->fetchColumn();
    $s = db()->prepare("SELECT COUNT(*) FROM registrations r JOIN events e ON e.id=r.event_id WHERE e.created_by=? AND DATE(e.start_date) BETWEEN ? AND ? AND r.attended_at IS NOT NULL"); $s->execute([$adminId,$dateFrom,$dateTo]); $totalAttended=(int)$s->fetchColumn();
    $s = db()->prepare("SELECT COUNT(DISTINCT r.student_id) FROM registrations r JOIN events e ON e.id=r.event_id WHERE e.created_by=? AND DATE(e.start_date) BETWEEN ? AND ?"); $s->execute([$adminId,$dateFrom,$dateTo]); $uniqueStudents=(int)$s->fetchColumn();
    $attRate = $totalRegs > 0 ? round($totalAttended/$totalRegs*100) : 0;
    $participants = [];

    $stmt = db()->prepare("
        SELECT e.id, e.title, e.venue, e.start_date, e.end_date, e.status, e.capacity, e.category,
               COUNT(r.id) AS registrations,
               SUM(r.attended_at IS NOT NULL) AS attended
        FROM events e LEFT JOIN registrations r ON r.event_id=e.id
        WHERE e.created_by=? AND DATE(e.start_date) BETWEEN ? AND ?
        GROUP BY e.id ORDER BY e.start_date ASC
    ");
    $stmt->execute([$adminId,$dateFrom,$dateTo]);
    $eventsData = $stmt->fetchAll();
}

$topStudents = db()->prepare("
    SELECT s.name, s.matric_no, COUNT(r.id) AS total_reg, SUM(r.attended_at IS NOT NULL) AS attended
    FROM registrations r JOIN students s ON s.id=r.student_id JOIN events e ON e.id=r.event_id
    WHERE e.created_by=? AND DATE(e.start_date) BETWEEN ? AND ?
    GROUP BY s.id ORDER BY attended DESC LIMIT 10
");
$topStudents->execute([$adminId,$dateFrom,$dateTo]);
$topStudents = $topStudents->fetchAll();

$generatedAt  = date('d F Y, h:i A');
$reportTitle  = $selectedEvent
    ? $selectedEvent['title']
    : 'Club Activity Report';
$periodLabel  = $selectedEvent
    ? date('d F Y', strtotime($selectedEvent['start_date']))
    : date('d M Y', strtotime($dateFrom)).' – '.date('d M Y', strtotime($dateTo));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>UiVent | Report — <?= htmlspecialchars($reportTitle) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<?php include 'partials/head_styles.php'; ?>
<style>
* { font-family:'Inter',sans-serif; box-sizing:border-box; }
.mono { font-family:'JetBrains Mono',monospace; }

/* ── form inputs ── */
.fi { padding:.5rem .875rem; border:1.5px solid #e5e7eb; border-radius:.5rem; font-size:.875rem; color:#374151; outline:none; background:#fff; transition:border-color .15s; }
.fi:focus { border-color:#582C83; box-shadow:0 0 0 3px rgba(88,44,131,.1); }

/* ══════════════════════════════════════════
   REPORT PAPER
══════════════════════════════════════════ */
#reportPaper {
  background:#fff;
  width:100%;
  max-width:860px;
  margin:0 auto;
  border-radius:20px;
  overflow:hidden;
  box-shadow:0 8px 40px rgba(39,19,74,.13), 0 1px 4px rgba(39,19,74,.07);
}

/* ── Cover band ── */
.cover {
  background:linear-gradient(135deg,#1a0d32 0%,#27134A 40%,#582C83 100%);
  padding:44px 52px 36px;
  position:relative;
  overflow:hidden;
}
.cover::after {
  content:'';
  position:absolute;
  right:-60px; top:-60px;
  width:280px; height:280px;
  border-radius:50%;
  background:rgba(249,165,27,.07);
  pointer-events:none;
}
.cover::before {
  content:'';
  position:absolute;
  right:80px; bottom:-40px;
  width:160px; height:160px;
  border-radius:50%;
  background:rgba(249,165,27,.05);
  pointer-events:none;
}

.cover-logo {
  display:flex; align-items:center; gap:10px; margin-bottom:28px;
}
.logo-ui {
  background:#F9A51B; color:#27134A;
  font-weight:900; font-size:15px;
  padding:4px 11px; border-radius:7px;
  letter-spacing:.04em; line-height:1.4;
}
.logo-vent { font-size:20px; font-weight:800; color:#fff; letter-spacing:.04em; }
.logo-tag {
  font-size:9px; font-weight:700; letter-spacing:.12em; text-transform:uppercase;
  background:rgba(249,165,27,.15); color:#F9A51B;
  border:1px solid rgba(249,165,27,.3);
  padding:3px 9px; border-radius:4px;
}

.cover-eyebrow {
  font-size:10px; font-weight:700; letter-spacing:.16em; text-transform:uppercase;
  color:#F9A51B; margin-bottom:8px;
}
.cover-title {
  font-size:28px; font-weight:900; color:#fff;
  line-height:1.15; margin-bottom:6px;
  max-width:600px;
}
.cover-sub {
  font-size:13px; color:rgba(255,255,255,.55); margin-bottom:28px;
}

.cover-pills {
  display:flex; gap:8px; flex-wrap:wrap;
}
.pill {
  display:flex; flex-direction:column;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.12);
  border-radius:10px;
  padding:10px 16px;
  min-width:110px;
}
.pill-label { font-size:9px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:rgba(255,255,255,.45); margin-bottom:3px; }
.pill-val   { font-size:13px; font-weight:700; color:#fff; }

/* ── Body ── */
.body { padding:44px 52px; }

/* ── Section ── */
.sec { margin-bottom:40px; }
.sec-head {
  display:flex; align-items:center; gap:12px;
  margin-bottom:20px;
  padding-bottom:10px;
  border-bottom:2px solid #f0ebfa;
}
.sec-num {
  width:28px; height:28px; border-radius:7px; flex-shrink:0;
  background:#582C83; color:#F9A51B;
  font-size:11px; font-weight:800;
  display:flex; align-items:center; justify-content:center;
}
.sec-title {
  font-size:10px; font-weight:800; letter-spacing:.14em;
  text-transform:uppercase; color:#582C83;
}
.sec-line {
  flex:1; height:1px; background:linear-gradient(90deg,#ddd5f5,transparent);
}

/* ── Info grid ── */
.info-grid { display:grid; grid-template-columns:1fr 1fr; gap:0 40px; }
.info-row {
  display:flex; align-items:flex-start; gap:12px;
  padding:9px 0;
  border-bottom:1px solid #f5f0ff;
  font-size:13px;
}
.info-row:last-child { border-bottom:none; }
.info-k { color:#9ca3af; font-weight:600; width:130px; flex-shrink:0; font-size:12px; }
.info-v { color:#111827; font-weight:600; }

/* ── Stat tiles ── */
.tiles { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:28px; }
.tile {
  border:1.5px solid #e5e7eb; border-radius:14px;
  padding:18px 16px 14px;
  text-align:center;
  position:relative; overflow:hidden;
}
.tile::before {
  content:''; position:absolute; top:0; left:0; right:0;
  height:3px; border-radius:2px 2px 0 0;
}
.tile-p::before { background:linear-gradient(90deg,#582C83,#7B4DB3); }
.tile-g::before { background:linear-gradient(90deg,#059669,#34d399); }
.tile-r::before { background:linear-gradient(90deg,#dc2626,#f87171); }
.tile-a::before { background:linear-gradient(90deg,#d97706,#F9A51B); }
.tile-num  { font-size:34px; font-weight:900; color:#27134A; line-height:1; margin-bottom:4px; }
.tile-num-gold { color:#d97706; }
.tile-lbl  { font-size:10px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.06em; }
.tile-icon { font-size:20px; margin-bottom:8px; }

/* ── Tables ── */
.rtable { width:100%; border-collapse:collapse; font-size:12.5px; border-radius:12px; overflow:hidden; border:1.5px solid #e5e7eb; }
.rtable thead tr { background:#27134A; }
.rtable thead th {
  padding:11px 14px; text-align:left;
  font-size:9px; font-weight:700; letter-spacing:.1em;
  text-transform:uppercase; color:#F9A51B;
}
.rtable tbody tr { border-bottom:1px solid #f5f0ff; transition:background .1s; }
.rtable tbody tr:last-child { border-bottom:none; }
.rtable tbody tr:hover td { background:#faf8ff; }
.rtable tbody td { padding:10px 14px; color:#374151; vertical-align:middle; }
.rtable tbody tr:nth-child(even) td { background:#fafafa; }
.rtable tfoot td { padding:10px 14px; font-weight:800; font-size:13px; background:#f0ebfa; color:#27134A; }

/* ── Att bar ── */
.abar { display:inline-flex; align-items:center; gap:6px; }
.abar-bg { width:52px; height:5px; background:#e5e7eb; border-radius:99px; overflow:hidden; }
.abar-fill { height:100%; border-radius:99px; }

/* ── Rate badge ── */
.rbadge { font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; }
.rbadge-hi  { background:#d1fae5; color:#065f46; }
.rbadge-mid { background:#fef3c7; color:#92400e; }
.rbadge-lo  { background:#fee2e2; color:#991b1b; }

/* ── Checklist ── */
.chklist { display:grid; grid-template-columns:1fr 1fr; gap:0; }
.chk-item {
  display:flex; align-items:center; gap:10px;
  padding:8px 0; font-size:12.5px; color:#374151;
  border-bottom:1px solid #f5f0ff;
}
.chk-item i { color:#059669; font-size:11px; flex-shrink:0; }

/* ── Desc box ── */
.desc-box {
  background:#faf8ff; border-left:3px solid #582C83;
  border-radius:0 8px 8px 0;
  padding:12px 16px; font-size:12.5px; color:#374151;
  line-height:1.7; margin-top:14px;
}

/* ── Conclusion ── */
.conclusion {
  background:linear-gradient(135deg,#faf8ff,#f0ebfa);
  border:1.5px solid #ddd5f5;
  border-radius:12px;
  padding:20px 24px;
  font-size:13px; color:#374151; line-height:1.75;
}

/* ── Signature ── */
.sig-block {
  display:grid; grid-template-columns:1fr 1fr; gap:48px;
  margin-top:48px; padding-top:32px;
  border-top:1px dashed #ddd5f5;
}
.sig-line {
  text-align:center;
}
.sig-rule {
  border-top:1.5px solid #374151;
  padding-top:8px; margin-top:52px;
}
.sig-name  { font-size:12px; font-weight:700; color:#111827; }
.sig-role  { font-size:11px; color:#6b7280; margin-top:2px; }
.sig-stamp {
  width:72px; height:72px; border-radius:50%;
  border:2px dashed #ddd5f5;
  margin:0 auto 8px;
  display:flex; align-items:center; justify-content:center;
  color:#ddd5f5; font-size:10px; font-weight:600;
  text-align:center; line-height:1.3;
}

/* ── Doc footer ── */
.doc-footer {
  background:#f9fafb; border-top:1.5px solid #e5e7eb;
  padding:14px 52px;
  display:flex; justify-content:space-between; align-items:center;
}
.doc-footer-l { font-size:10px; color:#9ca3af; font-weight:500; }
.doc-footer-r { font-size:10px; color:#9ca3af; font-weight:500; }

/* ── Rank medal ── */
.medal-1 { background:#fef3c7; color:#d97706; border:1.5px solid #fde68a; }
.medal-2 { background:#f3f4f6; color:#6b7280; border:1.5px solid #d1d5db; }
.medal-3 { background:#fff7ed; color:#c2410c; border:1.5px solid #fed7aa; }
.medal {
  width:24px; height:24px; border-radius:50%;
  font-size:10px; font-weight:800;
  display:inline-flex; align-items:center; justify-content:center;
}

/* ══════════════════════════════════════════
   PRINT
══════════════════════════════════════════ */
@media print {
  * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
  body { background:#fff !important; margin:0; padding:0; }
  .no-print { display:none !important; }
  #reportPaper {
    box-shadow:none !important;
    border-radius:0 !important;
    max-width:100% !important;
    border:none !important;
  }
  .cover { -webkit-print-color-adjust:exact; }
  .body { padding:32px 44px; }
  .cover { padding:32px 44px 28px; }
  .tiles { grid-template-columns:repeat(4,1fr); }
  .sec { margin-bottom:28px; }
  .page-break { page-break-before:always; margin-top:0; padding-top:0; }
}
</style>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 space-y-5 w-full mx-auto" style="max-width:920px;">

  <!-- ── Controls (screen only) ── -->
  <div class="no-print">
    <div class="flex items-center justify-between flex-wrap gap-4 mb-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Reports</h1>
        <p class="text-sm text-gray-500 mt-0.5">Generate printable programme reports.</p>
      </div>
      <button onclick="window.print()"
              class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white transition-all hover:opacity-90 shadow-sm"
              style="background:linear-gradient(135deg,#27134A,#582C83);">
        <i class="fas fa-print"></i> Print / Save PDF
      </button>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
      <form method="GET" class="flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[220px]">
          <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Select Event / Report</label>
          <select name="event_id" class="fi w-full" onchange="this.form.submit()">
            <option value="">── All Events (Date Range) ──</option>
            <?php foreach ($evList as $ev): ?>
              <option value="<?= $ev['id'] ?>" <?= $eventId===$ev['id']?'selected':'' ?>>
                <?= htmlspecialchars($ev['title']) ?>
                <?= $ev['start_date'] ? '('.date('d M Y', strtotime($ev['start_date'])).')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if (!$selectedEvent): ?>
        <div>
          <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">From</label>
          <input type="date" name="date_from" value="<?= $dateFrom ?>" class="fi">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">To</label>
          <input type="date" name="date_to" value="<?= $dateTo ?>" class="fi">
        </div>
        <button type="submit" class="btn-primary py-2.5 px-5">Apply</button>
        <a href="reports.php" class="btn-secondary py-2.5 px-5">Reset</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       REPORT PAPER
  ══════════════════════════════════════════ -->
  <div id="reportPaper">

    <!-- Cover -->
    <div class="cover">
      <div class="cover-logo">
        <span class="logo-ui">Ui</span>
        <span class="logo-vent">Vent</span>
        <span class="logo-tag">UiTM Machang · HEP Division</span>
      </div>

      <div class="cover-eyebrow">
        <?= $selectedEvent ? 'Programme Report' : 'Club Activity Report' ?>
      </div>
      <div class="cover-title"><?= htmlspecialchars($reportTitle) ?></div>
      <div class="cover-sub">
        <?= htmlspecialchars($admin['name'] ?? '') ?>
        <?= $admin['role'] ? ' · ' . htmlspecialchars($admin['role']) : '' ?>
      </div>

      <div class="cover-pills">
        <?php if ($selectedEvent): ?>
        <div class="pill">
          <span class="pill-label">Date</span>
          <span class="pill-val"><?= $selectedEvent['start_date'] ? date('d M Y', strtotime($selectedEvent['start_date'])) : 'TBD' ?></span>
        </div>
        <div class="pill">
          <span class="pill-label">Venue</span>
          <span class="pill-val"><?= htmlspecialchars($selectedEvent['venue'] ?? '—') ?></span>
        </div>
        <div class="pill">
          <span class="pill-label">Category</span>
          <span class="pill-val"><?= htmlspecialchars($selectedEvent['category'] ?? '—') ?></span>
        </div>
        <?php else: ?>
        <div class="pill">
          <span class="pill-label">Period</span>
          <span class="pill-val"><?= date('d M Y', strtotime($dateFrom)) ?> – <?= date('d M Y', strtotime($dateTo)) ?></span>
        </div>
        <div class="pill">
          <span class="pill-label">Events</span>
          <span class="pill-val"><?= $totalEvents ?> programmes</span>
        </div>
        <?php endif; ?>
        <div class="pill">
          <span class="pill-label">Generated</span>
          <span class="pill-val"><?= $generatedAt ?></span>
        </div>
      </div>
    </div>

    <!-- Body -->
    <div class="body">

    <?php if ($selectedEvent): ?>
    <!-- ══ SINGLE EVENT ══════════════════════════════════ -->

    <!-- 1 — Programme Background -->
    <div class="sec">
      <div class="sec-head">
        <span class="sec-num">1</span>
        <span class="sec-title">Programme Background</span>
        <span class="sec-line"></span>
      </div>
      <div class="info-grid">
        <div>
          <div class="info-row"><span class="info-k">Programme Name</span><span class="info-v"><?= htmlspecialchars($selectedEvent['title']) ?></span></div>
          <div class="info-row"><span class="info-k">Date</span><span class="info-v"><?= $selectedEvent['start_date'] ? date('d F Y', strtotime($selectedEvent['start_date'])) : 'TBD' ?></span></div>
          <div class="info-row"><span class="info-k">Time</span><span class="info-v">
            <?= $selectedEvent['start_date'] ? date('h:i A', strtotime($selectedEvent['start_date'])) : '—' ?>
            <?= $selectedEvent['end_date'] ? ' — ' . date('h:i A', strtotime($selectedEvent['end_date'])) : '' ?>
          </span></div>
          <div class="info-row"><span class="info-k">Venue</span><span class="info-v"><?= htmlspecialchars($selectedEvent['venue'] ?? '—') ?></span></div>
        </div>
        <div>
          <div class="info-row"><span class="info-k">Category</span><span class="info-v"><?= htmlspecialchars($selectedEvent['category'] ?? '—') ?></span></div>
          <div class="info-row"><span class="info-k">Max Capacity</span><span class="info-v"><?= number_format($selectedEvent['capacity']) ?> participants</span></div>
          <div class="info-row"><span class="info-k">Status</span><span class="info-v"><?= ucfirst(str_replace('_',' ',$selectedEvent['status'])) ?></span></div>
          <div class="info-row"><span class="info-k">Organised By</span><span class="info-v"><?= htmlspecialchars($admin['name'] ?? '—') ?></span></div>
        </div>
      </div>
      <?php if (!empty($selectedEvent['description'])): ?>
      <div class="desc-box"><?= nl2br(htmlspecialchars($selectedEvent['description'])) ?></div>
      <?php endif; ?>
    </div>

    <!-- 2 — Participant Involvement -->
    <div class="sec">
      <div class="sec-head">
        <span class="sec-num">2</span>
        <span class="sec-title">Participant Involvement</span>
        <span class="sec-line"></span>
      </div>

      <!-- Tiles -->
      <div class="tiles">
        <div class="tile tile-p">
          <div class="tile-icon" style="color:#582C83;"><i class="fas fa-users"></i></div>
          <div class="tile-num"><?= $totalRegs ?></div>
          <div class="tile-lbl">Registered</div>
        </div>
        <div class="tile tile-g">
          <div class="tile-icon" style="color:#059669;"><i class="fas fa-circle-check"></i></div>
          <div class="tile-num"><?= $totalAttended ?></div>
          <div class="tile-lbl">Attended</div>
        </div>
        <div class="tile tile-r">
          <div class="tile-icon" style="color:#dc2626;"><i class="fas fa-circle-xmark"></i></div>
          <div class="tile-num"><?= $totalRegs - $totalAttended ?></div>
          <div class="tile-lbl">Absent</div>
        </div>
        <div class="tile tile-a">
          <div class="tile-icon" style="color:#d97706;"><i class="fas fa-chart-pie"></i></div>
          <div class="tile-num tile-num-gold"><?= $attRate ?>%</div>
          <div class="tile-lbl">Attendance Rate</div>
        </div>
      </div>

      <!-- Breakdown table -->
      <table class="rtable">
        <thead>
          <tr>
            <th>No.</th>
            <th>Institution</th>
            <th style="text-align:center;">Registered</th>
            <th style="text-align:center;">Attended</th>
            <th style="text-align:center;">Absent</th>
            <th style="text-align:center;">Rate</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="color:#9ca3af;font-weight:600;">i.</td>
            <td style="font-weight:600;">UiTM Cawangan Kelantan, Kampus Machang</td>
            <td style="text-align:center;font-weight:700;"><?= $totalRegs ?></td>
            <td style="text-align:center;font-weight:700;color:#059669;"><?= $totalAttended ?></td>
            <td style="text-align:center;font-weight:700;color:#dc2626;"><?= $totalRegs - $totalAttended ?></td>
            <td style="text-align:center;">
              <?php
              $rc = $attRate>=70?'rbadge-hi':($attRate>=40?'rbadge-mid':'rbadge-lo');
              ?>
              <span class="rbadge <?= $rc ?>"><?= $attRate ?>%</span>
              <div class="abar" style="display:block;margin-top:4px;">
                <div class="abar-bg">
                  <div class="abar-fill" style="width:<?= $attRate ?>%;background:<?= $attRate>=70?'#059669':($attRate>=40?'#d97706':'#dc2626') ?>;"></div>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2">Total</td>
            <td style="text-align:center;"><?= $totalRegs ?></td>
            <td style="text-align:center;color:#059669;"><?= $totalAttended ?></td>
            <td style="text-align:center;color:#dc2626;"><?= $totalRegs - $totalAttended ?></td>
            <td style="text-align:center;color:#582C83;"><?= $attRate ?>%</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- 3 — Participant List -->
    <?php if (!empty($participants)): ?>
    <div class="sec <?= count($participants) > 20 ? 'page-break' : '' ?>">
      <div class="sec-head">
        <span class="sec-num">3</span>
        <span class="sec-title">Participant List</span>
        <span class="sec-line"></span>
      </div>
      <table class="rtable">
        <thead>
          <tr>
            <th width="36">No.</th>
            <th>Name</th>
            <th>Matric No.</th>
            <th style="text-align:center;">Registered</th>
            <th style="text-align:center;">Attendance</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($participants as $i => $p): ?>
          <tr>
            <td style="color:#9ca3af;font-size:11px;"><?= $i+1 ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></td>
            <td class="mono" style="font-size:11.5px;color:#582C83;"><?= htmlspecialchars($p['matric_no']) ?></td>
            <td style="text-align:center;font-size:11px;color:#6b7280;"><?= date('d M Y', strtotime($p['registered_at'])) ?></td>
            <td style="text-align:center;">
              <?php if ($p['attended_at']): ?>
                <span style="background:#d1fae5;color:#065f46;font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;">
                  ✓ <?= date('h:i A', strtotime($p['attended_at'])) ?>
                </span>
              <?php else: ?>
                <span style="background:#fee2e2;color:#991b1b;font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;">
                  ✗ Absent
                </span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2">Total Participants</td>
            <td colspan="1"></td>
            <td style="text-align:center;"><?= count($participants) ?></td>
            <td style="text-align:center;color:#059669;"><?= $totalAttended ?> attended</td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>

    <!-- 4 — Report Checklist -->
    <div class="sec">
      <div class="sec-head">
        <span class="sec-num">4</span>
        <span class="sec-title">Report Checklist</span>
        <span class="sec-line"></span>
      </div>
      <div class="chklist">
        <?php foreach ([
          'Programme Background','Programme Objectives','Programme Details (Date / Time / Venue)',
          'Organising Body & Committee','Programme Schedule','Participant Involvement & Statistics',
          'Programme Assessment','Problems & Shortcomings','Suggestions for Improvement',
          'Programme Impact','Conclusion & Summary','Appendix / Evidence',
        ] as $item): ?>
        <div class="chk-item"><i class="fas fa-check-circle"></i><?= $item ?></div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 5 — Conclusion -->
    <div class="sec">
      <div class="sec-head">
        <span class="sec-num">5</span>
        <span class="sec-title">Conclusion</span>
        <span class="sec-line"></span>
      </div>
      <div class="conclusion">
        The programme <strong>"<?= htmlspecialchars($selectedEvent['title']) ?>"</strong>
        was successfully conducted on
        <strong><?= $selectedEvent['start_date'] ? date('d F Y', strtotime($selectedEvent['start_date'])) : 'the scheduled date' ?></strong>
        <?= $selectedEvent['venue'] ? 'at <strong>' . htmlspecialchars($selectedEvent['venue']) . '</strong>' : '' ?>.
        A total of <strong><?= $totalRegs ?> participant<?= $totalRegs!==1?'s':'' ?></strong> registered for the programme,
        with <strong><?= $totalAttended ?> participant<?= $totalAttended!==1?'s':'' ?></strong> in attendance,
        achieving an overall attendance rate of
        <strong style="color:<?= $attRate>=70?'#059669':($attRate>=40?'#d97706':'#dc2626') ?>;"><?= $attRate ?>%</strong>.
        <?php if ($attRate >= 70): ?>
        The programme was well-received with a high attendance rate.
        <?php elseif ($attRate >= 40): ?>
        Attendance was moderate; improvements to scheduling and promotion are recommended for future programmes.
        <?php else: ?>
        Attendance was below expectations. A review of scheduling, promotion, and participant engagement strategies is recommended.
        <?php endif; ?>
        All shortcomings identified during the programme will be addressed in future events.
      </div>
    </div>

    <!-- Signature block -->
    <div class="sig-block">
      <div class="sig-line">
        <div class="sig-stamp">OFFICIAL<br>STAMP</div>
        <div class="sig-rule">
          <div class="sig-name"><?= htmlspecialchars($admin['name'] ?? '___________________') ?></div>
          <div class="sig-role">Programme Director / Club Admin</div>
          <div class="sig-role" style="margin-top:2px;color:#9ca3af;font-size:10px;">Date: _______________</div>
        </div>
      </div>
      <div class="sig-line">
        <div class="sig-stamp">OFFICIAL<br>STAMP</div>
        <div class="sig-rule">
          <div class="sig-name">___________________________</div>
          <div class="sig-role">Advisor / HEP Supervisor</div>
          <div class="sig-role" style="margin-top:2px;color:#9ca3af;font-size:10px;">Date: _______________</div>
        </div>
      </div>
    </div>

    <?php else: ?>
    <!-- ══ DATE RANGE REPORT ═══════════════════════════ -->

    <!-- 1 — Summary -->
    <div class="sec">
      <div class="sec-head">
        <span class="sec-num">1</span>
        <span class="sec-title">Summary Statistics</span>
        <span class="sec-line"></span>
      </div>
      <div class="tiles">
        <div class="tile tile-p">
          <div class="tile-icon" style="color:#582C83;"><i class="fas fa-calendar-check"></i></div>
          <div class="tile-num"><?= $totalEvents ?></div>
          <div class="tile-lbl">Total Events</div>
        </div>
        <div class="tile tile-a" style="">
          <div class="tile-icon" style="color:#0284c7;"><i class="fas fa-users"></i></div>
          <div class="tile-num" style="color:#0284c7;"><?= $totalRegs ?></div>
          <div class="tile-lbl">Registrations</div>
        </div>
        <div class="tile tile-g">
          <div class="tile-icon" style="color:#059669;"><i class="fas fa-clipboard-check"></i></div>
          <div class="tile-num"><?= $totalAttended ?></div>
          <div class="tile-lbl">Attended</div>
        </div>
        <div class="tile tile-a">
          <div class="tile-icon" style="color:#d97706;"><i class="fas fa-chart-pie"></i></div>
          <div class="tile-num tile-num-gold"><?= $attRate ?>%</div>
          <div class="tile-lbl">Avg. Rate</div>
        </div>
      </div>
    </div>

    <!-- 2 — Events Table -->
    <?php if (!empty($eventsData)): ?>
    <div class="sec">
      <div class="sec-head">
        <span class="sec-num">2</span>
        <span class="sec-title">Event Summary</span>
        <span class="sec-line"></span>
      </div>
      <table class="rtable">
        <thead>
          <tr>
            <th width="30">No.</th>
            <th>Event Title</th>
            <th>Date</th>
            <th style="text-align:center;">Registered</th>
            <th style="text-align:center;">Attended</th>
            <th style="text-align:center;">Rate</th>
            <th style="text-align:center;">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($eventsData as $i => $ev):
            $rate = $ev['registrations'] > 0 ? round($ev['attended']/$ev['registrations']*100) : 0;
            $rc   = $rate>=70?'rbadge-hi':($rate>=40?'rbadge-mid':'rbadge-lo');
            $bc   = $rate>=70?'#059669':($rate>=40?'#d97706':'#dc2626');
          ?>
          <tr>
            <td style="color:#9ca3af;font-size:11px;"><?= $i+1 ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($ev['title']) ?></td>
            <td style="font-size:11.5px;color:#6b7280;white-space:nowrap;"><?= $ev['start_date'] ? date('d M Y', strtotime($ev['start_date'])) : '—' ?></td>
            <td style="text-align:center;font-weight:600;"><?= $ev['registrations'] ?></td>
            <td style="text-align:center;font-weight:700;color:#059669;"><?= $ev['attended'] ?></td>
            <td style="text-align:center;">
              <span class="rbadge <?= $rc ?>"><?= $rate ?>%</span>
              <div class="abar-bg" style="margin:4px auto 0;width:48px;"><div class="abar-fill" style="width:<?= $rate ?>%;background:<?= $bc ?>;"></div></div>
            </td>
            <td style="text-align:center;">
              <span style="font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:99px;background:#f0ebfa;color:#582C83;text-transform:uppercase;letter-spacing:.04em;">
                <?= str_replace('_',' ',$ev['status']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3">Total</td>
            <td style="text-align:center;"><?= $totalRegs ?></td>
            <td style="text-align:center;color:#059669;"><?= $totalAttended ?></td>
            <td style="text-align:center;color:#582C83;"><?= $attRate ?>%</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>

    <!-- 3 — Top Students -->
    <?php if (!empty($topStudents)): ?>
    <div class="sec">
      <div class="sec-head">
        <span class="sec-num">3</span>
        <span class="sec-title">Top Participants by Attendance</span>
        <span class="sec-line"></span>
      </div>
      <table class="rtable">
        <thead>
          <tr>
            <th width="44">Rank</th>
            <th>Name</th>
            <th>Matric No.</th>
            <th style="text-align:center;">Events Registered</th>
            <th style="text-align:center;">Events Attended</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topStudents as $i => $st):
            $medals = ['🥇','🥈','🥉'];
            $mc = $i===0?'medal-1':($i===1?'medal-2':($i===2?'medal-3':''));
          ?>
          <tr>
            <td style="text-align:center;">
              <?php if ($i < 3): ?>
                <span class="medal <?= $mc ?>"><?= $medals[$i] ?></span>
              <?php else: ?>
                <span style="font-size:11px;color:#9ca3af;font-weight:700;">#<?= $i+1 ?></span>
              <?php endif; ?>
            </td>
            <td style="font-weight:600;"><?= htmlspecialchars($st['name']) ?></td>
            <td class="mono" style="font-size:11.5px;color:#582C83;"><?= htmlspecialchars($st['matric_no']) ?></td>
            <td style="text-align:center;color:#6b7280;font-weight:600;"><?= $st['total_reg'] ?></td>
            <td style="text-align:center;">
              <span style="background:#f0ebfa;color:#582C83;font-size:11px;font-weight:800;padding:2px 10px;border-radius:99px;"><?= $st['attended'] ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    </div><!-- /body -->

    <!-- Doc footer -->
    <div class="doc-footer">
      <span class="doc-footer-l">UiVent · UiTM Cawangan Kelantan, Kampus Machang · HEP Division</span>
      <span class="doc-footer-r">Generated: <?= $generatedAt ?></span>
    </div>

  </div><!-- /reportPaper -->

</main>
</div>

<?php include 'partials/modals_js.php'; ?>
</body>
</html>