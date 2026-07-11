<?php
require_once '../config.php';
requireSuperAdmin();



$activePage = 'global_events';
$pageTitle  = 'All Events';

// ── AJAX POST actions ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['event_id'])) {
    $eid    = (int) $_POST['event_id'];
    $action = $_POST['action'];

    if ($action === 'force_close') {
        db()->prepare("UPDATE events SET status='closed' WHERE id=:id")
           ->execute(['id'=>$eid]);
        logAction('FORCE CLOSED', "Event ID $eid force-closed");
        jsonResponse(true, 'Event force-closed.', ['reload'=>true]);
    }
    if ($action === 'approve') {
        db()->prepare("UPDATE events SET status='open' WHERE id=:id AND status='under_review'")
           ->execute(['id'=>$eid]);
        logAction('APPROVED', "Event ID $eid approved");
        jsonResponse(true, 'Event approved and published.', ['reload'=>true]);
    }
    if ($action === 'reject') {
        db()->prepare("UPDATE events SET status='cancelled' WHERE id=:id")
           ->execute(['id'=>$eid]);
        logAction('REJECTED', "Event ID $eid rejected");
        jsonResponse(true, 'Event rejected.', ['reload'=>true]);
    }
    jsonResponse(false, 'Unknown action.');
}

// ── Filters ──────────────────────────────────────────────────────
$search    = trim($_GET['q']        ?? '');
$statusF   = $_GET['status']        ?? '';
$categoryF = $_GET['category']      ?? '';

// ── Stat counts ──────────────────────────────────────────────────
$totalEvents    = (int) db()->query("SELECT COUNT(*) FROM events")->fetchColumn();
$openEvents     = (int) db()->query("SELECT COUNT(*) FROM events WHERE status='open'")->fetchColumn();
$upcomingEvents = (int) db()->query("SELECT COUNT(*) FROM events WHERE status='upcoming'")->fetchColumn();
$reviewEvents   = (int) db()->query("SELECT COUNT(*) FROM events WHERE status='under_review'")->fetchColumn();

