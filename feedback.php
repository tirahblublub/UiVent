<?php
require_once __DIR__ . '/../config.php';
requireStudent();

$sid     = (int) $_SESSION['student_id'];
$student = $_SESSION['student'] ?? [];
$sName   = htmlspecialchars($student['name'] ?? '');

// Load events the student attended for feedback
$attendedEvents = [];
try {
    $s = db()->prepare("
        SELECT e.id, e.title, e.category,
               DATE_FORMAT(e.start_date,'%d %b %Y') AS event_date
        FROM registrations r
        JOIN events e ON e.id = r.event_id
        WHERE r.student_id = ? AND r.attended_at IS NOT NULL
          AND e.end_date < NOW()
        ORDER BY e.start_date DESC
    ");
    $s->execute([$sid]);
    $attendedEvents = $s->fetchAll();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Feedback</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ---- shared.css ---- */

/* ============================================================
   SHARED / GLOBAL STYLES
   Rules used by more than one section — keep these here so every
   section doesn't have to duplicate them. Section-only rules live
   in that section's own css file instead.
   ============================================================ */

/* View switching (used by every section) */
.view-section { display: none; }
.view-section.active { display: block; animation: fadeIn 0.25s ease-out; }
@keyframes fadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:translateY(0); } }

/* Event drawer (opened from Home + Browse Events) */
#eventDrawer > div { transform: translateX(100%); transition: transform .3s cubic-bezier(.4,0,.2,1); }
#eventDrawer:not(.hidden) > div { transform: translateX(0); }
.progress-bar-fill { transition: width 1.1s cubic-bezier(.4,0,.2,1); }

/* Tab buttons (used by Browse Events + My Registrations) */
.tab-btn.active { background:#581c87; color:#fff; }
.tab-btn { transition: all .18s; }

/* Event cards (used by Home + Browse Events) */
.event-card { transition: transform .2s, box-shadow .2s; }
.event-card:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(80,0,120,0.12); }


/* ---- sidebar.css ---- */

/* ============================================================
   SIDEBAR COMPONENT STYLES
   ============================================================ */

.sidebar-nav-btn {
  transition: all 0.18s;
}


/* ---- feedback.css ---- */

/* ============================================================
   FEEDBACK — section-only styles
   No section-specific rules currently; uses Tailwind utilities
   + css/shared.css.
   ============================================================ */

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
      <a href="events.php" class="sidebar-nav-btn w-full flex items-center space-x-3 text-purple-200 hover:bg-purple-900 hover:text-white px-4 py-3 rounded-lg text-left group">
        <i class="fas fa-calendar-alt text-lg w-5 text-purple-400 group-hover:text-white"></i><span>Browse Events</span>
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
      <a href="feedback.php" class="sidebar-nav-btn w-full flex items-center space-x-3 bg-amber-500 text-purple-950 font-semibold px-4 py-3 rounded-lg shadow-sm text-left">
        <i class="fas fa-comment-dots text-lg w-5"></i><span>Feedback</span>
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
      <h2 class="text-xl font-bold text-gray-800" id="headerTitle">Feedback</h2>
    </div>
    <div class="flex items-center space-x-4">
      <!-- Notification Bell -->
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

  <div class="flex-1">

