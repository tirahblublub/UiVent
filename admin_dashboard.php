<?php
require_once '../config.php';
requireAdmin();

$activePage = 'admin_dashboard';
$pageTitle  = 'Dashboard';
$admin      = currentClubAdmin();
$adminId    = $_SESSION['admin_id'];

$s = db()->prepare("SELECT COUNT(*) FROM events WHERE created_by=?"); $s->execute([$adminId]); $totalEvents=(int)$s->fetchColumn();
$s = db()->prepare("SELECT COUNT(*) FROM events WHERE created_by=? AND status IN('open','upcoming')"); $s->execute([$adminId]); $activeEvents=(int)$s->fetchColumn();
$s = db()->prepare("SELECT COUNT(*) FROM registrations r JOIN events e ON e.id=r.event_id WHERE e.created_by=? AND r.status='registered'"); $s->execute([$adminId]); $totalRegistrations=(int)$s->fetchColumn();
$s = db()->prepare("SELECT COUNT(*) FROM registrations r JOIN events e ON e.id=r.event_id WHERE e.created_by=? AND r.attended_at IS NOT NULL"); $s->execute([$adminId]); $totalAttended=(int)$s->fetchColumn();
$attRate = $totalRegistrations > 0 ? round($totalAttended/$totalRegistrations*100) : 0;

// Upcoming events
$stmtEv = db()->prepare("
    SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status='registered') AS reg_count
    FROM events e WHERE e.created_by=? AND e.status IN('open','upcoming') AND e.start_date >= NOW()
    ORDER BY e.start_date ASC LIMIT 5
");
$stmtEv->execute([$adminId]);
$upcomingEvents = $stmtEv->fetchAll();

