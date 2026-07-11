<?php
require_once '../config.php';
requireSuperAdmin();

$activePage = 'blacklist_management';
$pageTitle  = 'Blacklist';

// ── AJAX Actions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add_blacklist') {
        $adminId   = (int)($_POST['admin_id'] ?? 0);
        $reason    = htmlspecialchars(trim($_POST['reason'] ?? ''));
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        if (!$adminId || !$reason) {
            jsonResponse(false, 'Club and reason are required.');
        }

        // Suspend the account and insert blacklist record
        try {
            db()->prepare("UPDATE admins SET status='suspended' WHERE id=:id")
               ->execute(['id' => $adminId]);

            db()->prepare("
                INSERT INTO blacklist (admin_id, reason, expires_at, created_by)
                VALUES (:admin_id, :reason, :expires_at, :created_by)
            ")->execute([
                'admin_id'   => $adminId,
                'reason'     => $reason,
                'expires_at' => $expiresAt,
                'created_by' => currentAdmin()['id'],
            ]);

            logAction('BLACKLISTED', "Club ID $adminId blacklisted. Reason: $reason");
            jsonResponse(true, 'Club has been blacklisted and suspended.', ['reload' => true]);
        } catch (Exception $e) {
            jsonResponse(false, 'This club is already blacklisted.');
        }
    }

    if ($_POST['action'] === 'remove_blacklist') {
        $blacklistId = (int)($_POST['blacklist_id'] ?? 0);
        if (!$blacklistId) jsonResponse(false, 'Invalid record.');

        $row = db()->prepare("SELECT admin_id FROM blacklist WHERE id=:id");
        $row->execute(['id' => $blacklistId]);
        $record = $row->fetch();

        if ($record) {
            db()->prepare("DELETE FROM blacklist WHERE id=:id")->execute(['id' => $blacklistId]);
            db()->prepare("UPDATE admins SET status='active' WHERE id=:id")->execute(['id' => $record['admin_id']]);
            logAction('UNBLACKLISTED', "Blacklist record ID $blacklistId removed; club reactivated.");
            jsonResponse(true, 'Club removed from blacklist and reactivated.', ['reload' => true]);
        }
        jsonResponse(false, 'Record not found.');
    }

    jsonResponse(false, 'Unknown action.');
}

// ── Fetch Data ────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT
        bl.id           AS bl_id,
        bl.reason,
        bl.created_at   AS banned_at,
        bl.expires_at,
        a.id            AS admin_id,
        a.name          AS club_name,
        a.email         AS club_email,
        a.role          AS club_type,
        op.name         AS banned_by
    FROM blacklist bl
    JOIN admins a  ON a.id  = bl.admin_id
    LEFT JOIN admins op ON op.id = bl.created_by
    WHERE 1=1
";
$params = [];
if ($search) {
    $sql .= " AND (a.name LIKE :s OR a.email LIKE :s OR bl.reason LIKE :s)";
    $params['s'] = "%$search%";
}
$sql .= " ORDER BY bl.created_at DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$blacklist = $stmt->fetchAll();

