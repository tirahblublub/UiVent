<?php
require_once '../config.php';
requireSuperAdmin();

// CSV Export
if (isset($_GET['export'])) {
    $logs = db()->query("SELECT al.*, c.name AS campus_name FROM audit_log al LEFT JOIN campuses c ON c.id=al.campus_id ORDER BY al.created_at DESC")->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_log_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Timestamp','Admin','Action','Target','Campus','IP']);
    foreach ($logs as $l) fputcsv($out, [$l['created_at'],$l['actor_name'],$l['action'],$l['target'],$l['campus_name']??'Global',$l['ip_address']]);
    fclose($out); exit;
}

$activePage = 'audit_log';
$pageTitle  = 'Audit Log';

$pendingClubs     = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='pending'")->fetchColumn();
$pendingApprovals = (int) db()->query("SELECT COUNT(*) FROM escalated_approvals WHERE status='pending'")->fetchColumn();

// Filters
$search    = $_GET['q']          ?? '';
$actor     = $_GET['actor']      ?? '';
$action    = $_GET['action']     ?? '';
$date      = $_GET['date']       ?? '';
$actorType = $_GET['actor_type'] ?? '';
$page   = max(1,(int)($_GET['page']??1));
$perPage = 15;
$offset  = ($page-1)*$perPage;

$where=[]; $params=[];
if ($search)    { $where[]="(al.actor_name LIKE :s OR al.action LIKE :s OR al.target LIKE :s)"; $params['s']="%$search%"; }
if ($actor)     { $where[]="al.actor_name=:actor";     $params['actor']=$actor; }
if ($action)    { $where[]="al.action=:action";         $params['action']=$action; }
if ($date)      { $where[]="DATE(al.created_at)=:date"; $params['date']=$date; }
if ($actorType) { $where[]="al.actor_type=:actor_type"; $params['actor_type']=$actorType; }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

