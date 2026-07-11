<?php
require_once '../config.php';
requireAdmin();

$activePage = 'announcements';
$pageTitle  = 'Announcements';
$adminId    = $_SESSION['admin_id'];
$errors     = [];

// ── AJAX delete ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)$_POST['id'];
    db()->prepare("DELETE FROM club_announcements WHERE id = ? AND admin_id = ?")->execute([$id, $adminId]);
    jsonResponse(true, 'Announcement deleted.');
}

// ── Handle create/edit ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create','edit'])) {
    $action  = $_POST['action'];
    $title   = trim($_POST['title'] ?? '');
    $body    = trim($_POST['body'] ?? '');
    $eventId = (int)($_POST['event_id'] ?? 0) ?: null;
    $status  = $_POST['status'] ?? 'draft';

    if (!$title) $errors[] = 'Title is required.';
    if (!$body)  $errors[] = 'Message body is required.';

    if (empty($errors)) {
        $sentAt = $status === 'sent' ? date('Y-m-d H:i:s') : null;
        if ($action === 'create') {
            db()->prepare("INSERT INTO club_announcements (admin_id, event_id, title, body, status, sent_at) VALUES (?,?,?,?,?,?)")
               ->execute([$adminId, $eventId, $title, $body, $status, $sentAt]);
        } else {
            $editId = (int)$_POST['edit_id'];
            db()->prepare("UPDATE club_announcements SET title=?, body=?, event_id=?, status=?, sent_at=? WHERE id=? AND admin_id=?")
               ->execute([$title, $body, $eventId, $status, $sentAt, $editId, $adminId]);
        }
        header('Location: announcements.php?msg=' . ($action === 'create' ? 'created' : 'updated'));
        exit;
    }
}

