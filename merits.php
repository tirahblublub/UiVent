<?php
require_once '../config.php';
requireAdmin();

$activePage = 'merits';
$pageTitle  = 'Merit Points';
$adminId    = $_SESSION['admin_id'];

// ── AJAX: Save merit settings for an event ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    $eventId = (int)$_POST['event_id'];
    $points  = max(1, (int)$_POST['points']);
    $desc    = trim($_POST['description'] ?? '');

    $evCheck = db()->prepare("SELECT id FROM events WHERE id=? AND created_by=?");
    $evCheck->execute([$eventId, $adminId]);
    if (!$evCheck->fetch()) jsonResponse(false, 'Unauthorised.');

    $existing = db()->prepare("SELECT id FROM merit_settings WHERE event_id=? AND admin_id=?");
    $existing->execute([$eventId, $adminId]);
    if ($existing->fetch()) {
        db()->prepare("UPDATE merit_settings SET points=?, description=? WHERE event_id=? AND admin_id=?")
           ->execute([$points, $desc, $eventId, $adminId]);
    } else {
        db()->prepare("INSERT INTO merit_settings (event_id, admin_id, points, description) VALUES (?,?,?,?)")
           ->execute([$eventId, $adminId, $points, $desc]);
    }
    jsonResponse(true, "Merit settings saved — $points points per student.");
}

// ── AJAX: Award merit to all attended students ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'award_all') {
    $eventId = (int)$_POST['event_id'];
    $points  = max(1, (int)$_POST['points']);
    $reason  = trim($_POST['reason'] ?? '');

    $evCheck = db()->prepare("SELECT id, title FROM events WHERE id=? AND created_by=?");
    $evCheck->execute([$eventId, $adminId]);
    $event = $evCheck->fetch();
    if (!$event) jsonResponse(false, 'Unauthorised.');

    // Get attended students who don't have merit for this event yet
    $students = db()->prepare("
        SELECT DISTINCT r.student_id
        FROM registrations r
        WHERE r.event_id = ? AND r.attended_at IS NOT NULL
          AND r.student_id NOT IN (
            SELECT student_id FROM merit_awards WHERE event_id = ?
          )
    ");
    $students->execute([$eventId, $eventId]);
    $students = $students->fetchAll();

    $awarded = 0;
    $insert  = db()->prepare("INSERT INTO merit_awards (student_id, event_id, admin_id, points, reason) VALUES (?,?,?,?,?)");
    $reasonText = $reason ?: 'Attended: ' . $event['title'];
    foreach ($students as $s) {
        $insert->execute([$s['student_id'], $eventId, $adminId, $points, $reasonText]);
        $awarded++;
    }
    jsonResponse(true, "$awarded student(s) awarded $points merit point(s) each.");
}

// ── AJAX: Revoke merit for a student ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revoke') {
    $awardId = (int)$_POST['award_id'];
    db()->prepare("DELETE FROM merit_awards WHERE id=? AND admin_id=?")->execute([$awardId, $adminId]);
    jsonResponse(true, 'Merit revoked.');
}

// ── Fetch events with merit stats ─────────────────────────────
$events = db()->prepare("
    SELECT e.id, e.title, e.start_date, e.status,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.attended_at IS NOT NULL) AS attended,
           (SELECT COUNT(*) FROM merit_awards ma WHERE ma.event_id=e.id AND ma.admin_id=?) AS awarded,
           (SELECT points FROM merit_settings ms WHERE ms.event_id=e.id AND ms.admin_id=? LIMIT 1) AS merit_points,
           (SELECT description FROM merit_settings ms WHERE ms.event_id=e.id AND ms.admin_id=? LIMIT 1) AS merit_desc
    FROM events e
    WHERE e.created_by = ?
    ORDER BY e.start_date DESC
");
$events->execute([$adminId, $adminId, $adminId, $adminId]);
$events = $events->fetchAll();

