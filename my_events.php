<?php
require_once '../config.php';
requireAdmin();

$activePage = 'my_events';
$pageTitle  = 'My Events';
$adminId    = $_SESSION['admin_id'];

// ── Handle DELETE (soft cancel) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_event') {
    $eid = (int)($_POST['event_id'] ?? 0);
    $stmt = db()->prepare("UPDATE events SET status='cancelled' WHERE id=? AND created_by=?");
    $stmt->execute([$eid, $adminId]);
    header('Location: my_events.php?msg=cancelled');
    exit;
}

// ── Filters ──────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');

$where  = "WHERE e.created_by = ?";
$params = [$adminId];

if ($filterStatus) { $where .= " AND e.status = ?"; $params[] = $filterStatus; }
if ($search)       { $where .= " AND e.title LIKE ?"; $params[] = "%$search%"; }

$stmt = db()->prepare("
    SELECT e.*,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status='registered') AS reg_count,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status='attended') AS attended_count
    FROM events e
    $where
    ORDER BY e.created_at DESC
");
$stmt->execute($params);
$events = $stmt->fetchAll();

$statuses = ['open','upcoming','under_review','closed','cancelled','archived'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | My Events</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 space-y-6 max-w-7xl w-full mx-auto">

  <?php if (isset($_GET['msg'])): ?>
    <div class="flex items-center gap-3 px-5 py-3 rounded-xl text-sm font-medium
      <?= $_GET['msg']==='created'||$_GET['msg']==='updated' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-amber-50 text-amber-800 border border-amber-200' ?>">
      <i class="fas <?= in_array($_GET['msg'],['created','updated']) ? 'fa-check-circle' : 'fa-info-circle' ?>"></i>
      <?php
        $msgMap = ['created'=>'Event created successfully!','updated'=>'Event updated successfully!','cancelled'=>'Event has been cancelled.'];
        echo $msgMap[$_GET['msg']] ?? 'Done.';
      ?>
    </div>
  <?php endif; ?>

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">My Events</h1>
      <p class="text-sm text-gray-500 mt-0.5"><?= count($events) ?> event<?= count($events)!==1?'s':'' ?> found</p>
    </div>
    <a href="create_event.php" class="btn-primary w-fit">
      <i class="fas fa-plus"></i> Create Event
    </a>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
      <div class="relative flex-1">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
               placeholder="Search events…"
               class="w-full pl-9 pr-4 py-2.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
      </div>
      <select name="status" onchange="this.form.submit()"
              class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-300 bg-white">
        <option value="">All Statuses</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-primary">Filter</button>
      <?php if ($filterStatus || $search): ?>
        <a href="my_events.php" class="btn-secondary">
          <i class="fas fa-times text-xs"></i> Clear
        </a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Events Table -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Event</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Category</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Registered</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Attended</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($events)): ?>
            <tr>
              <td colspan="7" class="px-6 py-16 text-center text-gray-400">
                <i class="fas fa-calendar-xmark text-4xl mb-3 block" style="color:#c4b5e8;"></i>
                No events found. <a href="create_event.php" style="color:#582C83;" class="font-semibold">Create your first event</a>
              </td>
            </tr>
          <?php else: foreach ($events as $ev): ?>
            <tr class="hover-row">
              <td class="px-6 py-4">
                <p class="font-semibold text-gray-800"><?= htmlspecialchars($ev['title']) ?></p>
                <p class="text-xs text-gray-400 mt-0.5"><i class="fas fa-location-dot mr-1"></i><?= htmlspecialchars($ev['venue'] ?? 'TBD') ?></p>
              </td>
              <td class="px-4 py-4 text-xs text-gray-600"><?= $ev['category'] ?></td>
              <td class="px-4 py-4 text-gray-600 whitespace-nowrap text-xs">
                <?= $ev['start_date'] ? date('d M Y', strtotime($ev['start_date'])) : '—' ?>
              </td>
              <td class="px-4 py-4 text-center">
                <span class="font-semibold text-gray-800"><?= $ev['reg_count'] ?></span>
                <span class="text-gray-400 text-xs"> / <?= $ev['capacity'] ?></span>
              </td>
              <td class="px-4 py-4 text-center">
                <span class="font-semibold <?= $ev['attended_count']>0?'text-emerald-700':'text-gray-400' ?>"><?= $ev['attended_count'] ?></span>
              </td>
              <td class="px-4 py-4 text-center">
                <span class="badge status-<?= $ev['status'] ?>"><?= str_replace('_',' ',$ev['status']) ?></span>
              </td>
              <td class="px-4 py-4">
                <div class="flex items-center justify-center gap-2">
                  <a href="attendance.php?event_id=<?= $ev['id'] ?>" class="btn-secondary" title="Attendance">
                    <i class="fas fa-clipboard-user"></i>
                  </a>
                  <a href="create_event.php?edit=<?= $ev['id'] ?>" class="btn-secondary" title="Edit">
                    <i class="fas fa-pen"></i>
                  </a>
                  <?php if (!in_array($ev['status'], ['cancelled','archived'])): ?>
                  <button onclick="confirmCancel(<?= $ev['id'] ?>, '<?= addslashes($ev['title']) ?>')"
                          class="btn-danger" title="Cancel Event">
                    <i class="fas fa-ban"></i>
                  </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</div>

<!-- Hidden cancel form (submitted by confirmCancel) -->
<form id="cancelForm" method="POST" class="hidden">
  <input type="hidden" name="action" value="cancel_event">
  <input type="hidden" name="event_id" id="cancelEventId">
</form>

<?php include 'partials/modals_js.php'; ?>
<script>
function confirmCancel(id, title) {
  openConfirm('cancel', 'Cancel Event?',
    `You're about to cancel "${title}". All registered students will lose their spots. This cannot be undone.`,
    'Yes, Cancel', 'red',
    () => {
      document.getElementById('cancelEventId').value = id;
      document.getElementById('cancelForm').submit();
    }
  );
}
</script>
</body>
</html>