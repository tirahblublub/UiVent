<?php
require_once '../config.php';
requireSuperAdmin();

$activePage = 'academic_sessions';
$pageTitle  = 'Academic Session Management';

$pendingClubs     = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='pending'")->fetchColumn();
$pendingApprovals = (int) db()->query("SELECT COUNT(*) FROM escalated_approvals WHERE status='pending'")->fetchColumn();

// ── AJAX handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $label = trim($_POST['label'] ?? '');
        $year  = trim($_POST['year']  ?? '');
        $sem   = (int)($_POST['semester'] ?? 1);
        $start = $_POST['start_date'] ?: null;
        $end   = $_POST['end_date']   ?: null;
        if (!$label || !$year) jsonResponse(false, 'Semester label and academic year are required.');
        $stmt = db()->prepare("INSERT INTO academic_sessions (label,year,semester,start_date,end_date) VALUES (?,?,?,?,?)");
        $stmt->execute([$label, $year, $sem, $start, $end]);
        logAction('ADD_ACADEMIC_SESSION', $label);
        jsonResponse(true, 'Academic session added successfully.');
    }

    if ($action === 'activate') {
        $id = (int)($_POST['id'] ?? 0);
        db()->exec("UPDATE academic_sessions SET is_active=0");
        db()->prepare("UPDATE academic_sessions SET is_active=1 WHERE id=?")->execute([$id]);
        // sync global_config label
        $labelStmt = db()->prepare("SELECT label FROM academic_sessions WHERE id=?");
        $labelStmt->execute([$id]);
        $row = $labelStmt->fetchColumn() ?: '';
        db()->prepare("UPDATE global_config SET config_value=? WHERE config_key='academic_term_label'")->execute([$row]);
        logAction('ACTIVATE_SEMESTER', "ID $id");
        jsonResponse(true, 'Semester activated.');
    }

    if ($action === 'close') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("UPDATE academic_sessions SET is_active=0, end_date=CURDATE() WHERE id=? AND end_date > CURDATE()")->execute([$id]);
        logAction('CLOSE_SEMESTER', "ID $id");
        jsonResponse(true, 'Semester closed.');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $active = (int) db()->query("SELECT is_active FROM academic_sessions WHERE id=$id")->fetchColumn();
        if ($active) jsonResponse(false, 'Cannot delete the currently active semester.');
        db()->prepare("DELETE FROM academic_sessions WHERE id=?")->execute([$id]);
        logAction('DELETE_SEMESTER', "ID $id");
        jsonResponse(true, 'Record deleted.');
    }
}

$sessions = db()->query("SELECT * FROM academic_sessions ORDER BY year DESC, semester DESC")->fetchAll();
$active   = db()->query("SELECT id FROM academic_sessions WHERE is_active=1 LIMIT 1")->fetchColumn();

