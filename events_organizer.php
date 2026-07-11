<?php
require_once '../config.php';
requireUser();

$events  = [];
$dbError = false;

try {
    // Use the db() singleton from config.php — no raw credentials needed
    $stmt = db()->query("
        SELECT
            e.id,
            e.title,
            e.category,
            e.venue,
            DATE_FORMAT(e.start_date, '%d %b %Y')                               AS event_date,
            DATE_FORMAT(e.start_date, '%h:%i %p')                               AS event_time,
            e.capacity,
            e.registered_count                                                   AS registered,
            COALESCE(ROUND(e.registered_count / NULLIF(e.capacity,0) * 100), 0) AS pct,
            e.description,
            e.status,
            e.registration_fee,
            e.image_url,
            COALESCE(a.club_name, a.name, 'Unknown Organizer')  AS organizer,
            COALESCE(a.avatar, '')                               AS organizer_avatar,
            COALESCE(a.email,  '')                               AS organizer_email,
            COALESCE(a.phone,  '')                               AS organizer_phone,
            COALESCE(a.office_location, '')                      AS organizer_office
        FROM events e
        LEFT JOIN admins a ON a.id = e.created_by
        WHERE e.start_date >= NOW()
          AND e.status NOT IN ('cancelled','archived')
        ORDER BY e.start_date ASC
    ");
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbError = true;
    // Fall back to demo data so the page never shows blank
}

// ─────────────────────────────────────────────
//  DEMO / FALLBACK DATA (used when DB is unavailable)
//  Replace image_url with paths to your own uploads or keep Unsplash
// ─────────────────────────────────────────────
if ($dbError || empty($events)) {
    $events = [
        [
            'id'          => 1,
            'title'       => 'UiTM Career Fair 2026',
            'category'    => 'Academic',
            'venue'       => 'DATC Hall',
            'event_date'  => '14 Jun 2026',
            'event_time'  => '9:00 AM',
            'capacity'    => 500,
            'registered'  => 380,
            'pct'         => 76,
            'description' => 'Connect with top employers, explore career opportunities, and attend industry talks. Dress code: Smart casual.',
            'image_url'   => 'https://images.unsplash.com/photo-1560523159-4a9692d222ef?w=600&q=80',
            'status'           => 'Open',
            'organizer'        => 'MPP UiTM Machang',
            'organizer_avatar' => '',
            'organizer_email'  => 'mpp@uitm.edu.my',
            'organizer_phone'  => '',
            'organizer_office' => '',
            'registration_fee' => 0,
        ],
        [
            'id'          => 2,
            'title'       => 'Fatihah Night Arts Festival',
            'category'    => 'Cultural',
            'venue'       => 'Laman Kreatif',
            'event_date'  => '20 Jun 2026',
            'event_time'  => '7:00 PM',
            'capacity'    => 200,
            'registered'  => 0,
            'pct'         => 0,
            'description' => 'An open-air arts and performance night featuring student theatre groups, live music, and visual art installations.',
            'image_url'   => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=600&q=80',
            'status'           => 'upcoming',
            'organizer'        => 'JPK Kolej Tun Hussein Onn',
            'organizer_avatar' => '',
            'organizer_email'  => 'jpk.tho@uitm.edu.my',
            'organizer_phone'  => '',
            'organizer_office' => '',
            'registration_fee' => 0,
        ],
        [
            'id'          => 3,
            'title'       => 'Hari Sukan UiTM 2026',
            'category'    => 'Sports',
            'venue'       => 'Stadium UiTM',
            'event_date'  => '28 Jun 2026',
            'event_time'  => '8:00 AM',
            'capacity'    => 800,
            'registered'  => 540,
            'pct'         => 67,
            'description' => 'Annual sports day with inter-faculty competitions in football, badminton, athletics, and more. Participation earns co-curricular points.',
            'image_url'   => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=600&q=80',
            'status'           => 'open',
            'organizer'        => 'MPP UiTM Machang',
            'organizer_avatar' => '',
            'organizer_email'  => 'mpp@uitm.edu.my',
            'organizer_phone'  => '',
            'organizer_office' => '',
            'registration_fee' => 0,
        ],
        [
            'id'          => 4,
            'title'       => 'Minggu Keusahawanan UiTM',
            'category'    => 'Other',
            'venue'       => 'Dataran Ilmu',
            'event_date'  => '5 Jul 2026',
            'event_time'  => '10:00 AM',
            'capacity'    => 300,
            'registered'  => 112,
            'pct'         => 37,
            'description' => 'Explore entrepreneurship opportunities with local SME mentors and pitch competitions.',
            'image_url'   => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=600&q=80',
            'status'           => 'open',
            'organizer'        => 'JPK Kolej Dato Onn',
            'organizer_avatar' => '',
            'organizer_email'  => 'jpk.dato@uitm.edu.my',
            'organizer_phone'  => '',
            'organizer_office' => '',
            'registration_fee' => 5,
        ],
        [
            'id'          => 5,
            'title'       => 'Tech Innovation Summit',
            'category'    => 'Academic',
            'venue'       => 'Auditorium FSG',
            'event_date'  => '12 Jul 2026',
            'event_time'  => '9:00 AM',
            'capacity'    => 250,
            'registered'  => 198,
            'pct'         => 79,
            'description' => 'A full-day summit featuring AI, cybersecurity, and data science talks from industry leaders.',
            'image_url'   => 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?w=600&q=80',
            'status'           => 'open',
            'organizer'        => 'MPP UiTM Machang',
            'organizer_avatar' => '',
            'organizer_email'  => 'mpp@uitm.edu.my',
            'organizer_phone'  => '',
            'organizer_office' => '',
            'registration_fee' => 10,
        ],
        [
            'id'          => 6,
            'title'       => 'Malam Kebudayaan Nusantara',
            'category'    => 'Cultural',
            'venue'       => 'Dewan Budaya',
            'event_date'  => '18 Jul 2026',
            'event_time'  => '7:30 PM',
            'capacity'    => 400,
            'registered'  => 44,
            'pct'         => 11,
            'description' => 'A celebration of Malay and Nusantara cultural performances, food, and crafts.',
            'image_url'   => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=600&q=80',
            'status'           => 'open',
            'organizer'        => 'JPK Kolej Tun Abdul Razak',
            'organizer_avatar' => '',
            'organizer_email'  => 'jpk.tar@uitm.edu.my',
            'organizer_phone'  => '',
            'organizer_office' => '',
            'registration_fee' => 0,
        ],
    ];
}

// Pass data to JS
$eventsJson = json_encode($events);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Browse Events</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>

/* ────────────────  GLOBAL  ──────────────── */
*, *::before, *::after { box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; }

.view-section { display: none; }
.view-section.active { display: block; animation: fadeIn 0.28s ease-out; }
@keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

/* ────────────────  SIDEBAR  ──────────────── */
.sidebar-nav-btn { transition: all 0.18s; }

/* ────────────────  TAB BUTTONS  ──────────────── */
.tab-btn.active { background:#581c87; color:#fff; }
.tab-btn { transition: all .18s; }

/* ────────────────  EVENT CARD  ──────────────── */
.event-card {
  transition: transform .25s cubic-bezier(.4,0,.2,1), box-shadow .25s cubic-bezier(.4,0,.2,1);
  border-radius: 16px;
  overflow: hidden;
}
.event-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 40px rgba(80,0,120,0.15);
}
.event-card .card-img {
  position: relative;
  height: 160px;
  overflow: hidden;
}
.event-card .card-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform .45s ease;
}
.event-card:hover .card-img img { transform: scale(1.06); }

