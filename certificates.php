<?php
require_once '../config.php';
requireAdmin();

$activePage = 'certificates';
$pageTitle  = 'Certificates';
$adminId    = $_SESSION['admin_id'];
$errors     = [];

// ── AJAX: Issue certificates for all attended students ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'issue') {
    $eventId    = (int)$_POST['event_id'];
    $templateId = (int)$_POST['template_id'];

    // Verify event belongs to this admin
    $evCheck = db()->prepare("SELECT id FROM events WHERE id=? AND created_by=?");
    $evCheck->execute([$eventId, $adminId]);
    if (!$evCheck->fetch()) jsonResponse(false, 'Unauthorised.');

    // Get attended students who don't have cert yet
    $students = db()->prepare("
        SELECT DISTINCT r.student_id
        FROM registrations r
        WHERE r.event_id = ? AND r.attended_at IS NOT NULL
          AND r.student_id NOT IN (
            SELECT student_id FROM issued_certificates WHERE event_id = ? AND template_id = ?
          )
    ");
    $students->execute([$eventId, $eventId, $templateId]);
    $students = $students->fetchAll();

    $issued = 0;
    $insert = db()->prepare("
        INSERT INTO issued_certificates (template_id, student_id, event_id, cert_type, cert_code, issued_by)
        VALUES (?, ?, ?, 'certificate', ?, ?)
    ");
    foreach ($students as $s) {
        $code = strtoupper(bin2hex(random_bytes(8)));
        $insert->execute([$templateId, $s['student_id'], $eventId, $code, $adminId]);
        $issued++;
    }
    jsonResponse(true, "$issued certificate(s) issued successfully.");
}

// ── AJAX: Save template ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_template') {
    $eventId   = (int)$_POST['event_id'];
    $titleText = trim($_POST['title_text'] ?? 'Certificate of Participation');
    $bodyText  = trim($_POST['body_text'] ?? '');
    $sigName   = trim($_POST['signature_name'] ?? '');
    $sigTitle  = trim($_POST['signature_title'] ?? '');

    $evCheck = db()->prepare("SELECT id FROM events WHERE id=? AND created_by=?");
    $evCheck->execute([$eventId, $adminId]);
    if (!$evCheck->fetch()) jsonResponse(false, 'Unauthorised.');

    // Check if template exists for this event
    $existing = db()->prepare("SELECT id FROM certificate_templates WHERE event_id=? AND created_by=?");
    $existing->execute([$eventId, $adminId]);
    $existing = $existing->fetch();

    if ($existing) {
        db()->prepare("UPDATE certificate_templates SET title_text=?,body_text=?,signature_name=?,signature_title=? WHERE id=?")
           ->execute([$titleText, $bodyText, $sigName, $sigTitle, $existing['id']]);
        jsonResponse(true, 'Template updated.');
    } else {
        db()->prepare("INSERT INTO certificate_templates (event_id, created_by, title_text, body_text, signature_name, signature_title) VALUES (?,?,?,?,?,?)")
           ->execute([$eventId, $adminId, $titleText, $bodyText, $sigName, $sigTitle]);
        jsonResponse(true, 'Template created.');
    }
}

// ── Fetch events with cert stats ──────────────────────────────
$events = db()->prepare("
    SELECT e.id, e.title, e.start_date,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.attended_at IS NOT NULL) AS attended,
           (SELECT COUNT(*) FROM issued_certificates ic WHERE ic.event_id=e.id AND ic.issued_by=?) AS issued,
           (SELECT id FROM certificate_templates ct WHERE ct.event_id=e.id AND ct.created_by=? LIMIT 1) AS template_id,
           (SELECT title_text FROM certificate_templates ct WHERE ct.event_id=e.id AND ct.created_by=? LIMIT 1) AS template_title,
           (SELECT body_text FROM certificate_templates ct WHERE ct.event_id=e.id AND ct.created_by=? LIMIT 1) AS template_body,
           (SELECT signature_name FROM certificate_templates ct WHERE ct.event_id=e.id AND ct.created_by=? LIMIT 1) AS sig_name,
           (SELECT signature_title FROM certificate_templates ct WHERE ct.event_id=e.id AND ct.created_by=? LIMIT 1) AS sig_title
    FROM events e
    WHERE e.created_by = ?
    ORDER BY e.start_date DESC
