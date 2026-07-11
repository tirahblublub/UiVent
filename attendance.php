<?php
require_once '../config.php';
requireAdmin();

$activePage = 'attendance';
$pageTitle  = 'Attendance';
$adminId    = $_SESSION['admin_id'];

// ── AJAX: Mark attendance ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $regId  = (int)($_POST['reg_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!in_array($status, ['attended','absent','pending'])) {
        echo json_encode(['success'=>false,'message'=>'Invalid status.']); exit;
    }

    $check = db()->prepare("
        SELECT r.id FROM registrations r
        JOIN events e ON e.id = r.event_id
        WHERE r.id = ? AND e.created_by = ?
    ");
    $check->execute([$regId, $adminId]);
    if (!$check->fetch()) {
        echo json_encode(['success'=>false,'message'=>'Not authorised.']); exit;
    }

    if ($status === 'attended') {
        db()->prepare("UPDATE registrations SET attended_at = NOW(), status = 'attended' WHERE id = ?")
           ->execute([$regId]);
    } elseif ($status === 'absent') {
        db()->prepare("UPDATE registrations SET attended_at = NULL, status = 'registered' WHERE id = ?")
           ->execute([$regId]);
    } else {
        db()->prepare("UPDATE registrations SET attended_at = NULL, status = 'registered' WHERE id = ?")
           ->execute([$regId]);
    }

    echo json_encode(['success'=>true,'status'=>$status]); exit;
}

// ── AJAX: QR Scan — find student by matric_no ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_scan'])) {
    header('Content-Type: application/json');
    $matric  = trim($_POST['matric_no'] ?? '');
    $eventId = (int)($_POST['event_id'] ?? 0);

    if (!$matric || !$eventId) {
        echo json_encode(['success'=>false,'message'=>'Invalid QR data.']); exit;
    }

    // Verify event belongs to this admin
    $evCheck = db()->prepare("SELECT id, title FROM events WHERE id=? AND created_by=?");
    $evCheck->execute([$eventId, $adminId]);
    $event = $evCheck->fetch();
    if (!$event) {
        echo json_encode(['success'=>false,'message'=>'Event not found.']); exit;
    }

    // Find student registration
    $stmt = db()->prepare("
        SELECT r.id, r.attended_at, r.status,
               s.name AS student_name, s.matric_no, s.email
        FROM registrations r
        JOIN students s ON s.id = r.student_id
        WHERE s.matric_no = ? AND r.event_id = ?
    ");
    $stmt->execute([$matric, $eventId]);
    $reg = $stmt->fetch();

    if (!$reg) {
        echo json_encode(['success'=>false,'message'=>"Student ($matric) is not registered for this event."]); exit;
    }

    if ($reg['attended_at']) {
        echo json_encode([
            'success'  => false,
            'already'  => true,
            'message'  => $reg['student_name'] . ' already marked as attended.',
            'student'  => $reg['student_name'],
            'matric'   => $reg['matric_no'],
        ]); exit;
    }

    // Mark attended
    db()->prepare("UPDATE registrations SET attended_at = NOW(), status = 'attended' WHERE id = ?")
       ->execute([$reg['id']]);

    echo json_encode([
        'success' => true,
        'message' => $reg['student_name'] . ' marked as attended!',
        'student' => $reg['student_name'],
        'matric'  => $reg['matric_no'],
        'reg_id'  => $reg['id'],
    ]); exit;
}

