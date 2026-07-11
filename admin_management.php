<?php
require_once '../config.php';
requireSuperAdmin();

$activePage = 'admin_management';
$pageTitle  = 'Club Accounts';

// ── AJAX Actions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['admin_id'] ?? 0);

    if ($_POST['action'] === 'suspend' && $id) {
        db()->prepare("UPDATE admins SET status='suspended' WHERE id=:id")->execute(['id'=>$id]);
        logAction('SUSPENDED', "Club ID $id suspended");
        jsonResponse(true, 'Club account suspended.', ['reload'=>true]);
    }
    if ($_POST['action'] === 'reactivate' && $id) {
        db()->prepare("UPDATE admins SET status='active' WHERE id=:id")->execute(['id'=>$id]);
        logAction('REACTIVATED', "Club ID $id reactivated");
        jsonResponse(true, 'Club account reactivated.', ['reload'=>true]);
    }
    if ($_POST['action'] === 'invite' && !empty($_POST['email'])) {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $role  = htmlspecialchars($_POST['role'] ?? 'Club Admin');
        $token = bin2hex(random_bytes(32));
        try {
            db()->prepare("INSERT INTO admins (email,role,status,invite_token) VALUES (:email,:role,'pending',:token)")
               ->execute(['email'=>$email,'role'=>$role,'token'=>$token]);
            logAction('ADMIN INVITED', "$email invited as $role");
            jsonResponse(true, "Invite sent to $email.");
        } catch (Exception $e) {
            jsonResponse(false, 'This email is already registered.');
        }
    }
    if ($_POST['action'] === 'revoke' && $id) {
        db()->prepare("DELETE FROM admins WHERE id=:id AND status='pending'")->execute(['id'=>$id]);
        logAction('INVITE REVOKED', "Club invite ID $id revoked");
        jsonResponse(true, 'Invite revoked.', ['reload'=>true]);
    }
    jsonResponse(false, 'Unknown action.');
}

// ── Filters ───────────────────────────────────────────────────────
$search  = $_GET['q']      ?? '';
$statusF = $_GET['status'] ?? '';

$sql    = "SELECT a.* FROM admins a WHERE 1=1";
$params = [];
if ($search)  { $sql .= " AND (a.name LIKE :s OR a.email LIKE :s)"; $params['s']="%$search%"; }
if ($statusF) { $sql .= " AND a.status=:st"; $params['st']=$statusF; }
$sql .= " ORDER BY a.status ASC, a.last_active DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$clubs = $stmt->fetchAll();

$pendingClubs     = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='pending'")->fetchColumn();
$pendingApprovals = (int) db()->query("SELECT COUNT(*) FROM escalated_approvals WHERE status='pending'")->fetchColumn();

// Counts for stat cards
$activeCount    = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='active'")->fetchColumn();
$suspendedCount = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='suspended'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Club Accounts</title>
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

  <div class="flex items-start justify-between flex-wrap gap-4">
    <div>
      <h3 class="text-2xl font-bold text-gray-900">Club Accounts</h3>
      <p class="text-sm text-gray-500 mt-1">
        Manage clubs subscribed to UiVent at UiTM Machang.
        <?php if ($pendingClubs > 0): ?>
          <span class="text-amber-600 font-semibold"><?= $pendingClubs ?> pending setup.</span>
        <?php endif; ?>
      </p>
    </div>
    <button onclick="document.getElementById('inviteModal').classList.remove('hidden')"
            class="btn-primary flex items-center gap-2">
      <i class="fas fa-plus"></i> Add Club
    </button>
  </div>

  <!-- Stat Cards -->
  <div class="grid grid-cols-3 gap-4">
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Active</p>
      <p class="text-2xl font-bold text-emerald-700 mt-1"><?= $activeCount ?></p>
    </div>
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Pending Setup</p>
      <p class="text-2xl font-bold text-amber-600 mt-1"><?= $pendingClubs ?></p>
    </div>
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Suspended</p>
      <p class="text-2xl font-bold text-red-600 mt-1"><?= $suspendedCount ?></p>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap gap-3">
    <input name="q" type="text" value="<?= htmlspecialchars($search) ?>" placeholder="Search club name or email…"
           class="flex-1 min-w-48 bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
    <select name="status" class="bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
      <option value="">All Statuses</option>
      <option value="active"    <?= $statusF==='active'   ?'selected':'' ?>>Active</option>
      <option value="pending"   <?= $statusF==='pending'  ?'selected':'' ?>>Pending</option>
      <option value="suspended" <?= $statusF==='suspended'?'selected':'' ?>>Suspended</option>
    </select>
    <button type="submit" class="btn-primary px-4">Filter</button>
    <?php if ($search || $statusF): ?>
      <a href="admin_management.php" class="text-xs text-gray-500 px-3 py-2 rounded-md bg-gray-100 hover:bg-gray-200 flex items-center">Clear</a>
    <?php endif; ?>
  </form>

  <!-- Club Table -->
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
            <th class="py-3 px-5">Club</th>
            <th class="py-3 px-5">Type</th>
            <th class="py-3 px-5">Status</th>
            <th class="py-3 px-5">Last Active</th>
            <th class="py-3 px-5">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php
        $statusColors=['active'=>'bg-emerald-50 text-emerald-700','pending'=>'bg-amber-50 text-amber-700','suspended'=>'bg-red-50 text-red-700'];
        foreach ($clubs as $club):
          $sc = $statusColors[$club['status']] ?? 'bg-gray-100 text-gray-500';
          $words = explode(' ', trim($club['name'] ?? '?'));
          $initials = strtoupper(substr($words[0],0,1).substr(end($words)?:'',0,1));
        ?>
        <tr class="hover-row">
          <td class="py-3.5 px-5">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                   style="background:#f0ebfa;color:#582C83;"><?= $initials ?></div>
              <div>
                <p class="font-semibold text-gray-900"><?= htmlspecialchars($club['name'] ?? '—') ?></p>
                <p class="text-xs text-gray-400"><?= htmlspecialchars($club['email']) ?></p>
              </div>
            </div>
          </td>
          <td class="py-3.5 px-5 text-xs text-gray-500"><?= htmlspecialchars($club['role'] ?? '—') ?></td>
          <td class="py-3.5 px-5"><span class="badge <?= $sc ?>"><?= ucfirst($club['status']) ?></span></td>
          <td class="py-3.5 px-5 text-xs text-gray-400">
            <?= $club['last_active'] ? date('d M Y', strtotime($club['last_active'])) : 'Never' ?>
          </td>
          <td class="py-3.5 px-5">
            <div class="flex gap-1.5">
              <?php if ($club['status']==='active'): ?>
                <button onclick="clubAction('suspend',<?= $club['id'] ?>,'<?= addslashes(htmlspecialchars($club['name']??$club['email'])) ?>')"
                        class="text-xs text-red-700 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded font-semibold">Suspend</button>
              <?php elseif ($club['status']==='suspended'): ?>
                <button onclick="clubAction('reactivate',<?= $club['id'] ?>,'<?= addslashes(htmlspecialchars($club['name']??$club['email'])) ?>')"
                        class="text-xs text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1.5 rounded font-semibold">Reactivate</button>
              <?php elseif ($club['status']==='pending'): ?>
                <button onclick="clubAction('revoke',<?= $club['id'] ?>,'<?= addslashes(htmlspecialchars($club['email'])) ?>')"
                        class="text-xs text-gray-600 bg-gray-100 hover:bg-gray-200 px-2.5 py-1.5 rounded font-semibold">Revoke</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($clubs)): ?>
          <tr><td colspan="5" class="py-12 text-center text-gray-400">No clubs found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-3 border-t border-gray-100 text-xs text-gray-500">
      <?= count($clubs) ?> club<?= count($clubs)!==1?'s':'' ?> shown
    </div>
  </div>

