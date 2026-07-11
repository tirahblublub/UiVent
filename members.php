<?php
require_once '../config.php';
requireAdmin();

$activePage = 'members';
$pageTitle  = 'Members';
$adminId    = $_SESSION['admin_id'];

// ── AJAX: Remove member ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['student_id'])) {
    $studentId = (int)$_POST['student_id'];
    if ($_POST['action'] === 'remove') {
        $stmt = db()->prepare("
            UPDATE registrations r
            JOIN events e ON e.id = r.event_id
            SET r.status = 'cancelled'
            WHERE r.student_id = ? AND e.created_by = ?
        ");
        $stmt->execute([$studentId, $adminId]);
        jsonResponse(true, 'Member removed successfully.');
    }
    jsonResponse(false, 'Invalid action.');
}

// ── Filters ───────────────────────────────────────────────────
$search      = trim($_GET['q'] ?? '');
$filterEvent = (int)($_GET['event_id'] ?? 0);

$where  = "WHERE e.created_by = ? AND r.status != 'cancelled'";
$params = [$adminId];
if ($search)      { $where .= " AND (s.name LIKE ? OR s.matric_no LIKE ? OR s.email LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
if ($filterEvent) { $where .= " AND r.event_id = ?"; $params[] = $filterEvent; }

// ── Fetch Members ─────────────────────────────────────────────
$stmt = db()->prepare("
    SELECT s.id, s.name, s.email, s.matric_no, s.is_active,
           COUNT(DISTINCT r.event_id)          AS events_joined,
           SUM(r.attended_at IS NOT NULL)       AS events_attended,
           MIN(r.registered_at)                 AS first_joined
    FROM registrations r
    JOIN students s ON s.id = r.student_id
    JOIN events e   ON e.id = r.event_id
    $where
    GROUP BY s.id
    ORDER BY first_joined DESC
");
$stmt->execute($params);
$members = $stmt->fetchAll();
$totalMembers = count($members);

// Events dropdown
$evStmt = db()->prepare("SELECT id, title FROM events WHERE created_by = ? ORDER BY start_date DESC");
$evStmt->execute([$adminId]);
$myEvents = $evStmt->fetchAll();

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
<title>UiVent | Members</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
<style>
  .avatar { width:38px; height:38px; border-radius:50%; background:#582C83; color:#F9A51B; font-weight:700; font-size:13px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .avatar-lg { width:52px; height:52px; font-size:16px; }
  .badge-active   { background:#D1FAE5; color:#065F46; }
  .badge-inactive { background:#FEE2E2; color:#991B1B; }
  .detail-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:60; opacity:0; pointer-events:none; transition:opacity .2s; }
  .detail-modal-overlay.open { opacity:1; pointer-events:all; }
  .detail-modal-box { background:#fff; border-radius:16px; width:500px; max-width:95vw; max-height:90vh; overflow-y:auto; transform:translateY(12px); transition:transform .2s; box-shadow:0 20px 60px rgba(39,19,74,0.25); }
  .detail-modal-overlay.open .detail-modal-box { transform:translateY(0); }
  .detail-row { display:flex; gap:8px; padding:9px 0; border-bottom:1px solid rgba(88,44,131,0.07); font-size:13px; }
  .detail-row:last-child { border-bottom:none; }
  .detail-label { color:#6B7280; width:140px; flex-shrink:0; font-weight:500; }
  .detail-val { color:#1e1e2e; font-weight:600; }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 space-y-6 max-w-7xl w-full mx-auto">

  <!-- Header -->
  <div class="flex items-center justify-between flex-wrap gap-4">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Members</h1>
      <p class="text-sm text-gray-500 mt-0.5">Students who registered for your club events.</p>
    </div>
    <div class="flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-xl" style="background:#f0ebfa;color:#582C83;">
      <i class="fas fa-users"></i> <?= $totalMembers ?> Members
    </div>
  </div>

  <!-- Stat cards -->
  <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
    <?php
    $totalAttended = array_sum(array_column($members, 'events_attended'));
    $activeMembers = count(array_filter($members, fn($m) => $m['is_active']));
    $cards = [
      ['label'=>'Total Members',   'val'=>$totalMembers,  'icon'=>'fa-users',         'color'=>'#582C83', 'bg'=>'#f0ebfa'],
      ['label'=>'Active Students', 'val'=>$activeMembers, 'icon'=>'fa-user-check',    'color'=>'#059669', 'bg'=>'#d1fae5'],
      ['label'=>'Total Attended',  'val'=>$totalAttended, 'icon'=>'fa-clipboard-check','color'=>'#d97706','bg'=>'#fef3c7'],
    ];
    foreach ($cards as $c): ?>
    <div class="stat-card bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;">
        <i class="fas <?= $c['icon'] ?>"></i>
      </div>
      <div>
        <p class="text-2xl font-extrabold text-gray-900"><?= $c['val'] ?></p>
        <p class="text-xs font-semibold text-gray-400"><?= $c['label'] ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
    <form method="GET" class="flex flex-wrap gap-3">
      <div class="relative flex-1 min-w-[200px]">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
               placeholder="Search name, matric or email…"
               class="w-full pl-9 pr-4 py-2.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
      </div>
      <select name="event_id" class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm bg-white text-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-300">
        <option value="">All Events</option>
        <?php foreach ($myEvents as $ev): ?>
          <option value="<?= $ev['id'] ?>" <?= $filterEvent===$ev['id']?'selected':'' ?>><?= htmlspecialchars($ev['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-primary">Filter</button>
      <?php if ($search || $filterEvent): ?>
        <a href="members.php" class="btn-secondary flex items-center gap-1"><i class="fas fa-times text-xs"></i> Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 class="font-bold text-gray-800 text-sm">All Members</h2>
      <span class="text-xs text-gray-400"><?= $totalMembers ?> member<?= $totalMembers!==1?'s':'' ?></span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Member</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Matric No</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Events Joined</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Attended</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($members)): ?>
            <tr><td colspan="6" class="px-6 py-16 text-center text-gray-400">
              <i class="fas fa-users-slash text-4xl mb-3 block" style="color:#D1BBF0;"></i>
              <p class="font-semibold text-gray-600">No members found.</p>
              <p class="text-xs mt-1">Members appear once students register for your events.</p>
            </td></tr>
          <?php else: foreach ($members as $m): ?>
            <tr class="hover-row">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="avatar"><?= getInitials($m['name']) ?></div>
                  <div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($m['name']) ?></p>
                    <p class="text-xs text-gray-400"><?= htmlspecialchars($m['email']) ?></p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4 font-mono text-xs font-semibold" style="color:#582C83;"><?= htmlspecialchars($m['matric_no'] ?? '—') ?></td>
              <td class="px-4 py-4 text-center font-semibold text-gray-800"><?= $m['events_joined'] ?></td>
              <td class="px-4 py-4 text-center font-semibold text-emerald-600"><?= $m['events_attended'] ?></td>
              <td class="px-4 py-4 text-center">
                <span class="badge text-xs font-bold px-2.5 py-1 rounded-full <?= $m['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                  <?= $m['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td class="px-4 py-4 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button onclick='openMemberDetail(<?= json_encode([
                    "id"       => $m["id"],
                    "name"     => $m["name"],
                    "email"    => $m["email"],
                    "matric"   => $m["matric_no"] ?? "",
                    "events"   => (int)$m["events_joined"],
                    "attended" => (int)$m["events_attended"],
                    "joined"   => $m["first_joined"],
                    "active"   => (bool)$m["is_active"],
                  ]) ?>)'
                    class="w-8 h-8 rounded-lg flex items-center justify-center transition-all"
                    style="background:rgba(88,44,131,0.1);color:#582C83;" title="View details">
                    <i class="fas fa-eye text-xs"></i>
                  </button>
                  <button onclick='removeMember(<?= $m["id"] ?>, "<?= addslashes($m["name"]) ?>")'
                    class="w-8 h-8 rounded-lg flex items-center justify-center transition-all"
                    style="background:rgba(239,68,68,0.1);color:#991B1B;" title="Remove member">
                    <i class="fas fa-user-minus text-xs"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-6 py-3 border-t border-gray-100">
      <span class="text-xs text-gray-400">Showing <?= $totalMembers ?> member<?= $totalMembers!==1?'s':'' ?></span>
    </div>
  </div>

</main>
</div>

<!-- Member Detail Modal -->
<div id="memberDetailModal" class="detail-modal-overlay" onclick="if(event.target===this)closeMemberDetail()">
  <div class="detail-modal-box" onclick="event.stopPropagation()">
    <div class="flex items-center gap-4 px-6 pt-6 pb-4 border-b" style="border-color:rgba(88,44,131,0.1);">
      <div id="mdAvatar" class="avatar avatar-lg"></div>
      <div class="flex-1 min-w-0">
        <h2 id="mdName" class="text-base font-bold truncate" style="color:#27134A;"></h2>
        <p id="mdMatric" class="text-xs font-mono font-semibold mt-0.5" style="color:#582C83;"></p>
      </div>
      <button onclick="closeMemberDetail()" class="text-gray-400 hover:text-gray-600 ml-2">
        <i class="fas fa-xmark text-lg"></i>
      </button>
    </div>
    <div class="px-6 py-5">
      <p class="text-xs font-bold uppercase tracking-widest mb-3 text-gray-400">Member Information</p>
      <div class="detail-row"><span class="detail-label">Full Name</span><span id="mdFullName" class="detail-val"></span></div>
      <div class="detail-row"><span class="detail-label">Email</span><span id="mdEmail" class="detail-val"></span></div>
      <div class="detail-row"><span class="detail-label">Events Joined</span><span id="mdEvents" class="detail-val"></span></div>
      <div class="detail-row"><span class="detail-label">Events Attended</span><span id="mdAttended" class="detail-val"></span></div>
      <div class="detail-row"><span class="detail-label">First Joined</span><span id="mdJoined" class="detail-val"></span></div>
      <div class="detail-row"><span class="detail-label">Account Status</span><span id="mdStatus" class="detail-val"></span></div>
    </div>
    <div class="px-6 pb-6 flex gap-3 justify-end border-t pt-4" style="border-color:rgba(88,44,131,0.1);">
      <button id="mdRemoveBtn" class="btn-primary flex items-center gap-2" style="background:#EF4444;">
        <i class="fas fa-user-minus"></i> Remove Member
      </button>
      <button onclick="closeMemberDetail()" class="btn-secondary">Close</button>
    </div>
  </div>
</div>

<?php include 'partials/modals_js.php'; ?>
<script>
function openMemberDetail(m) {
  document.getElementById('mdAvatar').textContent   = m.name.split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase();
  document.getElementById('mdName').textContent     = m.name;
  document.getElementById('mdMatric').textContent   = m.matric || '—';
  document.getElementById('mdFullName').textContent = m.name;
  document.getElementById('mdEmail').textContent    = m.email;
  document.getElementById('mdEvents').textContent   = m.events + ' event(s)';
  document.getElementById('mdAttended').textContent = m.attended + ' event(s)';
  document.getElementById('mdJoined').textContent   = new Date(m.joined).toLocaleDateString('en-MY',{day:'numeric',month:'long',year:'numeric'});
  const st = document.getElementById('mdStatus');
  st.textContent = m.active ? 'Active' : 'Inactive';
  st.className   = 'detail-val font-bold ' + (m.active ? 'text-emerald-600' : 'text-red-600');
  document.getElementById('mdRemoveBtn').onclick = () => { closeMemberDetail(); removeMember(m.id, m.name); };
  document.getElementById('memberDetailModal').classList.add('open');
}

function closeMemberDetail() {
  document.getElementById('memberDetailModal').classList.remove('open');
}

function removeMember(id, name) {
  openConfirm('remove', 'Remove Member',
    `Remove ${name} from your club? Their event registrations will be cancelled.`,
    'Remove', 'red', () => {
      const fd = new FormData();
      fd.append('action', 'remove');
      fd.append('student_id', id);
      fetch('members.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(data => {
          showToast(data.message, !data.success);
          if (data.success) setTimeout(() => location.reload(), 800);
        })
        .catch(() => showToast('Network error.', true));
    });
}
</script>
</body>
</html>