<main id="feedback-view" class="view-section active p-6 md:p-8 max-w-3xl w-full mx-auto space-y-6">
      <div>
        <h3 class="text-2xl font-bold text-gray-900">Feedback</h3>
        <p class="text-sm text-gray-500 mt-1">Share your thoughts on events you attended. Your feedback helps us improve.</p>
      </div>

      <!-- Pending Feedback -->
      <div class="space-y-3" id="pendingFeedbackSection">
        <h4 class="font-bold text-sm text-gray-700 uppercase tracking-wider">Awaiting Your Feedback</h4>

        <div class="space-y-3" id="pendingFeedbackList">
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center justify-between gap-4">
          <div>
            <p class="font-bold text-gray-900 text-sm">Seminar Kepimpinan 2026</p>
            <p class="text-xs text-gray-500 mt-0.5">Attended · 3 Jun 2026</p>
          </div>
          <button onclick="openFeedbackForm('Seminar Kepimpinan 2026', this)" class="bg-amber-500 hover:bg-amber-400 text-white text-xs font-bold px-3 py-2 rounded-lg transition-colors whitespace-nowrap">Give Feedback</button>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center justify-between gap-4">
          <div>
            <p class="font-bold text-gray-900 text-sm">Bengkel Resume &amp; CV</p>
            <p class="text-xs text-gray-500 mt-0.5">Attended · 14 Apr 2026</p>
          </div>
          <button onclick="openFeedbackForm('Bengkel Resume & CV', this)" class="bg-amber-500 hover:bg-amber-400 text-white text-xs font-bold px-3 py-2 rounded-lg transition-colors whitespace-nowrap">Give Feedback</button>
        </div>
        </div>
      </div>

      <!-- Feedback Form (hidden until triggered) -->
      <div id="feedbackFormSection" class="hidden bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
        <div class="flex items-center justify-between">
          <h4 class="font-bold text-gray-900">Feedback for: <span id="feedbackEventName" class="text-purple-900">—</span></h4>
          <button onclick="closeFeedbackForm()" class="text-gray-400 hover:text-gray-600 text-lg"><i class="fas fa-times"></i></button>
        </div>

        <!-- Star Rating -->
        <div class="space-y-2">
          <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider">Overall Experience</label>
          <div class="flex gap-2" id="starRating">
            <button onclick="setRating(1)" class="star-btn text-3xl text-gray-300 hover:text-amber-400 transition-colors" data-val="1">★</button>
            <button onclick="setRating(2)" class="star-btn text-3xl text-gray-300 hover:text-amber-400 transition-colors" data-val="2">★</button>
            <button onclick="setRating(3)" class="star-btn text-3xl text-gray-300 hover:text-amber-400 transition-colors" data-val="3">★</button>
            <button onclick="setRating(4)" class="star-btn text-3xl text-gray-300 hover:text-amber-400 transition-colors" data-val="4">★</button>
            <button onclick="setRating(5)" class="star-btn text-3xl text-gray-300 hover:text-amber-400 transition-colors" data-val="5">★</button>
          </div>
          <p class="text-xs text-gray-400" id="ratingLabel">Tap a star to rate</p>
        </div>

        <!-- Category Ratings -->
        <div class="space-y-3">
          <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider">Rate Specific Aspects</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-1">
              <p class="text-xs text-gray-600 font-medium">Organisation</p>
              <div class="flex gap-1">
                <button onclick="setAspectRating('org',1,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('org',2,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('org',3,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('org',4,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('org',5,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
              </div>
            </div>
            <div class="space-y-1">
              <p class="text-xs text-gray-600 font-medium">Content Quality</p>
              <div class="flex gap-1">
                <button onclick="setAspectRating('content',1,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('content',2,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('content',3,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('content',4,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('content',5,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
              </div>
            </div>
            <div class="space-y-1">
              <p class="text-xs text-gray-600 font-medium">Venue &amp; Facilities</p>
              <div class="flex gap-1">
                <button onclick="setAspectRating('venue',1,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('venue',2,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('venue',3,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('venue',4,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('venue',5,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
              </div>
            </div>
            <div class="space-y-1">
              <p class="text-xs text-gray-600 font-medium">Speaker / Facilitator</p>
              <div class="flex gap-1">
                <button onclick="setAspectRating('speaker',1,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('speaker',2,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('speaker',3,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('speaker',4,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
                <button onclick="setAspectRating('speaker',5,this)" class="aspect-star text-xl text-gray-300 hover:text-amber-400">★</button>
              </div>
            </div>
          </div>
        </div>

        <!-- What did you like -->
        <div class="space-y-2">
          <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider">What did you like most?</label>
          <div class="flex flex-wrap gap-2" id="likeChips">
            <button onclick="toggleChip(this)" class="chip text-xs border border-gray-300 px-3 py-1.5 rounded-full hover:bg-purple-50 hover:border-purple-400 transition-colors">Networking opportunities</button>
            <button onclick="toggleChip(this)" class="chip text-xs border border-gray-300 px-3 py-1.5 rounded-full hover:bg-purple-50 hover:border-purple-400 transition-colors">Speakers</button>
            <button onclick="toggleChip(this)" class="chip text-xs border border-gray-300 px-3 py-1.5 rounded-full hover:bg-purple-50 hover:border-purple-400 transition-colors">Workshop activities</button>
            <button onclick="toggleChip(this)" class="chip text-xs border border-gray-300 px-3 py-1.5 rounded-full hover:bg-purple-50 hover:border-purple-400 transition-colors">Venue</button>
            <button onclick="toggleChip(this)" class="chip text-xs border border-gray-300 px-3 py-1.5 rounded-full hover:bg-purple-50 hover:border-purple-400 transition-colors">Organisation</button>
            <button onclick="toggleChip(this)" class="chip text-xs border border-gray-300 px-3 py-1.5 rounded-full hover:bg-purple-50 hover:border-purple-400 transition-colors">Free merchandise</button>
          </div>
        </div>

        <!-- Written Feedback -->
        <div class="space-y-2">
          <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider">Additional Comments</label>
          <textarea id="feedbackComments" rows="4" placeholder="Share anything else — what worked well, what could be improved, or suggestions for future events." class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-1 focus:ring-purple-500 focus:outline-none resize-none"></textarea>
        </div>

        <!-- Would recommend -->
        <div class="space-y-2">
          <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider">Would you recommend this event?</label>
          <div class="flex gap-3">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="recommend" value="yes" class="accent-purple-900"> <span class="text-sm text-gray-700">Yes, definitely</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="recommend" value="maybe" class="accent-purple-900"> <span class="text-sm text-gray-700">Maybe</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="recommend" value="no" class="accent-purple-900"> <span class="text-sm text-gray-700">No</span>
            </label>
          </div>
        </div>

        <button onclick="submitFeedback()" class="w-full bg-purple-900 hover:bg-purple-800 text-white font-bold py-3 rounded-xl transition-colors text-sm">Submit Feedback</button>
      </div>

      <!-- Submitted Feedback -->
      <div class="space-y-3">
        <h4 class="font-bold text-sm text-gray-700 uppercase tracking-wider">Submitted Feedback</h4>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-2" id="submittedFeedbackList">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="font-bold text-sm text-gray-900">Forum Inovasi Pelajar</p>
              <div class="flex mt-1 text-amber-400 text-sm"><span>★★★★</span><span class="text-gray-200">★</span></div>
              <p class="text-xs text-gray-500 mt-1">Submitted 12 May 2026</p>
            </div>
            <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100 shrink-0">Submitted</span>
          </div>
        </div>
      </div>
    </main>

  </div><!-- /flex-1 inner -->
