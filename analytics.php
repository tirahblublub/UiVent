<?php
require_once '../config.php';
requireSuperAdmin();

$activePage = 'analytics';
$pageTitle  = 'Analytics Dashboard';

$pendingClubs     = (int) db()->query("SELECT COUNT(*) FROM admins WHERE status='pending'")->fetchColumn();
$pendingApprovals = (int) db()->query("SELECT COUNT(*) FROM escalated_approvals WHERE status='pending'")->fetchColumn();

// ── Events by month (last 6 months) ─────────────────────────
$eventsByMonth = db()->query("
    SELECT DATE_FORMAT(start_date,'%b %Y') AS month_label,
           DATE_FORMAT(start_date,'%Y-%m')  AS month_key,
           COUNT(*) AS total
    FROM events
    WHERE start_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
")->fetchAll();

// ── Registrations trend (last 8 weeks) ──────────────────────
$regTrend = db()->query("
    SELECT DATE_FORMAT(registered_at, '%d %b') AS week_label,
           DATE_FORMAT(registered_at, '%Y-%u')  AS week_key,
           COUNT(*) AS total
    FROM registrations
    WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
    GROUP BY week_key, week_label
    ORDER BY week_key ASC
")->fetchAll();

// ── Registrations by category ─────────────────────────────
$byCategory = db()->query("
    SELECT e.category, COUNT(r.id) AS total
    FROM registrations r
    JOIN events e ON e.id = r.event_id
    GROUP BY e.category
    ORDER BY total DESC
")->fetchAll();

// ── Summary KPIs ──────────────────────────────────────────
$kpi = db()->query("
    SELECT
        (SELECT COUNT(*) FROM events)                       AS total_events,
        (SELECT COUNT(*) FROM registrations)                AS total_regs,
        (SELECT COUNT(*) FROM registrations WHERE attended_at IS NOT NULL) AS total_attended,
        (SELECT COUNT(*) FROM students WHERE is_active=1)  AS total_students,
        (SELECT COUNT(*) FROM admins WHERE status='active') AS active_clubs
")->fetch();

$attendRate = $kpi['total_regs'] > 0
    ? round($kpi['total_attended'] / $kpi['total_regs'] * 100, 1)
    : 0;

// JSON encode for Chart.js
$eventMonths   = json_encode(array_column($eventsByMonth, 'month_label'));
$eventCounts   = json_encode(array_column($eventsByMonth, 'total'));
$regWeeks      = json_encode(array_column($regTrend, 'week_label'));
$regCounts     = json_encode(array_column($regTrend, 'total'));
$catLabels     = json_encode(array_column($byCategory, 'category'));
$catCounts     = json_encode(array_column($byCategory, 'total'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>UiVent | Analytics</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<?php include 'partials/head_styles.php'; ?>
<style>
  body { font-family:'Inter',ui-sans-serif,system-ui,sans-serif; }
  .font-display { font-family:'Plus Jakarta Sans',ui-sans-serif,system-ui,sans-serif; }

  .analytics-bg {
    background:
      radial-gradient(circle at 100% 0%, rgba(88,44,131,0.06) 0%, transparent 45%),
      radial-gradient(circle at 0% 20%, rgba(249,165,27,0.05) 0%, transparent 40%),
      #f6f5f9;
  }

  @keyframes riseIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
  .rise-in { animation: riseIn .5s cubic-bezier(.16,1,.3,1) both; }
  @media (prefers-reduced-motion: reduce) { .rise-in { animation:none; } }

  .kpi-card { position:relative; overflow:hidden; transition:transform .25s ease, box-shadow .25s ease; }
  .kpi-card:hover { transform:translateY(-3px); box-shadow:0 12px 28px -12px rgba(39,19,74,0.18); }
  .kpi-card::before {
    content:''; position:absolute; inset:0 0 auto 0; height:3px;
    background:var(--accent,#582C83);
  }

  .chart-card { transition:box-shadow .25s ease, border-color .25s ease; }
  .chart-card:hover { box-shadow:0 14px 32px -16px rgba(39,19,74,0.16); border-color:rgba(88,44,131,0.15); }

  .eyebrow {
    font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; font-size:10.5px;
    letter-spacing:.12em; text-transform:uppercase;
  }

  .export-btn { transition:transform .18s ease, box-shadow .18s ease, opacity .18s ease; }
  .export-btn:hover { transform:translateY(-2px); }
  .export-btn:active { transform:translateY(0); }
</style>
</head>
<body class="analytics-bg font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<div class="flex-1">
<main class="p-6 md:p-8 space-y-7 max-w-7xl w-full mx-auto">

  <!-- Header -->
  <div class="relative overflow-hidden flex flex-col sm:flex-row justify-between items-start sm:items-center p-7 rounded-2xl text-white gap-4 rise-in shadow-lg"
       style="background:linear-gradient(135deg,#27134A 0%,#582C83 100%);">
    <div class="pointer-events-none absolute -top-16 -right-10 w-56 h-56 rounded-full" style="background:radial-gradient(circle,rgba(249,165,27,0.22) 0%,transparent 70%);"></div>
    <div class="pointer-events-none absolute -bottom-20 -left-10 w-64 h-64 rounded-full" style="background:radial-gradient(circle,rgba(255,255,255,0.08) 0%,transparent 70%);"></div>

    <div class="relative">
      <p class="eyebrow mb-1.5" style="color:#F9A51B;">Superadmin</p>
      <h3 class="font-display text-[26px] font-bold tracking-tight">Analytics Dashboard</h3>
      <p class="text-sm mt-1.5 text-purple-200">Overall statistics for the UiVent system</p>
    </div>
    <div class="relative flex items-center gap-2 px-4 py-2.5 rounded-xl"
         style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.14);">
      <i class="far fa-clock text-xs text-purple-300"></i>
      <div class="text-right">
        <p class="text-[10px] uppercase tracking-wider text-purple-300 leading-none mb-1">Last updated</p>
        <p class="font-semibold text-white text-sm leading-none"><?= date('d M Y, H:i') ?></p>
      </div>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
    <?php $kpis = [
      ['label'=>'Total Events',      'val'=>$kpi['total_events'],    'suffix'=>'',  'icon'=>'fa-calendar-alt',   'color'=>'#582C83'],
      ['label'=>'Registrations',     'val'=>$kpi['total_regs'],      'suffix'=>'',  'icon'=>'fa-user-check',     'color'=>'#1d4ed8'],
      ['label'=>'Attended',          'val'=>$kpi['total_attended'],  'suffix'=>'',  'icon'=>'fa-circle-check',   'color'=>'#059669'],
      ['label'=>'Attendance Rate',   'val'=>$attendRate,             'suffix'=>'%', 'icon'=>'fa-chart-pie',      'color'=>'#d97706'],
      ['label'=>'Active Clubs',      'val'=>$kpi['active_clubs'],    'suffix'=>'',  'icon'=>'fa-shield-halved',  'color'=>'#7c3aed'],
    ]; ?>
    <?php foreach ($kpis as $i => $k): ?>
    <div class="kpi-card rise-in bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4"
         style="--accent:<?= $k['color'] ?>; animation-delay:<?= $i * 60 ?>ms;">
      <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0" style="background:<?= $k['color'] ?>15;">
        <i class="fas <?= $k['icon'] ?> text-lg" style="color:<?= $k['color'] ?>;"></i>
      </div>
      <div class="min-w-0">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 truncate"><?= $k['label'] ?></p>
        <p class="font-display text-2xl font-bold text-gray-900 mt-0.5 kpi-number" data-target="<?= $k['val'] ?>" data-suffix="<?= $k['suffix'] ?>">0<?= $k['suffix'] ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts Row 1 -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Bar: Events by Month -->
    <div class="chart-card rise-in bg-white rounded-2xl shadow-sm border border-gray-100 p-6" style="animation-delay:120ms;">
      <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" style="background:#f0ebfa;color:#582C83;">
            <i class="fas fa-chart-column text-sm"></i>
          </div>
          <div>
            <p class="eyebrow text-gray-400">Bar Chart</p>
            <h4 class="font-display font-bold text-gray-800 text-base mt-0.5">Events by Month</h4>
          </div>
        </div>
        <span class="badge" style="background:#f0ebfa;color:#582C83;">Last 6 months</span>
      </div>
      <div class="relative" style="height:240px;">
        <canvas id="chartEventsByMonth"></canvas>
      </div>
    </div>

    <!-- Pie: Registrations by Category -->
    <div class="chart-card rise-in bg-white rounded-2xl shadow-sm border border-gray-100 p-6" style="animation-delay:170ms;">
      <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" style="background:#f0ebfa;color:#582C83;">
            <i class="fas fa-chart-pie text-sm"></i>
          </div>
          <div>
            <p class="eyebrow text-gray-400">Pie Chart</p>
            <h4 class="font-display font-bold text-gray-800 text-base mt-0.5">Registrations by Category</h4>
          </div>
        </div>
        <span class="badge" style="background:#f0ebfa;color:#582C83;">Overall</span>
      </div>
      <div class="flex items-center gap-6">
        <div class="relative shrink-0" style="height:200px;width:200px;">
          <canvas id="chartByCategory"></canvas>
        </div>
        <div class="space-y-2.5 text-sm flex-1 min-w-0" id="catLegend"></div>
      </div>
    </div>
  </div>

  <!-- Line: Registration Trend -->
  <div class="chart-card rise-in bg-white rounded-2xl shadow-sm border border-gray-100 p-6" style="animation-delay:220ms;">
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" style="background:#e0f2fe;color:#0369a1;">
          <i class="fas fa-chart-line text-sm"></i>
        </div>
        <div>
          <p class="eyebrow text-gray-400">Line Chart</p>
          <h4 class="font-display font-bold text-gray-800 text-base mt-0.5">Event Registration Trend (8 weeks)</h4>
        </div>
      </div>
      <span class="badge" style="background:#e0f2fe;color:#0369a1;">Weekly</span>
    </div>
    <div class="relative" style="height:220px;">
      <canvas id="chartRegTrend"></canvas>
    </div>
    <?php if (empty($regTrend)): ?>
    <p class="text-center text-sm text-gray-400 mt-4">No registration data yet.</p>
    <?php endif; ?>
  </div>

  <!-- ── Generate Report ──────────────────────────────────────── -->
  <div class="chart-card rise-in bg-white rounded-2xl shadow-sm border border-gray-100 p-6" style="animation-delay:270ms;">
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" style="background:#f0ebfa;color:#582C83;">
          <i class="fas fa-file-export text-sm"></i>
        </div>
        <div>
          <p class="eyebrow text-gray-400">Export</p>
          <h4 class="font-display font-bold text-gray-800 text-base mt-0.5">Generate Report</h4>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1.5">Report Type</label>
        <select id="rpt_type" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition-colors">
          <option value="overview">Overview (All)</option>
          <option value="events">Events Detail</option>
          <option value="clubs">Club Activity</option>
          <option value="students">Student Participation</option>
          <option value="attendance">Attendance Analysis</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1.5">Date From</label>
        <input type="date" id="rpt_from" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition-colors">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1.5">Date To</label>
        <input type="date" id="rpt_to" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition-colors">
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <button onclick="exportReport('pdf')"
              class="export-btn flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-white shadow-sm"
              style="background:#DC2626;">
        <i class="fas fa-file-pdf"></i> Export PDF
      </button>
      <button onclick="exportReport('excel')"
              class="export-btn flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-white shadow-sm"
              style="background:#16A34A;">
        <i class="fas fa-file-excel"></i> Export CSV
      </button>
      <button onclick="window.print()"
              class="export-btn flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-gray-700 border border-gray-200 hover:bg-gray-50">
        <i class="fas fa-print"></i> Print
      </button>
    </div>
  </div>

</main>
</div>
</div>

<script>
// Set default dates on load
window.addEventListener('DOMContentLoaded', function() {
  const today = new Date().toISOString().split('T')[0];
  const jan1  = new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0];
  document.getElementById('rpt_from').value = jan1;
  document.getElementById('rpt_to').value   = today;

  // Animated KPI count-up
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('.kpi-number').forEach(function(el, idx) {
    const target = parseFloat(el.dataset.target) || 0;
    const suffix = el.dataset.suffix || '';
    if (reduceMotion) { el.textContent = (Number.isInteger(target) ? target.toLocaleString() : target) + suffix; return; }
    const duration = 900;
    const start = performance.now() + idx * 60;
    function tick(now) {
      const elapsed = now - start;
      if (elapsed < 0) { requestAnimationFrame(tick); return; }
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = target * eased;
      el.textContent = (Number.isInteger(target) ? Math.round(current).toLocaleString() : current.toFixed(1)) + suffix;
      if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  });
});

function exportReport(format) {
  const type = document.getElementById('rpt_type').value;
  const from = document.getElementById('rpt_from').value;
  const to   = document.getElementById('rpt_to').value;
  if (!from || !to) { alert('Sila pilih tarikh.'); return; }
  const url = '/UiVent/superadmin/reports/export.php?type=' + format + '&report_type=' + type + '&date_from=' + from + '&date_to=' + to;
  if (format === 'pdf') window.open(url, '_blank');
  else window.location.href = url;
}
</script>

<script>
const PURPLE = '#582C83', GOLD = '#F9A51B', DARK = '#27134A';
const PALETTE = ['#582C83','#F9A51B','#0ea5e9','#10b981','#f97316','#e11d48'];

Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";

// Bar — Events by Month
(function(){
  const labels = <?= $eventMonths ?: '["Jan","Feb","Mar","Apr","May","Jun"]' ?>;
  const data   = <?= $eventCounts ?: '[0,0,0,0,0,0]' ?>;
  const ctx = document.getElementById('chartEventsByMonth');
  const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
  gradient.addColorStop(0, PURPLE);
  gradient.addColorStop(1, '#8a5cb8');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Total Events',
        data,
        backgroundColor: gradient,
        borderRadius: 8,
        borderSkipped: false,
        maxBarThickness: 42,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 800, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: DARK, padding: 10, cornerRadius: 8, titleFont: { weight: '600' } }
      },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1, color: '#9ca3af' }, grid: { color: '#f3f4f6' } },
        x: { ticks: { color: '#9ca3af' }, grid: { display: false } }
      }
    }
  });
})();

