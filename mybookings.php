<?php
require_once __DIR__ . '/../config.php';
requireStudent();

$sid = (int) $_SESSION['student_id'];

$upcoming = []; $past = [];
try {
    $pdo = db();
    $s = $pdo->prepare("
        SELECT r.id AS reg_id, r.status AS reg_status, r.attended_at,
               r.registered_at,
               e.id AS event_id, e.title, e.category, e.venue,
               DATE_FORMAT(e.start_date,'%d %b %Y') AS event_date,
               DATE_FORMAT(e.start_date,'%h:%i %p') AS event_time,
               DATE_FORMAT(e.end_date,'%h:%i %p')   AS event_end,
               e.registration_fee, e.status AS event_status,
               DATE_FORMAT(e.start_date,'%b') AS month_short,
               DAY(e.start_date) AS day_num,
               e.start_date
        FROM registrations r
        JOIN events e ON e.id = r.event_id
        WHERE r.student_id = ? AND r.status != 'cancelled'
        ORDER BY e.start_date ASC
    ");
    $s->execute([$sid]);
    $allRegs = $s->fetchAll();

    foreach ($allRegs as $r) {
        if (date('Y-m-d', strtotime($r['start_date'])) >= date('Y-m-d')) {
            $upcoming[] = $r;
        } else {
            $past[] = $r;
        }
    }
} catch (Throwable $e) {}

$totalCount    = count($upcoming) + count($past);
$upcomingCount = count($upcoming);
$pastCount     = count($past);

function catColor(string $cat): array {
    return match($cat) {
        'Academic'  => ['bg' => 'bg-blue-100',   'text' => 'text-blue-800',   'badge' => 'bg-blue-50 text-blue-700 border-blue-100'],
        'Cultural'  => ['bg' => 'bg-amber-100',  'text' => 'text-amber-800',  'badge' => 'bg-amber-50 text-amber-700 border-amber-100'],
        'Sports'    => ['bg' => 'bg-rose-100',   'text' => 'text-rose-800',   'badge' => 'bg-rose-50 text-rose-700 border-rose-100'],
        'Business'  => ['bg' => 'bg-emerald-100','text' => 'text-emerald-800','badge' => 'bg-emerald-50 text-emerald-700 border-emerald-100'],
        'Religious' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-800', 'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-100'],
        default     => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'badge' => 'bg-purple-50 text-purple-700 border-purple-100'],
    };
}