</div><!-- /main area -->

<!-- EVENT DETAILS DRAWER -->
<div id="eventDrawer" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex justify-end">
  <div class="w-full max-w-lg bg-white h-full shadow-2xl overflow-y-auto flex flex-col">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10">
      <span class="text-xs bg-purple-100 text-purple-900 font-bold px-2 py-0.5 rounded uppercase tracking-wider" id="dCategory">Category</span>
      <button onclick="closeEventDrawer()" class="text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
    </div>
    <div class="flex-1 p-6 space-y-5">
      <div>
        <h2 class="text-2xl font-extrabold text-gray-900" id="dTitle">Event Title</h2>
        <p class="text-sm text-purple-900 font-medium mt-1"><i class="fas fa-map-marker-alt text-amber-500 mr-1.5"></i><span id="dVenue">Venue</span></p>
        <p class="text-sm text-gray-500 mt-1"><i class="far fa-calendar mr-1.5 text-gray-400"></i><span id="dDate">Date</span> &nbsp;&middot;&nbsp; <i class="far fa-clock mr-1 text-gray-400"></i><span id="dTime">Time</span></p>
      </div>
      <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-3">
        <h4 class="font-bold text-xs uppercase tracking-wider text-gray-500">Registration Capacity</h4>
        <div>
          <div class="flex justify-between text-xs font-semibold text-gray-700 mb-1.5">
            <span>Spots Filled</span><span id="dCapText">0 / 0</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2.5">
            <div id="dCapBar" class="bg-purple-700 h-2.5 rounded-full progress-bar-fill" style="width:0%"></div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3 text-center">
          <div class="bg-purple-50 p-2 rounded-lg"><p class="text-base font-bold text-purple-900" id="dReg">&mdash;</p><p class="text-xs text-gray-500">Registered</p></div>
          <div class="bg-gray-100 p-2 rounded-lg"><p class="text-base font-bold text-gray-700" id="dCap">&mdash;</p><p class="text-xs text-gray-500">Total Capacity</p></div>
        </div>
      </div>
      <div class="space-y-1">
        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">About This Event</h4>
        <p class="text-sm text-gray-600 leading-relaxed" id="dDesc">Event description here.</p>
      </div>
      <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 space-y-1">
        <p class="text-xs font-bold text-amber-700 uppercase tracking-wider"><i class="fas fa-info-circle mr-1"></i>What to Bring</p>
        <ul class="text-xs text-amber-800 space-y-1 mt-1">
          <li>&bull; Valid UiTM student ID card</li>
          <li>&bull; QR code from your My Registrations page</li>
          <li>&bull; Smart casual or faculty attire as appropriate</li>
        </ul>
      </div>
    </div>
    <div class="p-6 border-t border-gray-100 space-y-2 sticky bottom-0 bg-white">
      <button id="dRegisterBtn" onclick="drawerRegister()" class="w-full bg-purple-900 hover:bg-purple-800 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors flex items-center justify-center space-x-2">
        <i class="fas fa-ticket-alt"></i><span>Register for This Event</span>
      </button>
      <button onclick="showToast('Event saved to your favourites.')" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 rounded-lg text-xs transition-colors flex items-center justify-center space-x-2">
        <i class="far fa-bookmark"></i><span>Save to Favourites</span>
      </button>
      <button onclick="closeEventDrawer()" class="w-full bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 font-medium py-2 rounded-lg text-xs transition-colors">Close</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast" class="fixed bottom-6 right-6 z-[100] hidden">
  <div class="bg-gray-900 text-white text-sm font-medium px-5 py-3 rounded-xl shadow-2xl flex items-center space-x-3 max-w-sm">
    <i class="fas fa-check-circle text-emerald-400"></i>
    <span id="toastMsg">Done.</span>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════ -->
