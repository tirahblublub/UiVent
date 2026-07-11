<?php
require_once __DIR__ . '/../config.php';
requireStudent();

$sid     = (int) $_SESSION['student_id'];
$student = $_SESSION['student'] ?? [];
$sName   = htmlspecialchars($student['name'] ?? 'Student');
$sFirst  = htmlspecialchars(explode(' ', $student['name'] ?? 'Student')[0]);

// ── Stats ────────────────────────────────────────────────────
$totalRegistered = 0; $totalAttended = 0; $totalCerts = 0; $unreadAnnouncements = 0;
$upcomingEvents = []; $latestAnnouncements = []; $recommendedEvents = [];

try {
    $pdo = db();

    $s = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE student_id=? AND status='registered'");
    $s->execute([$sid]); $totalRegistered = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE student_id=? AND attended_at IS NOT NULL");
    $s->execute([$sid]); $totalAttended = (int)$s->fetchColumn();

    $totalAllRegs = $totalRegistered + $totalAttended;
    $attendanceRate = $totalAllRegs > 0 ? round($totalAttended / $totalAllRegs * 100) : 0;

    $s = $pdo->prepare("SELECT COUNT(*) FROM registrations r JOIN events e ON e.id=r.event_id WHERE r.student_id=? AND r.attended_at IS NOT NULL AND e.end_date < NOW()");
    $s->execute([$sid]); $totalCerts = (int)$s->fetchColumn();

    // Unread announcements
    $s = $pdo->prepare("
        SELECT COUNT(*) FROM club_announcements a
        WHERE a.status='sent'
          AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id=?)
    ");
    $s->execute([$sid]); $unreadAnnouncements = (int)$s->fetchColumn();

    // All upcoming open events with this student's registration status
    $s = $pdo->prepare("
        SELECT e.id, e.title, e.venue, e.category, e.status,
               e.capacity, e.registered_count AS registered,
               COALESCE(e.registration_fee, 0) AS registration_fee,
               DATE_FORMAT(e.start_date,'%d %b %Y') AS event_date,
               DATE_FORMAT(e.start_date,'%H:%i') AS event_time,
               DATE_FORMAT(e.start_date,'%b') AS month_short,
               DAY(e.start_date) AS day_num,
               CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END AS already_registered,
               r.id AS reg_id,
               COALESCE(pt.payment_status, '') AS payment_status,
               pt.id AS txn_id
        FROM events e
        LEFT JOIN registrations r
               ON r.event_id = e.id AND r.student_id = ? AND r.status = 'registered'
        LEFT JOIN payment_transactions pt
               ON pt.registration_id = r.id AND pt.student_id = ?
              AND pt.payment_status != 'Failed'
        WHERE e.status IN ('open','upcoming')
          AND e.start_date >= NOW()
        ORDER BY e.start_date ASC
        LIMIT 6
    ");
    $s->execute([$sid, $sid]);
    $upcomingEvents = $s->fetchAll();

    // Latest sent announcements (3)
    $s = $pdo->query("
        SELECT a.id, a.title, a.body, a.sent_at,
               TIMESTAMPDIFF(HOUR, a.sent_at, NOW()) AS hours_ago
        FROM club_announcements a
        WHERE a.status='sent'
        ORDER BY a.sent_at DESC LIMIT 3
    ");
    $latestAnnouncements = $s->fetchAll();

    // Recommended: open events student hasn't registered for (3)
    $s = $pdo->prepare("
        SELECT e.id, e.title, e.category, e.venue, e.capacity,
               e.registered_count AS registered,
               COALESCE(ROUND(e.registered_count/NULLIF(e.capacity,0)*100),0) AS pct,
               DATE_FORMAT(e.start_date,'%d %b %Y') AS event_date,
               e.description, e.registration_fee
        FROM events e
        WHERE e.status IN ('open','upcoming')
          AND e.start_date >= NOW()
          AND e.id NOT IN (
              SELECT event_id FROM registrations WHERE student_id=? AND status='registered'
          )
        ORDER BY e.start_date ASC LIMIT 3
    ");
    $s->execute([$sid]); $recommendedEvents = $s->fetchAll();

} catch (Throwable $e) { /* silently fall through to demo display */ }

// Category colour helper
function catBadge(string $cat): string {
    return match($cat) {
        'Academic'  => 'bg-blue-100 text-blue-800',
        'Cultural'  => 'bg-amber-100 text-amber-800',
        'Sports'    => 'bg-green-100 text-green-800',
        default     => 'bg-purple-100 text-purple-800',
    };
}
function calColor(string $cat): array {
    return match($cat) {
        'Academic'  => ['bg-blue-100','text-blue-700','text-blue-900'],
        'Cultural'  => ['bg-amber-100','text-amber-700','text-amber-900'],
        'Sports'    => ['bg-green-100','text-green-700','text-green-900'],
        default     => ['bg-rose-100','text-rose-700','text-rose-900'],
    };
}
$month = date('F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Welcome to UiVent</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ---- shared.css ---- */

/* ============================================================
   SHARED / GLOBAL STYLES
   Rules used by more than one section — keep these here so every
   section doesn't have to duplicate them. Section-only rules live
   in that section's own css file instead.
   ============================================================ */

/* View switching (used by every section) */
.view-section { display: none; }
.view-section.active { display: block; animation: fadeIn 0.25s ease-out; }
@keyframes fadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:translateY(0); } }

/* Event drawer (opened from Home + Browse Events) */
#eventDrawer > div { transform: translateX(100%); transition: transform .3s cubic-bezier(.4,0,.2,1); }
#eventDrawer:not(.hidden) > div { transform: translateX(0); }
.progress-bar-fill { transition: width 1.1s cubic-bezier(.4,0,.2,1); }

/* Tab buttons (used by Browse Events + My Registrations) */
.tab-btn.active { background:#581c87; color:#fff; }
.tab-btn { transition: all .18s; }

/* Event cards (used by Home + Browse Events) */
.event-card { transition: transform .2s, box-shadow .2s; }
.event-card:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(80,0,120,0.12); }


/* ---- sidebar.css ---- */

/* ============================================================
   SIDEBAR COMPONENT STYLES
   ============================================================ */

.sidebar-nav-btn {
  transition: all 0.18s;
}


/* ---- home.css ---- */

/* ============================================================
   HOME — section-only styles
   Shared .event-card rules live in css/shared.css.
   ============================================================ */

.stat-card {
  transition: transform .15s, box-shadow .15s;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(80,0,120,0.10);
}

</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">

<!-- ═══════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════ -->
<aside class="w-64 bg-purple-950 text-white flex flex-col hidden md:flex shrink-0 shadow-xl">
  <div class="overflow-y-auto flex-1 min-h-0">
    <!-- Logo -->
    <div class="h-16 flex items-center px-6 border-b border-purple-900 bg-purple-900/40 sticky top-0">
      <div class="bg-amber-500 text-purple-950 px-2.5 py-1 rounded-md font-extrabold text-lg tracking-wider mr-2">Ui</div>
      <span class="font-bold text-xl tracking-wide">Vent</span>
      <span class="text-xs bg-purple-800 text-purple-200 ml-2 px-1.5 py-0.5 rounded uppercase">Student</span>
    </div>

    <!-- Nav -->
    <nav class="mt-6 px-4 space-y-1 pb-4">
      <a href="home.php" class="sidebar-nav-btn w-full flex items-center space-x-3 bg-amber-500 text-purple-950 font-semibold px-4 py-3 rounded-lg shadow-sm text-left">
        <i class="fas fa-home text-lg w-5"></i><span>Home</span>
      </a>
      <a href="events.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-calendar-alt text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Browse Events</span>
      </a>
      <a href="mybookings.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-ticket-alt text-lg w-5 text-purple-400 group-hover:text-white"></i><span>My Registrations</span><span class="ml-auto bg-amber-500 text-purple-950 text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center">3</span>
      </a>
      <a href="attendance.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-chart-bar text-lg w-5 text-purple-400 group-hover:text-white"></i><span>My Attendance</span>
      </a>
      <a href="announcements.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-bullhorn text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Announcements</span><span class="ml-auto bg-red-500 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center">2</span>
      </a>
      <a href="profile.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-user text-lg w-5 text-purple-400 group-hover:text-white"></i><span>My Profile</span>
      </a>

      <div class="mt-4 mb-1 px-1">
        <p class="text-xs font-bold uppercase tracking-widest text-purple-600">More</p>
      </div>

      <a href="merchandise.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-tshirt text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Merchandise</span>
      </a>
      <a href="payments.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-credit-card text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Payments</span>
      </a>
      <a href="feedback.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-comment-dots text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Feedback</span>
      </a>

      <div class="mt-2 pt-2 border-t border-purple-900">
        <a href="logout.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-300 hover:bg-red-900/40 hover:text-red-300 px-4 py-3 rounded-lg text-left group">
          <i class="fas fa-sign-out-alt text-lg w-5 text-purple-500 group-hover:text-red-300"></i><span>Logout</span>
        </a>
      </div>
    </nav>
  </div>
</aside>

<!-- ═══════════════════════════════════════════
     MAIN AREA
═══════════════════════════════════════════ -->
<div class="flex-1 flex flex-col h-full overflow-y-auto">

  <!-- TOPBAR -->
  <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 md:px-8 shrink-0 sticky top-0 z-10 shadow-sm">
    <div class="flex items-center space-x-4">
      <button class="text-gray-500 hover:text-gray-700 md:hidden block"><i class="fas fa-bars text-xl"></i></button>
      <h2 class="text-xl font-bold text-gray-800" id="headerTitle">Welcome to UiVent</h2>
    </div>
    <div class="flex items-center space-x-4">
      <!-- Notification Bell -->
      <a href="announcements.php" class="p-2 text-gray-400 hover:text-purple-900 transition-colors relative inline-block">
        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
        <i class="far fa-bell text-lg"></i>
      </a>
      <div class="h-6 w-px bg-gray-200"></div>
      <a href="profile.php" class="flex items-center space-x-3 cursor-pointer">
        <div class="text-right hidden md:block">
          <p class="text-sm font-semibold text-gray-800"><?= $sName ?></p>
          <p class="text-xs text-gray-500">Information Science Faculty &middot; Year 3</p>
        </div>
        <img class="w-9 h-9 rounded-full ring-2 ring-purple-100 object-cover" src="images/passport.jpg" alt="User">
      </a>
    </div>
  </header>

  <div class="flex-1">

<main id="home-view" class="view-section active active p-6 md:p-8 space-y-8 max-w-7xl w-full mx-auto">

      <!-- Welcome Banner -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center bg-gradient-to-r from-purple-900 to-purple-950 p-6 rounded-2xl shadow-md text-white gap-4">
        <div>
          <p class="text-purple-300 text-sm font-medium mb-1">Selamat Datang!</p>
          <h3 class="text-2xl font-bold tracking-tight"><?= $sName ?></h3>
          <p class="text-purple-200 text-sm mt-1">You have <strong class="text-amber-400"><?= $totalRegistered ?> upcoming event<?= $totalRegistered != 1 ? 's' : '' ?></strong> and <strong class="text-amber-400"><?= $unreadAnnouncements ?> unread announcement<?= $unreadAnnouncements != 1 ? 's' : '' ?></strong>.</p>
        </div>
        <div class="bg-purple-900/50 border border-purple-800 px-4 py-2 rounded-xl flex items-center space-x-2 text-sm text-amber-400 font-medium">
          <i class="far fa-clock"></i><span><?= $month ?> Academic Term</span>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
          <div class="space-y-1">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Registered Events</p>
            <h4 class="text-2xl font-bold text-gray-900"><?= $totalRegistered ?></h4>
          </div>
          <div class="bg-purple-50 p-3.5 rounded-lg text-purple-900"><i class="fas fa-ticket-alt text-xl"></i></div>
        </div>
        <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
          <div class="space-y-1">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Events Attended</p>
            <h4 class="text-2xl font-bold text-gray-900"><?= $totalAttended ?></h4>
          </div>
          <div class="bg-emerald-50 p-3.5 rounded-lg text-emerald-600"><i class="fas fa-check-circle text-xl"></i></div>
        </div>
        <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
          <div class="space-y-1">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Attendance Rate</p>
            <h4 class="text-2xl font-bold text-amber-600"><?= $attendanceRate ?>%</h4>
          </div>
          <div class="bg-amber-50 p-3.5 rounded-lg text-amber-600"><i class="fas fa-chart-line text-xl"></i></div>
        </div>
        <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
          <div class="space-y-1">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Certificates Earned</p>
            <h4 class="text-2xl font-bold text-gray-900"><?= $totalCerts ?></h4>
          </div>
          <div class="bg-blue-50 p-3.5 rounded-lg text-blue-600"><i class="fas fa-award text-xl"></i></div>
        </div>
      </div>

      <!-- Upcoming Events + Announcements -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <!-- Upcoming Events -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <h4 class="font-bold text-lg text-gray-900">Upcoming Events</h4>
            <a href="events.php" class="text-xs font-semibold text-purple-900 bg-purple-50 px-3 py-1.5 rounded-md hover:bg-purple-100 transition-colors">Browse All</a>
          </div>
          <div class="divide-y divide-gray-100">
            <?php if (empty($upcomingEvents)): ?>
            <div class="p-8 text-center text-gray-400">
              <i class="fas fa-calendar-xmark text-3xl mb-2"></i>
              <p class="text-sm font-medium">No upcoming events right now.</p>
              <a href="events.php" class="text-xs text-purple-700 mt-1 inline-block hover:underline">Browse events →</a>
            </div>
            <?php else: foreach ($upcomingEvents as $ev):
              [$bg, $tc, $tn] = calColor($ev['category']);
              $isRegistered = (bool)$ev['already_registered'];
              $isFull       = $ev['registered'] >= $ev['capacity'];
              $fee          = (float)$ev['registration_fee'];
              $isPaid       = $ev['payment_status'] === 'Paid';
              $needsPay     = $isRegistered && $fee > 0 && !$isPaid;
            ?>
            <div class="p-4 flex items-center gap-4 hover:bg-purple-50/20 transition-colors">
              <div class="w-12 h-12 rounded-xl <?= $bg ?> flex flex-col items-center justify-center shrink-0">
                <span class="text-xs font-bold <?= $tc ?>"><?= strtoupper($ev['month_short']) ?></span>
                <span class="text-lg font-extrabold <?= $tn ?> leading-none"><?= $ev['day_num'] ?></span>
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars($ev['title']) ?></p>
                <p class="text-xs text-gray-500 mt-0.5"><i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($ev['venue']) ?> &nbsp;·&nbsp; <?= $ev['event_time'] ?></p>
                <?php if ($fee > 0): ?>
                <p class="text-xs text-purple-700 font-semibold mt-0.5"><i class="fas fa-tag mr-1"></i>RM <?= number_format($fee, 2) ?></p>
                <?php endif; ?>
              </div>
              <div class="shrink-0 flex flex-col gap-1 items-end">
                <?php if ($isRegistered && !$needsPay): ?>
                  <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">✓ Registered</span>
                <?php elseif ($needsPay): ?>
                  <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">✓ Registered</span>
                  <form method="POST" action="create_bill.php">
                    <input type="hidden" name="type"   value="event">
                    <input type="hidden" name="ref_id" value="<?= (int)$ev['txn_id'] ?>">
                    <input type="hidden" name="desc"   value="Registration: <?= htmlspecialchars($ev['title']) ?>">
                    <input type="hidden" name="amount" value="<?= number_format($fee, 2) ?>">
                    <button type="submit" class="px-2.5 py-1 rounded-full text-xs font-bold border" style="background:#f59e0b;color:#1c1917;border-color:#f59e0b;">
                      <i class="fas fa-credit-card mr-1"></i>Pay RM <?= number_format($fee, 2) ?>
                    </button>
                  </form>
                <?php elseif ($isFull): ?>
                  <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-500 border border-gray-200">Full</span>
                <?php else: ?>
                  <button onclick="homeRegister(this, <?= $ev['id'] ?>, <?= json_encode($ev['title']) ?>)"
                          class="home-reg-btn px-3 py-1.5 rounded-lg text-xs font-bold text-white transition-colors"
                          style="background:#581c87;">
                    <?= $fee > 0 ? 'Register — RM '.number_format($fee,2) : 'Register Now' ?>
                  </button>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>


        <!-- Right Column -->
        <div class="space-y-5">
          <!-- Latest Announcements -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-3">
              <h4 class="font-bold text-sm text-gray-900">Latest Announcements</h4>
              <button onclick="window.location.href='announcements.php'" class="text-xs text-purple-900 font-semibold hover:underline">See all</button>
            </div>
            <div class="space-y-3">
              <?php if (empty($latestAnnouncements)): ?>
              <p class="text-xs text-gray-400 text-center py-4">No announcements yet.</p>
              <?php else: foreach ($latestAnnouncements as $ann):
                $hoursAgo = (int)$ann['hours_ago'];
                $ago = $hoursAgo < 1 ? 'Just now' : ($hoursAgo < 24 ? $hoursAgo . 'h ago' : 'Yesterday');
              ?>
              <div class="flex items-start gap-3 p-3 bg-purple-50 border border-purple-100 rounded-lg cursor-pointer hover:bg-purple-100/60 transition-colors" onclick="window.location.href='announcements.php'">
                <div class="w-7 h-7 bg-purple-700 rounded-full flex items-center justify-center shrink-0 mt-0.5"><i class="fas fa-bullhorn text-white text-xs"></i></div>
                <div><p class="text-xs font-semibold text-gray-800 leading-snug"><?= htmlspecialchars($ann['title']) ?></p><p class="text-xs text-gray-400 mt-0.5"><?= $ago ?></p></div>
              </div>
              <?php endforeach; endif; ?>
            </div>
          </div>

          <!-- Attendance Summary -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="font-bold text-sm text-gray-900 mb-3">Attendance This Term</h4>
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs text-gray-500">Overall Rate</span>
              <span class="text-xs font-bold text-amber-600"><?= $attendanceRate ?>%</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2.5 mb-3">
              <div class="bg-amber-400 h-2.5 rounded-full progress-bar-fill" style="width:<?= $attendanceRate ?>%"></div>
            </div>
            <p class="text-xs text-gray-500"><?= $totalAttended ?> of <?= $totalAllRegs ?> registered events attended. Aim for <strong class="text-purple-900">80%</strong> to maintain your co-curricular standing.</p>
          </div>
        </div>
      </div>

      <!-- Recommended Events -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-bold text-lg text-gray-900">Recommended Events</h4>
          <button onclick="window.location.href='events.php'" class="text-xs font-semibold text-purple-900 bg-purple-50 px-3 py-1.5 rounded-md hover:bg-purple-100 transition-colors">Browse All</button>
        </div>
        <?php if (empty($recommendedEvents)): ?>
        <div class="text-center py-10 text-gray-400">
          <i class="fas fa-calendar-check text-3xl mb-2"></i>
          <p class="text-sm">You're registered for all available events, or no open events right now.</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <?php foreach ($recommendedEvents as $ev):
            $isFull = $ev['registered'] >= $ev['capacity'];
            $statusLabel = $isFull ? '<span class="font-bold text-red-600">Full</span>' : ($ev['pct'] >= 70 ? '<span class="font-bold text-amber-600">Filling Fast</span>' : '<span class="font-bold text-emerald-700">Open</span>');
            $descEsc = addslashes(htmlspecialchars($ev['description'] ?? ''));
            $feeLabel = (float)($ev['registration_fee'] ?? 0) > 0 ? 'RM '.number_format($ev['registration_fee'],2) : 'Free';
          ?>
          <div class="event-card bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col gap-3 cursor-pointer"
               data-event-id="<?= $ev['id'] ?>"
               onclick="openEventDrawer(<?= $ev['id'] ?>, <?= json_encode($ev['title']) ?>, <?= json_encode($ev['category']) ?>, <?= json_encode($ev['venue']) ?>, <?= json_encode($ev['event_date']) ?>, '—', <?= $ev['capacity'] ?>, <?= $ev['registered'] ?>, '<?= $ev['pct'] ?>%', <?= json_encode($ev['description'] ?? '') ?>, <?= json_encode($feeLabel) ?>)">
            <span class="<?= catBadge($ev['category']) ?> text-xs font-bold px-2.5 py-1 rounded self-start"><?= htmlspecialchars($ev['category']) ?></span>
            <div>
              <h5 class="font-bold text-gray-900"><?= htmlspecialchars($ev['title']) ?></h5>
              <p class="text-xs text-gray-500 mt-1"><i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($ev['venue']) ?> &nbsp;·&nbsp; <?= $ev['event_date'] ?></p>
            </div>
            <div class="flex justify-between text-xs text-gray-500">
              <span><?= $ev['registered'] ?> / <?= $ev['capacity'] ?> spots filled</span>
              <?= $statusLabel ?>
            </div>
            <?php if (!$isFull): ?>
            <button onclick="event.stopPropagation(); registerEvent(this, <?= $ev['id'] ?>, <?= json_encode($ev['title']) ?>)"
                    class="reg-btn w-full text-xs bg-purple-900 text-white font-semibold py-2 rounded-md hover:bg-purple-800 transition-colors">
              Register Now
            </button>
            <?php else: ?>
            <button disabled class="w-full text-xs bg-gray-200 text-gray-500 font-semibold py-2 rounded-md cursor-not-allowed">Full</button>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </main>

  </div><!-- /flex-1 inner -->
</div><!-- /main area -->

<!-- EVENT DETAILS DRAWER -->
<div id="eventDrawer" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex justify-end">
  <div class="w-full max-w-lg bg-white h-full shadow-2xl overflow-y-auto flex flex-col">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10">
      <span class="text-xs bg-purple-100 text-purple-900 font-bold px-2 py-0.5 rounded uppercase tracking-wider" id="dCategory">Category</span>
      <button onclick="closeEventDrawer()" class="text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
    </div>
    <div class="flex-1 p-6 space-y-5">
      <div>
        <h2 class="text-2xl font-extrabold text-gray-900" id="dTitle">Event Title</h2>
        <p class="text-sm text-purple-900 font-medium mt-1"><i class="fas fa-map-marker-alt text-amber-500 mr-1.5"></i><span id="dVenue">Venue</span></p>
        <p class="text-sm text-gray-500 mt-1"><i class="far fa-calendar mr-1.5 text-gray-400"></i><span id="dDate">Date</span> &nbsp;&middot;&nbsp; <i class="far fa-clock mr-1 text-gray-400"></i><span id="dTime">Time</span></p>
      </div>
      <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-3">
        <h4 class="font-bold text-xs uppercase tracking-wider text-gray-500">Registration Capacity</h4>
        <div>
          <div class="flex justify-between text-xs font-semibold text-gray-700 mb-1.5">
            <span>Spots Filled</span><span id="dCapText">0 / 0</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2.5">
            <div id="dCapBar" class="bg-purple-700 h-2.5 rounded-full progress-bar-fill" style="width:0%"></div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3 text-center">
          <div class="bg-purple-50 p-2 rounded-lg"><p class="text-base font-bold text-purple-900" id="dReg">&mdash;</p><p class="text-xs text-gray-500">Registered</p></div>
          <div class="bg-gray-100 p-2 rounded-lg"><p class="text-base font-bold text-gray-700" id="dCap">&mdash;</p><p class="text-xs text-gray-500">Total Capacity</p></div>
        </div>
      </div>
      <div class="space-y-1">
        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">About This Event</h4>
        <p class="text-sm text-gray-600 leading-relaxed" id="dDesc">Event description here.</p>
      </div>
      <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 space-y-1">
        <p class="text-xs font-bold text-amber-700 uppercase tracking-wider"><i class="fas fa-info-circle mr-1"></i>What to Bring</p>
        <ul class="text-xs text-amber-800 space-y-1 mt-1">
          <li>&bull; Valid UiTM student ID card</li>
          <li>&bull; QR code from your My Registrations page</li>
          <li>&bull; Smart casual or faculty attire as appropriate</li>
        </ul>
      </div>
    </div>
    <div class="p-6 border-t border-gray-100 space-y-2 sticky bottom-0 bg-white">
      <button id="dRegisterBtn" onclick="drawerRegister()" class="w-full bg-purple-900 hover:bg-purple-800 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors flex items-center justify-center space-x-2">
        <i class="fas fa-ticket-alt"></i><span>Register for This Event</span>
      </button>
      <button onclick="showToast('Event saved to your favourites.')" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 rounded-lg text-xs transition-colors flex items-center justify-center space-x-2">
        <i class="far fa-bookmark"></i><span>Save to Favourites</span>
      </button>
      <button onclick="closeEventDrawer()" class="w-full bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 font-medium py-2 rounded-lg text-xs transition-colors">Close</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast" class="fixed bottom-6 right-6 z-[100] hidden">
  <div class="bg-gray-900 text-white text-sm font-medium px-5 py-3 rounded-xl shadow-2xl flex items-center space-x-3 max-w-sm">
    <i class="fas fa-check-circle text-emerald-400"></i>
    <span id="toastMsg">Done.</span>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════ -->
<script>
/* ---- shared.js ---- */

/* ============================================================
   SHARED / GLOBAL JS
   Functions used by more than one section (toast, event drawer,
   register-from-card). Section-only logic lives in that section's
   own js file instead.
   ============================================================ */

// ---- Toast ----
let toastTimer;
function showToast(msg) {
  const el = document.getElementById('toast');
  document.getElementById('toastMsg').innerText = msg;
  el.classList.remove('hidden');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.add('hidden'), 3200);
}

// ---- Event Drawer (triggered from Home + Browse Events cards) ----
let currentDrawerTitle = '';

function openEventDrawer(id, title, category, venue, date, time, capacity, registered, pct, desc, fee) {
  _drawerId = id; _drawerTitle = title;
  currentDrawerTitle = title;
  document.getElementById('dTitle').innerText = title;
  document.getElementById('dCategory').innerText = category + ' Track';
  document.getElementById('dVenue').innerText = venue;
  document.getElementById('dDate').innerText = date;
  document.getElementById('dTime').innerText = time;
  document.getElementById('dCapText').innerText = registered + ' / ' + capacity + ' registered';
  document.getElementById('dReg').innerText = registered;
  document.getElementById('dCap').innerText = capacity;
  document.getElementById('dCapBar').style.width = pct;
  document.getElementById('dDesc').innerText = desc;
  document.getElementById('eventDrawer').classList.remove('hidden');
}

function closeEventDrawer() {
  document.getElementById('eventDrawer').classList.add('hidden');
}

function drawerRegister() {
  closeEventDrawer();
  showToast(`Registered for "${currentDrawerTitle}" successfully!`);
}

// ---- Register button on an event card (Home + Browse Events) ----
function registerEvent(btn, name) {
  btn.className = 'w-full text-xs bg-emerald-600 text-white font-semibold py-2 rounded-md cursor-default';
  btn.innerHTML = '✓ Registered';
  btn.onclick = null;
  showToast(`Registered for "${name}" successfully!`);
}

// ---- Home page register button (calls register_event.php) ----
function homeRegister(btn, eventId, title) {
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Registering…';

  const fd = new FormData();
  fd.append('event_id', eventId);

  fetch('register_event.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        btn.outerHTML = '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">✓ Registered</span>';
        showToast(data.already ? 'You are already registered.' : `Registered for "${title}" successfully!`);
        // If paid event, show pay button
        if (!data.already && data.fee > 0 && data.txn_id) {
          showToast(`Registered! Please complete payment of RM ${parseFloat(data.fee).toFixed(2)}.`);
          setTimeout(() => { window.location.reload(); }, 1800);
        }
      } else {
        btn.disabled = false;
        btn.innerHTML = 'Register Now';
        showToast('⚠ ' + data.message);
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = 'Register Now';
      showToast('⚠ Network error. Please try again.');
    });
}

// Close drawer when clicking the backdrop
document.addEventListener('DOMContentLoaded', function () {
  const drawer = document.getElementById('eventDrawer');
  if (drawer) {
    drawer.addEventListener('click', function (e) {
      if (e.target === this) closeEventDrawer();
    });
  }
});


/* ---- home.js ---- */

/* ============================================================
   HOME — section-only JS
   This section has no functions of its own — it only calls
   shared functions (openEventDrawer, registerEvent) defined in
   js/shared.js, plus page-navigation links to other sections.
   ============================================================ */

</script>
</body>
</html>