// ── Total merit points per student (leaderboard) ──────────────
$leaderboard = db()->prepare("
    SELECT s.id, s.name, s.matric_no, s.email,
           COUNT(ma.id) AS events_count,
           SUM(ma.points) AS total_points
    FROM merit_awards ma
    JOIN students s ON s.id = ma.student_id
    JOIN events e ON e.id = ma.event_id
    WHERE e.created_by = ?
    GROUP BY s.id
    ORDER BY total_points DESC
    LIMIT 20
");
$leaderboard->execute([$adminId]);
$leaderboard = $leaderboard->fetchAll();

// ── View awards for a specific event ─────────────────────────
$viewEventId = (int)($_GET['event_id'] ?? 0);
$eventAwards = [];
$viewEvent   = null;
if ($viewEventId) {
    $evStmt = db()->prepare("SELECT * FROM events WHERE id=? AND created_by=?");
    $evStmt->execute([$viewEventId, $adminId]);
    $viewEvent = $evStmt->fetch();

    if ($viewEvent) {
        $aStmt = db()->prepare("
            SELECT ma.*, s.name AS student_name, s.matric_no, s.email
            FROM merit_awards ma
            JOIN students s ON s.id = ma.student_id
            WHERE ma.event_id = ? AND ma.admin_id = ?
            ORDER BY ma.awarded_at DESC
        ");
        $aStmt->execute([$viewEventId, $adminId]);
        $eventAwards = $aStmt->fetchAll();
    }
}

// Summary stats
$totalAwarded   = array_sum(array_column($leaderboard, 'total_points'));
$totalStudents  = count($leaderboard);
$topStudent     = $leaderboard[0] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Merit Points</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
<style>
  .tab-btn { padding:8px 18px; border-radius:8px; font-size:13px; font-weight:600; transition:all .15s; cursor:pointer; }
  .tab-btn.active { background:#582C83; color:#fff; }
  .tab-btn:not(.active) { color:#582C83; background:#f0ebfa; }

  .points-badge {
    display:inline-flex; align-items:center; gap:4px;
    background:#fef3c7; color:#92400e;
    font-size:12px; font-weight:800;
    padding:3px 10px; border-radius:20px;
    border:1px solid #fde68a;
  }

  .merit-bar-bg { background:#f0ebfa; border-radius:99px; height:6px; overflow:hidden; }
  .merit-bar    { background:linear-gradient(90deg,#582C83,#F9A51B); height:100%; border-radius:99px; transition:width .5s ease; }

  .rank-1 { background:linear-gradient(135deg,#fef3c7,#fde68a); border-color:#f59e0b; }
  .rank-2 { background:linear-gradient(135deg,#f3f4f6,#e5e7eb); border-color:#9ca3af; }
  .rank-3 { background:linear-gradient(135deg,#fef2e7,#fed7aa); border-color:#f97316; }

  .event-card { transition:all .15s; }
  .event-card:hover { border-color:#c4b5e8; box-shadow:0 4px 16px rgba(88,44,131,0.1); }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-4 md:p-8 space-y-6 max-w-7xl w-full mx-auto">

  <!-- Header -->
  <div class="flex items-center justify-between flex-wrap gap-4">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <i class="fas fa-star text-amber-400"></i> Merit Points
      </h1>
      <p class="text-sm text-gray-500 mt-0.5">Award merit points to students who attend your events.</p>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $maxPoints = !empty($leaderboard) ? (int)$leaderboard[0]['total_points'] : 1;
    $cards = [
      ['label'=>'Total Points Awarded', 'val'=>number_format($totalAwarded), 'icon'=>'fa-star',          'color'=>'#d97706','bg'=>'#fef3c7'],
      ['label'=>'Students with Merits', 'val'=>$totalStudents,               'icon'=>'fa-user-graduate', 'color'=>'#582C83','bg'=>'#f0ebfa'],
      ['label'=>'Events with Merits',   'val'=>count(array_filter($events, fn($e)=>$e['awarded']>0)), 'icon'=>'fa-calendar-check','color'=>'#059669','bg'=>'#d1fae5'],
      ['label'=>'Top Student Points',   'val'=>$topStudent ? $topStudent['total_points'] : 0, 'icon'=>'fa-trophy','color'=>'#dc2626','bg'=>'#fee2e2'],
    ];
    foreach ($cards as $c): ?>
    <div class="stat-card bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;">
        <i class="fas <?= $c['icon'] ?>"></i>
      </div>
      <div>
        <p class="text-2xl font-extrabold text-gray-900"><?= $c['val'] ?></p>
        <p class="text-xs font-semibold text-gray-400 leading-tight"><?= $c['label'] ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Tabs -->
  <div class="flex gap-2 flex-wrap">
    <button class="tab-btn active" onclick="switchTab('events', this)">
      <i class="fas fa-calendar-alt mr-1"></i> Events
    </button>
    <button class="tab-btn" onclick="switchTab('leaderboard', this)">
      <i class="fas fa-trophy mr-1"></i> Leaderboard
    </button>
    <?php if ($viewEvent): ?>
    <button class="tab-btn" onclick="switchTab('detail', this)" id="detailTabBtn">
      <i class="fas fa-list mr-1"></i> <?= htmlspecialchars($viewEvent['title']) ?>
    </button>
    <?php endif; ?>
  </div>

  <!-- ═══ TAB: EVENTS ════════════════════════════════════════ -->
  <div id="tab-events" class="space-y-4">
    <?php if (empty($events)): ?>
    <div class="bg-white rounded-2xl p-16 text-center border border-gray-100">
      <i class="fas fa-star text-4xl mb-3 block" style="color:#fde68a;"></i>
      <p class="font-semibold text-gray-600">No events yet.</p>
      <a href="create_event.php" class="btn-primary inline-flex mt-4">Create an Event</a>
    </div>
    <?php else: foreach ($events as $ev):
      $remaining = max(0, $ev['attended'] - $ev['awarded']);
      $pct = $ev['attended'] > 0 ? round($ev['awarded']/$ev['attended']*100) : 0;
    ?>
    <div class="event-card bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="px-4 py-4 sm:px-6 sm:py-5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <!-- Event info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <h3 class="font-bold text-gray-800"><?= htmlspecialchars($ev['title']) ?></h3>
              <span class="badge status-<?= $ev['status'] ?>"><?= $ev['status'] ?></span>
              <?php if ($ev['merit_points']): ?>
                <span class="points-badge"><i class="fas fa-star text-xs"></i><?= $ev['merit_points'] ?> pts/student</span>
              <?php endif; ?>
            </div>
            <p class="text-xs text-gray-400">
              <?= $ev['start_date'] ? date('d M Y', strtotime($ev['start_date'])) : 'TBD' ?>
              · <i class="fas fa-users mr-1"></i><?= $ev['attended'] ?> attended
              · <i class="fas fa-star mr-1 text-amber-400"></i><?= $ev['awarded'] ?> awarded
              <?php if ($remaining > 0): ?>
                · <span class="text-amber-600 font-semibold"><?= $remaining ?> pending</span>
              <?php elseif ($ev['awarded'] > 0): ?>
                · <span class="text-emerald-600 font-semibold">all awarded ✓</span>
              <?php endif; ?>
            </p>

            <!-- Progress bar -->
            <?php if ($ev['attended'] > 0): ?>
            <div class="mt-3 merit-bar-bg w-full max-w-xs">
              <div class="merit-bar" style="width:<?= $pct ?>%;"></div>
            </div>
            <p class="text-xs text-gray-400 mt-1"><?= $pct ?>% awarded</p>
            <?php endif; ?>
          </div>

          <!-- Actions -->
          <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
            <?php if ($ev['awarded'] > 0): ?>
            <a href="?event_id=<?= $ev['id'] ?>" onclick="setTimeout(()=>switchTab('detail',document.getElementById('detailTabBtn')),100)"
               class="btn-secondary flex items-center gap-1">
              <i class="fas fa-list text-xs"></i> View Awards
            </a>
            <?php endif; ?>
            <button onclick="toggleSettings(<?= $ev['id'] ?>)" class="btn-secondary flex items-center gap-1">
              <i class="fas fa-gear text-xs"></i> Settings
            </button>
            <?php if ($ev['merit_points'] && $remaining > 0): ?>
            <button onclick='awardAll(<?= $ev["id"] ?>, <?= $ev["merit_points"] ?>, "<?= addslashes($ev["title"]) ?>", <?= $remaining ?>)'
                    class="btn-primary flex items-center gap-2">
              <i class="fas fa-star"></i> Award <?= $remaining ?> Students
            </button>
            <?php elseif (!$ev['merit_points']): ?>
            <button onclick="toggleSettings(<?= $ev['id'] ?>)"
                    class="btn-primary flex items-center gap-2" style="background:#d97706;">
              <i class="fas fa-plus"></i> Set Points
            </button>
            <?php endif; ?>
          </div>
        </div>

        <!-- Settings Panel (hidden) -->
        <div id="settings-<?= $ev['id'] ?>" class="hidden mt-5 pt-5 border-t border-gray-100">
          <h4 class="font-bold text-gray-700 text-sm mb-4 flex items-center gap-2">
            <i class="fas fa-gear text-xs" style="color:#582C83;"></i> Merit Settings for this Event
          </h4>
          <div class="grid grid-cols-1 gap-4 items-end sm:grid-cols-3">
            <div>
              <label class="form-label">Points per Student <span class="text-red-500">*</span></label>
              <div class="relative">
                <i class="fas fa-star absolute left-3 top-1/2 -translate-y-1/2 text-amber-400 text-xs"></i>
                <input type="number" id="pts-<?= $ev['id'] ?>" class="form-input pl-8"
                       value="<?= $ev['merit_points'] ?? 2 ?>" min="1" max="100" placeholder="2">
              </div>
            </div>
            <div class="sm:col-span-2">
              <label class="form-label">Description (optional)</label>
              <input type="text" id="desc-<?= $ev['id'] ?>" class="form-input"
                     value="<?= htmlspecialchars($ev['merit_desc'] ?? '') ?>"
                     placeholder="e.g. Co-curriculum participation merit">
            </div>
          </div>

          <!-- Points preview -->
          <div class="mt-4 p-4 rounded-xl flex items-center gap-4 flex-wrap" style="background:#fef3c7;border:1px solid #fde68a;">
            <div class="flex items-center gap-2">
              <i class="fas fa-calculator text-amber-600"></i>
              <span class="text-sm font-semibold text-amber-800">Preview:</span>
            </div>
            <span class="text-sm text-amber-700">
              <?= $ev['attended'] ?> students ×
              <span class="font-bold" id="preview-pts-<?= $ev['id'] ?>"><?= $ev['merit_points'] ?? 2 ?></span>
              points =
              <span class="font-extrabold text-amber-900" id="preview-total-<?= $ev['id'] ?>"><?= $ev['attended'] * ($ev['merit_points'] ?? 2) ?></span>
              total points
            </span>
          </div>

          <div class="flex flex-wrap gap-2 mt-4">
            <button onclick='saveSettings(<?= $ev["id"] ?>, <?= $ev["attended"] ?>)' class="btn-primary">
              <i class="fas fa-floppy-disk"></i> Save Settings
            </button>
            <?php if ($ev['merit_points'] && $remaining > 0): ?>
            <button onclick='awardAll(<?= $ev["id"] ?>, <?= $ev["merit_points"] ?>, "<?= addslashes($ev["title"]) ?>", <?= $remaining ?>)'
                    class="btn-primary" style="background:#d97706;">
              <i class="fas fa-star"></i> Award Now (<?= $remaining ?> students)
            </button>
            <?php endif; ?>
            <button onclick="toggleSettings(<?= $ev['id'] ?>)" class="btn-secondary">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- ═══ TAB: LEADERBOARD ══════════════════════════════════ -->
  <div id="tab-leaderboard" class="hidden space-y-4">

    <!-- Top 3 podium -->
    <?php if (count($leaderboard) >= 1): ?>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <?php
      $podium = [
        1 => ['icon'=>'🥇','label'=>'1st Place','color'=>'#f59e0b'],
        2 => ['icon'=>'🥈','label'=>'2nd Place','color'=>'#9ca3af'],
        3 => ['icon'=>'🥉','label'=>'3rd Place','color'=>'#f97316'],
      ];
      foreach ([1,2,3] as $rank):
        $s = $leaderboard[$rank-1] ?? null;
        $p = $podium[$rank];
        if (!$s) continue;
        $initials = strtoupper(substr($s['name'],0,1));
      ?>
      <div class="bg-white rounded-2xl border-2 p-5 text-center <?= "rank-$rank" ?>">
        <div class="text-3xl mb-2"><?= $p['icon'] ?></div>
        <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-lg mx-auto mb-2"
             style="background:#582C83;color:#F9A51B;"><?= $initials ?></div>
        <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($s['name']) ?></p>
        <p class="text-xs text-gray-400 mb-2"><?= htmlspecialchars($s['matric_no']) ?></p>
        <div class="points-badge mx-auto">
          <i class="fas fa-star text-xs"></i>
          <?= $s['total_points'] ?> points
        </div>
        <p class="text-xs text-gray-400 mt-1"><?= $s['events_count'] ?> event<?= $s['events_count']!==1?'s':'' ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Full leaderboard table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 border-b border-gray-100 gap-1">
        <h2 class="font-bold text-gray-800 text-sm flex items-center gap-2">
          <i class="fas fa-trophy text-amber-400"></i> Full Leaderboard
        </h2>
        <span class="text-xs text-gray-400"><?= count($leaderboard) ?> students</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">#</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Student</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Events</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Points</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-40">Progress</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php if (empty($leaderboard)): ?>
            <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">
              <i class="fas fa-star text-3xl mb-3 block" style="color:#fde68a;"></i>
              No merit points awarded yet.
            </td></tr>
            <?php else:
            $maxPts = max(array_column($leaderboard, 'total_points') ?: [1]);
            foreach ($leaderboard as $i => $s):
              $rank = $i + 1;
              $barW = round($s['total_points']/$maxPts*100);
            ?>
            <tr class="hover-row">
              <td class="px-6 py-4">
                <span class="font-bold text-gray-<?= $rank<=3?'900':'400' ?> text-sm">
                  <?= $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : "#$rank")) ?>
                </span>
              </td>
              <td class="px-4 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                       style="background:#f0ebfa;color:#582C83;">
                    <?= strtoupper(substr($s['name'],0,1)) ?>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($s['name']) ?></p>
                    <p class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($s['matric_no']) ?></p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4 text-center text-gray-700 font-semibold"><?= $s['events_count'] ?></td>
              <td class="px-4 py-4 text-center">
                <span class="points-badge">
                  <i class="fas fa-star text-xs"></i><?= $s['total_points'] ?>
                </span>
              </td>
              <td class="px-4 py-4">
                <div class="merit-bar-bg w-full">
                  <div class="merit-bar" style="width:<?= $barW ?>%;"></div>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ═══ TAB: EVENT DETAIL ═════════════════════════════════ -->
  <?php if ($viewEvent): ?>
  <div id="tab-detail" class="hidden">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 border-b border-gray-100 gap-3">
        <div>
          <h2 class="font-bold text-gray-800"><?= htmlspecialchars($viewEvent['title']) ?></h2>
          <p class="text-xs text-gray-400 mt-0.5"><?= count($eventAwards) ?> award<?= count($eventAwards)!==1?'s':'' ?> given</p>
        </div>
        <a href="merits.php" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Student</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Points</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reason</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Awarded At</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php if (empty($eventAwards)): ?>
            <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">No awards yet for this event.</td></tr>
            <?php else: foreach ($eventAwards as $a): ?>
            <tr class="hover-row" id="award-row-<?= $a['id'] ?>">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                       style="background:#f0ebfa;color:#582C83;"><?= strtoupper(substr($a['student_name'],0,1)) ?></div>
                  <div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($a['student_name']) ?></p>
                    <p class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($a['matric_no']) ?></p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4 text-center">
                <span class="points-badge"><i class="fas fa-star text-xs"></i><?= $a['points'] ?></span>
              </td>
              <td class="px-4 py-4 text-xs text-gray-500"><?= htmlspecialchars($a['reason'] ?? '—') ?></td>
              <td class="px-4 py-4 text-xs text-gray-500"><?= date('d M Y, H:i', strtotime($a['awarded_at'])) ?></td>
              <td class="px-4 py-4 text-center">
                <button onclick='revokeAward(<?= $a["id"] ?>, "<?= addslashes($a["student_name"]) ?>")'
                        class="w-8 h-8 rounded-lg flex items-center justify-center mx-auto"
                        style="background:rgba(239,68,68,0.1);color:#991B1B;" title="Revoke merit">
                  <i class="fas fa-trash-can text-xs"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

</main>
</div>

<?php include 'partials/modals_js.php'; ?>
<script>
// ── Tabs ──────────────────────────────────────────────────────
function switchTab(name, btn) {
  document.querySelectorAll('[id^="tab-"]').forEach(t => t.classList.add('hidden'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name)?.classList.remove('hidden');
  if (btn) btn.classList.add('active');
}
<?php if ($viewEvent): ?>
// Auto-open detail tab if event_id in URL
window.addEventListener('DOMContentLoaded', () => {
  switchTab('detail', document.getElementById('detailTabBtn'));
});
<?php endif; ?>

// ── Toggle settings panel ─────────────────────────────────────
function toggleSettings(eventId) {
  document.getElementById('settings-' + eventId).classList.toggle('hidden');
}

// ── Live preview: points × attended ──────────────────────────
document.querySelectorAll('[id^="pts-"]').forEach(input => {
  const eventId = input.id.replace('pts-', '');
  const attended = parseInt(input.closest('.event-card')?.querySelector('[data-attended]')?.dataset.attended || '0');
  input.addEventListener('input', () => {
    const pts = parseInt(input.value) || 0;
    const prev = document.getElementById('preview-pts-' + eventId);
    const tot  = document.getElementById('preview-total-' + eventId);
    if (prev) prev.textContent = pts;
    // We don't have attended easily here — just show pts
  });
});

// ── Save settings ─────────────────────────────────────────────
function saveSettings(eventId, attended) {
  const points = parseInt(document.getElementById('pts-' + eventId)?.value) || 2;
  const desc   = document.getElementById('desc-' + eventId)?.value || '';

  // Update preview
  const prevPts = document.getElementById('preview-pts-' + eventId);
  const prevTot = document.getElementById('preview-total-' + eventId);
  if (prevPts) prevPts.textContent = points;
  if (prevTot) prevTot.textContent = attended * points;

  const fd = new FormData();
  fd.append('action',      'save_settings');
  fd.append('event_id',    eventId);
  fd.append('points',      points);
  fd.append('description', desc);

  fetch('merits.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => { showToast(d.message, !d.success); if (d.success) setTimeout(() => location.reload(), 900); })
    .catch(() => showToast('Network error.', true));
}

// ── Award all ─────────────────────────────────────────────────
function awardAll(eventId, points, title, remaining) {
  openConfirm('award', 'Award Merit Points',
    `Award ${points} merit point(s) to ${remaining} student(s) who attended "${title}"?`,
    `Award ${remaining} Students`, 'amber', () => {
      const fd = new FormData();
      fd.append('action',   'award_all');
      fd.append('event_id', eventId);
      fd.append('points',   points);
      fetch('merits.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => { showToast(d.message, !d.success); if (d.success) setTimeout(() => location.reload(), 900); })
        .catch(() => showToast('Network error.', true));
    });
}

// ── Revoke merit ──────────────────────────────────────────────
function revokeAward(awardId, studentName) {
  openConfirm('revoke', 'Revoke Merit',
    `Remove merit points from ${studentName}? This cannot be undone.`,
    'Revoke', 'red', () => {
      const fd = new FormData();
      fd.append('action',   'revoke');
      fd.append('award_id', awardId);
      fetch('merits.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
          showToast(d.message, !d.success);
          if (d.success) {
            const row = document.getElementById('award-row-' + awardId);
            if (row) { row.style.opacity='0'; row.style.transition='opacity .3s'; setTimeout(()=>row.remove(),300); }
          }
        })
        .catch(() => showToast('Network error.', true));
    });
}
</script>
</body>
</html>