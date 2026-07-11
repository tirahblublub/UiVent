<?php
require_once '../config.php';
requireSuperAdmin();

$activePage = 'student_management';
$pageTitle  = 'Student Management';

$faculties = [
  'FSM'  => 'Fakulti Sains Matematik',
  'FSKM' => 'Fakulti Sains Komputer & Matematik',
  'FSPP' => 'Fakulti Sains Pentadbiran & Pengajian Polisi',
  'FP'   => 'Fakulti Perladangan',
  'FPP'  => 'Fakulti Pengurusan Perniagaan',
  'ACIS' => 'Akademi Pengajian Islam Kontemporari',
  'FSR'  => 'Fakulti Sains Sukan & Rekreasi',
];

// ── AJAX Actions ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['student_id'])) {
  $studentId = (int) $_POST['student_id'];
  $action    = $_POST['action'];

  try {
    if ($action === 'suspend') {
      db()->prepare("UPDATE students SET is_active = 0 WHERE id = ?")->execute([$studentId]);
      logAction('SUSPEND_STUDENT', "Student ID: $studentId");
      jsonResponse(true, 'Student suspended successfully.');

    } elseif ($action === 'unsuspend') {
      db()->prepare("UPDATE students SET is_active = 1 WHERE id = ?")->execute([$studentId]);
      logAction('UNSUSPEND_STUDENT', "Student ID: $studentId");
      jsonResponse(true, 'Student unsuspended successfully.');

    } elseif ($action === 'delete') {
      $nameStmt = db()->prepare("SELECT name FROM students WHERE id = ?");
      $nameStmt->execute([$studentId]);
      $studentName = $nameStmt->fetchColumn() ?? "ID $studentId";
      db()->prepare("DELETE FROM students WHERE id = ?")->execute([$studentId]);
      logAction('DELETE_STUDENT', $studentName);
      jsonResponse(true, 'Student deleted successfully.');

    } else {
      jsonResponse(false, 'Invalid action.');
    }
  } catch (PDOException $e) {
    jsonResponse(false, 'Database error: ' . $e->getMessage());
  }
}