// Recent registrations
$stmtReg = db()->prepare("
    SELECT r.*, s.name AS student_name, s.matric_no, e.title AS event_title
    FROM registrations r
    JOIN students s ON s.id=r.student_id
    JOIN events e ON e.id=r.event_id
    WHERE e.created_by=?
    ORDER BY r.registered_at DESC LIMIT 8
");
$stmtReg->execute([$adminId]);
$recentRegs = $stmtReg->fetchAll();

// Recent events (all)
$stmtAllEv = db()->prepare("
    SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status='registered') AS reg_count
    FROM events e WHERE e.created_by=? ORDER BY e.created_at DESC LIMIT 5
");
$stmtAllEv->execute([$adminId]);
$myEvents = $stmtAllEv->fetchAll();
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
<body class="bg-gray-100 font-sans flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 space-y-6 max-w-7xl w-full mx-auto">

  <!-- Welcome Banner -->
  <div class="relative overflow-hidden rounded-2xl text-white p-6 md:p-8"
       style="background:linear-gradient(135deg,#27134A 0%,#582C83 60%,#7B4DB3 100%);">
    <!-- Decorative circles -->
    <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full opacity-10" style="background:#F9A51B;"></div>
    <div class="absolute -bottom-8 right-20 w-32 h-32 rounded-full opacity-10" style="background:#F9A51B;"></div>

    <div class="relative flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:#F9A51B;">
          <i class="fas fa-shield-halved mr-1"></i>Club Admin Portal
        </p>
        <h3 class="text-2xl font-extrabold tracking-tight">
          Welcome back, <?= htmlspecialchars(explode(' ', $admin['name'] ?? 'Admin')[0]) ?>!
        </h3>
        <p class="text-sm mt-1 text-purple-200">
          <?= htmlspecialchars($admin['role'] ?? 'Club Admin') ?>
          · Attendance rate this period: <span class="font-bold" style="color:#F9A51B;"><?= $attRate ?>%</span>
        </p>
      </div>
      <div class="flex gap-2 flex-wrap shrink-0">
        <a href="create_event.php"
           class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition-all"
           style="background:#F9A51B;color:#27134A;">
          <i class="fas fa-plus"></i> Create Event
        </a>
        <a href="attendance.php"
           class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all"
           style="background:rgba(255,255,255,.12);color:white;border:1px solid rgba(255,255,255,.25);">
          <i class="fas fa-qrcode"></i> Scan QR
        </a>
      </div>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php $cards = [
      ['label'=>'Total Events',    'val'=>$totalEvents,        'sub'=>"$activeEvents active",        'icon'=>'fa-calendar-check',  'color'=>'#582C83','bg'=>'#f0ebfa'],
      ['label'=>'Registrations',   'val'=>$totalRegistrations, 'sub'=>'across all events',            'icon'=>'fa-users',           'color'=>'#0284c7','bg'=>'#e0f2fe'],
      ['label'=>'Attended',        'val'=>$totalAttended,      'sub'=>'confirmed attendance',         'icon'=>'fa-clipboard-check', 'color'=>'#059669','bg'=>'#d1fae5'],
      ['label'=>'Attendance Rate', 'val'=>$attRate.'%',        'sub'=>'registered vs attended',       'icon'=>'fa-chart-pie',       'color'=>'#d97706','bg'=>'#fef3c7'],
    ];
    foreach ($cards as $c): ?>
    <div class="stat-card bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400"><?= $c['label'] ?></p>
        <h4 class="text-2xl font-extrabold text-gray-900 mt-1"><?= $c['val'] ?></h4>
        <p class="text-xs font-medium mt-0.5 text-gray-400"><?= $c['sub'] ?></p>
      </div>
      <div class="p-3.5 rounded-xl" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;">
        <i class="fas <?= $c['icon'] ?> text-xl"></i>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Two-column -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Upcoming Events -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-800 text-sm flex items-center gap-2">
          <i class="fas fa-calendar-days text-xs" style="color:#582C83;"></i> Upcoming Events
        </h2>
        <a href="my_events.php" class="text-xs font-semibold hover:underline" style="color:#582C83;">View all →</a>
      </div>
      <div class="scrollable divide-y divide-gray-50">
        <?php if (empty($upcomingEvents)): ?>
          <div class="px-6 py-10 text-center text-gray-400">
            <i class="fas fa-calendar-xmark text-3xl mb-3 block" style="color:#ddd5f5;"></i>
            No upcoming events.
            <a href="create_event.php" class="block mt-2 text-sm font-semibold" style="color:#582C83;">Create one →</a>
          </div>
        <?php else: foreach ($upcomingEvents as $ev): ?>
          <div class="px-6 py-3.5 flex items-center gap-3 hover:bg-purple-50 transition-colors">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 text-white text-xs font-bold"
                 style="background:#582C83;">
              <?= strtoupper(substr($ev['title'],0,2)) ?>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($ev['title']) ?></p>
              <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-1">
                <i class="fas fa-calendar text-xs"></i>
                <?= $ev['start_date'] ? date('d M Y, H:i', strtotime($ev['start_date'])) : 'TBD' ?>
                · <i class="fas fa-users text-xs"></i> <?= $ev['reg_count'] ?>
              </p>
            </div>
            <span class="badge status-<?= $ev['status'] ?>"><?= str_replace('_',' ',$ev['status']) ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Recent Registrations -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-800 text-sm flex items-center gap-2">
          <i class="fas fa-user-plus text-xs" style="color:#582C83;"></i> Recent Registrations
        </h2>
        <a href="registrations.php" class="text-xs font-semibold hover:underline" style="color:#582C83;">View all →</a>
      </div>
      <div class="scrollable divide-y divide-gray-50">
        <?php if (empty($recentRegs)): ?>
          <div class="px-6 py-10 text-center text-gray-400">
            <i class="fas fa-users-slash text-3xl mb-3 block" style="color:#ddd5f5;"></i>
            No registrations yet.
          </div>
        <?php else: foreach ($recentRegs as $reg): ?>
          <div class="px-6 py-3 flex items-center gap-3 hover:bg-purple-50 transition-colors">
            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 font-bold text-xs"
                 style="background:#f0ebfa;color:#582C83;">
              <?= strtoupper(substr($reg['student_name'],0,1)) ?>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($reg['student_name']) ?></p>
              <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($reg['matric_no']) ?> · <?= htmlspecialchars($reg['event_title']) ?></p>
            </div>
            <span class="text-xs text-gray-400 shrink-0"><?= date('d M', strtotime($reg['registered_at'])) ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="font-bold text-gray-800 text-sm mb-4 flex items-center gap-2">
      <i class="fas fa-bolt text-xs" style="color:#F9A51B;"></i> Quick Actions
    </h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
      <?php $actions = [
        ['href'=>'create_event.php',   'icon'=>'fa-plus-circle',    'color'=>'#582C83','bg'=>'#f0ebfa','label'=>'New Event'],
        ['href'=>'attendance.php',     'icon'=>'fa-qrcode',         'color'=>'#0284c7','bg'=>'#e0f2fe','label'=>'Scan QR'],
        ['href'=>'registrations.php',  'icon'=>'fa-list-check',     'color'=>'#059669','bg'=>'#d1fae5','label'=>'Registrations'],
        ['href'=>'members.php',        'icon'=>'fa-users',          'color'=>'#7c3aed','bg'=>'#ede9fe','label'=>'Members'],
        ['href'=>'certificates.php',   'icon'=>'fa-certificate',    'color'=>'#d97706','bg'=>'#fef3c7','label'=>'Certificates'],
        ['href'=>'announcements.php',  'icon'=>'fa-bullhorn',       'color'=>'#dc2626','bg'=>'#fee2e2','label'=>'Announce'],
      ];
      foreach ($actions as $a): ?>
      <a href="<?= $a['href'] ?>"
         class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-purple-200 hover:shadow-md transition-all text-center group">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center transition-transform group-hover:scale-110"
             style="background:<?= $a['bg'] ?>;color:<?= $a['color'] ?>;">
          <i class="fas <?= $a['icon'] ?> text-lg"></i>
        </div>
        <span class="text-xs font-semibold text-gray-600 group-hover:text-gray-900"><?= $a['label'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- My Events (recent) -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 class="font-bold text-gray-800 text-sm flex items-center gap-2">
        <i class="fas fa-calendar-alt text-xs" style="color:#582C83;"></i> Recent Events
      </h2>
      <a href="my_events.php" class="text-xs font-semibold hover:underline" style="color:#582C83;">View all →</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Event</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Registered</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($myEvents)): ?>
          <tr><td colspan="5" class="px-6 py-10 text-center text-gray-400">
            No events yet. <a href="create_event.php" class="font-semibold" style="color:#582C83;">Create one →</a>
          </td></tr>
          <?php else: foreach ($myEvents as $ev): ?>
          <tr class="hover-row">
            <td class="px-6 py-4">
              <p class="font-semibold text-gray-800"><?= htmlspecialchars($ev['title']) ?></p>
              <p class="text-xs text-gray-400 mt-0.5"><i class="fas fa-location-dot mr-1"></i><?= htmlspecialchars($ev['venue'] ?? 'TBD') ?></p>
            </td>
            <td class="px-4 py-4 text-gray-500 text-xs whitespace-nowrap">
              <?= $ev['start_date'] ? date('d M Y', strtotime($ev['start_date'])) : '—' ?>
            </td>
            <td class="px-4 py-4 text-center">
              <span class="font-semibold text-gray-800"><?= $ev['reg_count'] ?></span>
              <span class="text-gray-400 text-xs"> / <?= $ev['capacity'] ?></span>
            </td>
            <td class="px-4 py-4 text-center">
              <span class="badge status-<?= $ev['status'] ?>"><?= str_replace('_',' ',$ev['status']) ?></span>
            </td>
            <td class="px-4 py-4 text-center">
              <a href="attendance.php?event_id=<?= $ev['id'] ?>" class="btn-secondary text-xs">
                <i class="fas fa-clipboard-user"></i> Attendance
              </a>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</div>
<?php include 'partials/modals_js.php'; ?>
</body>
</html>