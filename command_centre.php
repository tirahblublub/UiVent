<?php
require_once '../config.php';
requireSuperAdmin();

$activePage = 'command_centre';
$pageTitle  = 'Dashboard';

// ── Stats ─────────────────────────────────────────────────────────
$totalClubs       = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='active'")->fetchColumn();
$pendingClubs     = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='pending'")->fetchColumn();
$totalAdmins      = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status IN('active','pending')")->fetchColumn();
$totalEvents      = (int) db()->query("SELECT COUNT(*) FROM events WHERE status IN('open','upcoming')")->fetchColumn();
$totalMembers     = (int) db()->query("SELECT COUNT(*) FROM students WHERE is_active=1")->fetchColumn();
$activeSessions   = (int) db()->query("SELECT COUNT(*) FROM active_sessions")->fetchColumn();

// ── Recent clubs ──────────────────────────────────────────────────
$clubs = db()->query("
    SELECT a.name, a.email, a.role, a.status, a.last_active
    FROM admins a
    ORDER BY a.status ASC, a.last_active DESC LIMIT 8
")->fetchAll();

// ── Upcoming events ───────────────────────────────────────────────
$events = db()->query("
    SELECT e.title, e.start_date, e.status, a.name AS organiser_name
    FROM events e LEFT JOIN admins a ON a.id=e.created_by
    WHERE e.status IN('open','upcoming')
    ORDER BY e.start_date ASC LIMIT 6
")->fetchAll();

// ── Config ────────────────────────────────────────────────────────
$cfg = [];
foreach (db()->query('SELECT config_key,config_value FROM global_config')->fetchAll() as $r) {
    $cfg[$r['config_key']] = $r['config_value'];
}

// ── Notifications ─────────────────────────────────────────────────
$notifications = [];

// Clubs pending approval
$pendingClubCount = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='pending'")->fetchColumn();
if ($pendingClubCount > 0) {
    $notifications[] = [
        'type'  => 'warning',
        'icon'  => 'fa-building-circle-exclamation',
        'msg'   => "$pendingClubCount club" . ($pendingClubCount !== 1 ? 's' : '') . " pending approval",
        'link'  => 'admin_management.php?filter=pending',
        'label' => 'Review',
    ];
}

// Events awaiting approval (status = 'pending')
$pendingEventCount = (int) db()->query("SELECT COUNT(*) FROM events WHERE status='pending'")->fetchColumn();
if ($pendingEventCount > 0) {
    $notifications[] = [
        'type'  => 'info',
        'icon'  => 'fa-calendar-circle-exclamation',
        'msg'   => "$pendingEventCount event" . ($pendingEventCount !== 1 ? 's' : '') . " awaiting approval",
        'link'  => 'global_events.php?filter=pending',
        'label' => 'Review',
    ];
}

// New club admins registered in last 7 days
$newAdminCount = (int) db()->query("
    SELECT COUNT(*) FROM admins
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND status IN('active','pending')
")->fetchColumn();
if ($newAdminCount > 0) {
    $notifications[] = [
        'type'  => 'success',
        'icon'  => 'fa-user-plus',
        'msg'   => "$newAdminCount new club admin" . ($newAdminCount !== 1 ? 's' : '') . " registered this week",
        'link'  => 'admin_management.php?filter=new',
        'label' => 'View',
    ];
}

// Fallback – all clear
if (empty($notifications)) {
    $notifications[] = [
        'type'  => 'clear',
        'icon'  => 'fa-circle-check',
        'msg'   => 'No pending actions — everything is up to date.',
        'link'  => null,
        'label' => null,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<div class="flex-1">
<main class="p-6 md:p-8 space-y-6 max-w-7xl w-full mx-auto">

  <!-- Welcome Banner -->
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center p-6 rounded-2xl text-white gap-4"
       style="background:linear-gradient(135deg,#27134A 0%,#582C83 100%);">
    <div>
      <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:#F9A51B;">UiVent Operator Portal</p>
      <h3 class="text-2xl font-bold tracking-tight">
        Welcome back, <?= htmlspecialchars(explode(' ', currentAdmin()['name'])[0]) ?>!
      </h3>
      <p class="text-sm mt-1 text-purple-200">
        <?= number_format($totalClubs) ?> active club<?= $totalClubs!==1?'s':'' ?> · <?= number_format($totalMembers) ?> registered members
      </p>
    </div>
    <div class="flex flex-col gap-2">
      <div class="px-4 py-2 rounded-xl flex items-center gap-2"
           style="background:rgba(249,165,27,.15);border:1px solid rgba(249,165,27,.30);color:#F9A51B;">
        <i class="far fa-clock text-xs"></i>
        <span class="text-xs font-medium"><?= htmlspecialchars($cfg['academic_term_label'] ?? 'Current Semester') ?></span>
      </div>
      <div class="px-3 py-2 rounded-xl flex items-center gap-2"
           style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:#c4b5e8;">
        <span class="w-2 h-2 bg-emerald-400 rounded-full"></span>
        <span class="text-xs">All systems operational</span>
      </div>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">

    <!-- Total Clubs -->
    <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Clubs</p>
        <h4 class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($totalClubs + $pendingClubs) ?></h4>
        <p class="text-xs mt-0.5 <?= $pendingClubs > 0 ? 'text-amber-600' : 'text-emerald-600' ?> font-medium">
          <?= $pendingClubs > 0 ? "$pendingClubs pending setup" : 'All verified' ?>
        </p>
      </div>
      <div class="p-3.5 rounded-lg" style="background:#f0ebfa;color:#582C83;">
        <i class="fas fa-building text-xl"></i>
      </div>
    </div>

    <!-- Total Club Admins -->
    <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Club Admins</p>
        <h4 class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($totalAdmins) ?></h4>
        <p class="text-xs mt-0.5 font-medium" style="color:#582C83;">registered accounts</p>
      </div>
      <div class="p-3.5 rounded-lg" style="background:#f0ebfa;color:#582C83;">
        <i class="fas fa-user-shield text-xl"></i>
      </div>
    </div>

    <!-- Total Students -->
    <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Students</p>
        <h4 class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($totalMembers) ?></h4>
        <p class="text-xs text-gray-400 font-medium mt-0.5">across all clubs</p>
      </div>
      <div class="bg-amber-50 p-3.5 rounded-lg text-amber-600">
        <i class="fas fa-user-graduate text-xl"></i>
      </div>
    </div>

    <!-- Total Events -->
    <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Events</p>
        <h4 class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($totalEvents) ?></h4>
        <p class="text-xs font-medium mt-0.5" style="color:#582C83;">open + upcoming</p>
      </div>
      <div class="p-3.5 rounded-lg" style="background:#f0ebfa;color:#582C83;">
        <i class="fas fa-calendar-check text-xl"></i>
      </div>
    </div>

    <!-- Active Clubs -->
    <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Active Clubs</p>
        <h4 class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($totalClubs) ?></h4>
        <p class="text-xs mt-0.5 text-emerald-600 font-medium">
          <?= $pendingClubs > 0 ? "$pendingClubs pending" : 'All verified' ?>
        </p>
      </div>
      <div class="bg-emerald-50 p-3.5 rounded-lg text-emerald-600">
        <i class="fas fa-circle-check text-xl"></i>
      </div>
    </div>

  </div>

  <!-- Notifications + Quick Actions Row -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- 🔔 Notifications -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="p-5 border-b border-gray-100 flex items-center gap-2">
        <span class="relative flex">
          <i class="fas fa-bell text-base" style="color:#582C83;"></i>
          <?php if (count($notifications) > 0 && $notifications[0]['type'] !== 'clear'): ?>
          <span class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-amber-400 ring-2 ring-white"></span>
          <?php endif; ?>
        </span>
        <h4 class="font-bold text-base text-gray-900">Notifications</h4>
        <span class="ml-auto text-xs text-gray-400"><?= count($notifications) === 1 && $notifications[0]['type'] === 'clear' ? 'All clear' : count($notifications) . ' alert' . (count($notifications) !== 1 ? 's' : '') ?></span>
      </div>
      <ul class="divide-y divide-gray-100">
        <?php
        $typeMeta = [
          'warning' => ['bg' => 'bg-amber-50',   'icon_color' => 'text-amber-500',  'badge' => 'bg-amber-50 text-amber-700'],
          'info'    => ['bg' => 'bg-blue-50',     'icon_color' => 'text-blue-500',   'badge' => 'bg-blue-50 text-blue-700'],
          'success' => ['bg' => 'bg-emerald-50',  'icon_color' => 'text-emerald-500','badge' => 'bg-emerald-50 text-emerald-700'],
          'clear'   => ['bg' => 'bg-gray-50',     'icon_color' => 'text-gray-400',   'badge' => ''],
        ];
        foreach ($notifications as $n):
          $m = $typeMeta[$n['type']] ?? $typeMeta['clear'];
        ?>
        <li class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50 transition-colors">
          <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center <?= $m['bg'] ?>">
            <i class="fas <?= $n['icon'] ?> text-sm <?= $m['icon_color'] ?>"></i>
          </div>
          <p class="flex-1 text-sm text-gray-700 leading-snug"><?= htmlspecialchars($n['msg']) ?></p>
          <?php if ($n['link']): ?>
          <a href="<?= htmlspecialchars($n['link']) ?>"
             class="flex-shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg <?= $m['badge'] ?> hover:opacity-80 transition-opacity whitespace-nowrap">
            <?= htmlspecialchars($n['label']) ?> →
          </a>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- ⚡ Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="p-5 border-b border-gray-100 flex items-center gap-2">
        <i class="fas fa-bolt text-base" style="color:#F9A51B;"></i>
        <h4 class="font-bold text-base text-gray-900">Quick Actions</h4>
      </div>
      <div class="p-5 grid grid-cols-2 gap-3">

        <!-- Approve Club -->
        <a href="admin_management.php?filter=pending"
           class="group flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 hover:border-purple-200 hover:bg-purple-50 transition-all text-center">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center transition-colors"
               style="background:#f0ebfa;color:#582C83;">
            <i class="fas fa-building-circle-check text-xl group-hover:scale-110 transition-transform"></i>
          </div>
          <div>
            <p class="text-xs font-bold text-gray-800 leading-tight">Approve Club</p>
            <?php if ($pendingClubCount > 0): ?>
            <p class="text-xs text-amber-600 font-medium mt-0.5"><?= $pendingClubCount ?> waiting</p>
            <?php else: ?>
            <p class="text-xs text-gray-400 mt-0.5">None pending</p>
            <?php endif; ?>
          </div>
        </a>

        <!-- Approve Event -->
        <a href="global_events.php?filter=pending"
           class="group flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50 transition-all text-center">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center"
               style="background:#eff6ff;color:#3b82f6;">
            <i class="fas fa-calendar-check text-xl group-hover:scale-110 transition-transform"></i>
          </div>
          <div>
            <p class="text-xs font-bold text-gray-800 leading-tight">Approve Event</p>
            <?php if ($pendingEventCount > 0): ?>
            <p class="text-xs text-blue-600 font-medium mt-0.5"><?= $pendingEventCount ?> waiting</p>
            <?php else: ?>
            <p class="text-xs text-gray-400 mt-0.5">None pending</p>
            <?php endif; ?>
          </div>
        </a>

        <!-- Generate Report -->
        <div class="group flex flex-col items-center gap-2.5 p-4 rounded-xl border border-gray-100 hover:border-emerald-200 hover:bg-emerald-50 transition-all text-center cursor-pointer"
             onclick="openReportModal()">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center"
               style="background:#ecfdf5;color:#10b981;">
            <i class="fas fa-chart-bar text-xl group-hover:scale-110 transition-transform"></i>
          </div>
          <div>
            <p class="text-xs font-bold text-gray-800 leading-tight">Generate Report</p>
            <p class="text-xs text-gray-400 mt-0.5">PDF or Excel</p>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Report Modal -->
  <div id="reportModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4"
       style="background:rgba(0,0,0,0.45);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-5">
      <div class="flex items-center justify-between">
        <h5 class="font-bold text-lg text-gray-900 flex items-center gap-2">
          <i class="fas fa-chart-bar text-purple-600"></i> Generate Report
        </h5>
        <button onclick="closeReportModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
      </div>

      <p class="text-sm text-gray-500">Configure a custom report with date filters and report type before exporting.</p>

      <!-- Quick-export shortcuts -->
      <div class="grid grid-cols-2 gap-3">
        <a href="/UiVent/superadmin/reports/export.php?type=pdf&report_type=overview&date_from=<?= date('Y-01-01') ?>&date_to=<?= date('Y-m-d') ?>"
           target="_blank"
           class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-red-100 hover:bg-red-50 hover:border-red-300 transition-all text-center">
          <i class="fas fa-file-pdf text-2xl text-red-500"></i>
          <span class="text-xs font-bold text-gray-700">Quick PDF</span>
          <span class="text-xs text-gray-400">This year · All data</span>
        </a>
        <a href="/UiVent/superadmin/reports/export.php?type=excel&report_type=overview&date_from=<?= date('Y-01-01') ?>&date_to=<?= date('Y-m-d') ?>"
           class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-emerald-100 hover:bg-emerald-50 hover:border-emerald-300 transition-all text-center">
          <i class="fas fa-file-excel text-2xl text-emerald-500"></i>
          <span class="text-xs font-bold text-gray-700">Quick CSV</span>
          <span class="text-xs text-gray-400">This year · All data</span>
        </a>
      </div>

      <div class="relative">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
        <div class="relative flex justify-center">
          <span class="bg-white px-3 text-xs text-gray-400 font-semibold uppercase tracking-widest">or</span>
        </div>
      </div>

      <!-- Go to Analytics for custom report -->
      <a href="/UiVent/superadmin/analytics.php"
         class="flex items-center justify-center gap-2 w-full py-3 px-4 rounded-xl font-bold text-sm text-white transition-all hover:opacity-90"
         style="background:linear-gradient(135deg,#27134A,#582C83);">
        <i class="fas fa-sliders"></i> Custom Report (Analytics) →
      </a>

      <div class="flex justify-end">
        <button onclick="closeReportModal()"
                class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">Close</button>
      </div>
    </div>
  </div>

  <!-- Club Table (full width) -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
      <h4 class="font-bold text-base text-gray-900">Club Accounts</h4>
      <a href="admin_management.php" class="btn-secondary">Manage all →</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
            <th class="py-3 px-5">Club</th>
            <th class="py-3 px-5">Type</th>
            <th class="py-3 px-5">Last Active</th>
            <th class="py-3 px-5">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php
        $statusMap = ['active'=>'bg-emerald-50 text-emerald-700','pending'=>'bg-amber-50 text-amber-700','suspended'=>'bg-red-50 text-red-700'];
        foreach ($clubs as $club):
          $sc = $statusMap[$club['status']] ?? 'bg-gray-100 text-gray-500';
        ?>
        <tr class="hover-row">
          <td class="py-3 px-5">
            <p class="font-semibold text-gray-900"><?= htmlspecialchars($club['name'] ?? '—') ?></p>
            <p class="text-xs text-gray-400"><?= htmlspecialchars($club['email']) ?></p>
          </td>
          <td class="py-3 px-5 text-xs text-gray-500"><?= htmlspecialchars($club['role'] ?? '—') ?></td>
          <td class="py-3 px-5 text-xs text-gray-400">
            <?= $club['last_active'] ? date('d M Y', strtotime($club['last_active'])) : 'Never' ?>
          </td>
          <td class="py-3 px-5"><span class="badge <?= $sc ?>"><?= ucfirst($club['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Upcoming Events -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
      <h4 class="font-bold text-base text-gray-900">Upcoming Events</h4>
      <a href="global_events.php" class="btn-secondary">View all →</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
            <th class="py-3 px-5">Event</th>
            <th class="py-3 px-5">Club</th>
            <th class="py-3 px-5">Date</th>
            <th class="py-3 px-5">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($events as $ev):
          $isOpen = $ev['status']==='open';
          $bc = $isOpen?'bg-emerald-50 text-emerald-700':'bg-blue-50 text-blue-700';
        ?>
        <tr class="hover-row">
          <td class="py-3 px-5 font-medium text-gray-900"><?= htmlspecialchars($ev['title']) ?></td>
          <td class="py-3 px-5 text-xs text-gray-500"><?= htmlspecialchars($ev['organiser_name'] ?? '—') ?></td>
          <td class="py-3 px-5 text-xs text-gray-600"><?= $ev['start_date'] ? date('d M Y', strtotime($ev['start_date'])) : '—' ?></td>
          <td class="py-3 px-5"><span class="badge <?= $bc ?>"><?= $isOpen?'Open':'Upcoming' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</div>
</div>
<?php include 'partials/modals_js.php'; ?>
<script>
// ── Report Modal ───────────────────────────────────────────────────
function openReportModal() {
  const m = document.getElementById('reportModal');
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function closeReportModal() {
  const m = document.getElementById('reportModal');
  m.classList.add('hidden');
  m.classList.remove('flex');
}
document.getElementById('reportModal').addEventListener('click', function(e){
  if (e.target === this) closeReportModal();
});
</script>
</body>
</html>