<?php
require_once '../config.php';
requireSuperAdmin();

$activePage = 'global_broadcasts';
$pageTitle  = 'Broadcasts';

$pendingClubs     = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='pending'")->fetchColumn();
$pendingApprovals = (int) db()->query("SELECT COUNT(*) FROM escalated_approvals WHERE status='pending'")->fetchColumn();

// ── Audience counts (for the email composer) ──────────────────────────
$activeAdmins = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='active'")->fetchColumn();
$allAudience  = $activeAdmins + $pendingClubs;

// ── Banner Notices (existing feature: broadcast_messages) ─────────────
$bannerCount = (int) db()->query("SELECT COUNT(*) FROM broadcast_messages")->fetchColumn();
$banners = db()->query("
    SELECT bm.id, bm.message, bm.severity, bm.expires_label, bm.created_at, a.name AS sender_name
    FROM broadcast_messages bm
    LEFT JOIN admins a ON a.id = bm.created_by
    ORDER BY bm.created_at DESC
    LIMIT 20
")->fetchAll();

// ── Email Broadcasts (new feature: broadcasts) ─────────────────────────
$emailCount     = (int) db()->query("SELECT COUNT(*) FROM broadcasts")->fetchColumn();
$emailReach     = (int) db()->query("SELECT COALESCE(SUM(recipient_count),0) FROM broadcasts")->fetchColumn();
$emails = db()->query("
    SELECT b.id, b.subject, b.body, b.audience, b.recipient_count, b.created_at, a.name AS sender_name
    FROM broadcasts b
    LEFT JOIN admins a ON a.id = b.sent_by
    ORDER BY b.created_at DESC
    LIMIT 20
")->fetchAll();

$audienceLabels  = ['all' => 'All clubs & admins', 'active' => 'Active clubs only', 'pending' => 'Pending clubs only'];
$severityBadge   = ['Info' => 'bg-blue-50 text-blue-700', 'Warning' => 'bg-amber-50 text-amber-700', 'Critical' => 'bg-red-50 text-red-700'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Broadcasts</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<div class="flex-1">
<main class="p-6 md:p-8 space-y-8 max-w-7xl w-full mx-auto">

  <div>
    <h3 class="text-2xl font-bold tracking-tight text-gray-900">Broadcasts</h3>
    <p class="text-sm text-gray-500 mt-1">Two ways to reach clubs: a dashboard banner notice, or a direct email to admins.</p>
  </div>

  <!-- ══════════════ SECTION 1: BANNER NOTICES ══════════════ -->
  <section class="space-y-4">
    <div class="flex items-center justify-between">
      <h4 class="font-bold text-lg text-gray-900 flex items-center gap-2">
        <i class="fas fa-broadcast-tower" style="color:#582C83;"></i> Banner Notices
        <span class="text-xs font-normal text-gray-400">shown on every club dashboard</span>
      </h4>
      <button onclick="openBannerModal()"
              class="flex items-center gap-2 px-4 py-2 rounded-xl font-bold text-sm text-white transition-all hover:opacity-90"
              style="background:linear-gradient(135deg,#27134A,#582C83);">
        <i class="fas fa-plus"></i> New Banner
      </button>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Sent</p>
          <h4 class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($bannerCount) ?></h4>
          <p class="text-xs mt-0.5 font-medium" style="color:#582C83;">all-time banners</p>
        </div>
        <div class="p-3.5 rounded-lg" style="background:#f0ebfa;color:#582C83;">
          <i class="fas fa-broadcast-tower text-xl"></i>
        </div>
      </div>
      <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Most Recent</p>
          <h4 class="text-lg font-bold text-gray-900 mt-1"><?= !empty($banners) ? date('d M Y', strtotime($banners[0]['created_at'])) : '—' ?></h4>
          <p class="text-xs text-gray-400 font-medium mt-0.5"><?= !empty($banners) ? htmlspecialchars($banners[0]['severity']) . ' severity' : 'No banners yet' ?></p>
        </div>
        <div class="bg-amber-50 p-3.5 rounded-lg text-amber-600">
          <i class="far fa-clock text-xl"></i>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="p-5 border-b border-gray-100 flex items-center justify-between">
        <h4 class="font-bold text-base text-gray-900">Banner History</h4>
        <span class="text-xs text-gray-400"><?= count($banners) ?> shown</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
              <th class="py-3 px-5">Message</th>
              <th class="py-3 px-5">Severity</th>
              <th class="py-3 px-5">Expires</th>
              <th class="py-3 px-5">Sent By</th>
              <th class="py-3 px-5">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
          <?php if (empty($banners)): ?>
            <tr><td colspan="5" class="py-8 text-center text-gray-400 text-sm">No banner notices sent yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($banners as $b): $bc = $severityBadge[$b['severity']] ?? 'bg-gray-100 text-gray-500'; ?>
          <tr class="hover-row">
            <td class="py-3 px-5 text-gray-800 max-w-md"><?= htmlspecialchars($b['message']) ?></td>
            <td class="py-3 px-5"><span class="badge <?= $bc ?>"><?= htmlspecialchars($b['severity']) ?></span></td>
            <td class="py-3 px-5 text-xs text-gray-500"><?= htmlspecialchars($b['expires_label']) ?></td>
            <td class="py-3 px-5 text-xs text-gray-500"><?= htmlspecialchars($b['sender_name'] ?? '—') ?></td>
            <td class="py-3 px-5 text-xs text-gray-400"><?= date('d M Y, g:ia', strtotime($b['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ══════════════ SECTION 2: EMAIL BROADCAST ══════════════ -->
  <section class="space-y-4">
    <div class="flex items-center justify-between">
      <h4 class="font-bold text-lg text-gray-900 flex items-center gap-2">
        <i class="fas fa-envelope" style="color:#582C83;"></i> Email Broadcast
        <span class="text-xs font-normal text-gray-400">sent directly to club admin inboxes</span>
      </h4>
      <button onclick="openEmailModal()"
              class="flex items-center gap-2 px-4 py-2 rounded-xl font-bold text-sm text-white transition-all hover:opacity-90"
              style="background:linear-gradient(135deg,#27134A,#582C83);">
        <i class="fas fa-plus"></i> New Email
      </button>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Sent</p>
          <h4 class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($emailCount) ?></h4>
          <p class="text-xs mt-0.5 font-medium" style="color:#582C83;">all-time emails</p>
        </div>
        <div class="p-3.5 rounded-lg" style="background:#f0ebfa;color:#582C83;">
          <i class="fas fa-envelope text-xl"></i>
        </div>
      </div>
      <div class="stat-card bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Reach</p>
          <h4 class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($emailReach) ?></h4>
          <p class="text-xs text-gray-400 font-medium mt-0.5">recipients across all sends</p>
        </div>
        <div class="bg-amber-50 p-3.5 rounded-lg text-amber-600">
          <i class="fas fa-users text-xl"></i>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="p-5 border-b border-gray-100 flex items-center justify-between">
        <h4 class="font-bold text-base text-gray-900">Email History</h4>
        <span class="text-xs text-gray-400"><?= count($emails) ?> shown</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
              <th class="py-3 px-5">Subject</th>
              <th class="py-3 px-5">Audience</th>
              <th class="py-3 px-5">Recipients</th>
              <th class="py-3 px-5">Sent By</th>
              <th class="py-3 px-5">Date</th>
              <th class="py-3 px-5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
          <?php if (empty($emails)): ?>
            <tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">No emails sent yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($emails as $e): ?>
          <tr class="hover-row">
            <td class="py-3 px-5 font-semibold text-gray-900"><?= htmlspecialchars($e['subject']) ?></td>
            <td class="py-3 px-5"><span class="badge bg-blue-50 text-blue-700"><?= htmlspecialchars($audienceLabels[$e['audience']] ?? $e['audience']) ?></span></td>
            <td class="py-3 px-5 text-gray-600"><?= number_format($e['recipient_count']) ?></td>
            <td class="py-3 px-5 text-xs text-gray-500"><?= htmlspecialchars($e['sender_name'] ?? '—') ?></td>
            <td class="py-3 px-5 text-xs text-gray-400"><?= date('d M Y, g:ia', strtotime($e['created_at'])) ?></td>
            <td class="py-3 px-5 text-right">
              <button
                onclick='viewEmail(<?= htmlspecialchars(json_encode([
                    "subject"  => $e['subject'],
                    "body"     => $e['body'],
                    "audience" => $audienceLabels[$e['audience']] ?? $e['audience'],
                    "sender"   => $e['sender_name'] ?? '—',
                    "date"     => date('d M Y, g:ia', strtotime($e['created_at'])),
                    "count"    => number_format($e['recipient_count']),
                ]), ENT_QUOTES, 'UTF-8') ?>)'
                class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 transition-colors">
                View →
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ══════════════ MODALS ══════════════ -->

  <!-- Banner Notice Modal -->
  <div id="bannerModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.45);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h5 class="font-bold text-lg text-gray-900 flex items-center gap-2">
          <i class="fas fa-broadcast-tower text-purple-600"></i> New Banner Notice
        </h5>
        <button onclick="closeBannerModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
      </div>
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Message</label>
          <textarea id="bn_message" rows="3" placeholder="e.g. Event submission deadline is this Friday at 11:59 PM."
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2" style="--tw-ring-color:#582C83;"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Severity</label>
            <select id="bn_severity" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2" style="--tw-ring-color:#582C83;">
              <option>Info</option><option>Warning</option><option>Critical</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Expires In</label>
            <select id="bn_expires" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2" style="--tw-ring-color:#582C83;">
              <option>1 hour</option><option selected>6 hours</option><option>24 hours</option><option>Until dismissed</option>
            </select>
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-1">
        <button onclick="closeBannerModal()" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
        <button onclick="sendBanner()" class="px-5 py-2 rounded-lg text-sm font-bold text-white transition-colors" style="background:#582C83;">Send Banner</button>
      </div>
    </div>
  </div>

  <!-- Email Broadcast Modal -->
  <div id="emailModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.45);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h5 class="font-bold text-lg text-gray-900 flex items-center gap-2">
          <i class="fas fa-envelope text-amber-500"></i> New Email Broadcast
        </h5>
        <button onclick="closeEmailModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
      </div>
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Audience</label>
          <select id="em_audience" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2" style="--tw-ring-color:#582C83;">
            <option value="all">All clubs &amp; admins (<?= number_format($allAudience) ?>)</option>
            <option value="active">Active clubs only (<?= number_format($activeAdmins) ?>)</option>
            <option value="pending">Pending clubs only (<?= number_format($pendingClubs) ?>)</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Subject</label>
          <input type="text" id="em_subject" placeholder="e.g. Semester update"
                 class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2" style="--tw-ring-color:#582C83;">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Message</label>
          <textarea id="em_body" rows="5" placeholder="Type your message here…"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2" style="--tw-ring-color:#582C83;"></textarea>
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-1">
        <button onclick="closeEmailModal()" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
        <button onclick="sendEmailBroadcast()" class="px-5 py-2 rounded-lg text-sm font-bold text-white transition-colors" style="background:#582C83;">Send Broadcast</button>
      </div>
    </div>
  </div>

  <!-- View Email Modal -->
  <div id="viewModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.45);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h5 id="viewSubject" class="font-bold text-lg text-gray-900"></h5>
        <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
      </div>
      <div class="flex flex-wrap gap-2 text-xs">
        <span id="viewAudience" class="badge bg-blue-50 text-blue-700"></span>
        <span id="viewCount" class="badge bg-gray-50 text-gray-600"></span>
      </div>
      <p id="viewBody" class="text-sm text-gray-700 whitespace-pre-line leading-relaxed border-t border-gray-100 pt-4"></p>
      <p class="text-xs text-gray-400 border-t border-gray-100 pt-3">
        Sent by <span id="viewSender" class="font-medium text-gray-500"></span> · <span id="viewDate"></span>
      </p>
      <div class="flex justify-end">
        <button onclick="closeViewModal()" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">Close</button>
      </div>
    </div>
  </div>

</main>
</div>
</div>
<?php include 'partials/modals_js.php'; ?>
<script>
// ── Banner Notice modal + submit (reuses existing global_config.php endpoint) ──
function openBannerModal(){ const m=document.getElementById('bannerModal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeBannerModal(){ const m=document.getElementById('bannerModal'); m.classList.add('hidden'); m.classList.remove('flex'); }
document.getElementById('bannerModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeBannerModal(); });

function sendBanner() {
  const msg = document.getElementById('bn_message').value.trim();
  if (!msg) { showToast('Message cannot be empty.', true); return; }
  const fd = new FormData();
  fd.append('action', 'broadcast');
  fd.append('message', msg);
  fd.append('severity', document.getElementById('bn_severity').value);
  fd.append('expires', document.getElementById('bn_expires').value);
  fetch('global_config.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { showToast(d.message, !d.success); if (d.success) { closeBannerModal(); setTimeout(() => location.reload(), 600); } });
}

// ── Email Broadcast modal + submit ──
function openEmailModal(){ const m=document.getElementById('emailModal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeEmailModal(){ const m=document.getElementById('emailModal'); m.classList.add('hidden'); m.classList.remove('flex'); }
document.getElementById('emailModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeEmailModal(); });

function sendEmailBroadcast() {
  const subject = document.getElementById('em_subject').value.trim();
  const body    = document.getElementById('em_body').value.trim();
  if (!subject || !body) { showToast('Subject and message are required.', true); return; }
  const fd = new FormData();
  fd.append('broadcast_audience', document.getElementById('em_audience').value);
  fd.append('broadcast_subject', subject);
  fd.append('broadcast_body', body);
  fetch('broadcast.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { showToast(d.message, !d.success); if (d.success) { closeEmailModal(); setTimeout(() => location.reload(), 600); } });
}

// ── View Email modal ──
function viewEmail(data) {
  document.getElementById('viewSubject').textContent  = data.subject;
  document.getElementById('viewBody').textContent     = data.body;
  document.getElementById('viewAudience').textContent = data.audience;
  document.getElementById('viewCount').textContent    = data.count + ' recipients';
  document.getElementById('viewSender').textContent   = data.sender;
  document.getElementById('viewDate').textContent     = data.date;
  const m = document.getElementById('viewModal'); m.classList.remove('hidden'); m.classList.add('flex');
}
function closeViewModal(){ const m=document.getElementById('viewModal'); m.classList.add('hidden'); m.classList.remove('flex'); }
document.getElementById('viewModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeViewModal(); });
</script>
</body>
</html>