/* gradient overlay on card image */
.card-img-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, rgba(0,0,0,0) 40%, rgba(15,5,30,0.72) 100%);
}

/* category pill on image */
.card-cat-pill {
  position: absolute;
  top: 10px;
  left: 10px;
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

/* status badge on image */
.card-status-pill {
  position: absolute;
  top: 10px;
  right: 10px;
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

/* date label at bottom of image */
.card-date-label {
  position: absolute;
  bottom: 10px;
  left: 12px;
  color: #fff;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: .03em;
  text-shadow: 0 1px 4px rgba(0,0,0,.6);
}

/* ────────────────  PROGRESS BAR  ──────────────── */
.progress-bar-fill { transition: width 1.1s cubic-bezier(.4,0,.2,1); }

/* ────────────────  EVENT DRAWER  ──────────────── */
#eventDrawer > div {
  transform: translateX(100%);
  transition: transform .32s cubic-bezier(.4,0,.2,1);
}
#eventDrawer:not(.hidden) > div { transform: translateX(0); }

.drawer-hero {
  position: relative;
  height: 200px;
  overflow: hidden;
}
.drawer-hero img {
  width: 100%; height: 100%; object-fit: cover;
}
.drawer-hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(10,2,30,.85) 0%, rgba(10,2,30,.25) 100%);
}
.drawer-hero-content {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 16px 20px;
}

/* ────────────────  SEARCH BAR  ──────────────── */
.search-bar:focus { box-shadow: 0 0 0 3px rgba(124,58,237,0.18); }