");
$events->execute([$adminId,$adminId,$adminId,$adminId,$adminId,$adminId,$adminId]);
$events = $events->fetchAll();

// Recent issued certs
$recentCerts = db()->prepare("
    SELECT ic.*, s.name AS student_name, s.matric_no, e.title AS event_title
    FROM issued_certificates ic
    JOIN students s ON s.id = ic.student_id
    JOIN events e ON e.id = ic.event_id
    WHERE ic.issued_by = ?
    ORDER BY ic.issued_at DESC
    LIMIT 20
");
$recentCerts->execute([$adminId]);
$recentCerts = $recentCerts->fetchAll();
$totalIssued = count($recentCerts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Certificates</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
<style>
  .form-input { width:100%; padding:.625rem .875rem; border:1px solid #e5e7eb; border-radius:.5rem; font-size:.875rem; color:#374151; transition:border-color .15s,box-shadow .15s; outline:none; }
  .form-input:focus { border-color:#582C83; box-shadow:0 0 0 3px rgba(88,44,131,.12); }
  .form-label { display:block; font-size:.75rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.375rem; }
  .cert-preview { background:linear-gradient(135deg,#27134A,#582C83); color:#fff; border-radius:12px; padding:32px; text-align:center; position:relative; overflow:hidden; }
  .cert-preview::before { content:''; position:absolute; inset:8px; border:2px solid rgba(249,165,27,0.4); border-radius:8px; pointer-events:none; }
  .tab-btn { padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; transition:all .15s; }
  .tab-btn.active { background:#582C83; color:#fff; }
  .tab-btn:not(.active) { color:#582C83; background:#f0ebfa; }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 space-y-6 max-w-6xl w-full mx-auto">

  <!-- Header -->
  <div>
    <h1 class="text-2xl font-bold text-gray-900">Certificates</h1>
    <p class="text-sm text-gray-500 mt-0.5">Create templates and issue certificates to event participants.</p>
  </div>

  <!-- Tabs -->
  <div class="flex gap-2">
    <button class="tab-btn active" onclick="switchTab('issue', this)">Issue Certificates</button>
    <button class="tab-btn" onclick="switchTab('issued', this)">Issued Certificates</button>
  </div>

  <!-- Tab: Issue -->
  <div id="tab-issue" class="space-y-5">
    <?php if (empty($events)): ?>
    <div class="bg-white rounded-xl p-16 text-center text-gray-400 border border-gray-100">
      <i class="fas fa-certificate text-4xl mb-3 block" style="color:#D1BBF0;"></i>
      <p class="font-semibold text-gray-600">No events yet.</p>
      <a href="create_event.php" class="btn-primary inline-block mt-4">Create an Event</a>
    </div>
    <?php else: ?>
    <?php foreach ($events as $ev): ?>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <div>
          <h3 class="font-bold text-gray-800"><?= htmlspecialchars($ev['title']) ?></h3>
          <p class="text-xs text-gray-400 mt-0.5">
            <?= $ev['start_date'] ? date('d M Y', strtotime($ev['start_date'])) : 'TBD' ?>
            · <?= $ev['attended'] ?> attended · <?= $ev['issued'] ?> certs issued
          </p>
        </div>
        <div class="flex items-center gap-2">
          <?php if ($ev['template_id']): ?>
            <button onclick='issueAll(<?= $ev["id"] ?>, <?= $ev["template_id"] ?>, "<?= addslashes($ev["title"]) ?>", <?= $ev["attended"] - $ev["issued"] ?>)'
              class="btn-primary flex items-center gap-2 <?= ($ev["attended"] - $ev["issued"]) <= 0 ? "opacity-50 cursor-not-allowed" : "" ?>"
              <?= ($ev["attended"] - $ev["issued"]) <= 0 ? "disabled" : "" ?>>
              <i class="fas fa-certificate"></i>
              Issue <?= max(0, $ev['attended'] - $ev['issued']) ?> Cert(s)
            </button>
          <?php endif; ?>
          <button onclick='toggleTemplate(<?= $ev["id"] ?>)'
            class="btn-secondary flex items-center gap-2">
            <i class="fas <?= $ev["template_id"] ? "fa-pen" : "fa-plus" ?>"></i>
            <?= $ev["template_id"] ? "Edit Template" : "Create Template" ?>
          </button>
        </div>
      </div>

      <!-- Template Form (hidden) -->
      <div id="template-<?= $ev['id'] ?>" class="hidden px-6 py-5 bg-gray-50 border-b border-gray-100">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Form -->
          <div class="space-y-4">
            <h4 class="font-semibold text-gray-700 text-sm">Certificate Template</h4>
            <div>
              <label class="form-label">Certificate Title</label>
              <input type="text" id="ttitle-<?= $ev['id'] ?>" class="form-input"
                     value="<?= htmlspecialchars($ev['template_title'] ?? 'Certificate of Participation') ?>">
            </div>
            <div>
              <label class="form-label">Body Text</label>
              <textarea id="tbody-<?= $ev['id'] ?>" rows="3" class="form-input resize-none"
                        placeholder="This is to certify that [NAME] has participated in..."><?= htmlspecialchars($ev['template_body'] ?? '') ?></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="form-label">Signature Name</label>
                <input type="text" id="tsigname-<?= $ev['id'] ?>" class="form-input"
                       value="<?= htmlspecialchars($ev['sig_name'] ?? '') ?>" placeholder="e.g. Dr. Ahmad">
              </div>
              <div>
                <label class="form-label">Signature Title</label>
                <input type="text" id="tsigtitle-<?= $ev['id'] ?>" class="form-input"
                       value="<?= htmlspecialchars($ev['sig_title'] ?? '') ?>" placeholder="e.g. Club Advisor">
              </div>
            </div>
            <button onclick='saveTemplate(<?= $ev["id"] ?>)' class="btn-primary flex items-center gap-2">
              <i class="fas fa-floppy-disk"></i> Save Template
            </button>
          </div>

          <!-- Preview -->
          <div>
            <h4 class="font-semibold text-gray-700 text-sm mb-3">Preview</h4>
            <div class="cert-preview" id="preview-<?= $ev['id'] ?>">
              <div style="color:#F9A51B;font-size:10px;font-weight:700;letter-spacing:.15em;text-transform:uppercase;margin-bottom:8px;">UiVent · UiTM Machang</div>
              <div style="font-size:18px;font-weight:800;margin-bottom:4px;" id="prev-title-<?= $ev['id'] ?>"><?= htmlspecialchars($ev['template_title'] ?? 'Certificate of Participation') ?></div>
              <div style="font-size:11px;opacity:.7;margin-bottom:12px;">This is to certify that</div>
              <div style="font-size:16px;font-weight:700;border-bottom:1px solid rgba(249,165,27,0.5);padding-bottom:8px;margin-bottom:8px;">Student Name</div>
              <div style="font-size:11px;opacity:.8;margin-bottom:16px;" id="prev-body-<?= $ev['id'] ?>"><?= htmlspecialchars($ev['template_body'] ?? 'has successfully participated in ' . $ev['title']) ?></div>
              <div style="display:flex;justify-content:center;gap:40px;margin-top:16px;">
                <div style="text-align:center;">
                  <div style="border-top:1px solid rgba(255,255,255,0.4);padding-top:4px;font-size:10px;">
                    <div style="font-weight:700;" id="prev-signame-<?= $ev['id'] ?>"><?= htmlspecialchars($ev['sig_name'] ?? 'Signature') ?></div>
                    <div style="opacity:.7;" id="prev-sigtitle-<?= $ev['id'] ?>"><?= htmlspecialchars($ev['sig_title'] ?? 'Title') ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats bar -->
      <?php if ($ev['attended'] > 0): ?>
      <div class="px-6 py-3 flex items-center gap-4 text-xs text-gray-500">
        <span><i class="fas fa-users mr-1 text-purple-400"></i><?= $ev['attended'] ?> attended</span>
        <span><i class="fas fa-certificate mr-1 text-amber-400"></i><?= $ev['issued'] ?> issued</span>
        <?php $remaining = max(0, $ev['attended'] - $ev['issued']); ?>
        <?php if ($remaining > 0): ?>
          <span class="text-amber-600 font-semibold"><i class="fas fa-clock mr-1"></i><?= $remaining ?> pending</span>
        <?php else: ?>
          <span class="text-emerald-600 font-semibold"><i class="fas fa-check-circle mr-1"></i>All issued</span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Tab: Issued -->
  <div id="tab-issued" class="hidden">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-800 text-sm">Issued Certificates</h2>
        <span class="text-xs text-gray-400"><?= count($recentCerts) ?> records</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Student</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Event</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Cert Code</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Issued At</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php if (empty($recentCerts)): ?>
            <tr><td colspan="4" class="px-6 py-12 text-center text-gray-400">
              <i class="fas fa-certificate text-3xl mb-3 block" style="color:#D1BBF0;"></i>
              No certificates issued yet.
            </td></tr>
            <?php else: foreach ($recentCerts as $c): ?>
            <tr class="hover-row">
              <td class="px-6 py-4">
                <p class="font-semibold text-gray-800"><?= htmlspecialchars($c['student_name']) ?></p>
                <p class="text-xs text-gray-400"><?= htmlspecialchars($c['matric_no']) ?></p>
              </td>
              <td class="px-4 py-4 text-gray-600 text-xs"><?= htmlspecialchars($c['event_title']) ?></td>
              <td class="px-4 py-4 font-mono text-xs font-semibold" style="color:#582C83;"><?= $c['cert_code'] ?></td>
              <td class="px-4 py-4 text-xs text-gray-500"><?= date('d M Y H:i', strtotime($c['issued_at'])) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</main>
</div>

<?php include 'partials/modals_js.php'; ?>
<script>
// Tabs
function switchTab(name, btn) {
  document.querySelectorAll('[id^="tab-"]').forEach(t => t.classList.add('hidden'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.remove('hidden');
  btn.classList.add('active');
}

// Toggle template form
function toggleTemplate(eventId) {
  const el = document.getElementById('template-' + eventId);
  el.classList.toggle('hidden');
}

// Live preview
function setupPreview(eventId) {
  ['ttitle','tbody','tsigname','tsigtitle'].forEach(field => {
    const el = document.getElementById(field + '-' + eventId);
    if (!el) return;
    el.addEventListener('input', () => {
      const previewMap = {
        'ttitle':    'prev-title-'   + eventId,
        'tbody':     'prev-body-'    + eventId,
        'tsigname':  'prev-signame-' + eventId,
        'tsigtitle': 'prev-sigtitle-'+ eventId,
      };
      const preview = document.getElementById(previewMap[field]);
      if (preview) preview.textContent = el.value;
    });
  });
}
<?php foreach ($events as $ev): ?>
setupPreview(<?= $ev['id'] ?>);
<?php endforeach; ?>

// Save template
function saveTemplate(eventId) {
  const fd = new FormData();
  fd.append('action',          'save_template');
  fd.append('event_id',        eventId);
  fd.append('title_text',      document.getElementById('ttitle-'    + eventId)?.value || '');
  fd.append('body_text',       document.getElementById('tbody-'     + eventId)?.value || '');
  fd.append('signature_name',  document.getElementById('tsigname-'  + eventId)?.value || '');
  fd.append('signature_title', document.getElementById('tsigtitle-' + eventId)?.value || '');

  fetch('certificates.php', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => { showToast(d.message, !d.success); if (d.success) setTimeout(() => location.reload(), 1000); })
    .catch(() => showToast('Network error.', true));
}

// Issue all certs
function issueAll(eventId, templateId, title, remaining) {
  if (remaining <= 0) return;
  openConfirm('issue', 'Issue Certificates',
    `Issue ${remaining} certificate(s) for "${title}" to all attended students?`,
    'Issue Certificates', 'purple', () => {
      const fd = new FormData();
      fd.append('action',      'issue');
      fd.append('event_id',    eventId);
      fd.append('template_id', templateId);
      fetch('certificates.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => { showToast(d.message, !d.success); if (d.success) setTimeout(() => location.reload(), 1000); })
        .catch(() => showToast('Network error.', true));
    });
}
</script>
</body>
</html>