<?php
require_once '../config.php';
requireSuperAdmin();

$activePage = 'global_config';
$pageTitle  = 'Settings';

$pendingClubs     = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='pending'")->fetchColumn();
$pendingApprovals = (int) db()->query("SELECT COUNT(*) FROM escalated_approvals WHERE status='pending'")->fetchColumn();

// ── Save ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        if ($_POST['action']==='save_rules') {
            $keys = ['max_event_capacity','academic_term_label'];
            foreach ($keys as $k) {
                if (isset($_POST[$k])) {
                    $v = htmlspecialchars(trim($_POST[$k]));
                    db()->prepare("INSERT INTO global_config (config_key,config_value,updated_at) VALUES(:k,:v,NOW())
                                   ON DUPLICATE KEY UPDATE config_value=:v2, updated_at=NOW()")
                       ->execute(['k'=>$k,'v'=>$v,'v2'=>$v]);
                }
            }
            try { logAction('CONFIG CHANGED', 'Platform rules updated'); } catch(Throwable $e) {}
            jsonResponse(true, 'Settings saved.');
        }
        if ($_POST['action']==='toggle_flag') {
            $key = htmlspecialchars($_POST['flag_key'] ?? '');
            $val = ($_POST['flag_value']==='1') ? '1' : '0';
            if ($key) {
                db()->prepare("INSERT INTO global_config (config_key,config_value,updated_at) VALUES(:k,:v,NOW())
                               ON DUPLICATE KEY UPDATE config_value=:v2, updated_at=NOW()")
                   ->execute(['k'=>$key,'v'=>$val,'v2'=>$val]);
                try { logAction('CONFIG CHANGED', "Feature '$key' set to $val"); } catch(Throwable $e) {}
                jsonResponse(true, 'Feature flag updated.');
            }
            jsonResponse(false, 'Invalid flag key.');
        }
        if ($_POST['action']==='broadcast') {
            $msg      = htmlspecialchars(trim($_POST['message'] ?? ''));
            $severity = htmlspecialchars($_POST['severity'] ?? 'Info');
            $expires  = htmlspecialchars($_POST['expires']   ?? '6 hours');
            if ($msg) {
                try {
                    db()->prepare("INSERT INTO broadcast_messages (message,severity,expires_label,created_by,created_at) VALUES(:m,:s,:e,:by,NOW())")
                       ->execute(['m'=>$msg,'s'=>$severity,'e'=>$expires,'by'=>$_SESSION['super_admin_id']]);
                } catch (\PDOException $e) {
                    db()->exec("CREATE TABLE IF NOT EXISTS broadcast_messages (
                        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        message TEXT NOT NULL,
                        severity VARCHAR(20) DEFAULT 'Info',
                        expires_label VARCHAR(50) DEFAULT '6 hours',
                        created_by INT UNSIGNED,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )");
                    db()->prepare("INSERT INTO broadcast_messages (message,severity,expires_label,created_by,created_at) VALUES(:m,:s,:e,:by,NOW())")
                       ->execute(['m'=>$msg,'s'=>$severity,'e'=>$expires,'by'=>$_SESSION['super_admin_id']]);
                }
                try { logAction('BROADCAST SENT', "Severity: $severity"); } catch(Throwable $e) {}
                jsonResponse(true, 'Broadcast sent to all clubs.');
            }
            jsonResponse(false, 'Message cannot be empty.');
        }
        if ($_POST['action']==='archive_events') {
            $count = db()->exec("UPDATE events SET status='archived' WHERE status='closed'");
            try { logAction('EVENTS ARCHIVED', "$count closed events archived"); } catch(Throwable $e) {}
            jsonResponse(true, "$count closed event(s) archived successfully.");
        }
        if ($_POST['action']==='suspend_all') {
            $count = db()->exec("UPDATE admins SET status='suspended' WHERE status='active'");
            try { logAction('ALL CLUBS SUSPENDED', "$count club accounts suspended"); } catch(Throwable $e) {}
            jsonResponse(true, "$count club account(s) suspended.");
        }
        jsonResponse(false, 'Unknown action.');
    } catch (Throwable $ex) {
        error_log('[UiVent] global_config error: ' . $ex->getMessage());
        jsonResponse(false, 'Server error: ' . $ex->getMessage());
    }
}