/* ────────────────  HERO BANNER  ──────────────── */
.events-hero {
  background: linear-gradient(135deg, #3b0764 0%, #581c87 45%, #7c3aed 100%);
  border-radius: 20px;
  position: relative;
  overflow: hidden;
}
.events-hero::before {
  content:'';
  position:absolute;
  inset:0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

/* ────────────────  CATEGORY COLOURS  ──────────────── */
.cat-academic   { background: rgba(59,130,246,.18); color:#1e40af; border:1px solid rgba(59,130,246,.25); }
.cat-cultural   { background: rgba(245,158,11,.18); color:#92400e; border:1px solid rgba(245,158,11,.25); }
.cat-sports     { background: rgba(239,68,68,.18);  color:#991b1b; border:1px solid rgba(239,68,68,.25); }
.cat-business   { background: rgba(16,185,129,.18); color:#065f46; border:1px solid rgba(16,185,129,.25); }
.cat-religious  { background: rgba(99,102,241,.18); color:#3730a3; border:1px solid rgba(99,102,241,.25); }
.cat-default    { background: rgba(107,114,128,.18);color:#374151; border:1px solid rgba(107,114,128,.25); }

/* status colours */
.status-open    { background:rgba(16,185,129,.12); color:#065f46; border:1px solid rgba(16,185,129,.25); }
.status-upcoming{ background:rgba(59,130,246,.12); color:#1e40af; border:1px solid rgba(59,130,246,.25); }
.status-filling { background:rgba(245,158,11,.15); color:#92400e; border:1px solid rgba(245,158,11,.25); }
.status-closed  { background:rgba(239,68,68,.12);  color:#991b1b; border:1px solid rgba(239,68,68,.25); }

/* ────────────────  TOAST  ──────────────── */
#toast { transition: opacity .3s; }

/* ────────────────  REGISTER BUTTON  ──────────────── */
.btn-register {
  background: linear-gradient(135deg, #581c87, #7c3aed);
  color: #fff;
  font-weight: 600;
  font-size: 0.75rem;
  padding: 9px 0;
  border-radius: 10px;
  width: 100%;
  transition: opacity .2s, transform .15s;
  border: none;
  cursor: pointer;
  letter-spacing: .02em;
}
.btn-register:hover { opacity:.9; transform:translateY(-1px); }
.btn-register.registered {
  background: linear-gradient(135deg, #059669, #10b981);
  cursor: default;
  transform: none;
}

/* ────────────────  RESPONSIVE GRID  ──────────────── */
#eventsGrid { transition: all .2s; }

/* ────────────────  DB BANNER  ──────────────── */
.db-banner {
  background: linear-gradient(90deg, #fef3c7, #fde68a);
  border: 1px solid #f59e0b;
  border-radius: 10px;
  padding: 10px 16px;
  font-size: 0.75rem;
  color: #92400e;
  display: flex;
  align-items: center;
  gap: 8px;
}
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">

<!-- ═══════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════ -->
<aside class="w-64 bg-purple-950 text-white flex flex-col hidden md:flex shrink-0 shadow-xl">
  <div class="overflow-y-auto flex-1 min-h-0">
    <!-- Logo -->
    <div class="h-16 flex items-center px-6 border-b border-purple-900 bg-purple-900/40 sticky top-0">
      <div class="bg-amber-500 text-purple-950 px-2.5 py-1 rounded-md font-extrabold text-lg tracking-wider mr-2">Ui</div>
      <span class="font-bold text-xl tracking-wide">Vent</span>
      <span class="text-xs bg-purple-800 text-purple-200 ml-2 px-1.5 py-0.5 rounded uppercase">Student</span>
    </div>

    <!-- Nav -->
    <nav class="mt-6 px-4 space-y-1 pb-4">
      <a href="home.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-home text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Home</span>
      </a>
      <a href="events.php" class="sidebar-nav-btn w-full flex items-center space-x-3 bg-amber-500 text-purple-950 font-semibold px-4 py-3 rounded-lg shadow-sm text-left">
        <i class="fas fa-calendar-alt text-lg w-5"></i><span>Browse Events</span>
      </a>
      <a href="mybookings.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-ticket-alt text-lg w-5 text-purple-400 group-hover:text-white"></i><span>My Registrations</span><span class="ml-auto bg-amber-500 text-purple-950 text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center">3</span>
      </a>
      <a href="attendance.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-chart-bar text-lg w-5 text-purple-400 group-hover:text-white"></i><span>My Attendance</span>
      </a>
      <a href="announcements.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-bullhorn text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Announcements</span><span class="ml-auto bg-red-500 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center">2</span>
      </a>
      <a href="profile.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-user text-lg w-5 text-purple-400 group-hover:text-white"></i><span>My Profile</span>
      </a>

      <div class="mt-4 mb-1 px-1">
        <p class="text-xs font-bold uppercase tracking-widest text-purple-600">More</p>
      </div>

      <a href="merchandise.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-tshirt text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Merchandise</span>
      </a>
      <a href="payments.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-credit-card text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Payments</span>
      </a>
      <a href="feedback.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-comment-dots text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Feedback</span>
      </a>

      <div class="mt-2 pt-2 border-t border-purple-900">
        <a href="logout.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-300 hover:bg-red-900/40 hover:text-red-300 px-4 py-3 rounded-lg text-left group">
          <i class="fas fa-sign-out-alt text-lg w-5 text-purple-500 group-hover:text-red-300"></i><span>Logout</span>
        </a>
      </div>
    </nav>
  </div>
</aside>

<!-- ═══════════════════════════════════════════
     MAIN AREA
═══════════════════════════════════════════ -->
<div class="flex-1 flex flex-col h-full overflow-y-auto">

  <!-- TOPBAR -->
  <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 md:px-8 shrink-0 sticky top-0 z-10 shadow-sm">
    <div class="flex items-center space-x-4">
      <button class="text-gray-500 hover:text-gray-700 md:hidden block"><i class="fas fa-bars text-xl"></i></button>
      <h2 class="text-xl font-bold text-gray-800">Browse Events</h2>
    </div>
    <div class="flex items-center space-x-4">
      <a href="announcements.php" class="p-2 text-gray-400 hover:text-purple-900 transition-colors relative inline-block">
        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
        <i class="far fa-bell text-lg"></i>
      </a>
      <div class="h-6 w-px bg-gray-200"></div>
      <a href="profile.php" class="flex items-center space-x-3 cursor-pointer">
        <div class="text-right hidden md:block">
          <p class="text-sm font-semibold text-gray-800">Nur Fatirah</p>
          <p class="text-xs text-gray-500">Information Science Faculty &middot; Year 3</p>
        </div>
        <img class="w-9 h-9 rounded-full ring-2 ring-purple-100 object-cover" src="images/passport.jpg" alt="User">
      </a>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <div class="flex-1">
    <main class="view-section active p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">

      <?php if ($dbError): ?>
      <!-- DB warning banner -->
      <div class="db-banner">
        <i class="fas fa-database"></i>
        <span>Could not connect to the database — showing demo events. Check your DB credentials in <code>events.php</code>.</span>
      </div>
      <?php endif; ?>

      <!-- ── Hero Banner ── -->
      <div class="events-hero px-8 py-7 flex items-center justify-between">
        <div class="relative z-10">
          <p class="text-purple-300 text-xs font-semibold uppercase tracking-widest mb-1">UiVent Platform</p>
          <h3 class="text-white font-extrabold text-2xl md:text-3xl leading-tight" style="font-family:'Poppins',sans-serif;">
            Discover Campus Events
          </h3>
          <p class="text-purple-200 text-sm mt-2 max-w-xs">
            Find, register, and attend events that shape your student journey.
          </p>
        </div>
        <div class="hidden md:flex items-center gap-6 relative z-10">
          <div class="text-center">
            <p class="text-3xl font-extrabold text-white" id="totalEventsCount"><?= count($events) ?></p>
            <p class="text-purple-300 text-xs mt-0.5">Events Live</p>
          </div>
          <div class="h-10 w-px bg-purple-700"></div>
          <div class="text-center">
            <p class="text-3xl font-extrabold text-amber-400" id="openEventsCount">
              <?= count(array_filter($events, fn($e) => strtolower($e['status']) === 'open')) ?>
            </p>
            <p class="text-purple-300 text-xs mt-0.5">Open Now</p>
          </div>
        </div>
      </div>

      <!-- ── Filter Bar ── -->
      <div class="bg-white rounded-2xl border border-gray-200 p-4 flex flex-wrap gap-3 items-center shadow-sm">
        <div class="relative flex-1 min-w-48">
          <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
          <input id="searchInput" type="text" placeholder="Search events or venues…"
            class="search-bar w-full bg-gray-50 border border-gray-200 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 transition-colors">
        </div>
        <select id="filterCategory" class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-purple-400 transition-colors">
          <option value="">All Categories</option>
          <option>Academic</option><option>Cultural</option><option>Sports</option>
          <option>Business</option><option>Religious</option>
        </select>
        <select id="filterStatus" class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-purple-400 transition-colors">
          <option value="">All Statuses</option>
          <option>Open</option><option>Upcoming</option><option>Filling Fast</option>
        </select>
        <div class="flex gap-1 border border-gray-200 rounded-xl overflow-hidden bg-gray-50">
          <button class="tab-btn active px-3 py-2.5 text-xs font-semibold rounded-l-xl" id="tab-grid" onclick="setLayoutTab('grid')">
            <i class="fas fa-th-large"></i>
          </button>
          <button class="tab-btn px-3 py-2.5 text-xs font-semibold bg-white text-gray-600 hover:bg-gray-50 rounded-r-xl" id="tab-list" onclick="setLayoutTab('list')">
            <i class="fas fa-list"></i>
          </button>
        </div>
      </div>

      <!-- ── Events Grid (rendered by JS from PHP JSON) ── -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5" id="eventsGrid"></div>

      <!-- Empty state -->
      <div id="emptyState" class="hidden text-center py-20">
        <div class="w-16 h-16 bg-purple-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-calendar-times text-purple-300 text-2xl"></i>
        </div>
        <p class="font-semibold text-gray-700">No events match your filters</p>
        <p class="text-sm text-gray-400 mt-1">Try clearing the search or selecting a different category.</p>
        <button onclick="clearFilters()" class="mt-4 text-xs text-purple-700 font-semibold underline">Clear filters</button>
      </div>

    </main>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     EVENT DETAILS DRAWER
═══════════════════════════════════════════ -->
<div id="eventDrawer" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex justify-end">
  <div class="w-full max-w-lg bg-white h-full shadow-2xl overflow-y-auto flex flex-col">

    <!-- Drawer hero image -->
    <div class="drawer-hero shrink-0">
      <img id="dImage" src="" alt="Event image" onerror="this.style.display='none'">
      <div class="drawer-hero-overlay"></div>
      <div class="drawer-hero-content">
        <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/20 text-white border border-white/30 backdrop-blur-sm" id="dCategory">Category</span>
        <h2 class="text-xl font-extrabold text-white mt-2 leading-tight" id="dTitle" style="font-family:'Poppins',sans-serif;">Event Title</h2>
      </div>
      <button onclick="closeEventDrawer()" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-black/30 text-white flex items-center justify-center hover:bg-black/50 transition-colors backdrop-blur-sm">
        <i class="fas fa-times text-sm"></i>
      </button>
    </div>

    <!-- Drawer body -->
    <div class="flex-1 p-6 space-y-5">
      <!-- Meta -->
      <div class="flex flex-wrap gap-3">
        <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 rounded-xl px-3 py-2">
          <i class="fas fa-map-marker-alt text-amber-500 text-xs"></i>
          <span id="dVenue">Venue</span>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 rounded-xl px-3 py-2">
          <i class="far fa-calendar text-purple-400 text-xs"></i>
          <span id="dDate">Date</span>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 rounded-xl px-3 py-2">
          <i class="far fa-clock text-purple-400 text-xs"></i>
          <span id="dTime">Time</span>
        </div>
      </div>

      <!-- Capacity -->
      <div class="bg-purple-50 p-4 rounded-2xl border border-purple-100 space-y-3">
        <h4 class="font-bold text-xs uppercase tracking-wider text-purple-400">Registration Capacity</h4>
        <div>
          <div class="flex justify-between text-xs font-semibold text-gray-700 mb-2">
            <span>Spots Filled</span><span id="dCapText">0 / 0</span>
          </div>
          <div class="w-full bg-purple-100 rounded-full h-2.5">
            <div id="dCapBar" class="bg-purple-700 h-2.5 rounded-full progress-bar-fill" style="width:0%"></div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3 text-center">
          <div class="bg-white p-3 rounded-xl border border-purple-100 shadow-sm">
            <p class="text-xl font-extrabold text-purple-900" id="dReg">—</p>
            <p class="text-xs text-gray-400 mt-0.5">Registered</p>
          </div>
          <div class="bg-white p-3 rounded-xl border border-purple-100 shadow-sm">
            <p class="text-xl font-extrabold text-gray-600" id="dCap">—</p>
            <p class="text-xs text-gray-400 mt-0.5">Total Spots</p>
          </div>
        </div>
      </div>

      <!-- Organizer -->
      <div class="flex items-center gap-3 bg-purple-50 border border-purple-100 rounded-2xl p-4">
        <div id="dOrgAvatar" class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white text-sm shrink-0" style="background:#7c3aed">?</div>
        <div class="min-w-0">
          <p class="text-xs font-bold uppercase tracking-wider text-purple-400 mb-0.5">Organised By</p>
          <p class="text-sm font-semibold text-gray-800 truncate" id="dOrganizer">—</p>
        </div>
      </div>

      <!-- Description -->
      <div>
        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">About This Event</h4>
        <p class="text-sm text-gray-600 leading-relaxed" id="dDesc">Event description here.</p>
      </div>

      <!-- Organizer Information Card -->
      <div class="bg-purple-50 border border-purple-100 rounded-2xl p-4 space-y-3">
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" style="background:#582C83;">
            <i class="fas fa-building text-white text-xs"></i>
          </div>
          <p class="text-xs font-bold uppercase tracking-widest text-purple-700">Organizer Information</p>
        </div>
        <div class="space-y-2">
          <div class="flex items-start gap-3">
            <i class="fas fa-users text-purple-400 text-xs mt-0.5 w-3 shrink-0"></i>
            <div>
              <p class="text-xs text-purple-500 font-semibold uppercase tracking-wide">Club Name</p>
              <p class="text-sm font-semibold text-gray-800" id="dOrgClub">—</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <i class="fas fa-envelope text-purple-400 text-xs mt-0.5 w-3 shrink-0"></i>
            <div>
              <p class="text-xs text-purple-500 font-semibold uppercase tracking-wide">Email Address</p>
              <a id="dOrgEmail" href="#" class="text-sm font-semibold text-purple-700 hover:underline">—</a>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <i class="fas fa-phone text-purple-400 text-xs mt-0.5 w-3 shrink-0"></i>
            <div>
              <p class="text-xs text-purple-500 font-semibold uppercase tracking-wide">Phone Number</p>
              <a id="dOrgPhone" href="#" class="text-sm font-semibold text-gray-800 hover:underline">—</a>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <i class="fas fa-location-dot text-purple-400 text-xs mt-0.5 w-3 shrink-0"></i>
            <div>
              <p class="text-xs text-purple-500 font-semibold uppercase tracking-wide">Office Location</p>
              <p class="text-sm font-semibold text-gray-800" id="dOrgOffice">—</p>
            </div>
          </div>
        </div>
      </div>

      <!-- What to bring -->
      <div class="bg-amber-50 border border-amber-100 rounded-2xl p-4">
        <p class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-2">
          <i class="fas fa-info-circle mr-1"></i>What to Bring
        </p>
        <ul class="text-xs text-amber-800 space-y-1.5">
          <li class="flex items-center gap-2"><i class="fas fa-check-circle text-amber-400"></i>Valid UiTM student ID card</li>
          <li class="flex items-center gap-2"><i class="fas fa-check-circle text-amber-400"></i>QR code from My Registrations</li>
          <li class="flex items-center gap-2"><i class="fas fa-check-circle text-amber-400"></i>Smart casual or faculty-appropriate attire</li>
        </ul>
      </div>
    </div>

    <!-- Drawer footer -->
    <div class="p-6 border-t border-gray-100 space-y-2.5 sticky bottom-0 bg-white">
      <button id="dRegisterBtn" onclick="drawerRegister()"
        class="btn-register flex items-center justify-center gap-2">
        <i class="fas fa-ticket-alt text-xs"></i><span>Register for This Event</span>
      </button>
      <button onclick="showToast('Event saved to your favourites.')"
        class="w-full bg-gray-50 hover:bg-gray-100 text-gray-600 font-medium py-2.5 rounded-xl text-xs transition-colors flex items-center justify-center gap-2 border border-gray-200">
        <i class="far fa-bookmark"></i><span>Save to Favourites</span>
      </button>
      <button onclick="closeEventDrawer()"
        class="w-full bg-white border border-gray-200 hover:bg-gray-50 text-gray-500 font-medium py-2 rounded-xl text-xs transition-colors">
        Close
      </button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast" class="fixed bottom-6 right-6 z-[100] hidden pointer-events-none">
  <div class="bg-gray-900 text-white text-sm font-medium px-5 py-3 rounded-2xl shadow-2xl flex items-center space-x-3 max-w-sm">
    <i id="toastIcon" class="fas fa-check-circle text-emerald-400"></i>
    <span id="toastMsg">Done.</span>
  </div>
</div>

<!-- QR MODAL -->
<div id="qrModal" class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm z-[200] hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden">
    <!-- Header -->
    <div class="bg-gradient-to-br from-purple-900 to-purple-700 px-6 py-5 text-white text-center relative">
      <button onclick="closeQrModal()" class="absolute top-4 right-4 w-7 h-7 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors">
        <i class="fas fa-times text-xs"></i>
      </button>
      <div class="w-12 h-12 bg-amber-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
        <i class="fas fa-ticket-alt text-purple-900 text-xl"></i>
      </div>
      <h3 class="font-extrabold text-lg leading-tight" style="font-family:'Poppins',sans-serif;">You're Registered!</h3>
      <p class="text-purple-200 text-xs mt-1" id="qrEventTitle">Event Title</p>
    </div>

    <!-- QR Code body -->
    <div class="px-6 py-6 text-center space-y-4">
      <p class="text-xs text-gray-500 font-medium">Show this QR code at the event entrance for attendance check-in</p>

      <!-- QR render target -->
      <div class="flex justify-center">
        <div id="qrCanvas" class="p-3 border-2 border-purple-100 rounded-2xl bg-white inline-block shadow-inner"></div>
      </div>

      <div class="bg-purple-50 rounded-xl px-4 py-2 border border-purple-100">
        <p class="text-xs text-purple-400 font-semibold uppercase tracking-wider">Registration ID</p>
        <p class="text-sm font-bold text-purple-900 mt-0.5" id="qrRegId">#REG-00000</p>
      </div>

      <div class="space-y-2 pt-1">
        <a id="qrDirectLink" href="#" target="_blank"
           class="block w-full bg-purple-50 hover:bg-purple-100 text-purple-700 font-semibold py-2.5 rounded-xl text-xs transition-colors border border-purple-200">
          <i class="fas fa-external-link-alt mr-1.5"></i>Open Check-In Link
        </a>
        <button onclick="closeQrModal()"
          class="block w-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-medium py-2.5 rounded-xl text-xs transition-colors">
          Close
        </button>
      </div>

      <p class="text-xs text-gray-400">
        <i class="fas fa-info-circle mr-1"></i>
        Your QR code is also saved in <a href="mybookings.php" class="text-purple-600 underline font-medium">My Registrations</a>
      </p>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════ -->
<script>
// ── Data from PHP ──
const ALL_EVENTS = <?= $eventsJson ?>;

// ── Category helpers ──
const CAT_CLASS = {
  academic : 'cat-academic',
  cultural : 'cat-cultural',
  sports   : 'cat-sports',
  business : 'cat-business',
  religious: 'cat-religious',
};
const STATUS_CLASS = {
  'open'        : 'status-open',
  'upcoming'    : 'status-upcoming',
  'filling fast': 'status-filling',
  'closed'      : 'status-closed',
};
const BAR_COLOR = {
  academic : '#3b82f6',
  cultural : '#f59e0b',
  sports   : '#ef4444',
  business : '#10b981',
  religious: '#6366f1',
};

function catClass(c)    { return CAT_CLASS[c.toLowerCase()]    || 'cat-default'; }
function statusClass(s) { return STATUS_CLASS[s.toLowerCase()] || 'cat-default'; }
function barColor(c)    { return BAR_COLOR[c.toLowerCase()]    || '#7c3aed'; }

// Build initials from a name string (up to 2 chars)
function initials(name) {
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0,2) || '?';
}
// Render a small organizer avatar (photo or coloured initials circle)
function organizerAvatar(ev, size='w-6 h-6 text-[10px]') {
  if (ev.organizer_avatar) {
    return `<img src="${ev.organizer_avatar}" class="${size} rounded-full object-cover ring-1 ring-white" alt="${safe(ev.organizer)}">`;
  }
  // Deterministic pastel based on name
  const colors = ['#7c3aed','#059669','#d97706','#dc2626','#2563eb','#db2777'];
  const idx    = (ev.organizer || '').split('').reduce((a,c) => a + c.charCodeAt(0), 0) % colors.length;
  return `<span class="${size} rounded-full flex items-center justify-center font-bold text-white ring-1 ring-white shrink-0"
               style="background:${colors[idx]}">${initials(ev.organizer || '?')}</span>`;
}

// ── Render cards ──
let currentLayout = 'grid';

function buildCard(ev) {
  // Real DB rows have no image_url — use a category-matched placeholder
  if (!ev.image_url) {
    const placeholders = {
      'Academic' : 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?w=600&q=60',
      'Cultural' : 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=600&q=60',
      'Sports'   : 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=600&q=60',
    };
    ev.image_url = placeholders[ev.category] || 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=600&q=60';
  }
  const pct    = Math.min(parseInt(ev.pct) || 0, 100);
  const color  = barColor(ev.category);
  const isFull = pct >= 100;
  const alreadyRegistered = !!ev._registered;

  const btnLabel = alreadyRegistered ? '<i class="fas fa-check text-xs"></i> Registered' : 'Register Now';
  const btnClass = alreadyRegistered ? 'btn-register registered' : 'btn-register';

  // Drawer args — escape carefully
  const safe = (s) => String(s).replace(/'/g, "\\'");

  if (currentLayout === 'list') {
    return `
    <div class="event-card bg-white border border-gray-200 shadow-sm flex overflow-hidden cursor-pointer"
         onclick="openEventDrawer(${ev.id})">
      <div class="w-36 shrink-0 relative">
        <img src="${ev.image_url}" alt="${safe(ev.title)}" class="w-full h-full object-cover"
             onerror="this.src='https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=300&q=60'">
        <div class="card-img-overlay"></div>
      </div>
      <div class="flex-1 p-4 flex flex-col gap-2">
        <div class="flex items-start justify-between gap-2">
          <div>
            <span class="text-xs font-bold px-2 py-0.5 rounded-full ${catClass(ev.category)}">${ev.category}</span>
          </div>
          <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full shrink-0 ${statusClass(ev.status)}">${ev.status}</span>
        </div>
        <div>
          <h4 class="font-bold text-gray-900 text-sm leading-snug">${ev.title}</h4>
          <p class="text-xs text-gray-400 mt-0.5">
            <i class="fas fa-map-marker-alt mr-1 text-amber-400"></i>${ev.venue}
            &nbsp;·&nbsp;
            <i class="far fa-calendar mr-1"></i>${ev.event_date}
          </p>
          <div class="flex items-center gap-1.5 mt-1.5">
            ${organizerAvatar(ev)}
            <span class="text-xs text-gray-500 font-medium">${safe(ev.organizer)}</span>
          </div>
        </div>
        <div class="mt-auto">
          <div class="flex justify-between text-xs text-gray-400 mb-1">
            <span>Registrations</span>
            <span class="font-semibold text-gray-600">${ev.registered} / ${ev.capacity}</span>
          </div>
          <div class="w-full bg-gray-100 rounded-full h-1.5">
            <div class="h-1.5 rounded-full transition-all" style="width:${pct}%;background:${color}"></div>
          </div>
        </div>
      </div>
      <div class="flex items-center pr-4">
        <button onclick="event.stopPropagation(); registerEvent(this, ${ev.id})"
          class="${btnClass} !w-auto px-4 text-xs" ${alreadyRegistered ? 'disabled' : ''}>
          ${btnLabel}
        </button>
      </div>
    </div>`;
  }

  // Grid card
  return `
  <div class="event-card bg-white border border-gray-200 shadow-sm flex flex-col cursor-pointer"
       onclick="openEventDrawer(${ev.id})">
    <!-- Image banner -->
    <div class="card-img">
      <img src="${ev.image_url}" alt="${safe(ev.title)}"
           onerror="this.src='https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=600&q=60'">
      <div class="card-img-overlay"></div>
      <span class="card-cat-pill text-xs font-bold px-2.5 py-1 rounded-full ${catClass(ev.category)}">${ev.category}</span>
      <span class="card-status-pill text-xs font-semibold px-2.5 py-1 rounded-full ${statusClass(ev.status)}">${ev.status}</span>
      <p class="card-date-label"><i class="far fa-calendar mr-1"></i>${ev.event_date} &nbsp;·&nbsp; ${ev.event_time}</p>
    </div>
    <!-- Card body -->
    <div class="p-5 flex flex-col gap-3 flex-1">
      <div>
        <h4 class="font-bold text-gray-900 text-sm leading-snug">${ev.title}</h4>
        <p class="text-xs text-gray-400 mt-1">
          <i class="fas fa-map-marker-alt mr-1 text-amber-400"></i>${ev.venue}
        </p>
      </div>
      <!-- Organizer chip -->
      <div class="flex items-center gap-2 bg-gray-50 rounded-xl px-2.5 py-1.5 w-fit max-w-full border border-gray-100">
        ${organizerAvatar(ev)}
        <span class="text-xs text-gray-600 font-medium truncate">${safe(ev.organizer)}</span>
      </div>
      <!-- Progress -->
      <div>
        <div class="flex justify-between text-xs text-gray-400 mb-1.5">
          <span>Registrations</span>
          <span class="font-semibold text-gray-600">${ev.registered} / ${ev.capacity}</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-1.5">
          <div class="h-1.5 rounded-full" style="width:${pct}%;background:${color}"></div>
        </div>
      </div>
      <!-- Register button -->
      <button onclick="event.stopPropagation(); registerEvent(this, ${ev.id})"
        class="${btnClass} mt-auto" ${alreadyRegistered ? 'disabled' : ''}>
        ${btnLabel}
      </button>
    </div>
  </div>`;
}

function renderEvents(list) {
  const grid  = document.getElementById('eventsGrid');
  const empty = document.getElementById('emptyState');
  if (!list.length) {
    grid.innerHTML  = '';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');
  grid.innerHTML = list.map(buildCard).join('');
}

// ── Filters ──
function getFiltered() {
  const q   = document.getElementById('searchInput').value.toLowerCase();
  const cat = document.getElementById('filterCategory').value.toLowerCase();
  const st  = document.getElementById('filterStatus').value.toLowerCase();
  return ALL_EVENTS.filter(ev => {
    const matchQ   = !q   || ev.title.toLowerCase().includes(q) || ev.venue.toLowerCase().includes(q);
    const matchCat = !cat || ev.category.toLowerCase() === cat;
    const matchSt  = !st  || ev.status.toLowerCase() === st;
    return matchQ && matchCat && matchSt;
  });
}

function applyFilters() { renderEvents(getFiltered()); }
function clearFilters() {
  document.getElementById('searchInput').value = '';
  document.getElementById('filterCategory').value = '';
  document.getElementById('filterStatus').value = '';
  applyFilters();
}

document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('filterCategory').addEventListener('change', applyFilters);
document.getElementById('filterStatus').addEventListener('change', applyFilters);

// ── Layout toggle ──
function setLayoutTab(mode) {
  currentLayout = mode;
  const grid = document.getElementById('eventsGrid');
  const btnG = document.getElementById('tab-grid');
  const btnL = document.getElementById('tab-list');
  const activeBase = 'tab-btn active px-3 py-2.5 text-xs font-semibold';
  const inactiveBase = 'tab-btn px-3 py-2.5 text-xs font-semibold bg-white text-gray-600 hover:bg-gray-50';

  if (mode === 'grid') {
    btnG.className = activeBase + ' rounded-l-xl';
    btnL.className = inactiveBase + ' rounded-r-xl';
    grid.className = 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5';
  } else {
    btnG.className = inactiveBase + ' rounded-l-xl';
    btnL.className = activeBase + ' rounded-r-xl';
    grid.className = 'flex flex-col gap-4';
  }
  renderEvents(getFiltered());
}

// ── Drawer ──
let currentDrawerTitle = '';

function openEventDrawer(id) {
  const ev = ALL_EVENTS.find(e => String(e.id) === String(id));
  if (!ev) return;
  currentDrawerTitle   = ev.title;
  currentDrawerEventId = ev.id;

  // Reset / set drawer register button state
  const dBtn = document.getElementById('dRegisterBtn');
  if (ev._registered) {
    dBtn.className = 'btn-register registered flex items-center justify-center gap-2';
    dBtn.innerHTML = '<i class="fas fa-check text-xs"></i> Already Registered';
    dBtn.disabled  = true;
    dBtn.onclick   = null;
  } else {
    dBtn.className = 'btn-register flex items-center justify-center gap-2';
    dBtn.innerHTML = '<i class="fas fa-ticket-alt text-xs"></i><span>Register for This Event</span>';
    dBtn.disabled  = false;
    dBtn.onclick   = drawerRegister;
  }

  document.getElementById('dImage').src    = ev.image_url || 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=600&q=60';
  document.getElementById('dTitle').innerText    = ev.title;
  document.getElementById('dCategory').innerText = ev.category + ' Track';
  document.getElementById('dVenue').innerText    = ev.venue;
  document.getElementById('dDate').innerText     = ev.event_date;
  document.getElementById('dTime').innerText     = ev.event_time;
  document.getElementById('dCapText').innerText  = ev.registered + ' / ' + ev.capacity + ' registered';
  document.getElementById('dReg').innerText      = ev.registered;
  document.getElementById('dCap').innerText      = ev.capacity;
  document.getElementById('dDesc').innerText     = ev.description;
  document.getElementById('dOrganizer').innerText = ev.organizer || 'Unknown Organizer';

  // ── Organizer Information card ──────────────────────────────
  const NA = '<span style="color:#9ca3af;font-weight:400;">Not Available</span>';

  // Club name
  const dOrgClub = document.getElementById('dOrgClub');
  dOrgClub.innerHTML = ev.organizer ? ev.organizer : NA;

  // Email — make it a mailto link if present
  const dOrgEmail = document.getElementById('dOrgEmail');
  if (ev.organizer_email) {
    dOrgEmail.href      = 'mailto:' + ev.organizer_email;
    dOrgEmail.innerText = ev.organizer_email;
    dOrgEmail.style.pointerEvents = '';
  } else {
    dOrgEmail.innerHTML        = NA;
    dOrgEmail.removeAttribute('href');
    dOrgEmail.style.pointerEvents = 'none';
  }

  // Phone — make it a tel link if present
  const dOrgPhone = document.getElementById('dOrgPhone');
  if (ev.organizer_phone) {
    dOrgPhone.href      = 'tel:' + ev.organizer_phone.replace(/\s/g, '');
    dOrgPhone.innerText = ev.organizer_phone;
    dOrgPhone.style.pointerEvents = '';
  } else {
    dOrgPhone.innerHTML        = NA;
    dOrgPhone.removeAttribute('href');
    dOrgPhone.style.pointerEvents = 'none';
  }

  // Office location
  const dOrgOffice = document.getElementById('dOrgOffice');
  dOrgOffice.innerHTML = ev.organizer_office ? ev.organizer_office : NA;

  // Organizer avatar in drawer
  const dOrgAvatar = document.getElementById('dOrgAvatar');
  const colors = ['#7c3aed','#059669','#d97706','#dc2626','#2563eb','#db2777'];
  const idx    = (ev.organizer || '').split('').reduce((a,c) => a + c.charCodeAt(0), 0) % colors.length;
  if (ev.organizer_avatar) {
    dOrgAvatar.innerHTML = '';
    dOrgAvatar.style.background = 'transparent';
    const img = document.createElement('img');
    img.src = ev.organizer_avatar;
    img.className = 'w-10 h-10 rounded-full object-cover';
    dOrgAvatar.appendChild(img);
  } else {
    dOrgAvatar.innerHTML = initials(ev.organizer || '?');
    dOrgAvatar.style.background = colors[idx];
  }

  // Animate bar after paint
  const bar = document.getElementById('dCapBar');
  bar.style.width = '0%';
  setTimeout(() => { bar.style.width = ev.pct + '%'; }, 50);

  document.getElementById('eventDrawer').classList.remove('hidden');
}

function closeEventDrawer() {
  document.getElementById('eventDrawer').classList.add('hidden');
}

// ── Current drawer event id ──
let currentDrawerEventId = null;

function drawerRegister() {
  if (!currentDrawerEventId) return;
  doRegister(currentDrawerEventId, null);
}

// Close on backdrop click
document.getElementById('eventDrawer').addEventListener('click', function(e) {
  if (e.target === this) closeEventDrawer();
});

// ── Real registration via AJAX ──
function doRegister(eventId, cardBtn) {
  // Disable whichever button triggered this
  const drawerBtn = document.getElementById('dRegisterBtn');
  if (cardBtn)  { cardBtn.disabled  = true; cardBtn.innerHTML  = '<i class="fas fa-spinner fa-spin text-xs"></i> Registering…'; }
  if (drawerBtn){ drawerBtn.disabled = true; drawerBtn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Registering…'; }

  const fd = new FormData();
  fd.append('event_id', eventId);

  fetch('register_event.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        // Mark card button as registered
        if (cardBtn) {
          cardBtn.className = 'btn-register registered flex items-center justify-center gap-2';
          cardBtn.innerHTML = '<i class="fas fa-check text-xs"></i> Registered';
          cardBtn.onclick   = null;
          cardBtn.disabled  = false;
        }
        // Mark drawer button
        if (drawerBtn) {
          drawerBtn.className = 'btn-register registered flex items-center justify-center gap-2';
          drawerBtn.innerHTML = '<i class="fas fa-check text-xs"></i> Registered';
          drawerBtn.disabled  = false;
          drawerBtn.onclick   = null;
        }
        // Mark in ALL_EVENTS so re-renders keep state
        const ev = ALL_EVENTS.find(e => String(e.id) === String(eventId));
        if (ev) { ev._registered = true; ev._regId = data.registration_id; }

        if (!data.already && data.qr_url) {
          closeEventDrawer();
          openQrModal(data.event_title || currentDrawerTitle, data.qr_url, data.registration_id);
        } else {
          showToast(data.message);
        }
      } else {
        showToast('⚠ ' + data.message, true);
        // Restore buttons
        if (cardBtn)  { cardBtn.disabled  = false; cardBtn.innerHTML  = 'Register Now'; }
        if (drawerBtn){ drawerBtn.disabled = false; drawerBtn.innerHTML = '<i class="fas fa-ticket-alt text-xs"></i><span>Register for This Event</span>'; }
      }
    })
    .catch(() => {
      showToast('⚠ Network error. Please try again.', true);
      if (cardBtn)  { cardBtn.disabled  = false; cardBtn.innerHTML  = 'Register Now'; }
      if (drawerBtn){ drawerBtn.disabled = false; drawerBtn.innerHTML = '<i class="fas fa-ticket-alt text-xs"></i><span>Register for This Event</span>'; }
    });
}

// ── Register button on card ──
function registerEvent(btn, eventId) {
  doRegister(eventId, btn);
}

// ── QR Modal ──
function openQrModal(title, qrUrl, regId) {
  document.getElementById('qrEventTitle').innerText = title;
  document.getElementById('qrRegId').innerText      = '#REG-' + String(regId).padStart(5, '0');
  document.getElementById('qrDirectLink').href      = qrUrl;

  // Render QR using QRCode.js into the container
  const container = document.getElementById('qrCanvas');
  container.innerHTML = '';
  new QRCode(container, {
    text      : qrUrl,
    width     : 200,
    height    : 200,
    colorDark : '#3b0764',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H,
  });

  document.getElementById('qrModal').classList.remove('hidden');
}

function closeQrModal() {
  document.getElementById('qrModal').classList.add('hidden');
}

document.getElementById('qrModal').addEventListener('click', function(e) {
  if (e.target === this) closeQrModal();
});

// ── Toast ──
let toastTimer;
function showToast(msg, isError = false) {
  const el   = document.getElementById('toast');
  const icon = document.getElementById('toastIcon');
  document.getElementById('toastMsg').innerText = msg;
  icon.className = isError
    ? 'fas fa-exclamation-circle text-red-400'
    : 'fas fa-check-circle text-emerald-400';
  el.classList.remove('hidden');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.add('hidden'), 3800);
}

// ── Init ──
document.addEventListener('DOMContentLoaded', () => {
  renderEvents(ALL_EVENTS);
});
</script>
</body>
</html>