// Pie — by Category
(function(){
  const labels = <?= $catLabels ?: '["Academic","Cultural","Sports","Other"]' ?>;
  const data   = <?= $catCounts ?: '[0,0,0,0]' ?>;
  new Chart(document.getElementById('chartByCategory'), {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{ data, backgroundColor: PALETTE.slice(0, labels.length), borderWidth: 3, borderColor: '#fff', hoverOffset: 6 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '62%',
      animation: { duration: 800, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: DARK, padding: 10, cornerRadius: 8 }
      }
    }
  });
  // Custom legend
  const leg = document.getElementById('catLegend');
  const total = data.reduce((a,b) => a+b, 0);
  labels.forEach((l,i) => {
    const pct = total > 0 ? Math.round((data[i]/total)*100) : 0;
    leg.innerHTML += `<div class="flex items-center gap-2.5">
      <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:${PALETTE[i]};"></span>
      <span class="text-gray-600 font-medium truncate">${l}</span>
      <span class="ml-auto font-bold text-gray-800 tabular-nums">${data[i]}</span>
      <span class="text-gray-400 text-xs w-9 text-right tabular-nums">${pct}%</span>
    </div>`;
  });
  if (!labels.length) leg.innerHTML = '<p class="text-gray-400 text-xs">No data.</p>';
})();

// Line — Registration Trend
(function(){
  const labels = <?= $regWeeks ?: '[]' ?>;
  const data   = <?= $regCounts ?: '[]' ?>;
  if (!labels.length) return;
  const ctx = document.getElementById('chartRegTrend');
  const areaGradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 220);
  areaGradient.addColorStop(0, PURPLE + '33');
  areaGradient.addColorStop(1, PURPLE + '00');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Registrations',
        data,
        borderColor: PURPLE,
        borderWidth: 2.5,
        backgroundColor: areaGradient,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: GOLD,
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 800, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: DARK, padding: 10, cornerRadius: 8 }
      },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1, color: '#9ca3af' }, grid: { color: '#f3f4f6' } },
        x: { ticks: { color: '#9ca3af' }, grid: { display: false } }
      }
    }
  });
})();
</script>
</body>
</html>