// ── Fetch Students ─────────────────────────────────────────────────────────
try {
  $students = db()->query("
    SELECT s.id, s.name, s.email, s.matric_no, s.is_active, s.created_at,
           s.faculty, s.year, c.name AS campus_name
    FROM students s
    LEFT JOIN campuses c ON c.id = s.campus_id
    ORDER BY s.created_at DESC
  ")->fetchAll();
} catch (PDOException $e) {
  $students = [];
}

$totalStudents  = count($students);
$activeCount    = count(array_filter($students, fn($s) => $s['is_active'] == 1));
$suspendedCount = count(array_filter($students, fn($s) => $s['is_active'] == 0));

$pendingClubs     = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='pending'")->fetchColumn();
$pendingApprovals = (int) db()->query("SELECT COUNT(*) FROM escalated_approvals WHERE status='pending'")->fetchColumn();

function getInitials(string $name): string {
  $parts = explode(' ', trim($name));
  $first = strtoupper(substr($parts[0] ?? '', 0, 1));
  $last  = strtoupper(substr(end($parts) ?? '', 0, 1));
  return $first !== $last ? $first . $last : $first;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Student Management</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
<style>
  .avatar {
    width:36px; height:36px; border-radius:50%;
    background:#582C83; color:#F9A51B;
    font-weight:700; font-size:12px;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
  }
  .avatar-lg { width:52px; height:52px; font-size:16px; }

  .badge-active    { background:#D1FAE5; color:#065F46; }
  .badge-suspended { background:#FEE2E2; color:#991B1B; }

  .data-table thead th {
    background:rgba(88,44,131,0.07); color:#582C83;
    font-size:11px; font-weight:700; letter-spacing:.08em;
    text-transform:uppercase; padding:10px 16px;
  }
  .data-table tbody tr { border-bottom:1px solid rgba(88,44,131,0.07); transition:background .12s; }
  .data-table tbody tr:hover { background:rgba(88,44,131,0.04); }
  .data-table tbody td { padding:13px 16px; font-size:13.5px; }

  .search-input {
    border:1px solid rgba(88,44,131,0.2); border-radius:8px;
    padding:8px 12px 8px 36px; font-size:13px; width:240px;
    outline:none; background:#fff; color:#1e1e2e; transition:border-color .15s;
  }
  .search-input:focus { border-color:#582C83; box-shadow:0 0 0 3px rgba(88,44,131,0.12); }

  select.filter-select {
    border:1px solid rgba(88,44,131,0.2); border-radius:8px;
    padding:8px 32px 8px 12px; font-size:13px; color:#1e1e2e;
    background:#fff; appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23582C83' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 10px center;
    outline:none; transition:border-color .15s;
  }
  select.filter-select:focus { border-color:#582C83; box-shadow:0 0 0 3px rgba(88,44,131,0.12); }

  /* Detail modal */
  .detail-modal-overlay {
    position:fixed; inset:0; background:rgba(0,0,0,0.5);
    display:flex; align-items:center; justify-content:center;
    z-index:60; opacity:0; pointer-events:none; transition:opacity .2s;
  }
  .detail-modal-overlay.open { opacity:1; pointer-events:all; }
  .detail-modal-box {
    background:#fff; border-radius:16px; width:520px; max-width:95vw;
    max-height:90vh; overflow-y:auto;
    transform:translateY(12px); transition:transform .2s;
    box-shadow:0 20px 60px rgba(39,19,74,0.25);
  }
  .detail-modal-overlay.open .detail-modal-box { transform:translateY(0); }

  .detail-row { display:flex; gap:8px; padding:9px 0; border-bottom:1px solid rgba(88,44,131,0.07); font-size:13px; }
  .detail-row:last-child { border-bottom:none; }
  .detail-label { color:#6B7280; width:140px; flex-shrink:0; font-weight:500; }
  .detail-val   { color:#1e1e2e; font-weight:600; }

  .btn-ghost {
    border:1px solid rgba(88,44,131,0.25); color:#582C83;
    padding:7px 16px; border-radius:8px; font-size:13px; font-weight:500;
    background:#fff; transition:all .15s;
  }
  .btn-ghost:hover { border-color:#582C83; background:rgba(88,44,131,0.05); }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<div class="flex-1">
<main class="p-6 md:p-8 space-y-6 max-w-7xl w-full mx-auto">

  <!-- Page Header -->
  <div class="flex items-center gap-3 mb-2">
    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white text-sm" style="background:#582C83;">
      <i class="fas fa-user-graduate"></i>
    </span>
    <div>
      <h3 class="text-2xl font-bold text-gray-900">Student Management</h3>
      <p class="text-sm text-gray-500">View, search, filter and manage student accounts. All changes are logged.</p>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="grid grid-cols-3 gap-5">
    <div class="stat-card bg-white rounded-xl border p-5 flex items-center gap-4" style="border-color:rgba(88,44,131,0.1);">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl" style="background:#582C83;">
        <i class="fas fa-user-graduate"></i>
      </div>
      <div>
        <p class="text-2xl font-extrabold" style="color:#27134A;"><?= $totalStudents ?></p>
        <p class="text-xs font-semibold text-gray-400">Total Students</p>
      </div>
    </div>
    <div class="stat-card bg-white rounded-xl border p-5 flex items-center gap-4" style="border-color:rgba(88,44,131,0.1);">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl" style="background:#10B981;">
        <i class="fas fa-circle-check"></i>
      </div>
      <div>
        <p class="text-2xl font-extrabold" style="color:#27134A;"><?= $activeCount ?></p>
        <p class="text-xs font-semibold text-gray-400">Active</p>
      </div>
    </div>
    <div class="stat-card bg-white rounded-xl border p-5 flex items-center gap-4" style="border-color:rgba(88,44,131,0.1);">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl" style="background:#EF4444;">
        <i class="fas fa-ban"></i>
      </div>
      <div>
        <p class="text-2xl font-extrabold" style="color:#27134A;"><?= $suspendedCount ?></p>
        <p class="text-xs font-semibold text-gray-400">Suspended</p>
      </div>
    </div>
  </div>

  <!-- Table Card -->
  <div class="bg-white rounded-2xl border overflow-hidden" style="border-color:rgba(88,44,131,0.1);box-shadow:0 1px 4px rgba(39,19,74,0.06);">

    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-3 px-5 py-4 border-b" style="border-color:rgba(88,44,131,0.08);">
      <div class="relative">
        <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-purple-300 text-xs"></i>
        <input type="text" id="searchInput" class="search-input" placeholder="Search name or matric…">
      </div>
      <select id="facultyFilter" class="filter-select">
        <option value="">All Faculties</option>
        <?php foreach ($faculties as $code => $name): ?>
          <option value="<?= $code ?>"><?= $code ?> — <?= $name ?></option>
        <?php endforeach; ?>
      </select>
      <select id="statusFilter" class="filter-select">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="suspended">Suspended</option>
      </select>
      <span id="resultCount" class="ml-auto text-xs font-semibold text-gray-400"></span>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="data-table w-full">
        <thead>
          <tr>
            <th class="text-left">Student</th>
            <th class="text-left">Matric No.</th>
            <th class="text-left">Faculty</th>
            <th class="text-left">Year</th>
            <th class="text-left">Status</th>
            <th class="text-left">Actions</th>
          </tr>
        </thead>
        <tbody id="studentTableBody">
          <?php if (empty($students)): ?>
            <tr>
              <td colspan="6" class="text-center py-16 text-sm text-gray-400">
                <i class="fas fa-user-graduate text-4xl mb-3 block" style="color:#D1BBF0;"></i>
                No students found in the database.
              </td>
            </tr>
          <?php else: ?>
          <?php foreach ($students as $s):
            $isActive = $s['is_active'] == 1;
            $status   = $isActive ? 'active' : 'suspended';
            $faculty  = $s['faculty'] ?? '';
            $year     = $s['year'] ?? '';
            $initials = getInitials($s['name']);
          ?>
          <tr class="student-row"
              data-name="<?= strtolower(htmlspecialchars($s['name'])) ?>"
              data-matric="<?= strtolower($s['matric_no'] ?? '') ?>"
              data-faculty="<?= htmlspecialchars($faculty) ?>"
              data-status="<?= $status ?>">

            <td>
              <div class="flex items-center gap-3">
                <div class="avatar"><?= $initials ?></div>
                <div>
                  <p class="font-semibold text-sm text-gray-900"><?= htmlspecialchars($s['name']) ?></p>
                  <p class="text-xs text-gray-400"><?= htmlspecialchars($s['email']) ?></p>
                </div>
              </div>
            </td>
            <td class="font-mono text-xs font-semibold" style="color:#582C83;"><?= htmlspecialchars($s['matric_no'] ?? '—') ?></td>
            <td>
              <?php if ($faculty): ?>
                <span class="text-xs font-bold px-2 py-0.5 rounded-md" style="background:rgba(88,44,131,0.1);color:#582C83;"><?= htmlspecialchars($faculty) ?></span>
              <?php else: ?>
                <span class="text-xs text-gray-400">—</span>
              <?php endif; ?>
            </td>
            <td class="text-sm font-medium text-gray-700"><?= $year ? 'Year '.htmlspecialchars($year) : '—' ?></td>
            <td>
              <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $isActive ? 'badge-active' : 'badge-suspended' ?>">
                <?= $isActive ? 'Active' : 'Suspended' ?>
              </span>
            </td>
            <td>
              <div class="flex items-center gap-2">
                <!-- View -->
                <button onclick='openDetailModal(<?= json_encode([
                  "id"      => $s["id"],
                  "name"    => $s["name"],
                  "email"   => $s["email"],
                  "matric"  => $s["matric_no"] ?? "",
                  "faculty" => $faculty,
                  "year"    => $year,
                  "status"  => $status,
                  "joined"  => $s["created_at"],
                  "campus"  => $s["campus_name"] ?? "UiTM Machang",
                  "initials"=> $initials,
                ]) ?>)'
                  class="w-8 h-8 rounded-lg flex items-center justify-center"
                  style="background:rgba(88,44,131,0.1);color:#582C83;" title="View details">
                  <i class="fas fa-eye text-xs"></i>
                </button>

                <!-- Suspend / Unsuspend — guna openConfirm dari modals_js.php -->
                <?php if ($isActive): ?>
                <button onclick='doStudentAction(<?= $s["id"] ?>, "<?= addslashes($s["name"]) ?>", "suspend")'
                  class="w-8 h-8 rounded-lg flex items-center justify-center"
                  style="background:rgba(239,68,68,0.1);color:#991B1B;" title="Suspend student">
                  <i class="fas fa-ban text-xs"></i>
                </button>
                <?php else: ?>
                <button onclick='doStudentAction(<?= $s["id"] ?>, "<?= addslashes($s["name"]) ?>", "unsuspend")'
                  class="w-8 h-8 rounded-lg flex items-center justify-center"
                  style="background:rgba(16,185,129,0.12);color:#065F46;" title="Unsuspend student">
                  <i class="fas fa-lock-open text-xs"></i>
                </button>
                <?php endif; ?>

                <!-- Delete -->
                <button onclick='doStudentAction(<?= $s["id"] ?>, "<?= addslashes($s["name"]) ?>", "delete")'
                  class="w-8 h-8 rounded-lg flex items-center justify-center"
                  style="background:rgba(107,114,128,0.1);color:#6B7280;" title="Delete student">
                  <i class="fas fa-trash-can text-xs"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Empty filtered state -->
    <div id="emptyState" class="hidden py-16 text-center">
      <i class="fas fa-magnifying-glass text-3xl mb-3" style="color:#D1BBF0;"></i>
      <p class="text-sm font-semibold" style="color:#582C83;">No students match your filters</p>
      <p class="text-xs mt-1 text-gray-400">Try a different search or clear the filters.</p>
    </div>

    <!-- Pagination -->
    <div class="px-5 py-3 border-t flex items-center justify-between" style="border-color:rgba(88,44,131,0.08);">
      <span class="text-xs text-gray-400">Showing <span id="shownCount"><?= $totalStudents ?></span> of <?= $totalStudents ?> students</span>
    </div>
  </div>

</main>
</div>
</div>

<!-- DETAIL MODAL (separate from confirmModal in modals_js.php) -->
<div id="detailModal" class="detail-modal-overlay" onclick="if(event.target===this)closeDetailModal()">
  <div class="detail-modal-box" onclick="event.stopPropagation()">
    <div class="flex items-center gap-4 px-6 pt-6 pb-4 border-b" style="border-color:rgba(88,44,131,0.1);">
      <div id="dAvatar" class="avatar avatar-lg"></div>
      <div class="flex-1 min-w-0">
        <h2 id="dName" class="text-base font-bold truncate" style="color:#27134A;"></h2>
        <p id="dMatric" class="text-xs font-mono font-semibold mt-0.5" style="color:#582C83;"></p>
      </div>
      <span id="dBadge" class="text-xs font-bold px-3 py-1 rounded-full"></span>
      <button onclick="closeDetailModal()" class="text-gray-400 hover:text-gray-600 ml-2">
        <i class="fas fa-xmark text-lg"></i>
      </button>
    </div>
    <div class="px-6 py-5">
      <p class="text-xs font-bold uppercase tracking-widest mb-3 text-gray-400">Account Information</p>
      <div class="detail-row"><span class="detail-label">Full Name</span><span id="dFullName" class="detail-val"></span></div>
      <div class="detail-row"><span class="detail-label">Email</span><span id="dEmail" class="detail-val"></span></div>
      <div class="detail-row"><span class="detail-label">Campus</span><span id="dCampus" class="detail-val"></span></div>
      <div class="detail-row"><span class="detail-label">Faculty</span><span id="dFaculty" class="detail-val"></span></div>
      <div class="detail-row"><span class="detail-label">Year of Study</span><span id="dYear" class="detail-val"></span></div>
      <div class="detail-row"><span class="detail-label">Registered Since</span><span id="dJoined" class="detail-val"></span></div>
      <div class="detail-row"><span class="detail-label">Account Status</span><span id="dStatus" class="detail-val"></span></div>
    </div>
    <div class="px-6 pb-6 flex gap-3 justify-end border-t pt-4" style="border-color:rgba(88,44,131,0.1);">
      <button id="dActionBtn" class="btn-primary flex items-center gap-2"></button>
      <button onclick="closeDetailModal()" class="btn-ghost">Close</button>
    </div>
  </div>
</div>

<?php include 'partials/modals_js.php'; ?>

<script>
const facultyNames = <?= json_encode($faculties) ?>;

// ── Filter ────────────────────────────────────────────────────────
function applyFilters() {
  const q   = document.getElementById('searchInput').value.toLowerCase().trim();
  const fac = document.getElementById('facultyFilter').value;
  const st  = document.getElementById('statusFilter').value;
  let visible = 0;

  document.querySelectorAll('.student-row').forEach(row => {
    const show = (!q   || row.dataset.name.includes(q) || row.dataset.matric.includes(q))
              && (!fac || row.dataset.faculty === fac)
              && (!st  || row.dataset.status === st);
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  document.getElementById('emptyState').classList.toggle('hidden', visible > 0);
  document.getElementById('shownCount').textContent  = visible;
  document.getElementById('resultCount').textContent = visible + ' result' + (visible !== 1 ? 's' : '');
}
document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('facultyFilter').addEventListener('change', applyFilters);
document.getElementById('statusFilter').addEventListener('change', applyFilters);
applyFilters();

// ── Detail Modal ──────────────────────────────────────────────────
let _currentStudent = null;

function openDetailModal(s) {
  _currentStudent = s;
  const isSusp = s.status === 'suspended';

  document.getElementById('dAvatar').textContent   = s.initials;
  document.getElementById('dName').textContent     = s.name;
  document.getElementById('dMatric').textContent   = s.matric || '—';
  document.getElementById('dFullName').textContent = s.name;
  document.getElementById('dEmail').textContent    = s.email;
  document.getElementById('dCampus').textContent   = s.campus || 'UiTM Machang';
  document.getElementById('dFaculty').textContent  = s.faculty ? s.faculty + ' — ' + (facultyNames[s.faculty] || '') : '—';
  document.getElementById('dYear').textContent     = s.year ? 'Year ' + s.year : '—';
  document.getElementById('dJoined').textContent   = new Date(s.joined).toLocaleDateString('en-MY',{day:'numeric',month:'long',year:'numeric'});

  const dStatus = document.getElementById('dStatus');
  dStatus.textContent = isSusp ? 'Suspended' : 'Active';
  dStatus.className   = 'detail-val ' + (isSusp ? 'text-red-600' : 'text-emerald-600');

  const badge = document.getElementById('dBadge');
  badge.textContent = isSusp ? 'Suspended' : 'Active';
  badge.className   = 'text-xs font-bold px-3 py-1 rounded-full ' + (isSusp ? 'badge-suspended' : 'badge-active');

  const btn = document.getElementById('dActionBtn');
  btn.innerHTML        = isSusp ? '<i class="fas fa-lock-open mr-1"></i> Unsuspend' : '<i class="fas fa-ban mr-1"></i> Suspend';
  btn.style.background = isSusp ? '#10B981' : '#EF4444';
  btn.onclick = () => { closeDetailModal(); doStudentAction(s.id, s.name, isSusp ? 'unsuspend' : 'suspend'); };

  document.getElementById('detailModal').classList.add('open');
}
function closeDetailModal() {
  document.getElementById('detailModal').classList.remove('open');
}

// ── Student Actions — guna openConfirm dari modals_js.php ─────────
function doStudentAction(studentId, studentName, action) {
  const cfg = {
    suspend:   { title:'Suspend Student',       body:`Suspend ${studentName}? They will be blocked from logging in.`,          btn:'Suspend',           color:'red'    },
    unsuspend: { title:'Unsuspend Student',      body:`Restore access for ${studentName}? They can log in again.`,             btn:'Unsuspend',         color:'green'  },
    delete:    { title:'Delete Student Account', body:`Permanently delete ${studentName}? This cannot be undone.`,             btn:'Delete Permanently', color:'red'   },
  };
  const c = cfg[action];

  // Guna openConfirm dari modals_js.php
  openConfirm(action, c.title, c.body, c.btn, c.color, () => {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('student_id', studentId);

    fetch(window.location.href, { method:'POST', body:fd })
      .then(r => r.json())
      .then(data => {
        if (!data.success) { showToast(data.message, true); return; }
        showToast(data.message);

        const row = [...document.querySelectorAll('.student-row')]
                    .find(r => r.querySelector('p.font-semibold')?.textContent === studentName);
        if (!row) { setTimeout(() => location.reload(), 800); return; }

        if (action === 'delete') {
          row.style.opacity = '0'; row.style.transition = 'opacity .3s';
          setTimeout(() => { row.remove(); applyFilters(); }, 300);
        } else {
          const isNowSusp    = action === 'suspend';
          row.dataset.status = isNowSusp ? 'suspended' : 'active';

          const badge = row.querySelector('span.text-xs.font-bold.px-2\\.5');
          if (badge) {
            badge.textContent = isNowSusp ? 'Suspended' : 'Active';
            badge.className   = 'text-xs font-bold px-2.5 py-1 rounded-full ' + (isNowSusp ? 'badge-suspended' : 'badge-active');
          }

          // Update suspend/unsuspend button in row
          const btns = row.querySelectorAll('button');
          const suspBtn = btns[1];
          if (suspBtn) {
            suspBtn.style.background = isNowSusp ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.1)';
            suspBtn.style.color      = isNowSusp ? '#065F46' : '#991B1B';
            suspBtn.querySelector('i').className = 'fas ' + (isNowSusp ? 'fa-lock-open' : 'fa-ban') + ' text-xs';
            suspBtn.title   = isNowSusp ? 'Unsuspend student' : 'Suspend student';
            suspBtn.onclick = () => doStudentAction(studentId, studentName, isNowSusp ? 'unsuspend' : 'suspend');
          }
          applyFilters();
        }
      })
      .catch(() => showToast('Network error. Please try again.', true));
  });
}
</script>
</body>
</html>