$cfg = [];
foreach (db()->query('SELECT config_key,config_value FROM global_config')->fetchAll() as $r) {
    $cfg[$r['config_key']] = $r['config_value'];
}
function cfgVal($cfg,$key,$default='') { return htmlspecialchars($cfg[$key] ?? $default); }
function cfgChecked($cfg,$key) { return ($cfg[$key]??'0')==='1' ? 'checked' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Settings</title>
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

  <div>
    <h3 class="text-2xl font-bold text-gray-900">Settings</h3>
    <p class="text-sm text-gray-500 mt-1">Platform-wide configuration for UiVent. Changes take effect immediately for all clubs.</p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Platform Rules -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
      <h4 class="font-bold text-gray-900 flex items-center gap-2">
        <i class="fas fa-sliders-h text-sm" style="color:#582C83;"></i> Platform Rules
      </h4>
      <div class="space-y-3 text-sm">
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Maximum Event Capacity</label>
          <input type="number" id="max_event_capacity" value="<?= cfgVal($cfg,'max_event_capacity','1000') ?>"
                 class="w-full bg-gray-50 border border-gray-300 rounded-md px-3 py-2 focus:ring-1 focus:ring-purple-600 focus:outline-none">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Current Academic Term Label</label>
          <input type="text" id="academic_term_label" value="<?= cfgVal($cfg,'academic_term_label','Semester 2, 2025/2026') ?>"
                 class="w-full bg-gray-50 border border-gray-300 rounded-md px-3 py-2 focus:ring-1 focus:ring-purple-600 focus:outline-none">
        </div>
        <button onclick="saveRules()" class="btn-primary px-4 py-2 text-sm">Save Rules</button>
      </div>
    </div>

    <!-- Feature Flags -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
      <h4 class="font-bold text-gray-900 flex items-center gap-2">
        <i class="fas fa-toggle-on text-sm" style="color:#582C83;"></i> Feature Flags
      </h4>
      <div class="space-y-3 text-sm">
        <?php
        $flags = [
          ['key'=>'maintenance_mode',      'label'=>'Maintenance Mode',           'desc'=>'All student portals go offline when enabled'],
          ['key'=>'registration_frozen',   'label'=>'Freeze All Registrations',   'desc'=>'Pause all event registrations platform-wide'],
        ];
        foreach ($flags as $f):
        ?>
        <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 border border-gray-200">
          <div>
            <p class="font-semibold text-gray-800"><?= $f['label'] ?></p>
            <p class="text-xs text-gray-400"><?= $f['desc'] ?></p>
          </div>
          <label class="toggle-switch">
            <input type="checkbox" <?= cfgChecked($cfg,$f['key']) ?>
                   onchange="toggleFlag('<?= $f['key'] ?>',this.checked,'<?= $f['label'] ?>')">
            <span class="toggle-slider"></span>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Broadcast -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
      <h4 class="font-bold text-gray-900 flex items-center gap-2">
        <i class="fas fa-broadcast-tower text-sm" style="color:#582C83;"></i> Broadcast to All Clubs
      </h4>
      <p class="text-xs text-gray-500">Push a banner to all club admin portals. Useful for maintenance notices or deadline reminders.</p>
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Message</label>
          <textarea id="broadcastMsg" rows="3" placeholder="e.g. Event submission deadline is this Friday at 11:59 PM."
                    class="w-full bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none resize-none"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Severity</label>
            <select id="broadcastSeverity" class="w-full bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
              <option>Info</option><option>Warning</option><option>Critical</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Expires In</label>
            <select id="broadcastExpires" class="w-full bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
              <option>1 hour</option><option selected>6 hours</option><option>24 hours</option><option>Until dismissed</option>
            </select>
          </div>
        </div>
        <button onclick="sendBroadcast()" class="w-full btn-primary py-2.5 text-sm flex items-center justify-center gap-2">
          <i class="fas fa-broadcast-tower"></i> Send Broadcast
        </button>
      </div>
    </div>

    <!-- Danger Zone — trimmed, no student wipe or password reset -->
    <div class="danger-zone rounded-xl p-5 space-y-4">
      <h4 class="font-bold text-red-800 flex items-center gap-2">
        <i class="fas fa-triangle-exclamation"></i> Danger Zone
      </h4>
      <p class="text-xs text-red-600">These actions cannot be undone. Use only when necessary.</p>
      <div class="space-y-3">
        <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-red-200">
          <div>
            <p class="text-sm font-semibold text-gray-800">Archive All Closed Events</p>
            <p class="text-xs text-gray-500">Moves all closed events to cold storage. Clubs lose access to these records.</p>
          </div>
          <button onclick="openConfirm('archive','Archive Closed Events','Move all closed events to cold storage? Clubs will no longer see them in their dashboard.','Archive','amber',()=>showToast('Closed events archived.'))"
                  class="text-xs bg-amber-50 text-amber-700 font-semibold px-3 py-1.5 rounded hover:bg-amber-100">Archive</button>
        </div>
        <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-red-200">
          <div>
            <p class="text-sm font-semibold text-gray-800">Suspend All Clubs</p>
            <p class="text-xs text-gray-500">Immediately lock all club accounts. Use only in an emergency.</p>
          </div>
          <button onclick="openConfirm('suspend_all','Suspend All Clubs','This will immediately lock every club account on the platform. They will be unable to log in until you reactivate them individually.','Suspend All','red',()=>showToast('All clubs suspended.'))"
                  class="text-xs bg-red-50 text-red-700 font-semibold px-3 py-1.5 rounded hover:bg-red-100">Suspend All</button>
        </div>
      </div>
    </div>

  </div>
</main>
</div>
</div>
<?php include 'partials/modals_js.php'; ?>
<script>
function saveRules() {
  const fields = ['max_event_capacity','academic_term_label'];
  const fd = new FormData();
  fd.append('action','save_rules');
  fields.forEach(f => fd.append(f, document.getElementById(f).value));
  fetch('global_config.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>showToast(d.message, !d.success))
    .catch(()=>showToast('Network error.', true));
}
function toggleFlag(key, isChecked, label) {
  const fd = new FormData();
  fd.append('action','toggle_flag');
  fd.append('flag_key', key);
  fd.append('flag_value', isChecked ? '1' : '0');
  fetch('global_config.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>showToast(d.success ? `${label} ${isChecked?'enabled':'disabled'}.` : d.message, !d.success))
    .catch(()=>showToast('Network error.', true));
}
function sendBroadcast() {
  const msg = document.getElementById('broadcastMsg').value.trim();
  if (!msg) { showToast('Message cannot be empty.', true); return; }
  const fd = new FormData();
  fd.append('action','broadcast');
  fd.append('message', msg);
  fd.append('severity', document.getElementById('broadcastSeverity').value);
  fd.append('expires', document.getElementById('broadcastExpires').value);
  fetch('global_config.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{ showToast(d.message, !d.success); if(d.success) document.getElementById('broadcastMsg').value=''; })
    .catch(()=>showToast('Network error.', true));
}
function archiveEvents() {
  const fd = new FormData();
  fd.append('action','archive_events');
  fetch('global_config.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>showToast(d.message, !d.success))
    .catch(()=>showToast('Network error.', true));
}
function suspendAll() {
  const fd = new FormData();
  fd.append('action','suspend_all');
  fetch('global_config.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>showToast(d.message, !d.success))
    .catch(()=>showToast('Network error.', true));
}
</script>
</body>
</html>