$countStmt = db()->prepare("SELECT COUNT(*) FROM audit_log al $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

// If filtered results are empty but unfiltered table has data, strip actor_type filter and redirect
if ($total == 0 && $actorType && !$search && !$actor && !$action && !$date) {
    $totalAll = (int) db()->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
    if ($totalAll > 0) {
        header('Location: audit_log.php');
        exit;
    }
}

$logStmt = db()->prepare("SELECT al.*, c.name AS campus_name FROM audit_log al LEFT JOIN campuses c ON c.id=al.campus_id $whereStr ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset");
$logStmt->execute($params);
$logs = $logStmt->fetchAll();

$actors  = db()->query("SELECT DISTINCT actor_name FROM audit_log ORDER BY actor_name")->fetchAll(PDO::FETCH_COLUMN);
$actions = db()->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent Super Admin | Audit Log</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
<style>
  .log-row:hover { background:#f5f0ff; }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<div class="flex-1">
<main class="p-6 md:p-8 space-y-6 max-w-7xl w-full mx-auto">

  <div class="flex justify-between items-start">
    <div>
      <h3 class="text-2xl font-bold text-gray-900">Audit Log</h3>
      <p class="text-sm text-gray-500 mt-1">Full immutable record of all admin actions across the entire platform.</p>
    </div>
    <a href="audit_log.php?export=1" class="btn-primary flex items-center gap-2">
      <i class="fas fa-download"></i> Export Log
    </a>
  </div>

  <!-- Filters -->
  <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap gap-3">
    <input name="q" type="text" value="<?= htmlspecialchars($search) ?>" placeholder="Search action or admin…"
           class="flex-1 min-w-48 bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
    <select name="actor_type" class="bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
      <option value="">All Types</option>
      <option value="super_admin" <?= $actorType==='super_admin'?'selected':'' ?>>Super Admin</option>
      <option value="admin"       <?= $actorType==='admin'?'selected':'' ?>>Club Admin</option>
    </select>
    <select name="actor" class="bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
      <option value="">All Admins</option>
      <?php foreach ($actors as $a): ?>
        <option value="<?= htmlspecialchars($a) ?>" <?= $actor===$a?'selected':'' ?>><?= htmlspecialchars($a) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="action" class="bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
      <option value="">All Actions</option>
      <?php foreach ($actions as $ac): ?>
        <option value="<?= htmlspecialchars($ac) ?>" <?= $action===$ac?'selected':'' ?>><?= htmlspecialchars($ac) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date" value="<?= htmlspecialchars($date) ?>"
           class="bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-purple-600 focus:outline-none">
    <button type="submit" class="btn-primary px-4">Filter</button>
    <?php if ($search||$actor||$action||$date||$actorType): ?>
      <a href="audit_log.php" class="text-xs font-semibold px-4 py-2 rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200 flex items-center">Clear</a>
    <?php endif; ?>
  </form>

  <!-- Log Table -->
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
    <div class="scrollable">
      <table class="w-full text-left border-collapse">
        <thead class="sticky top-0 bg-gray-50 z-10">
          <tr class="border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
            <th class="py-3 px-5">Timestamp</th><th class="py-3 px-5">Admin</th>
            <th class="py-3 px-5">Type</th>
            <th class="py-3 px-5">Action</th><th class="py-3 px-5">Target</th>
            <th class="py-3 px-5">Campus</th><th class="py-3 px-5">IP</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-xs">
        <?php
        $actionColors = [
          'APPROVED'      => 'bg-emerald-50 text-emerald-700',
          'PUBLISHED'     => 'bg-blue-50 text-blue-700',
          'CONFIG CHANGED'=> 'bg-red-50 text-red-700',
          'EVENT CREATED' => 'bg-purple-50 text-purple-700',
          'REJECTED'      => 'bg-rose-50 text-rose-700',
          'ADMIN INVITED' => 'bg-amber-50 text-amber-700',
          'EXPORTED'      => 'bg-gray-100 text-gray-700',
          'FORCE CLOSED'  => 'bg-red-50 text-red-700',
          'SUSPENDED'     => 'bg-red-50 text-red-700',
          'LOGIN'         => 'bg-emerald-50 text-emerald-700',
          'LOGOUT'        => 'bg-gray-100 text-gray-700',
        ];
        foreach ($logs as $l):
          $ac = $actionColors[$l['action']] ?? 'bg-gray-100 text-gray-600';
          $isSA = $l['actor_type'] === 'super_admin';
        ?>
        <tr class="log-row">
          <td class="py-3 px-5 text-gray-400 whitespace-nowrap"><?= date('d M Y, h:i A', strtotime($l['created_at'])) ?></td>
          <td class="py-3 px-5 font-semibold <?= $isSA?'text-red-700':'text-gray-800' ?>"><?= htmlspecialchars($l['actor_name']) ?></td>
          <td class="py-3 px-5">
            <?php if ($isSA): ?>
              <span class="bg-red-50 text-red-700 font-bold px-2 py-0.5 rounded text-xs">Super Admin</span>
            <?php else: ?>
              <span class="bg-purple-50 text-purple-700 font-bold px-2 py-0.5 rounded text-xs">Club Admin</span>
            <?php endif; ?>
          </td>
          <td class="py-3 px-5"><span class="<?= $ac ?> font-bold px-2 py-0.5 rounded"><?= htmlspecialchars($l['action']) ?></span></td>
          <td class="py-3 px-5 text-gray-600"><?= htmlspecialchars($l['target'] ?? '—') ?></td>
          <td class="py-3 px-5 text-gray-500"><?= htmlspecialchars($l['campus_name'] ?? 'Global') ?></td>
          <td class="py-3 px-5 text-gray-400"><?= htmlspecialchars($l['ip_address'] ?? '—') ?></td>
        </tr>
        <?php endforeach; if (empty($logs)): ?>
        <tr><td colspan="7" class="py-10 text-center text-gray-400">No log entries found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
      <span>Showing <?= count($logs) ?> of <?= number_format($total) ?> entries</span>
      <div class="flex gap-1">
        <?php
        $totalPages = ceil($total/$perPage);
        $qs = http_build_query(['q'=>$search,'actor'=>$actor,'action'=>$action,'date'=>$date,'actor_type'=>$actorType]);
        if ($page > 1): ?>
          <a href="?page=<?= $page-1 ?>&<?= $qs ?>" class="px-3 py-1.5 rounded-md bg-gray-100 font-medium">Prev</a>
        <?php endif; ?>
        <?php for ($p=1; $p<=$totalPages && $p<=7; $p++): ?>
          <a href="?page=<?= $p ?>&<?= $qs ?>"
             class="px-3 py-1.5 rounded-md font-medium <?= $p===$page?'text-white':'bg-gray-100' ?>"
             <?= $p===$page?'style="background:#582C83;"':'' ?>><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page+1 ?>&<?= $qs ?>" class="px-3 py-1.5 rounded-md bg-gray-100 font-medium">Next</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

</main>
</div>
</div>
<?php include 'partials/modals_js.php'; ?>
</body>
</html>