// ── Summary counts ────────────────────────────────────────────
$totalSessions = count($sessions);
$activeCount   = 0;
$closedCount   = 0;
foreach ($sessions as $s) {
    if ($s['is_active']) { $activeCount++; continue; }
    if ($s['end_date'] && $s['end_date'] < date('Y-m-d')) $closedCount++;
}
$inactiveCount = $totalSessions - $activeCount - $closedCount;
$activeLabel   = null;
foreach ($sessions as $s) { if ($s['is_active']) { $activeLabel = $s['label']; break; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>UiVent | Academic Sessions</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
<style>
  body { font-family:'Inter',ui-sans-serif,system-ui,sans-serif; }
  .font-display { font-family:'Plus Jakarta Sans',ui-sans-serif,system-ui,sans-serif; }

  .sessions-bg {
    background:
      radial-gradient(circle at 100% 0%, rgba(88,44,131,0.06) 0%, transparent 45%),
      radial-gradient(circle at 0% 20%, rgba(249,165,27,0.05) 0%, transparent 40%),
      #f6f5f9;
  }

  @keyframes riseIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
  .rise-in { animation: riseIn .5s cubic-bezier(.16,1,.3,1) both; }
  @media (prefers-reduced-motion: reduce) { .rise-in { animation:none; } }

  .eyebrow { font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; font-size:10.5px; letter-spacing:.12em; text-transform:uppercase; }

  .stat-card { position:relative; overflow:hidden; transition:transform .25s ease, box-shadow .25s ease; }
  .stat-card:hover { transform:translateY(-3px); box-shadow:0 12px 28px -12px rgba(39,19,74,0.18); }
  .stat-card::before { content:''; position:absolute; inset:0 0 auto 0; height:3px; background:var(--accent,#582C83); }

  .action-btn { transition:transform .15s ease, opacity .15s ease; }
  .action-btn:hover { transform:translateY(-1px); }

  #addModal .modal-panel { animation: riseIn .3s cubic-bezier(.16,1,.3,1) both; }
</style>
</head>
<body class="sessions-bg font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<div class="flex-1">
<main class="p-6 md:p-8 space-y-6 max-w-4xl w-full mx-auto">

  <!-- Header -->
  <div class="relative overflow-hidden flex flex-col sm:flex-row justify-between items-start sm:items-center p-7 rounded-2xl text-white gap-4 rise-in shadow-lg"
       style="background:linear-gradient(135deg,#27134A 0%,#582C83 100%);">
    <div class="pointer-events-none absolute -top-16 -right-10 w-56 h-56 rounded-full" style="background:radial-gradient(circle,rgba(249,165,27,0.22) 0%,transparent 70%);"></div>
    <div class="pointer-events-none absolute -bottom-20 -left-10 w-64 h-64 rounded-full" style="background:radial-gradient(circle,rgba(255,255,255,0.08) 0%,transparent 70%);"></div>

    <div class="relative">
      <p class="eyebrow mb-1.5" style="color:#F9A51B;">Superadmin</p>
      <h3 class="font-display text-[26px] font-bold tracking-tight">Academic Session Management</h3>
      <p class="text-sm mt-1.5 text-purple-200">Add, activate, or close academic semesters</p>
    </div>
    <button onclick="document.getElementById('addModal').classList.remove('hidden')"
            class="relative flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-sm transition hover:opacity-90 shadow-sm"
            style="background:#F9A51B;color:#27134A;">
      <i class="fas fa-plus"></i> Add Semester
    </button>
  </div>

  <!-- Toast -->
  <div id="toast" class="hidden fixed top-6 right-6 z-50 px-5 py-3 rounded-xl shadow-lg text-white text-sm font-semibold" style="background:#582C83;"></div>

  <!-- Stat Cards -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="stat-card rise-in bg-white p-5 rounded-xl shadow-sm border border-gray-100" style="--accent:#582C83; animation-delay:0ms;">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Sessions</p>
      <h4 class="font-display text-2xl font-bold text-gray-900 mt-1"><?= number_format($totalSessions) ?></h4>
      <p class="text-xs mt-0.5 font-medium" style="color:#582C83;">all-time records</p>
    </div>
    <div class="stat-card rise-in bg-white p-5 rounded-xl shadow-sm border border-gray-100" style="--accent:#059669; animation-delay:60ms;">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Active Semester</p>
      <h4 class="font-display text-lg font-bold text-gray-900 mt-1 truncate"><?= $activeLabel ? htmlspecialchars($activeLabel) : '—' ?></h4>
      <p class="text-xs mt-0.5 text-emerald-600 font-medium"><?= $activeLabel ? 'currently running' : 'none active' ?></p>
    </div>
    <div class="stat-card rise-in bg-white p-5 rounded-xl shadow-sm border border-gray-100" style="--accent:#dc2626; animation-delay:120ms;">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Closed</p>
      <h4 class="font-display text-2xl font-bold text-gray-900 mt-1"><?= number_format($closedCount) ?></h4>
      <p class="text-xs mt-0.5 text-red-600 font-medium">past semesters</p>
    </div>
    <div class="stat-card rise-in bg-white p-5 rounded-xl shadow-sm border border-gray-100" style="--accent:#6b7280; animation-delay:180ms;">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Inactive</p>
      <h4 class="font-display text-2xl font-bold text-gray-900 mt-1"><?= number_format($inactiveCount) ?></h4>
      <p class="text-xs mt-0.5 text-gray-400 font-medium">not yet started</p>
    </div>
  </div>

  <!-- Sessions Table -->
  <div class="rise-in bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" style="animation-delay:220ms;">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h4 class="font-display font-bold text-gray-800">Academic Sessions List</h4>
      <span class="badge" style="background:#f0ebfa;color:#582C83;"><?= count($sessions) ?> record<?= count($sessions) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="scrollable">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
          <tr>
            <th class="px-5 py-3 text-left">Label</th>
            <th class="px-5 py-3 text-left">Year</th>
            <th class="px-5 py-3 text-left">Sem</th>
            <th class="px-5 py-3 text-left">Start</th>
            <th class="px-5 py-3 text-left">End</th>
            <th class="px-5 py-3 text-left">Status</th>
            <th class="px-5 py-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($sessions)): ?>
          <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">
            <i class="fas fa-calendar-xmark text-2xl mb-2 block"></i>
            No academic session records yet.
          </td></tr>
          <?php endif; ?>
          <?php foreach ($sessions as $s): ?>
          <tr class="hover-row">
            <td class="px-5 py-3 font-semibold text-gray-800"><?= htmlspecialchars($s['label']) ?></td>
            <td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($s['year']) ?></td>
            <td class="px-5 py-3 text-gray-600">Sem <?= $s['semester'] ?></td>
            <td class="px-5 py-3 text-gray-500"><?= $s['start_date'] ? date('d M Y', strtotime($s['start_date'])) : '—' ?></td>
            <td class="px-5 py-3 text-gray-500"><?= $s['end_date']   ? date('d M Y', strtotime($s['end_date']))   : '—' ?></td>
            <td class="px-5 py-3">
              <?php if ($s['is_active']): ?>
                <span class="badge" style="background:#d1fae5;color:#065f46;">● Active</span>
              <?php elseif ($s['end_date'] && $s['end_date'] < date('Y-m-d')): ?>
                <span class="badge" style="background:#fee2e2;color:#991b1b;">Closed</span>
              <?php else: ?>
                <span class="badge" style="background:#f3f4f6;color:#6b7280;">Inactive</span>
              <?php endif; ?>
            </td>
            <td class="px-5 py-3">
              <div class="flex items-center justify-center gap-2">
                <?php if (!$s['is_active']): ?>
                <button onclick="doAction('activate',<?= $s['id'] ?>,'Activate this semester?')"
                        class="action-btn text-xs px-3 py-1 rounded-lg font-semibold"
                        style="background:#d1fae5;color:#065f46;" title="Activate">
                  <i class="fas fa-circle-check mr-1"></i>Activate
                </button>
                <?php endif; ?>
                <?php if ($s['is_active']): ?>
                <button onclick="doAction('close',<?= $s['id'] ?>,'Close this semester now?')"
                        class="action-btn text-xs px-3 py-1 rounded-lg font-semibold"
                        style="background:#fee2e2;color:#991b1b;">
                  <i class="fas fa-lock mr-1"></i>Close
                </button>
                <?php endif; ?>
                <?php if (!$s['is_active']): ?>
                <button onclick="doAction('delete',<?= $s['id'] ?>,'Delete this record? This action cannot be undone.')"
                        class="action-btn text-xs px-2 py-1 rounded-lg text-gray-400 hover:text-red-500">
                  <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</div>
</div>

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5);">
  <div class="modal-panel bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-7">
    <div class="flex items-center justify-between mb-5">
      <h5 class="font-display font-bold text-gray-800 text-lg flex items-center gap-2">
        <i class="fas fa-calendar-plus text-sm" style="color:#582C83;"></i> Add New Semester
      </h5>
      <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <div class="space-y-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Semester Label *</label>
        <input id="f_label" type="text" placeholder="e.g. Semester 1, 2026/2027"
               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:border-purple-400 transition-colors" style="--tw-ring-color:#582C83;">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Academic Year *</label>
          <input id="f_year" type="text" placeholder="2026/2027"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:border-purple-400 transition-colors" style="--tw-ring-color:#582C83;">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Semester</label>
          <select id="f_sem" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:border-purple-400 transition-colors" style="--tw-ring-color:#582C83;">
            <option value="1">Semester 1</option>
            <option value="2">Semester 2</option>
            <option value="3">Short Semester</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Start Date</label>
          <input id="f_start" type="date" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:border-purple-400 transition-colors" style="--tw-ring-color:#582C83;">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">End Date</label>
          <input id="f_end" type="date" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:border-purple-400 transition-colors" style="--tw-ring-color:#582C83;">
        </div>
      </div>
    </div>
    <div class="flex gap-3 mt-6">
      <button onclick="document.getElementById('addModal').classList.add('hidden')"
              class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold hover:bg-gray-50 transition-colors">Cancel</button>
      <button onclick="submitAdd()" class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition hover:opacity-90" style="background:#582C83;">Save</button>
    </div>
  </div>
</div>

<script>
function toast(msg, ok=true) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = ok ? '#059669' : '#dc2626';
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), 3000);
}

async function doAction(action, id, confirm_msg) {
  if (!confirm(confirm_msg)) return;
  const fd = new FormData();
  fd.append('action', action);
  fd.append('id', id);
  const r = await fetch('', {method:'POST', body:fd});
  const d = await r.json();
  toast(d.message, d.success);
  if (d.success) setTimeout(() => location.reload(), 900);
}

async function submitAdd() {
  const fd = new FormData();
  fd.append('action','add');
  fd.append('label',    document.getElementById('f_label').value);
  fd.append('year',     document.getElementById('f_year').value);
  fd.append('semester', document.getElementById('f_sem').value);
  fd.append('start_date', document.getElementById('f_start').value);
  fd.append('end_date',   document.getElementById('f_end').value);
  const r = await fetch('', {method:'POST', body:fd});
  const d = await r.json();
  toast(d.message, d.success);
  if (d.success) setTimeout(() => location.reload(), 900);
}
</script>
</body>
</html>