// ── Fetch announcements ───────────────────────────────────────
$announcements = db()->prepare("
    SELECT a.*, e.title AS event_title
    FROM club_announcements a
    LEFT JOIN events e ON e.id = a.event_id
    WHERE a.admin_id = ?
    ORDER BY a.created_at DESC
");
$announcements->execute([$adminId]);
$announcements = $announcements->fetchAll();

// Events for dropdown
$evStmt = db()->prepare("SELECT id, title FROM events WHERE created_by = ? ORDER BY start_date DESC");
$evStmt->execute([$adminId]);
$myEvents = $evStmt->fetchAll();

$totalSent   = count(array_filter($announcements, fn($a) => $a['status'] === 'sent'));
$totalDrafts = count(array_filter($announcements, fn($a) => $a['status'] === 'draft'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Announcements</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
<style>
  .form-input { width:100%; padding:.625rem .875rem; border:1px solid #e5e7eb; border-radius:.5rem; font-size:.875rem; color:#374151; transition:border-color .15s,box-shadow .15s; outline:none; }
  .form-input:focus { border-color:#582C83; box-shadow:0 0 0 3px rgba(88,44,131,.12); }
  .form-label { display:block; font-size:.75rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.375rem; }
  .badge-sent  { background:#D1FAE5; color:#065F46; }
  .badge-draft { background:#FEF3C7; color:#92400E; }
  .slide-panel { transition: all .3s ease; }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 space-y-6 max-w-6xl w-full mx-auto">

  <?php if (isset($_GET['msg'])): ?>
  <div class="flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-medium bg-green-50 text-green-800 border border-green-200">
    <i class="fas fa-check-circle"></i>
    Announcement <?= $_GET['msg'] === 'created' ? 'created' : 'updated' ?> successfully!
  </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
  <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-5 py-4 text-sm space-y-1">
    <?php foreach ($errors as $e): ?>
      <p class="flex items-start gap-2"><i class="fas fa-circle-exclamation mt-0.5 shrink-0"></i><?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Header -->
  <div class="flex items-center justify-between flex-wrap gap-4">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Announcements</h1>
      <p class="text-sm text-gray-500 mt-0.5">Send announcements to your event participants.</p>
    </div>
    <button onclick="toggleForm()" class="btn-primary flex items-center gap-2">
      <i class="fas fa-plus"></i> New Announcement
    </button>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-3 gap-4">
    <?php $cards = [
      ['label'=>'Total','val'=>count($announcements),'icon'=>'fa-bullhorn','color'=>'#582C83','bg'=>'#f0ebfa'],
      ['label'=>'Sent', 'val'=>$totalSent,           'icon'=>'fa-paper-plane','color'=>'#059669','bg'=>'#d1fae5'],
      ['label'=>'Drafts','val'=>$totalDrafts,         'icon'=>'fa-file-lines','color'=>'#d97706','bg'=>'#fef3c7'],
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

  <!-- Create/Edit Form (hidden by default) -->
  <div id="announcementForm" class="hidden bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h2 class="font-bold text-gray-800 mb-5 flex items-center gap-2">
      <i class="fas fa-bullhorn text-sm" style="color:#582C83;"></i>
      <span id="formTitle">New Announcement</span>
    </h2>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="edit_id" id="formEditId" value="">

      <div>
        <label class="form-label">Title <span class="text-red-500">*</span></label>
        <input type="text" name="title" id="formTitleInput" class="form-input" placeholder="e.g. Event Reminder — Sports Day 2026" maxlength="255" required>
      </div>

      <div>
        <label class="form-label">Message <span class="text-red-500">*</span></label>
        <textarea name="body" id="formBody" rows="5" class="form-input resize-none" placeholder="Write your announcement here…" required></textarea>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="form-label">Link to Event (optional)</label>
          <select name="event_id" id="formEventId" class="form-input bg-white">
            <option value="">— No specific event —</option>
            <?php foreach ($myEvents as $ev): ?>
              <option value="<?= $ev['id'] ?>"><?= htmlspecialchars($ev['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Status</label>
          <select name="status" id="formStatus" class="form-input bg-white">
            <option value="draft">Save as Draft</option>
            <option value="sent">Send Now</option>
          </select>
        </div>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn-primary flex items-center gap-2">
          <i class="fas fa-paper-plane"></i> <span id="formSubmitLabel">Create Announcement</span>
        </button>
        <button type="button" onclick="toggleForm()" class="btn-secondary">Cancel</button>
      </div>
    </form>
  </div>

  <!-- Announcements List -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 class="font-bold text-gray-800 text-sm">All Announcements</h2>
      <span class="text-xs text-gray-400"><?= count($announcements) ?> total</span>
    </div>

    <?php if (empty($announcements)): ?>
    <div class="py-16 text-center text-gray-400">
      <i class="fas fa-bullhorn text-4xl mb-3 block" style="color:#D1BBF0;"></i>
      <p class="font-semibold text-gray-600">No announcements yet.</p>
      <p class="text-xs mt-1">Click "New Announcement" to create one.</p>
    </div>
    <?php else: ?>
    <div class="divide-y divide-gray-50">
      <?php foreach ($announcements as $a): ?>
      <div class="px-6 py-5 hover:bg-gray-50 transition-colors">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <span class="badge text-xs font-bold px-2.5 py-1 rounded-full <?= $a['status'] === 'sent' ? 'badge-sent' : 'badge-draft' ?>">
                <?= $a['status'] === 'sent' ? '✓ Sent' : '✎ Draft' ?>
              </span>
              <?php if ($a['event_title']): ?>
                <span class="text-xs font-medium px-2 py-0.5 rounded-md" style="background:rgba(88,44,131,0.1);color:#582C83;">
                  <i class="fas fa-calendar-alt mr-1"></i><?= htmlspecialchars($a['event_title']) ?>
                </span>
              <?php endif; ?>
              <span class="text-xs text-gray-400"><?= date('d M Y, H:i', strtotime($a['created_at'])) ?></span>
            </div>
            <h3 class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($a['title']) ?></h3>
            <p class="text-sm text-gray-500 mt-1 line-clamp-2"><?= nl2br(htmlspecialchars($a['body'])) ?></p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button onclick='editAnnouncement(<?= json_encode([
              "id"       => $a["id"],
              "title"    => $a["title"],
              "body"     => $a["body"],
              "event_id" => $a["event_id"],
              "status"   => $a["status"],
            ]) ?>)'
              class="w-8 h-8 rounded-lg flex items-center justify-center"
              style="background:rgba(88,44,131,0.1);color:#582C83;" title="Edit">
              <i class="fas fa-pen text-xs"></i>
            </button>
            <button onclick='deleteAnnouncement(<?= $a["id"] ?>, "<?= addslashes($a["title"]) ?>")'
              class="w-8 h-8 rounded-lg flex items-center justify-center"
              style="background:rgba(239,68,68,0.1);color:#991B1B;" title="Delete">
              <i class="fas fa-trash-can text-xs"></i>
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</main>
</div>

<?php include 'partials/modals_js.php'; ?>
<script>
function toggleForm(show) {
  const f = document.getElementById('announcementForm');
  if (show === undefined) show = f.classList.contains('hidden');
  f.classList.toggle('hidden', !show);
  if (show) f.scrollIntoView({behavior:'smooth', block:'start'});
}

function editAnnouncement(a) {
  document.getElementById('formTitle').textContent       = 'Edit Announcement';
  document.getElementById('formAction').value            = 'edit';
  document.getElementById('formEditId').value            = a.id;
  document.getElementById('formTitleInput').value        = a.title;
  document.getElementById('formBody').value              = a.body;
  document.getElementById('formEventId').value           = a.event_id || '';
  document.getElementById('formStatus').value            = a.status;
  document.getElementById('formSubmitLabel').textContent = 'Save Changes';
  toggleForm(true);
}

function deleteAnnouncement(id, title) {
  openConfirm('delete', 'Delete Announcement', `Delete "${title}"? This cannot be undone.`, 'Delete', 'red', () => {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('announcements.php', {method:'POST', body:fd})
      .then(r => r.json())
      .then(d => { showToast(d.message, !d.success); if (d.success) setTimeout(() => location.reload(), 800); })
      .catch(() => showToast('Network error.', true));
  });
}
</script>
</body>
</html>