// Clubs eligible to blacklist (active or pending, not already blacklisted)
$eligibleClubs = db()->query("
    SELECT a.id, a.name, a.email FROM admins a
    WHERE a.status IN ('active','pending')
      AND a.id NOT IN (SELECT admin_id FROM blacklist)
    ORDER BY a.name ASC
")->fetchAll();

$pendingClubs     = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='pending'")->fetchColumn();
$pendingApprovals = (int) db()->query("SELECT COUNT(*) FROM escalated_approvals WHERE status='pending'")->fetchColumn();
$totalBlacklisted = count($blacklist);

// Count permanent vs temporary
$permanentCount  = 0;
$temporaryCount  = 0;
$expiredCount    = 0;
$now             = new DateTime();
foreach ($blacklist as $row) {
    if (!$row['expires_at']) {
        $permanentCount++;
    } else {
        $exp = new DateTime($row['expires_at']);
        if ($exp < $now) $expiredCount++; else $temporaryCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Blacklist Management</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>

<style>
  .ban-row { transition: background .15s; }
  .ban-row:hover { background: #fef2f2; }

  .status-badge-active    { background:#fef2f2; color:#b91c1c; }
  .status-badge-temporary { background:#fff7ed; color:#c2410c; }
  .status-badge-expired   { background:#f0fdf4; color:#166534; }

  /* Striped ban icon watermark on empty state */
  .empty-icon { opacity:.08; font-size:6rem; }

  @keyframes popIn { from{opacity:0;transform:scale(.95)} to{opacity:1;transform:scale(1)} }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<div class="flex-1">
<main class="p-6 md:p-8 space-y-6 max-w-7xl w-full mx-auto">

  <!-- Page Header -->
  <div class="flex items-start justify-between flex-wrap gap-4">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white text-sm" style="background:#b91c1c;">
          <i class="fas fa-ban"></i>
        </span>
        <h3 class="text-2xl font-bold text-gray-900">Blacklist</h3>
      </div>
      <p class="text-sm text-gray-500">
        Suspended clubs with blacklist records. Changes take effect immediately.
      </p>
    </div>
    <button onclick="document.getElementById('addBlacklistModal').classList.remove('hidden')"
            class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-white transition-all hover:opacity-90"
            style="background:#b91c1c;">
      <i class="fas fa-ban"></i> Blacklist Club
    </button>
  </div>

  <!-- Stat Cards -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Blacklisted</p>
      <p class="text-2xl font-bold text-red-700 mt-1"><?= $totalBlacklisted ?></p>
    </div>
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Permanent</p>
      <p class="text-2xl font-bold text-gray-800 mt-1"><?= $permanentCount ?></p>
    </div>
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Temporary (Active)</p>
      <p class="text-2xl font-bold text-orange-600 mt-1"><?= $temporaryCount ?></p>
    </div>
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm text-center">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Bans Expired</p>
      <p class="text-2xl font-bold text-emerald-600 mt-1"><?= $expiredCount ?></p>
    </div>
  </div>

  <!-- Search -->
  <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap gap-3">
    <input name="q" type="text" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search club name, email, or reason…"
           class="flex-1 min-w-48 bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-red-600 focus:outline-none">
    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition-all hover:opacity-90" style="background:#582C83;">Search</button>
    <?php if ($search): ?>
      <a href="blacklist_management.php" class="text-xs text-gray-500 px-3 py-2 rounded-md bg-gray-100 hover:bg-gray-200 flex items-center">Clear</a>
    <?php endif; ?>
  </form>

  <!-- Blacklist Table -->
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
            <th class="py-3 px-5">Club</th>
            <th class="py-3 px-5">Reason</th>
            <th class="py-3 px-5">Banned On</th>
            <th class="py-3 px-5">Expires</th>
            <th class="py-3 px-5">Banned By</th>
            <th class="py-3 px-5">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php if (empty($blacklist)): ?>
          <tr>
            <td colspan="6" class="py-16 text-center">
              <div class="flex flex-col items-center gap-3 text-gray-300">
                <i class="fas fa-ban empty-icon"></i>
                <p class="text-gray-400 font-medium text-sm">No blacklisted clubs<?= $search ? ' matching your search' : '' ?>.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($blacklist as $row):
            $now    = new DateTime();
            $isPerm = empty($row['expires_at']);
            $isExp  = !$isPerm && (new DateTime($row['expires_at'])) < $now;

            if ($isPerm)       { $badgeClass = 'status-badge-active';    $badgeLabel = 'Permanent'; }
            elseif ($isExp)    { $badgeClass = 'status-badge-expired';   $badgeLabel = 'Expired'; }
            else               { $badgeClass = 'status-badge-temporary'; $badgeLabel = 'Temporary'; }

            $words    = explode(' ', trim($row['club_name'] ?? '?'));
            $initials = strtoupper(substr($words[0],0,1) . substr(end($words)?:'',0,1));
          ?>
          <tr class="ban-row">
            <!-- Club -->
            <td class="py-3.5 px-5">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                     style="background:#fee2e2;color:#b91c1c;"><?= $initials ?></div>
                <div>
                  <p class="font-semibold text-gray-900"><?= htmlspecialchars($row['club_name'] ?? '—') ?></p>
                  <p class="text-xs text-gray-400"><?= htmlspecialchars($row['club_email']) ?></p>
                  <p class="text-xs text-gray-400"><?= htmlspecialchars($row['club_type'] ?? '') ?></p>
                </div>
              </div>
            </td>

            <!-- Reason -->
            <td class="py-3.5 px-5 max-w-xs">
              <p class="text-sm text-gray-700 leading-snug line-clamp-2"><?= htmlspecialchars($row['reason']) ?></p>
            </td>

            <!-- Banned On -->
            <td class="py-3.5 px-5 text-xs text-gray-500 whitespace-nowrap">
              <?= date('d M Y', strtotime($row['banned_at'])) ?>
              <p class="text-gray-400"><?= date('H:i', strtotime($row['banned_at'])) ?></p>
            </td>

            <!-- Expires -->
            <td class="py-3.5 px-5">
              <span class="badge text-xs font-semibold px-2 py-1 rounded-full <?= $badgeClass ?>">
                <?php if ($isPerm): ?>
                  <i class="fas fa-infinity mr-1"></i> Permanent
                <?php elseif ($isExp): ?>
                  <i class="fas fa-check mr-1"></i> <?= date('d M Y', strtotime($row['expires_at'])) ?>
                <?php else: ?>
                  <i class="fas fa-clock mr-1"></i> <?= date('d M Y', strtotime($row['expires_at'])) ?>
                <?php endif; ?>
              </span>
            </td>

            <!-- Banned By -->
            <td class="py-3.5 px-5 text-xs text-gray-500">
              <?= htmlspecialchars($row['banned_by'] ?? 'System') ?>
            </td>

            <!-- Actions -->
            <td class="py-3.5 px-5">
              <div class="flex gap-1.5 flex-wrap">
                <button onclick="viewReason(<?= htmlspecialchars(json_encode($row['reason'])) ?>, '<?= addslashes(htmlspecialchars($row['club_name'] ?? $row['club_email'])) ?>')"
                        class="text-xs text-purple-700 bg-purple-50 hover:bg-purple-100 px-2.5 py-1.5 rounded font-semibold whitespace-nowrap">
                  View Reason
                </button>
                <button onclick="removeBlacklist(<?= $row['bl_id'] ?>, '<?= addslashes(htmlspecialchars($row['club_name'] ?? $row['club_email'])) ?>')"
                        class="text-xs text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1.5 rounded font-semibold whitespace-nowrap">
                  Remove Ban
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-3 border-t border-gray-100 text-xs text-gray-500">
      <?= count($blacklist) ?> record<?= count($blacklist) !== 1 ? 's' : '' ?> shown
    </div>
  </div>

</main>
</div>
</div>

<!-- ── Add Blacklist Modal ───────────────────────────────────────── -->
<div id="addBlacklistModal" class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4" style="animation:popIn .2s cubic-bezier(.4,0,.2,1);">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs" style="background:#b91c1c;">
          <i class="fas fa-ban"></i>
        </span>
        <h3 class="font-bold text-lg text-gray-900">Blacklist a Club</h3>
      </div>
      <button onclick="document.getElementById('addBlacklistModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <p class="text-xs text-gray-500 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
      <i class="fas fa-triangle-exclamation text-red-500 mr-1"></i>
      The club's account will be <strong>suspended immediately</strong> and they will lose all access.
    </p>

    <div class="space-y-3">
      <!-- Club select -->
      <div>
        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Club</label>
        <select id="blAdminId" class="w-full bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-red-600 focus:outline-none">
          <option value="">— Select a club —</option>
          <?php foreach ($eligibleClubs as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name'] ?: $c['email']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($eligibleClubs)): ?>
          <p class="text-xs text-gray-400 mt-1">All active clubs are already blacklisted.</p>
        <?php endif; ?>
      </div>

      <!-- Reason -->
      <div>
        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Reason for Blacklist</label>
        <textarea id="blReason" rows="3" placeholder="Describe why this club is being blacklisted…"
                  class="w-full bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-red-600 focus:outline-none resize-none"></textarea>
      </div>

      <!-- Expiry -->
      <div>
        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">
          Ban Expiry <span class="text-gray-400 font-normal normal-case">(leave blank for permanent)</span>
        </label>
        <input type="date" id="blExpiry" min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
               class="w-full bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-red-600 focus:outline-none">
      </div>
    </div>

    <div class="flex gap-3 pt-2">
      <button onclick="submitBlacklist()"
              class="flex-1 text-white font-semibold py-2.5 rounded-lg text-sm transition-all hover:opacity-90" style="background:#b91c1c;">
        <i class="fas fa-ban mr-1.5"></i> Confirm Blacklist
      </button>
      <button onclick="document.getElementById('addBlacklistModal').classList.add('hidden')"
              class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-lg text-sm">Cancel</button>
    </div>
  </div>
</div>

<!-- ── Reason Viewer Modal ───────────────────────────────────────── -->
<div id="reasonModal" class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-3" style="animation:popIn .2s cubic-bezier(.4,0,.2,1);">
    <div class="flex items-center justify-between">
      <h3 class="font-bold text-base text-gray-900">Suspension Reason</h3>
      <button onclick="document.getElementById('reasonModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <p id="reasonClubName" class="text-xs font-semibold text-purple-700 uppercase tracking-wider"></p>
    <p id="reasonText" class="text-sm text-gray-700 leading-relaxed bg-gray-50 rounded-lg p-3 border border-gray-100"></p>
    <button onclick="document.getElementById('reasonModal').classList.add('hidden')"
            class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 rounded-lg text-sm">Close</button>
  </div>
</div>

<?php include 'partials/modals_js.php'; ?>
<script>
function submitBlacklist() {
  const adminId = document.getElementById('blAdminId').value;
  const reason  = document.getElementById('blReason').value.trim();
  const expiry  = document.getElementById('blExpiry').value;

  if (!adminId) { showToast('Please select a club.', true); return; }
  if (!reason)  { showToast('Please provide a reason.', true); return; }

  const fd = new FormData();
  fd.append('action', 'add_blacklist');
  fd.append('admin_id', adminId);
  fd.append('reason', reason);
  if (expiry) fd.append('expires_at', expiry);

  fetch('blacklist_management.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      showToast(d.message, !d.success);
      if (d.success) {
        document.getElementById('addBlacklistModal').classList.add('hidden');
        if (d.reload) setTimeout(() => location.reload(), 1000);
      }
    });
}

function removeBlacklist(id, name) {
  openConfirm(
    'remove_blacklist',
    'Remove Ban',
    `Remove the blacklist on <strong>${name}</strong>? Their account will be reactivated immediately.`,
    'Remove Ban',
    'green',
    function () {
      const fd = new FormData();
      fd.append('action', 'remove_blacklist');
      fd.append('blacklist_id', id);
      fetch('blacklist_management.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
          showToast(d.message, !d.success);
          if (d.reload) setTimeout(() => location.reload(), 1000);
        });
    }
  );
}

function viewReason(reason, clubName) {
  document.getElementById('reasonClubName').textContent = clubName;
  document.getElementById('reasonText').textContent = reason;
  document.getElementById('reasonModal').classList.remove('hidden');
}
</script>
</body>
</html>