// ── Events query ─────────────────────────────────────────────────
$sql    = "SELECT e.*, a.name AS created_by_name, a.role AS created_by_role FROM events e LEFT JOIN admins a ON a.id=e.created_by WHERE 1=1";
$params = [];
if ($search)    { $sql .= " AND (e.title LIKE :q OR e.venue LIKE :q OR a.name LIKE :q)"; $params['q'] = "%$search%"; }
if ($statusF)   { $sql .= " AND e.status=:st";  $params['st']  = $statusF; }
if ($categoryF) { $sql .= " AND e.category=:cat"; $params['cat'] = $categoryF; }
$sql .= " ORDER BY e.start_date ASC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$pendingClubs     = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='pending'")->fetchColumn();
$pendingApprovals = (int) db()->query("SELECT COUNT(*) FROM escalated_approvals WHERE status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent Super Admin | All Events</title>
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

  <div class="flex justify-between items-start flex-wrap gap-4">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <h3 class="text-2xl font-bold text-gray-900">All Events</h3>
        <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full border"
              style="background:#f0ebfa;color:#582C83;border-color:#c4a8e8;">
          <i class="fas fa-location-dot text-xs"></i> UiTM Machang
        </span>
      </div>
      <p class="text-sm text-gray-500">All events created by admins registered under UiTM Machang.</p>
    </div>
    <button onclick="showToast('Event CSV exported for UiTM Machang.')" class="btn-primary flex items-center gap-2">
      <i class="fas fa-download"></i> Export
    </button>
  </div>

  <!-- Stat Cards -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="stat-card bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Events</p>
      <p class="text-2xl font-bold text-gray-900 mt-1"><?= $totalEvents ?></p>
      <p class="text-xs text-gray-400 mt-0.5">UiTM Machang</p>
    </div>
    <div class="stat-card bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Open</p>
      <p class="text-2xl font-bold text-emerald-700 mt-1"><?= $openEvents ?></p>
      <p class="text-xs text-gray-400 mt-0.5">Accepting registrations</p>
    </div>
    <div class="stat-card bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Upcoming</p>
      <p class="text-2xl font-bold mt-1" style="color:#582C83;"><?= $upcomingEvents ?></p>
      <p class="text-xs text-gray-400 mt-0.5">Scheduled</p>
    </div>
    <div class="stat-card bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Under Review</p>
      <p class="text-2xl font-bold mt-1 <?= $reviewEvents>0?'text-amber-600':'text-gray-400' ?>"><?= $reviewEvents ?></p>
      <p class="text-xs mt-0.5 <?= $reviewEvents>0?'text-amber-500':'text-gray-400' ?>"><?= $reviewEvents>0?'Needs action':'All clear' ?></p>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap gap-3">
    <input name="q" type="text" value="<?= htmlspecialchars($search) ?>" placeholder="Search event name, venue, or organiser…"
           class="flex-1 min-w-48 bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
    <select name="status" class="bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
      <option value="">All Statuses</option>
      <?php foreach (['open','upcoming','under_review','closed','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $statusF===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="category" class="bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
      <option value="">All Categories</option>
      <?php foreach (['Academic','Cultural','Sports','Other'] as $c): ?>
        <option value="<?= $c ?>" <?= $categoryF===$c?'selected':'' ?>><?= $c ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-primary px-4">Filter</button>
    <?php if ($search || $statusF || $categoryF): ?>
      <a href="global_events.php" class="text-xs text-gray-500 px-3 py-2 rounded-md bg-gray-100 hover:bg-gray-200 flex items-center gap-1">
        <i class="fas fa-times text-[10px]"></i> Clear
      </a>
    <?php endif; ?>
  </form>

  <!-- Events Table -->
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
            <th class="py-3 px-5">Event</th><th class="py-3 px-5">Organiser</th>
            <th class="py-3 px-5">Category</th><th class="py-3 px-5">Capacity</th>
            <th class="py-3 px-5">Status</th><th class="py-3 px-5">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-sm">
        <?php if (empty($events)): ?>
          <tr><td colspan="6" class="py-14 text-center text-gray-400">
            <i class="fas fa-calendar-xmark text-4xl text-gray-300 mb-3 block"></i>
            No events found<?= ($search||$statusF||$categoryF)?' matching your filter':' for UiTM Machang' ?>.
          </td></tr>
        <?php else:
          $catColors = ['Academic'=>'bg-blue-50 text-blue-700','Cultural'=>'bg-amber-50 text-amber-700','Sports'=>'bg-rose-50 text-rose-700','Other'=>'bg-gray-50 text-gray-600'];
          $stColors  = ['open'=>'bg-emerald-50 text-emerald-700','upcoming'=>'bg-blue-50 text-blue-700','under_review'=>'bg-amber-50 text-amber-700','closed'=>'bg-gray-100 text-gray-500','cancelled'=>'bg-red-50 text-red-600'];
          $stLabels  = ['open'=>'Open','upcoming'=>'Upcoming','under_review'=>'Under Review','closed'=>'Closed','cancelled'=>'Cancelled'];
          foreach ($events as $ev):
            $pct      = $ev['capacity'] > 0 ? round(($ev['registered_count']/$ev['capacity'])*100) : 0;
            $catCls   = $catColors[$ev['category']] ?? 'bg-gray-50 text-gray-600';
            $stCls    = $stColors[$ev['status']]    ?? 'bg-gray-50 text-gray-500';
            $stLabel  = $stLabels[$ev['status']]    ?? ucfirst($ev['status']);
            $isReview = $ev['status'] === 'under_review';
            $isClosed = in_array($ev['status'], ['closed','cancelled','archived']);
        ?>
        <tr class="hover-row <?= $isReview?'bg-amber-50/30':'' ?>">
          <td class="py-3.5 px-5">
            <p class="font-semibold text-gray-900"><?= htmlspecialchars($ev['title']) ?></p>
            <p class="text-xs text-gray-400 mt-0.5">
              <?= $ev['start_date'] ? date('d M Y', strtotime($ev['start_date'])) : 'TBC' ?>
              <?= $ev['venue'] ? ' · '.htmlspecialchars($ev['venue']) : '' ?>
            </p>
          </td>
          <td class="py-3.5 px-5 text-xs text-gray-600">
            <?= htmlspecialchars($ev['created_by_name'] ?? '—') ?>
            <?php if ($ev['created_by_role']): ?>
              <p class="text-gray-400"><?= htmlspecialchars($ev['created_by_role']) ?></p>
            <?php endif; ?>
          </td>
          <td class="py-3.5 px-5">
            <span class="px-2 py-0.5 rounded text-xs font-medium <?= $catCls ?>"><?= htmlspecialchars($ev['category'] ?? '—') ?></span>
          </td>
          <td class="py-3.5 px-5 text-xs font-semibold">
            <?php if ($ev['capacity'] > 0): ?>
              <span class="<?= $pct>=90?'text-red-600':($pct>=60?'text-amber-600':'text-gray-700') ?>">
                <?= $ev['registered_count'] ?>/<?= $ev['capacity'] ?>
              </span>
              <div class="w-20 h-1.5 bg-gray-100 rounded-full mt-1">
                <div class="h-1.5 rounded-full <?= $pct>=90?'bg-red-500':($pct>=60?'bg-amber-400':'bg-emerald-500') ?>" style="width:<?= $pct ?>%"></div>
              </div>
            <?php else: ?><span class="text-gray-400">—</span><?php endif; ?>
          </td>
          <td class="py-3.5 px-5">
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $stCls ?>"><?= $stLabel ?></span>
          </td>
          <td class="py-3.5 px-5">
            <div class="flex gap-1 flex-wrap">
              <?php if ($isReview): ?>
                <button onclick="eventAction('approve',<?= $ev['id'] ?>,'<?= addslashes(htmlspecialchars($ev['title'])) ?>','Approve and publish this event? It will become visible to students.','Approve','green')"
                        class="text-xs text-emerald-700 bg-emerald-50 px-2 py-1.5 rounded hover:bg-emerald-100 font-semibold">
                  <i class="fas fa-check mr-1"></i>Approve
                </button>
                <button onclick="eventAction('reject',<?= $ev['id'] ?>,'<?= addslashes(htmlspecialchars($ev['title'])) ?>','Reject this event? It will be cancelled permanently.','Reject','red')"
                        class="text-xs text-red-600 bg-red-50 px-2 py-1.5 rounded hover:bg-red-100 font-semibold">
                  <i class="fas fa-times mr-1"></i>Reject
                </button>
              <?php elseif (!$isClosed): ?>
                <button onclick="eventAction('force_close',<?= $ev['id'] ?>,'<?= addslashes(htmlspecialchars($ev['title'])) ?>','Force-close this event? Registrations will be locked. This cannot be undone by campus admins.','Force Close','red')"
                        class="text-xs text-red-700 bg-red-50 px-2 py-1.5 rounded hover:bg-red-100 font-semibold">
                  <i class="fas fa-ban mr-1"></i>Force Close
                </button>
              <?php else: ?>
                <span class="text-xs text-gray-400 italic">No actions</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
      <span>Showing <?= count($events) ?> event<?= count($events)!==1?'s':'' ?> </span>
      <?php if ($reviewEvents > 0): ?>
        <span class="text-amber-600 font-medium"><i class="fas fa-clock mr-1"></i><?= $reviewEvents ?> pending review</span>
      <?php endif; ?>
    </div>
  </div>

</main>
</div>
</div>

<?php include 'partials/modals_js.php'; ?>
<script>
function eventAction(action, id, title, body, btnLabel, color) {
  openConfirm(action, btnLabel+' Event', `"${title}" — ${body}`, btnLabel, color, function() {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('event_id', id);
    fetch('global_events.php', { method:'POST', body:fd })
      .then(r => r.json())
      .then(d => { showToast(d.message, !d.success); if (d.reload) setTimeout(() => location.reload(), 1200); });
  });
}
</script>
</body>
</html>