$student = $_SESSION['student'] ?? [];
$sName   = htmlspecialchars($student['name'] ?? 'Student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | My Registrations</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; }
.tab-btn { transition: all .18s; }
.tab-btn.active { background: #581c87; color: #fff; border-color: #581c87; }
.booking-card { transition: box-shadow .2s; }
.booking-card:hover { box-shadow: 0 8px 24px rgba(80,0,120,0.10); }
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
      <a href="mybookings.php" class="sidebar-nav-btn w-full flex items-center space-x-3 bg-amber-500 text-purple-950 font-semibold px-4 py-3 rounded-lg shadow-sm">
        <i class="fas fa-ticket-alt text-lg w-5"></i><span>My Registrations</span>
        <?php if ($upcomingCount > 0): ?>
        <span class="ml-auto bg-purple-900 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center"><?= $upcomingCount ?></span>
        <?php endif; ?>
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
      <a href="payments.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg group">
        <i class="fas fa-credit-card text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Payments</span>
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
      <h2 class="text-xl font-bold text-gray-800">My Registrations</h2>
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

  <main class="flex-1 p-6 md:p-8 max-w-5xl w-full mx-auto space-y-6">

    <!-- Page heading -->
    <div>
      <h3 class="text-2xl font-bold text-gray-900">My Registrations</h3>
      <p class="text-sm text-gray-500 mt-1">All events you have signed up for this academic term.</p>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 flex-wrap">
      <button onclick="switchTab('all', this)"
              class="tab-btn active px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200">
        All (<?= $totalCount ?>)
      </button>
      <button onclick="switchTab('upcoming', this)"
              class="tab-btn px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
        Upcoming (<?= $upcomingCount ?>)
      </button>
      <button onclick="switchTab('past', this)"
              class="tab-btn px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
        Past (<?= $pastCount ?>)
      </button>
    </div>

    <!-- ── UPCOMING ── -->
    <div id="tab-upcoming" class="space-y-4">
      <?php if (empty($upcoming)): ?>
      <div id="upcoming-empty" class="hidden text-center py-16 text-gray-400 bg-white rounded-xl border border-gray-100">
        <i class="fas fa-calendar-xmark text-4xl mb-3 block"></i>
        <p class="text-sm font-medium text-gray-500">No upcoming registrations.</p>
        <a href="events.php" class="text-xs text-purple-700 mt-2 inline-block hover:underline">Browse events →</a>
      </div>
      <?php else: foreach ($upcoming as $r):
        $c   = catColor($r['category']);
        $fee = (float)($r['registration_fee'] ?? 0);
        $ref = 'UV-' . str_pad($r['reg_id'], 6, '0', STR_PAD_LEFT);
        $regDate = $r['registered_at'] ? date('d M Y', strtotime($r['registered_at'])) : '—';
      ?>
      <div class="booking-card bg-white rounded-xl border border-gray-200 shadow-sm p-5" data-tab="upcoming">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">

          <!-- Date badge -->
          <div class="w-14 h-14 rounded-xl <?= $c['bg'] ?> <?= $c['text'] ?> flex flex-col items-center justify-center shrink-0 font-bold">
            <span class="text-xs"><?= strtoupper($r['month_short']) ?></span>
            <span class="text-xl leading-none"><?= $r['day_num'] ?></span>
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <h4 class="font-bold text-gray-900"><?= htmlspecialchars($r['title']) ?></h4>
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold border <?= $c['badge'] ?>"><?= htmlspecialchars($r['category']) ?></span>
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">Upcoming</span>
            </div>
            <p class="text-xs text-gray-500"><i class="fas fa-map-marker-alt mr-1 text-amber-500"></i><?= htmlspecialchars($r['venue']) ?></p>
            <p class="text-xs text-gray-500 mt-0.5">
              <i class="far fa-clock mr-1"></i><?= $r['event_date'] ?> · <?= $r['event_time'] ?><?= $r['event_end'] ? ' – ' . $r['event_end'] : '' ?>
            </p>
            <p class="text-xs text-gray-400 mt-1">
              Registered on <?= $regDate ?> &nbsp;·&nbsp; Ref: <span class="font-mono font-semibold text-gray-600"><?= $ref ?></span>
            </p>
            <?php if ($fee > 0): ?>
            <p class="text-xs text-purple-700 font-semibold mt-1"><i class="fas fa-tag mr-1"></i>Fee: RM <?= number_format($fee, 2) ?></p>
            <?php endif; ?>
          </div>

          <!-- Actions -->
          <div class="flex flex-col gap-2 shrink-0 sm:items-end">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-100 self-start sm:self-auto">
              <i class="fas fa-check mr-1"></i>Registered
            </span>
            <button onclick="showQRModal('<?= addslashes(htmlspecialchars($r['title'])) ?>', '<?= $ref ?>', '<?= addslashes(htmlspecialchars($r['venue'])) ?>', '<?= $r['event_date'] ?> · <?= $r['event_time'] ?>')"
                    class="text-xs bg-purple-900 text-white font-semibold px-3 py-1.5 rounded-lg hover:bg-purple-800 transition-colors flex items-center gap-1.5">
              <i class="fas fa-qrcode"></i> Show QR
            </button>
            <button onclick="cancelReg(<?= $r['reg_id'] ?>, this)"
                    class="text-xs text-red-500 hover:text-red-700 hover:underline font-medium">
              Cancel Registration
            </button>
          </div>

        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- ── PAST ── -->
    <div id="tab-past" class="space-y-4">
      <?php if (empty($past)): ?>
      <div class="text-center py-16 text-gray-400 bg-white rounded-xl border border-gray-100">
        <i class="fas fa-history text-4xl mb-3 block"></i>
        <p class="text-sm font-medium text-gray-500">No past event registrations.</p>
      </div>
      <?php else: foreach ($past as $r):
        $c        = catColor($r['category']);
        $attended = !empty($r['attended_at']);
      ?>
      <div class="booking-card bg-white rounded-xl border border-gray-200 shadow-sm p-5 opacity-80" data-tab="past">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">

          <div class="w-14 h-14 rounded-xl bg-gray-100 text-gray-500 flex flex-col items-center justify-center shrink-0 font-bold">
            <span class="text-xs"><?= strtoupper($r['month_short']) ?></span>
            <span class="text-xl leading-none"><?= $r['day_num'] ?></span>
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <h4 class="font-bold text-gray-700"><?= htmlspecialchars($r['title']) ?></h4>
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold border <?= $c['badge'] ?>"><?= htmlspecialchars($r['category']) ?></span>
            </div>
            <p class="text-xs text-gray-400"><i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($r['venue']) ?></p>
            <p class="text-xs text-gray-400 mt-0.5"><i class="far fa-calendar mr-1"></i><?= $r['event_date'] ?> · <?= $r['event_time'] ?></p>
          </div>

          <div class="shrink-0">
            <?php if ($attended): ?>
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">
              <i class="fas fa-check mr-1"></i>Attended
            </span>
            <?php else: ?>
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-500 border border-gray-200">Not Attended</span>
            <?php endif; ?>
          </div>

        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </main>
</div>

<!-- QR MODAL -->
<div id="qrModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
      <h3 class="font-bold text-gray-900 text-sm">Your Event Ticket</h3>
      <button onclick="closeQRModal()" class="text-gray-400 hover:text-gray-600 text-lg"><i class="fas fa-times"></i></button>
    </div>
    <div class="p-6 flex flex-col items-center text-center space-y-3">
      <h4 class="font-extrabold text-gray-900" id="qrEventTitle"></h4>
      <p class="text-xs text-gray-500">
        <i class="fas fa-map-marker-alt text-amber-500 mr-1"></i>
        <span id="qrEventVenue"></span> &nbsp;·&nbsp; <span id="qrEventDate"></span>
      </p>
      <div class="bg-white p-3 rounded-xl border-2 border-purple-100">
        <img id="qrCodeImg" src="" alt="QR Code" class="w-48 h-48" width="192" height="192">
      </div>
      <p class="text-xs text-gray-400">Booking Ref</p>
      <p class="font-mono font-bold text-purple-900 tracking-wider" id="qrEventRef"></p>
      <p class="text-xs text-gray-400 leading-relaxed">Present this QR code at the check-in gate along with your student ID.</p>
    </div>
    <div class="p-5 border-t border-gray-100">
      <button onclick="closeQRModal()" class="w-full bg-purple-900 hover:bg-purple-800 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors">Close</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast" class="fixed bottom-6 right-6 z-[100] hidden">
  <div class="bg-gray-900 text-white text-sm font-medium px-5 py-3 rounded-xl shadow-2xl flex items-center space-x-3">
    <i class="fas fa-check-circle text-emerald-400"></i>
    <span id="toastMsg">Done.</span>
  </div>
</div>

<script>
// ── Tabs ──────────────────────────────────────────────────────
function switchTab(tab, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => {
    b.className = 'tab-btn px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200 bg-white text-gray-600 hover:bg-gray-50';
  });
  btn.className = 'tab-btn active px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200';

  const upcoming      = document.getElementById('tab-upcoming');
  const past          = document.getElementById('tab-past');
  const upcomingEmpty = document.getElementById('upcoming-empty');

  if (tab === 'all') {
    upcoming.classList.remove('hidden');
    past.classList.remove('hidden');
    if (upcomingEmpty) upcomingEmpty.classList.add('hidden'); // don't show "no upcoming" when All is active
  } else if (tab === 'upcoming') {
    upcoming.classList.remove('hidden');
    past.classList.add('hidden');
    if (upcomingEmpty) upcomingEmpty.classList.remove('hidden'); // show empty state only on Upcoming tab
  } else {
    upcoming.classList.add('hidden');
    past.classList.remove('hidden');
  }
}

// ── Cancel registration ───────────────────────────────────────
function cancelReg(regId, btn) {
  if (!confirm('Cancel this registration?')) return;
  btn.disabled = true;
  btn.innerText = 'Cancelling…';
  fetch('cancel_registration.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'reg_id=' + regId
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      const card = btn.closest('.booking-card');
      card.style.transition = 'opacity .3s';
      card.style.opacity = '0';
      setTimeout(() => card.remove(), 300);
      showToast('Registration cancelled.');
    } else {
      btn.disabled = false;
      btn.innerText = 'Cancel Registration';
      showToast('⚠ ' + d.message);
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.innerText = 'Cancel Registration';
    showToast('⚠ Network error.');
  });
}

// ── QR Modal ─────────────────────────────────────────────────
function showQRModal(title, ref, venue, dateTime) {
  document.getElementById('qrEventTitle').innerText = title;
  document.getElementById('qrEventRef').innerText   = ref;
  document.getElementById('qrEventVenue').innerText = venue;
  document.getElementById('qrEventDate').innerText  = dateTime;
  const payload = `UiVent|${ref}|${title}|${venue}|${dateTime}`;
  document.getElementById('qrCodeImg').src =
    'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=0&data=' + encodeURIComponent(payload);
  document.getElementById('qrModal').classList.remove('hidden');
}

function closeQRModal() {
  document.getElementById('qrModal').classList.add('hidden');
}

document.getElementById('qrModal').addEventListener('click', function(e) {
  if (e.target === this) closeQRModal();
});

// ── Toast ─────────────────────────────────────────────────────
let toastTimer;
function showToast(msg) {
  const el = document.getElementById('toast');
  document.getElementById('toastMsg').innerText = msg;
  el.classList.remove('hidden');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.add('hidden'), 3200);
}
</script>
</body>
</html>