// ── Load events ───────────────────────────────────────────────
$stmtEvs = db()->prepare("
    SELECT id, title, start_date, status FROM events
    WHERE created_by = ? AND status NOT IN ('cancelled','archived')
    ORDER BY start_date DESC
");
$stmtEvs->execute([$adminId]);
$myEvents = $stmtEvs->fetchAll();

// ── Load registrations for selected event ─────────────────────
$eventId       = (int)($_GET['event_id'] ?? 0);
$selectedEvent = null;
$registrations = [];
$stats         = ['total'=>0,'attended'=>0,'absent'=>0,'pending'=>0];

if ($eventId) {
    $evStmt = db()->prepare("SELECT * FROM events WHERE id=? AND created_by=?");
    $evStmt->execute([$eventId, $adminId]);
    $selectedEvent = $evStmt->fetch();

    if ($selectedEvent) {
        $search = trim($_GET['q'] ?? '');
        $filter = $_GET['filter'] ?? '';

        $where  = "WHERE r.event_id = ? AND r.status != 'cancelled'";
        $params = [$eventId];
        if ($search) { $where .= " AND (s.name LIKE ? OR s.matric_no LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($filter === 'attended') { $where .= " AND r.attended_at IS NOT NULL"; }
        elseif ($filter === 'pending') { $where .= " AND r.attended_at IS NULL"; }

        $stmt = db()->prepare("
            SELECT r.id, r.registered_at, r.attended_at, r.status,
                   s.name AS student_name, s.matric_no, s.email
            FROM registrations r
            JOIN students s ON s.id = r.student_id
            $where
            ORDER BY s.name ASC
        ");
        $stmt->execute($params);
        $registrations = $stmt->fetchAll();

        // Stats
        $statStmt = db()->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(attended_at IS NOT NULL) AS attended,
                SUM(attended_at IS NULL) AS pending
            FROM registrations
            WHERE event_id = ? AND status != 'cancelled'
        ");
        $statStmt->execute([$eventId]);
        $statRow = $statStmt->fetch();
        $stats['total']    = (int)($statRow['total']    ?? 0);
        $stats['attended'] = (int)($statRow['attended'] ?? 0);
        $stats['pending']  = (int)($statRow['pending']  ?? 0);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Attendance</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- QR Scanner library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<?php include 'partials/head_styles.php'; ?>
<style>
  .att-btn { padding:.35rem .75rem; border-radius:.5rem; font-size:.7rem; font-weight:700; letter-spacing:.03em; border:2px solid transparent; cursor:pointer; transition:all .15s; }
  .att-attended { background:#dcfce7; color:#166534; border-color:#86efac; }
  .att-attended.active, .att-attended:hover { background:#16a34a; color:#fff; border-color:#16a34a; }
  .att-pending  { background:#f3f4f6; color:#6b7280; border-color:#d1d5db; }
  .att-pending.active, .att-pending:hover  { background:#6b7280; color:#fff; border-color:#6b7280; }

  /* QR Scanner */
  .qr-modal { position:fixed; inset:0; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; z-index:100; opacity:0; pointer-events:none; transition:opacity .2s; }
  .qr-modal.open { opacity:1; pointer-events:all; }
  .qr-box { background:#fff; border-radius:20px; width:420px; max-width:95vw; padding:24px; box-shadow:0 25px 60px rgba(0,0,0,0.3); }
  #qr-reader { width:100%; border-radius:12px; overflow:hidden; }
  #qr-reader video { border-radius:12px; }

  .scan-result { padding:12px 16px; border-radius:10px; font-size:13px; font-weight:600; display:none; }
  .scan-success { background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0; }
  .scan-error   { background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5; }
  .scan-warning { background:#FEF3C7; color:#92400E; border:1px solid #FDE68A; }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 space-y-6 max-w-5xl w-full mx-auto">

  <!-- Header -->
  <div class="flex items-center justify-between flex-wrap gap-4">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Attendance</h1>
      <p class="text-sm text-gray-500 mt-0.5">Mark attendance manually or scan student QR codes.</p>
    </div>
    <?php if ($eventId && $selectedEvent): ?>
    <button onclick="openQRScanner()"
      class="btn-primary flex items-center gap-2">
      <i class="fas fa-qrcode"></i> Scan QR Code
    </button>
    <?php endif; ?>
  </div>

  <!-- Event Selector -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Select Event</label>
    <form method="GET" class="flex gap-3">
      <select name="event_id" onchange="this.form.submit()"
              class="flex-1 px-4 py-2.5 rounded-lg border border-gray-200 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-300 bg-white">
        <option value="">— Choose an event —</option>
        <?php foreach ($myEvents as $ev): ?>
          <option value="<?= $ev['id'] ?>" <?= $eventId===$ev['id']?'selected':'' ?>>
            <?= htmlspecialchars($ev['title']) ?>
            (<?= $ev['start_date'] ? date('d M Y', strtotime($ev['start_date'])) : 'TBD' ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if ($selectedEvent): ?>

  <!-- Stats -->
  <div class="grid grid-cols-3 gap-4">
    <?php $statItems = [
      ['label'=>'Total Registered','count'=>$stats['total'],   'icon'=>'fa-users',        'color'=>'#582C83','bg'=>'#f0ebfa'],
      ['label'=>'Attended',        'count'=>$stats['attended'],'icon'=>'fa-circle-check',  'color'=>'#059669','bg'=>'#d1fae5'],
      ['label'=>'Pending',         'count'=>$stats['pending'], 'icon'=>'fa-clock',         'color'=>'#d97706','bg'=>'#fef3c7'],
    ];
    foreach ($statItems as $si): ?>
    <div class="stat-card bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" style="background:<?= $si['bg'] ?>;color:<?= $si['color'] ?>;">
        <i class="fas <?= $si['icon'] ?>"></i>
      </div>
      <div>
        <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide"><?= $si['label'] ?></p>
        <p class="text-xl font-bold text-gray-900"><?= $si['count'] ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Progress Bar -->
  <?php if ($stats['total'] > 0): $pct = round($stats['attended']/$stats['total']*100); ?>
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
    <div class="flex justify-between text-xs font-semibold text-gray-500 mb-2">
      <span>Attendance Rate</span>
      <span style="color:#059669;"><?= $pct ?>%</span>
    </div>
    <div class="w-full bg-gray-100 rounded-full h-2.5">
      <div class="h-2.5 rounded-full transition-all" style="width:<?= $pct ?>%;background:#059669;"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Filter + Search -->
  <form method="GET" class="flex flex-col sm:flex-row gap-3">
    <input type="hidden" name="event_id" value="<?= $eventId ?>">
    <div class="relative flex-1">
      <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
      <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
             placeholder="Search by name or matric no…"
             class="w-full pl-9 pr-4 py-2.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
    </div>
    <select name="filter" onchange="this.form.submit()"
            class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm bg-white text-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-300">
      <option value="">All Status</option>
      <option value="attended" <?= ($_GET['filter']??'')==='attended'?'selected':'' ?>>Attended</option>
      <option value="pending"  <?= ($_GET['filter']??'')==='pending'?'selected':'' ?>>Pending</option>
    </select>
    <button type="submit" class="btn-primary">Search</button>
  </form>

  <!-- Attendance Table -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 class="font-bold text-gray-800 text-sm">
        <?= htmlspecialchars($selectedEvent['title']) ?> — Registrations
      </h2>
      <span class="text-xs text-gray-400"><?= count($registrations) ?> shown</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Student</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Matric No</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Registered On</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Attendance</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50" id="attTable">
          <?php if (empty($registrations)): ?>
            <tr><td colspan="4" class="px-6 py-12 text-center text-gray-400">
              <i class="fas fa-users-slash text-3xl mb-3 block"></i>
              No registrations found.
            </td></tr>
          <?php else: foreach ($registrations as $reg):
            $isAttended = !empty($reg['attended_at']);
          ?>
            <tr class="hover-row" id="row-<?= $reg['id'] ?>">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center font-semibold text-xs shrink-0"
                       style="background:#f0ebfa;color:#582C83;"><?= strtoupper(substr($reg['student_name'],0,1)) ?></div>
                  <div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($reg['student_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= htmlspecialchars($reg['email']) ?></p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4 text-gray-600 font-mono text-xs"><?= htmlspecialchars($reg['matric_no']) ?></td>
              <td class="px-4 py-4 text-gray-500 text-xs"><?= date('d M Y', strtotime($reg['registered_at'])) ?></td>
              <td class="px-4 py-4">
                <div class="flex items-center justify-center gap-2">
                  <button class="att-btn att-attended <?= $isAttended ? 'active' : '' ?>"
                          onclick="markAttendance(<?= $reg['id'] ?>, 'attended', this)">
                    <i class="fas fa-check mr-1"></i>Attended
                  </button>
                  <button class="att-btn att-pending <?= !$isAttended ? 'active' : '' ?>"
                          onclick="markAttendance(<?= $reg['id'] ?>, 'pending', this)">
                    <i class="fas fa-clock mr-1"></i>Pending
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php elseif (!empty($myEvents)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 py-16 text-center text-gray-400">
      <i class="fas fa-hand-pointer text-4xl mb-3 block" style="color:#c4b5e8;"></i>
      <p class="font-semibold text-gray-600">Select an event above to manage attendance.</p>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 py-16 text-center text-gray-400">
      <i class="fas fa-calendar-xmark text-4xl mb-3 block" style="color:#c4b5e8;"></i>
      <p class="font-semibold text-gray-600">No events yet.</p>
      <a href="create_event.php" class="btn-primary inline-block mt-4">Create an Event</a>
    </div>
  <?php endif; ?>

</main>
</div>

<!-- ═══════════════════════════════════════
     QR SCANNER MODAL
════════════════════════════════════════ -->
<div id="qrModal" class="qr-modal">
  <div class="qr-box">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="font-bold text-gray-900 text-base">Scan Student QR Code</h3>
        <p class="text-xs text-gray-400 mt-0.5" id="qrEventLabel">
          <?= $selectedEvent ? htmlspecialchars($selectedEvent['title']) : '' ?>
        </p>
      </div>
      <button onclick="closeQRScanner()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100">
        <i class="fas fa-xmark"></i>
      </button>
    </div>

    <!-- Scanner -->
    <div id="qr-reader"></div>

    <!-- Manual input fallback -->
    <div class="mt-4">
      <p class="text-xs text-gray-400 text-center mb-2">— or enter matric no. manually —</p>
      <div class="flex gap-2">
        <input type="text" id="manualMatric" placeholder="e.g. CB22110001"
               class="flex-1 px-4 py-2.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300 uppercase"
               onkeydown="if(event.key==='Enter') manualScan()">
        <button onclick="manualScan()" class="btn-primary px-4">
          <i class="fas fa-arrow-right"></i>
        </button>
      </div>
    </div>

    <!-- Scan Result -->
    <div id="scanResult" class="scan-result mt-4">
      <div class="flex items-center gap-2">
        <i id="scanResultIcon" class="fas fa-check-circle"></i>
        <div>
          <p id="scanResultMsg" class="font-bold"></p>
          <p id="scanResultSub" class="text-xs opacity-80 mt-0.5"></p>
        </div>
      </div>
    </div>

    <!-- Scan log -->
    <div id="scanLog" class="mt-4 max-h-32 overflow-y-auto space-y-1 hidden">
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Recent Scans</p>
    </div>

    <!-- Close -->
    <button onclick="closeQRScanner()" class="mt-4 w-full btn-secondary py-2.5">
      Done
    </button>
  </div>
</div>

<?php include 'partials/modals_js.php'; ?>
<script>
const CURRENT_EVENT_ID = <?= $eventId ?: 'null' ?>;
let html5QrCode = null;
let scanCooldown = false;
const scanLogEntries = [];

// ── Manual attendance (table buttons) ────────────────────────
async function markAttendance(regId, status, clickedBtn) {
  const row  = document.getElementById('row-' + regId);
  const btns = row.querySelectorAll('.att-btn');
  btns.forEach(b => { b.classList.remove('active'); b.disabled = true; });
  clickedBtn.classList.add('active');

  try {
    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('reg_id', regId);
    fd.append('status', status);
    const res  = await fetch(window.location.href, { method:'POST', body:fd });
    const data = await res.json();
    if (!data.success) {
      alert('Error: ' + data.message);
      btns.forEach(b => b.classList.remove('active'));
    }
  } catch(e) {
    alert('Network error. Please refresh.');
  } finally {
    btns.forEach(b => b.disabled = false);
  }
}

// ── QR Scanner ────────────────────────────────────────────────
function openQRScanner() {
  if (!CURRENT_EVENT_ID) { alert('Please select an event first.'); return; }
  document.getElementById('qrModal').classList.add('open');
  document.getElementById('scanResult').style.display = 'none';
  document.getElementById('manualMatric').value = '';

  html5QrCode = new Html5Qrcode('qr-reader');
  html5QrCode.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: { width: 250, height: 250 } },
    onQRSuccess,
    () => {}
  ).catch(err => {
    document.getElementById('qr-reader').innerHTML =
      '<div class="py-10 text-center text-gray-400 text-sm"><i class="fas fa-camera-slash text-3xl mb-2 block"></i>Camera not available.<br>Use manual input below.</div>';
  });
}

function closeQRScanner() {
  document.getElementById('qrModal').classList.remove('open');
  if (html5QrCode) {
    html5QrCode.stop().catch(() => {}).finally(() => { html5QrCode = null; });
  }
}

function onQRSuccess(decodedText) {
  if (scanCooldown) return;
  scanCooldown = true;
  setTimeout(() => scanCooldown = false, 2000);
  processScan(decodedText.trim().toUpperCase());
}

function manualScan() {
  const val = document.getElementById('manualMatric').value.trim().toUpperCase();
  if (!val) return;
  processScan(val);
  document.getElementById('manualMatric').value = '';
}

async function processScan(matric) {
  showScanResult('scanning', `Checking ${matric}…`, '');

  try {
    const fd = new FormData();
    fd.append('qr_scan',  '1');
    fd.append('matric_no', matric);
    fd.append('event_id',  CURRENT_EVENT_ID);

    const res  = await fetch(window.location.href, { method:'POST', body:fd });
    const data = await res.json();

    if (data.success) {
      showScanResult('success', data.message, data.matric);
      addScanLog(data.student_name, data.matric, 'success');
      // Update table row if visible
      updateTableRow(data.reg_id);
    } else if (data.already) {
      showScanResult('warning', data.message, data.matric);
      addScanLog(data.student, data.matric, 'warning');
    } else {
      showScanResult('error', data.message, matric);
      addScanLog(matric, '', 'error');
    }
  } catch(e) {
    showScanResult('error', 'Network error. Try again.', '');
  }
}

function showScanResult(type, msg, sub) {
  const el   = document.getElementById('scanResult');
  const icon = document.getElementById('scanResultIcon');
  const msgEl= document.getElementById('scanResultMsg');
  const subEl= document.getElementById('scanResultSub');

  el.className = 'scan-result mt-4';
  el.style.display = 'block';
  msgEl.textContent = msg;
  subEl.textContent = sub;

  const configs = {
    success:  { cls:'scan-success', icon:'fa-check-circle' },
    warning:  { cls:'scan-warning', icon:'fa-exclamation-circle' },
    error:    { cls:'scan-error',   icon:'fa-times-circle' },
    scanning: { cls:'',             icon:'fa-spinner fa-spin' },
  };
  const c = configs[type] || configs.error;
  if (c.cls) el.classList.add(c.cls);
  icon.className = 'fas ' + c.icon;
}

function addScanLog(name, matric, type) {
  const log = document.getElementById('scanLog');
  log.classList.remove('hidden');
  const colors = { success:'text-emerald-700', warning:'text-amber-700', error:'text-red-700' };
  const icons  = { success:'fa-check', warning:'fa-exclamation', error:'fa-times' };
  const entry  = document.createElement('div');
  entry.className = 'flex items-center gap-2 text-xs ' + (colors[type] || '');
  entry.innerHTML = `<i class="fas ${icons[type]} w-3 text-center"></i><span class="font-semibold">${name}</span><span class="text-gray-400">${matric}</span><span class="ml-auto text-gray-300">${new Date().toLocaleTimeString('en-MY')}</span>`;
  log.appendChild(entry);
  log.scrollTop = log.scrollHeight;
}

function updateTableRow(regId) {
  if (!regId) return;
  const row = document.getElementById('row-' + regId);
  if (!row) return;
  const btns = row.querySelectorAll('.att-btn');
  btns.forEach(b => b.classList.remove('active'));
  const attendedBtn = row.querySelector('.att-attended');
  if (attendedBtn) attendedBtn.classList.add('active');
}

// Close modal on backdrop click
document.getElementById('qrModal').addEventListener('click', function(e) {
  if (e.target === this) closeQRScanner();
});
</script>
</body>
</html>