</main>
</div>
</div>

<!-- Add Club Modal -->
<div id="inviteModal" class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4" style="animation:popIn .2s cubic-bezier(.4,0,.2,1);">
    <div class="flex items-center justify-between">
      <h3 class="font-bold text-lg text-gray-900">Add New Club</h3>
      <button onclick="document.getElementById('inviteModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <p class="text-xs text-gray-500">Send a setup invite to a registered club at UiTM Machang.</p>
    <div class="space-y-3">
      <div>
        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Club Email</label>
        <input type="email" id="inviteEmail" placeholder="persatuan@uitm.edu.my"
               class="w-full bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Club Type</label>
        <select id="inviteRole" class="w-full bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
          <option value="Club Admin">Club Admin</option>
          <option value="Event Coordinator">Event Coordinator</option>
          <option value="Viewer">Viewer (read-only)</option>
        </select>
      </div>
    </div>
    <div class="flex gap-3 pt-2">
      <button onclick="sendInvite()" class="flex-1 btn-primary py-2.5 text-sm">
        <i class="fas fa-paper-plane mr-1.5"></i> Send Invite
      </button>
      <button onclick="document.getElementById('inviteModal').classList.add('hidden')"
              class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-lg text-sm">Cancel</button>
    </div>
  </div>
</div>

<?php include 'partials/modals_js.php'; ?>
<script>
function clubAction(action, id, name) {
  const labels  = { suspend:'Suspend Club', reactivate:'Reactivate Club', revoke:'Revoke Invite' };
  const bodies  = { suspend:`Suspend ${name}? They will lose access immediately.`, reactivate:`Reactivate ${name}'s account?`, revoke:`Revoke the pending invite for ${name}?` };
  const colors  = { suspend:'red', reactivate:'green', revoke:'amber' };
  openConfirm(action, labels[action], bodies[action], labels[action].split(' ')[0], colors[action], function() {
    const fd = new FormData();
    fd.append('action', action); fd.append('admin_id', id);
    fetch('admin_management.php', {method:'POST',body:fd})
      .then(r=>r.json()).then(d=>{ showToast(d.message,!d.success); if(d.reload) setTimeout(()=>location.reload(),1000); });
  });
}
function sendInvite() {
  const email = document.getElementById('inviteEmail').value.trim();
  const role  = document.getElementById('inviteRole').value;
  if (!email) { showToast('Enter an email address.',true); return; }
  const fd = new FormData();
  fd.append('action','invite'); fd.append('email',email); fd.append('role',role);
  fetch('admin_management.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    showToast(d.message,!d.success);
    if(d.success){ document.getElementById('inviteModal').classList.add('hidden'); document.getElementById('inviteEmail').value=''; }
  });
}
</script>
</body>
</html>