<script>
/* ---- shared.js ---- */

/* ============================================================
   SHARED / GLOBAL JS
   Functions used by more than one section (toast, event drawer,
   register-from-card). Section-only logic lives in that section's
   own js file instead.
   ============================================================ */

// ---- Toast ----
let toastTimer;
function showToast(msg) {
  const el = document.getElementById('toast');
  document.getElementById('toastMsg').innerText = msg;
  el.classList.remove('hidden');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.add('hidden'), 3200);
}

// ---- Event Drawer (triggered from Home + Browse Events cards) ----
let currentDrawerTitle = '';

function openEventDrawer(title, category, venue, date, time, capacity, registered, pct, desc) {
  currentDrawerTitle = title;
  document.getElementById('dTitle').innerText = title;
  document.getElementById('dCategory').innerText = category + ' Track';
  document.getElementById('dVenue').innerText = venue;
  document.getElementById('dDate').innerText = date;
  document.getElementById('dTime').innerText = time;
  document.getElementById('dCapText').innerText = registered + ' / ' + capacity + ' registered';
  document.getElementById('dReg').innerText = registered;
  document.getElementById('dCap').innerText = capacity;
  document.getElementById('dCapBar').style.width = pct;
  document.getElementById('dDesc').innerText = desc;
  document.getElementById('eventDrawer').classList.remove('hidden');
}

function closeEventDrawer() {
  document.getElementById('eventDrawer').classList.add('hidden');
}

function drawerRegister() {
  closeEventDrawer();
  showToast(`Registered for "${currentDrawerTitle}" successfully!`);
}

// ---- Register button on an event card (Home + Browse Events) ----
function registerEvent(btn, name) {
  btn.className = 'w-full text-xs bg-emerald-600 text-white font-semibold py-2 rounded-md cursor-default';
  btn.innerHTML = '✓ Registered';
  btn.onclick = null;
  showToast(`Registered for "${name}" successfully!`);
}

// Close drawer when clicking the backdrop
document.addEventListener('DOMContentLoaded', function () {
  const drawer = document.getElementById('eventDrawer');
  if (drawer) {
    drawer.addEventListener('click', function (e) {
      if (e.target === this) closeEventDrawer();
    });
  }
});


/* ---- feedback.js ---- */

/* ============================================================
   FEEDBACK — section-only JS
   ============================================================ */

let currentFeedbackEvent = null;
let currentFeedbackCard = null;
let overallRating = 0;
let aspectRatings = { org: 0, content: 0, venue: 0, speaker: 0 };
let selectedChips = [];

