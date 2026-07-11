<?php
require_once '../config.php';
requireAdmin();

$activePage = 'registrations';
$pageTitle  = 'Registrations';
$adminId    = $_SESSION['admin_id'];

// ── Filters ──────────────────────────────────────────────────
$filterEvent = (int)($_GET['event_id'] ?? 0);
$search      = trim($_GET['q'] ?? '');
$filterAtt   = $_GET['att'] ?? '';

$where  = "WHERE e.created_by = ?";
$params = [$adminId];
if ($filterEvent) { $where .= " AND r.event_id = ?"; $params[] = $filterEvent; }
if ($search)      { $where .= " AND (s.name LIKE ? OR s.matric_no LIKE ? OR e.title LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($filterAtt === 'attended') { $where .= " AND r.status = 'attended'"; }
elseif ($filterAtt === 'absent')   { $where .= " AND r.status IN ('cancelled','no_show')"; }
elseif ($filterAtt === 'pending')  { $where .= " AND r.status = 'registered'"; }

$stmt = db()->prepare("
    SELECT r.id, r.status, r.registered_at,
           s.name AS student_name, s.matric_no, s.email,
           e.title AS event_title, e.start_date
    FROM registrations r
    JOIN students s ON s.id = r.student_id
    JOIN events e ON e.id = r.event_id
    $where AND r.status != 'no_show'
    ORDER BY r.registered_at DESC
    LIMIT 200
");
$stmt->execute($params);
$regs = $stmt->fetchAll();

// ── Events list for filter ────────────────────────────────────
$evStmt = db()->prepare("SELECT id, title FROM events WHERE created_by=? ORDER BY start_date DESC");
$evStmt->execute([$adminId]);
$myEvents = $evStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Registrations</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 space-y-6 max-w-6xl w-full mx-auto">

  <!-- Header -->
  <div>
    <h1 class="text-2xl font-bold text-gray-900">Registrations</h1>
    <p class="text-sm text-gray-500 mt-0.5">View all student registrations across your events.</p>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
    <form method="GET" class="flex flex-col sm:flex-row gap-3 flex-wrap">
      <div class="relative flex-1 min-w-[180px]">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
               placeholder="Search name, matric, event…"
               class="w-full pl-9 pr-4 py-2.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
      </div>
      <select name="event_id" class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm bg-white text-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-300">
        <option value="">All Events</option>
        <?php foreach ($myEvents as $ev): ?>
          <option value="<?= $ev['id'] ?>" <?= $filterEvent===$ev['id']?'selected':'' ?>><?= htmlspecialchars($ev['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="att" class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm bg-white text-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-300">
        <option value="">Any Attendance</option>
        <option value="attended" <?= $filterAtt==='attended'?'selected':'' ?>>Attended</option>
        <option value="absent"   <?= $filterAtt==='absent'?'selected':'' ?>>Absent</option>
        <option value="pending"  <?= $filterAtt==='pending'?'selected':'' ?>>Pending</option>
      </select>
      <button type="submit" class="btn-primary">Filter</button>
      <?php if ($filterEvent || $search || $filterAtt): ?>
        <a href="registrations.php" class="btn-secondary flex items-center gap-1"><i class="fas fa-times text-xs"></i> Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h2 class="font-bold text-gray-800 text-sm">All Registrations</h2>
      <span class="text-xs text-gray-400"><?= count($regs) ?> record<?= count($regs)!==1?'s':'' ?></span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Student</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Matric No</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Event</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Registered</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Attendance</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($regs)): ?>
            <tr><td colspan="6" class="px-6 py-16 text-center text-gray-400">
              <i class="fas fa-users-slash text-4xl mb-3 block"></i>
              No registrations found.
            </td></tr>
          <?php else: foreach ($regs as $reg): ?>
            <?php
            $attColors = [
              'attended' => 'bg-green-100 text-green-800',
              'absent'   => 'bg-red-100 text-red-800',
              'pending'  => 'bg-gray-100 text-gray-600',
            ];
            $statusMap = ['attended' => 'attended', 'cancelled' => 'absent', 'no_show' => 'absent', 'registered' => 'pending'];
            $att = $statusMap[$reg['status']] ?? 'pending';
            ?>
            <tr class="hover-row">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center font-semibold text-xs shrink-0"
                       style="background:#f0ebfa;color:#582C83;"><?= strtoupper(substr($reg['student_name'],0,1)) ?></div>
                  <div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($reg['student_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= htmlspecialchars($reg['email']) ?></p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4 font-mono text-xs text-gray-600"><?= htmlspecialchars($reg['matric_no']) ?></td>
              <td class="px-4 py-4">
                <p class="font-medium text-gray-800 truncate max-w-[180px]"><?= htmlspecialchars($reg['event_title']) ?></p>
              </td>
              <td class="px-4 py-4 text-xs text-gray-500 whitespace-nowrap">
                <?= $reg['start_date'] ? date('d M Y', strtotime($reg['start_date'])) : '—' ?>
              </td>
              <td class="px-4 py-4 text-center text-xs text-gray-500"><?= date('d M Y', strtotime($reg["registered_at"])) ?></td>
              <td class="px-4 py-4 text-center">
                <span class="badge <?= $attColors[$att] ?? 'bg-gray-100 text-gray-600' ?>"><?= ucfirst($att) ?></span>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</div>
</body>
</html>