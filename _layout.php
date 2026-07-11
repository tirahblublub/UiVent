<?php
// ============================================================
//  _layout.php  —  Shared sidebar, topbar, event drawer, toast
//  Requires: config.php already included + requireStudent() called
//  Caller sets: $pageTitle (string), $activeNav (string)
//  Call layoutEnd() to close body/html
// ============================================================

$_sid     = (int) $_SESSION['student_id'];
$_student = $_SESSION['student'] ?? [];
$_sName   = htmlspecialchars($_student['name'] ?? 'Student');
$_sFirst  = htmlspecialchars(explode(' ', $_student['name'] ?? 'Student')[0]);
$_initials = strtoupper(substr($_student['name'] ?? 'S', 0, 1) . (strpos($_student['name'] ?? '', ' ') !== false ? substr(strrchr($_student['name'] ?? '', ' '), 1, 1) : ''));

// Count upcoming registered events for sidebar badge
$_upcomingCount = 0;
try {
    $s = db()->prepare("
        SELECT COUNT(*) FROM registrations r
        JOIN events e ON e.id = r.event_id
        WHERE r.student_id = ? AND r.status = 'registered'
          AND e.start_date >= NOW()
    ");
    $s->execute([$_sid]);
    $_upcomingCount = (int) $s->fetchColumn();
} catch (\Throwable $e) {}

function navBtn(string $id, string $icon, string $label, string $active, ?int $badge = null, string $badgeCls = 'bg-amber-500 text-purple-950'): void {
    $href = match($id) {
        'home'      => 'home.php',
        default     => $id . '.php',
    };
    $isActive = $id === $active;
    $cls = $isActive
        ? 'sidebar-nav-btn w-full flex items-center gap-3 bg-amber-500 text-purple-950 font-semibold px-4 py-2.5 rounded-lg text-left'
        : 'sidebar-nav-btn w-full flex items-center gap-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-2.5 rounded-lg text-left group';
    $iCls = $isActive ? "fas {$icon} w-4 text-sm" : "fas {$icon} w-4 text-sm text-purple-400 group-hover:text-white";
    echo "<a href=\"{$href}\" class=\"{$cls}\">";
    echo "<i class=\"{$iCls}\"></i><span>{$label}</span>";
    if ($badge !== null && $badge > 0)
        echo "<span class=\"ml-auto {$badgeCls} text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center\">{$badge}</span>";
    echo "</a>\n";
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | <?= htmlspecialchars($pageTitle ?? 'Portal') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.view-section{display:none}.view-section.active{display:block;animation:fadeIn .25s ease-out}
@keyframes fadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
#eventDrawer>div{transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1)}
#eventDrawer:not(.hidden)>div{transform:translateX(0)}
.progress-bar-fill{transition:width 1.1s cubic-bezier(.4,0,.2,1)}
.tab-btn.active{background:#581c87;color:#fff}.tab-btn{transition:all .18s}
.event-card{transition:transform .2s,box-shadow .2s}
.event-card:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(80,0,120,.12)}
.sidebar-nav-btn{transition:all .18s}
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">

<!-- SIDEBAR -->
<aside id="sidebar" class="w-64 bg-purple-950 text-white flex flex-col hidden md:flex shrink-0 shadow-xl">
  <div class="overflow-y-auto flex-1 min-h-0">
    <div class="h-16 flex items-center px-6 border-b border-purple-900 bg-purple-900/40 sticky top-0">
      <div class="bg-amber-500 text-purple-950 px-2.5 py-1 rounded-md font-extrabold text-lg mr-2">Ui</div>
      <span class="font-bold text-xl">Vent</span>
      <span class="text-xs bg-purple-800 text-purple-200 ml-2 px-1.5 py-0.5 rounded uppercase">Student</span>
    </div>
    <nav class="mt-4 px-3 space-y-0.5 pb-4">
      <?php
      navBtn('home',         'fa-home',          'Home',                  $activeNav ?? '');
      navBtn('events',       'fa-calendar-alt',  'Browse Events',         $activeNav ?? '');
      navBtn('mybookings',   'fa-ticket-alt',    'My Registrations',      $activeNav ?? '', $_upcomingCount, 'bg-amber-500 text-purple-950');
      navBtn('attendance',   'fa-chart-bar',     'My Attendance',         $activeNav ?? '');
      navBtn('announcements','fa-bullhorn',       'Announcements',         $activeNav ?? '');
      navBtn('profile',      'fa-user',          'My Profile',            $activeNav ?? '');
      ?>
      <p class="text-xs font-bold uppercase tracking-widest text-purple-600 px-1 pt-4 pb-1">More</p>
      <?php
      navBtn('merchandise',  'fa-tshirt',        'Merchandise',           $activeNav ?? '');
      navBtn('payments',     'fa-credit-card',   'Payments',              $activeNav ?? '');
      navBtn('feedback',     'fa-comment-dots',  'Feedback',              $activeNav ?? '');
      <?php
      ?>
      <div class="mt-2 pt-2 border-t border-purple-900">
        <a href="logout.php" class="sidebar-nav-btn w-full flex items-center gap-3 text-purple-300 hover:bg-red-900/40 hover:text-red-300 px-4 py-2.5 rounded-lg text-left group">
          <i class="fas fa-sign-out-alt w-4 text-sm text-purple-500 group-hover:text-red-300"></i><span>Logout</span>
        </a>
      </div>
    </nav>
  </div>
</aside>

<!-- MAIN -->
<div class="flex-1 flex flex-col h-full overflow-hidden">

  <!-- TOPBAR -->
  <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 md:px-8 shrink-0 sticky top-0 z-10 shadow-sm">
    <div class="flex items-center gap-4">
      <button onclick="document.getElementById('sidebar').classList.toggle('hidden');document.getElementById('sidebar').classList.toggle('flex')"
              class="text-gray-500 hover:text-gray-700 md:hidden">
        <i class="fas fa-bars text-xl"></i>
      </button>
      <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($pageTitle ?? '') ?></h2>
    </div>
    <div class="flex items-center gap-4">
      <a href="announcements.php" class="p-2 text-gray-400 hover:text-purple-900 transition-colors">
        <i class="far fa-bell text-lg"></i>
      </a>
      <div class="h-6 w-px bg-gray-200"></div>
      <a href="profile.php" class="flex items-center gap-3 cursor-pointer">
        <div class="text-right hidden md:block">
          <p class="text-sm font-semibold text-gray-800"><?= $_sFirst ?></p>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($_student['faculty'] ?? 'UiTM') ?> &middot; Year <?= htmlspecialchars($_student['year'] ?? '—') ?></p>
        </div>
        <div class="w-9 h-9 rounded-full bg-purple-200 flex items-center justify-center font-bold text-sm text-purple-900 ring-2 ring-purple-100">
          <?= $_initials ?>
        </div>
      </a>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <div class="flex-1 overflow-y-auto">
<?php

function layoutEnd(): void { ?>
  </div><!-- /page content -->
</div><!-- /main -->

<!-- EVENT DETAILS DRAWER -->
<div id="eventDrawer" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex justify-end">
  <div class="w-full max-w-lg bg-white h-full shadow-2xl overflow-y-auto flex flex-col">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10">
      <span class="text-xs bg-purple-100 text-purple-900 font-bold px-2 py-0.5 rounded uppercase tracking-wider" id="dCategory">Category</span>
      <button onclick="closeEventDrawer()" class="text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
    </div>
    <div class="flex-1 p-6 space-y-5">
      <div>
        <h2 class="text-2xl font-extrabold text-gray-900" id="dTitle"></h2>
        <p class="text-sm text-gray-600 mt-1"><i class="fas fa-map-marker-alt text-amber-500 mr-1.5"></i><span id="dVenue"></span></p>
        <p class="text-sm text-gray-500 mt-1"><i class="far fa-calendar mr-1.5 text-gray-400"></i><span id="dDate"></span> &nbsp;&middot;&nbsp; <i class="far fa-clock mr-1 text-gray-400"></i><span id="dTime"></span></p>
        <p class="text-sm mt-1" id="dFeeRow"></p>
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
          <div class="bg-purple-50 p-2 rounded-lg"><p class="text-base font-bold text-purple-900" id="dReg">—</p><p class="text-xs text-gray-500">Registered</p></div>
          <div class="bg-gray-100 p-2 rounded-lg"><p class="text-base font-bold text-gray-700" id="dCap">—</p><p class="text-xs text-gray-500">Capacity</p></div>
        </div>
      </div>
      <div>
        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">About This Event</h4>
        <p class="text-sm text-gray-600 leading-relaxed" id="dDesc"></p>
      </div>
      <div class="bg-amber-50 border border-amber-100 rounded-xl p-4">
        <p class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-2"><i class="fas fa-info-circle mr-1"></i>What to Bring</p>
        <ul class="text-xs text-amber-800 space-y-1">
          <li>&bull; Valid UiTM student ID card</li>
          <li>&bull; QR code from your My Registrations page</li>
          <li>&bull; Smart casual or faculty attire as appropriate</li>
        </ul>
      </div>
    </div>
    <div class="p-6 border-t border-gray-100 space-y-2 sticky bottom-0 bg-white">
      <button id="dRegBtn" onclick="drawerRegister()"
              class="w-full bg-purple-900 hover:bg-purple-800 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors flex items-center justify-center gap-2">
        <i class="fas fa-ticket-alt"></i><span>Register for This Event</span>
      </button>
      <button onclick="closeEventDrawer()"
              class="w-full bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 font-medium py-2 rounded-lg text-xs transition-colors">
        Close
      </button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast" class="fixed bottom-6 right-6 z-[100] hidden">
  <div class="bg-gray-900 text-white text-sm font-medium px-5 py-3 rounded-xl shadow-2xl flex items-center gap-3 max-w-sm">
    <i class="fas fa-check-circle text-emerald-400" id="toastIcon"></i>
    <span id="toastMsg">Done.</span>
  </div>
</div>

<script>
let _toastTimer;
function showToast(msg, icon = 'check-circle', color = 'text-emerald-400') {
  document.getElementById('toastMsg').innerText = msg;
  document.getElementById('toastIcon').className = `fas fa-${icon} ${color}`;
  const el = document.getElementById('toast');
  el.classList.remove('hidden');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => el.classList.add('hidden'), 3400);
}

let _drawerId = null, _drawerTitle = '';

function openEventDrawer(id, title, category, venue, date, time, cap, reg, pct, desc, fee) {
  _drawerId = id; _drawerTitle = title;
  document.getElementById('dTitle').innerText    = title;
  document.getElementById('dCategory').innerText = category + ' Track';
  document.getElementById('dVenue').innerText    = venue;
  document.getElementById('dDate').innerText     = date;
  document.getElementById('dTime').innerText     = time;
  document.getElementById('dCapText').innerText  = reg + ' / ' + cap + ' registered';
  document.getElementById('dReg').innerText      = reg;
  document.getElementById('dCap').innerText      = cap;
  document.getElementById('dCapBar').style.width = pct;
  document.getElementById('dDesc').innerText     = desc || 'No description provided.';
  const fr = document.getElementById('dFeeRow');
  fr.innerHTML = parseFloat(fee) > 0
    ? `<i class="fas fa-tag text-purple-400 mr-1.5"></i>Fee: <strong class="text-purple-900">RM ${parseFloat(fee).toFixed(2)}</strong>`
    : `<i class="fas fa-tag text-emerald-500 mr-1.5"></i><span class="text-emerald-700 font-semibold">Free entry</span>`;
  document.getElementById('eventDrawer').classList.remove('hidden');
}

function closeEventDrawer() {
  document.getElementById('eventDrawer').classList.add('hidden');
}

function drawerRegister() {
  if (!_drawerId) { closeEventDrawer(); return; }
  const btn = document.getElementById('dRegBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Registering…';
  fetch('register_event.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'event_id=' + encodeURIComponent(_drawerId)
  })
    .then(r => r.json())
    .then(d => {
      closeEventDrawer();
      showToast(d.message, d.success ? 'check-circle' : 'times-circle', d.success ? 'text-emerald-400' : 'text-red-400');
      if (d.success) {
        document.querySelectorAll(`[data-event-id="${_drawerId}"] .reg-btn`).forEach(markRegistered);
      }
    })
    .catch(() => { closeEventDrawer(); showToast('Network error. Try again.', 'times-circle', 'text-red-400'); });
}

function markRegistered(btn) {
  btn.className = 'reg-btn w-full text-xs bg-emerald-600 text-white font-semibold py-2 rounded-md cursor-default';
  btn.innerHTML = '&#10003; Registered';
  btn.disabled  = true;
  btn.onclick   = null;
}

function registerEvent(btn, id, name) {
  btn.disabled = true; btn.innerText = 'Registering…';
  fetch('register_event.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'event_id=' + encodeURIComponent(id)
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) { markRegistered(btn); showToast(d.message); }
      else { btn.disabled = false; btn.innerText = 'Register Now'; showToast(d.message, 'times-circle', 'text-red-400'); }
    })
    .catch(() => { btn.disabled = false; btn.innerText = 'Register Now'; showToast('Network error.', 'times-circle', 'text-red-400'); });
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('eventDrawer')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeEventDrawer(); });
});
</script>
</body></html>
<?php } // end layoutEnd()