function openFeedbackForm(eventName, btn) {
  currentFeedbackEvent = eventName;
  currentFeedbackCard = btn ? btn.closest('.bg-amber-50') : null;
  document.getElementById('feedbackEventName').innerText = eventName;
  resetFeedbackForm();
  const formSection = document.getElementById('feedbackFormSection');
  formSection.classList.remove('hidden');
  formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closeFeedbackForm() {
  document.getElementById('feedbackFormSection').classList.add('hidden');
  resetFeedbackForm();
}

function resetFeedbackForm() {
  overallRating = 0;
  aspectRatings = { org: 0, content: 0, venue: 0, speaker: 0 };
  selectedChips = [];

  document.querySelectorAll('.star-btn').forEach(s => {
    s.classList.remove('text-amber-400');
    s.classList.add('text-gray-300');
  });
  document.getElementById('ratingLabel').innerText = 'Tap a star to rate';

  document.querySelectorAll('.aspect-star').forEach(s => {
    s.classList.remove('text-amber-400');
    s.classList.add('text-gray-300');
  });

  document.querySelectorAll('#likeChips .chip').forEach(c => {
    c.classList.remove('bg-purple-50', 'border-purple-400', 'text-purple-900');
  });

  const comments = document.getElementById('feedbackComments');
  if (comments) comments.value = '';

  document.querySelectorAll('input[name="recommend"]').forEach(r => r.checked = false);
}

function setRating(val) {
  overallRating = val;
  const labels = { 1: 'Poor', 2: 'Fair', 3: 'Good', 4: 'Very Good', 5: 'Excellent' };
  document.querySelectorAll('.star-btn').forEach(s => {
    const v = parseInt(s.dataset.val, 10);
    if (v <= val) {
      s.classList.remove('text-gray-300');
      s.classList.add('text-amber-400');
    } else {
      s.classList.remove('text-amber-400');
      s.classList.add('text-gray-300');
    }
  });
  document.getElementById('ratingLabel').innerText = `${labels[val]} (${val}/5)`;
}

function setAspectRating(aspect, val, btn) {
  aspectRatings[aspect] = val;
  const group = Array.from(btn.parentElement.querySelectorAll('.aspect-star'));
  group.forEach((s, i) => {
    if (i < val) {
      s.classList.remove('text-gray-300');
      s.classList.add('text-amber-400');
    } else {
      s.classList.remove('text-amber-400');
      s.classList.add('text-gray-300');
    }
  });
}

function toggleChip(btn) {
  const label = btn.innerText;
  if (selectedChips.includes(label)) {
    selectedChips = selectedChips.filter(c => c !== label);
    btn.classList.remove('bg-purple-50', 'border-purple-400', 'text-purple-900');
  } else {
    selectedChips.push(label);
    btn.classList.add('bg-purple-50', 'border-purple-400', 'text-purple-900');
  }
}

function submitFeedback() {
  if (overallRating === 0) {
    showToast('Please give an overall star rating before submitting.');
    return;
  }

  const comments = (document.getElementById('feedbackComments') || {}).value || '';
  const recommendEl = document.querySelector('input[name="recommend"]:checked');
  const recommend = recommendEl ? recommendEl.value : null;

  // Remove the event from the "Awaiting Your Feedback" list
  if (currentFeedbackCard) {
    currentFeedbackCard.remove();
  }
  const pendingList = document.getElementById('pendingFeedbackList');
  if (pendingList && pendingList.children.length === 0) {
    pendingList.innerHTML = `
      <div class="text-center text-sm text-gray-400 border border-dashed border-gray-200 rounded-xl py-6">
        <i class="fas fa-check-circle text-emerald-400 mr-1"></i> You're all caught up — no pending feedback.
      </div>`;
  }

  // Add a new entry to the "Submitted Feedback" list
  const list = document.getElementById('submittedFeedbackList');
  const starsFull = '★'.repeat(overallRating);
  const starsEmpty = '★'.repeat(5 - overallRating);
  const today = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

  const entry = document.createElement('div');
  entry.className = 'flex items-start justify-between gap-4';
  entry.innerHTML = `
    <div>
      <p class="font-bold text-sm text-gray-900">${currentFeedbackEvent}</p>
      <div class="flex mt-1 text-amber-400 text-sm"><span>${starsFull}</span><span class="text-gray-200">${starsEmpty}</span></div>
      <p class="text-xs text-gray-500 mt-1">Submitted ${today}</p>
    </div>
    <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100 shrink-0">Submitted</span>
  `;
  list.insertBefore(entry, list.firstChild);

  const submittedEventName = currentFeedbackEvent;
  closeFeedbackForm();
  showToast(`Feedback submitted for "${submittedEventName}". Thank you!`);
}

</script>
</body>
</html>
