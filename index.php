<?php
// ============================================================
//  index.php — UiVent Unified Login (User / Admin / Super Admin)
//  http://localhost/uivent/index.php
// ============================================================
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {

        // ── 1. Check Super Admin ────────────────────────────────
        $stmt = db()->prepare('SELECT * FROM super_admins WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $superAdmin = $stmt->fetch();

        if ($superAdmin && password_verify($password, $superAdmin['password'])) {
            $_SESSION['super_admin_id'] = $superAdmin['id'];
            $_SESSION['super_admin']    = ['name' => $superAdmin['name'], 'email' => $superAdmin['email']];
            header('Location: superadmin/command_centre.php');
            exit;
        }

        // ── 2. Check Admin ──────────────────────────────────────
        $stmt = db()->prepare('SELECT * FROM admins WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin']    = [
                'name'   => $admin['name'],
                'email'  => $admin['email'],
                'role'   => $admin['role'],
                'status' => $admin['status'],
            ];
            header('Location: admin/admin_dashboard.php');
            exit;
        }

        // ── 3. Check Student ────────────────────────────────────
        // Support login by email OR matric_no
        $stmt = db()->prepare('SELECT * FROM students WHERE (email = :email OR matric_no = :matric) AND is_active = 1 LIMIT 1');
        $stmt->execute(['email' => $email, 'matric' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $userData = [
                'name'      => $user['name'],
                'email'     => $user['email'],
                'matric_no' => $user['matric_no'] ?? '',
                'campus_id' => $user['campus_id'] ?? '',
            ];
            // Set BOTH session keys so requireUser() and requireStudent() both work
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user']       = $userData;
            $_SESSION['student_id'] = $user['id'];
            $_SESSION['student']    = $userData;
            header('Location: users/home.php');
            exit;
        }

        // ── 4. No match ─────────────────────────────────────────
        $error = 'Invalid credentials. Please check your email and password.';
    }
}

// Redirect if already logged in
if (!empty($_SESSION['super_admin_id'])) { header('Location: superadmin/command_centre.php'); exit; }
if (!empty($_SESSION['admin_id']))        { header('Location: admin/admin_dashboard.php');     exit; }
if (!empty($_SESSION['student_id']))      { header('Location: users/home.php');                exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Sign In</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
  *, *::before, *::after { font-family: 'Inter', sans-serif; box-sizing: border-box; margin: 0; padding: 0; }

  html, body { min-height: 100%; background: #0E0720; }

  body {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 2rem 1rem;
  }

  /* ── Background layers ── */
  .bg-mesh {
    position: fixed; inset: 0;
    background:
      radial-gradient(ellipse 70% 55% at 15% 5%,  rgba(88,44,131,.70) 0%, transparent 55%),
      radial-gradient(ellipse 55% 75% at 85% 95%, rgba(59,31,94,.55) 0%, transparent 50%),
      linear-gradient(160deg, #120929 0%, #0E0720 45%, #120929 100%);
    z-index: 0;
  }
  .bg-grid {
    position: fixed; inset: 0;
    background-image:
      linear-gradient(rgba(88,44,131,.07) 1px, transparent 1px),
      linear-gradient(90deg, rgba(88,44,131,.07) 1px, transparent 1px);
    background-size: 56px 56px;
    z-index: 1;
  }
  .orb {
    position: fixed; border-radius: 50%;
    filter: blur(90px); opacity: .18;
    animation: drift 12s ease-in-out infinite;
    z-index: 1; pointer-events: none;
  }
  .orb-1 { width:500px; height:500px; background:#582C83; top:-150px; left:-100px; animation-duration:14s; }
  .orb-2 { width:350px; height:350px; background:#3B1F5E; bottom:-80px; right:-60px; animation-duration:10s; animation-delay:-4s; }
  .orb-3 { width:250px; height:250px; background:#F9A51B; top:35%; right:6%; animation-duration:16s; animation-delay:-7s; opacity:.05; }
  @keyframes drift {
    0%,100% { transform: translate(0,0) scale(1); }
    33%      { transform: translate(24px,-18px) scale(1.04); }
    66%      { transform: translate(-14px,22px) scale(.97); }
  }

  /* ── Wrapper ── */
  .page-wrapper {
    position: relative; z-index: 10;
    width: 100%; max-width: 460px;
  }

  /* ── Top bar ── */
  .top-bar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.5rem; padding: 0 .25rem;
  }

  /* ── Card ── */
  .login-card {
    background: rgba(18,9,41,.75);
    border: 1px solid rgba(255,255,255,.09);
    backdrop-filter: blur(32px) saturate(1.4);
    -webkit-backdrop-filter: blur(32px) saturate(1.4);
    box-shadow:
      0 40px 100px rgba(0,0,0,.70),
      0 0 0 1px rgba(88,44,131,.20) inset,
      0 1px 0 rgba(255,255,255,.06) inset;
    border-radius: 1.25rem;
    overflow: hidden;
  }
  .card-enter { animation: cardIn .55s cubic-bezier(.16,1,.3,1) both; }
  @keyframes cardIn {
    from { opacity:0; transform:translateY(32px) scale(.96); }
    to   { opacity:1; transform:translateY(0) scale(1); }
  }

  /* ── Header strip ── */
  .header-strip {
    background: linear-gradient(145deg, #1E0B3D 0%, #3D1872 50%, #582C83 100%);
    padding: 2rem 2.25rem 1.75rem;
    border-bottom: 1px solid rgba(255,255,255,.06);
    position: relative;
    overflow: hidden;
  }
  .header-strip::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(249,165,27,.12) 0%, transparent 70%);
    pointer-events: none;
  }

  /* ── Role badge ── */
  .role-badge {
    display: inline-flex; align-items: center; gap: .35rem;
    font-size: .62rem; font-weight: 700;
    letter-spacing: .13em; text-transform: uppercase;
    padding: .3rem .7rem; border-radius: 9999px; border: 1px solid;
    white-space: nowrap;
  }
  .badge-user  { color:#34D399; border-color:rgba(52,211,153,.30);  background:rgba(52,211,153,.08); }
  .badge-admin { color:#60A5FA; border-color:rgba(96,165,250,.30);  background:rgba(96,165,250,.08); }
  .badge-sa    { color:#F9A51B; border-color:rgba(249,165,27,.30);  background:rgba(249,165,27,.08); }

  /* ── Role tabs ── */
  .tabs-bar {
    display: flex;
    border-bottom: 1px solid rgba(255,255,255,.06);
    padding: 0 2.25rem;
    background: rgba(0,0,0,.20);
    gap: 0;
  }
  .role-tab {
    position: relative;
    color: rgba(255,255,255,.35);
    border: none; background: none; cursor: pointer;
    padding: .875rem 1.25rem .875rem 0;
    margin-right: 1.25rem;
    font-size: .7rem; font-weight: 700;
    letter-spacing: .08em; text-transform: uppercase;
    transition: color .2s;
    white-space: nowrap;
  }
  .role-tab::after {
    content: '';
    position: absolute; bottom: -1px; left: 0; right: 0;
    height: 2px; background: #F9A51B;
    border-radius: 2px 2px 0 0;
    opacity: 0; transition: opacity .2s;
  }
  .role-tab.active { color: #F9A51B; }
  .role-tab.active::after { opacity: 1; }
  .role-tab:hover:not(.active) { color: rgba(255,255,255,.60); }

  /* ── Form body ── */
  .form-body { padding: 2rem 2.25rem; }

  /* ── Inputs ── */
  .input-field {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.09);
    color: white;
    transition: border-color .2s, box-shadow .2s, background .2s;
    width: 100%; border-radius: .625rem;
    padding: .75rem 1rem .75rem 2.75rem;
    font-size: .875rem; font-weight: 400;
    letter-spacing: .01em;
  }
  .input-field:focus {
    outline: none;
    border-color: rgba(249,165,27,.55);
    background: rgba(255,255,255,.06);
    box-shadow: 0 0 0 3px rgba(249,165,27,.10), 0 1px 4px rgba(0,0,0,.3);
  }
  .input-field::placeholder { color: rgba(255,255,255,.22); font-weight: 300; }
  .input-field.pr-icon { padding-right: 2.75rem; }

  .input-wrap { position: relative; margin-top: .5rem; }
  .input-icon-left {
    position: absolute; left: .9rem; top: 50%; transform: translateY(-50%);
    color: rgba(255,255,255,.25); font-size: .8rem; pointer-events: none;
  }
  .input-icon-right {
    position: absolute; right: .9rem; top: 50%; transform: translateY(-50%);
    color: rgba(255,255,255,.20); background: none; border: none;
    cursor: pointer; font-size: .8rem; transition: color .2s;
    padding: 0; line-height: 1;
  }
  .input-icon-right:hover { color: rgba(255,255,255,.55); }

  /* ── Field label ── */
  .field-label {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: .15rem;
  }
  .field-label label {
    font-size: .68rem; font-weight: 600;
    letter-spacing: .09em; text-transform: uppercase;
    color: rgba(255,255,255,.50);
  }

  /* ── Forgot link ── */
  .forgot-link {
    font-size: .7rem; font-weight: 500;
    color: rgba(249,165,27,.65);
    background: none; border: none; cursor: pointer; padding: 0;
    transition: color .2s;
  }
  .forgot-link:hover { color: #F9A51B; }

  /* ── Custom checkbox ── */
  .custom-checkbox-wrap {
    display: flex; align-items: center; gap: .5rem;
    cursor: pointer; user-select: none;
  }
  .custom-checkbox-wrap input[type="checkbox"] {
    appearance: none; -webkit-appearance: none;
    width: 1rem; height: 1rem;
    border: 1px solid rgba(255,255,255,.20);
    border-radius: .25rem;
    background: rgba(255,255,255,.04);
    cursor: pointer; position: relative; flex-shrink: 0;
    transition: border-color .2s, background .2s;
  }
  .custom-checkbox-wrap input[type="checkbox"]:checked {
    background: #F9A51B; border-color: #F9A51B;
  }
  .custom-checkbox-wrap input[type="checkbox"]:checked::after {
    content: '';
    position: absolute; left: 3px; top: 1px;
    width: 5px; height: 8px;
    border: 2px solid #27134A;
    border-top: none; border-left: none;
    transform: rotate(45deg);
  }
  .custom-checkbox-wrap span { font-size: .75rem; color: rgba(255,255,255,.40); }

  /* ── MFA tag ── */
  .mfa-tag {
    display: flex; align-items: center; gap: .375rem;
    font-size: .7rem; color: rgba(255,255,255,.30);
    background: rgba(249,165,27,.06);
    border: 1px solid rgba(249,165,27,.15);
    padding: .25rem .6rem; border-radius: .375rem;
  }
  .mfa-tag i { color: rgba(249,165,27,.55); }

  /* ── Button ── */
  .btn-primary {
    display: flex; align-items: center; justify-content: center;
    gap: .625rem; width: 100%;
    background: linear-gradient(135deg, #C47E0E 0%, #F9A51B 50%, #E09010 100%);
    border: 1px solid rgba(249,165,27,.35);
    box-shadow: 0 4px 24px rgba(249,165,27,.28), 0 1px 0 rgba(255,255,255,.15) inset;
    color: #1A0D2E;
    font-weight: 700; font-size: .9rem;
    padding: .875rem 1rem; border-radius: .75rem;
    cursor: pointer; transition: box-shadow .2s, transform .15s;
    letter-spacing: .01em;
  }
  .btn-primary:hover {
    box-shadow: 0 8px 32px rgba(249,165,27,.45);
    transform: translateY(-1px);
  }
  .btn-primary:active { transform: translateY(0); box-shadow: 0 2px 12px rgba(249,165,27,.30); }

  /* ── Divider ── */
  .form-divider {
    display: flex; align-items: center; gap: .75rem;
    color: rgba(255,255,255,.15); font-size: .65rem;
    letter-spacing: .08em; text-transform: uppercase;
  }
  .form-divider::before, .form-divider::after {
    content: ''; flex: 1;
    height: 1px; background: rgba(255,255,255,.07);
  }

  /* ── Hint box ── */
  .hint-box {
    border-radius: .625rem; padding: .7rem 1rem;
    font-size: .72rem; line-height: 1.6;
  }
  .hint-user  { background:rgba(52,211,153,.06);  border:1px solid rgba(52,211,153,.15);  color:rgba(52,211,153,.80); }
  .hint-admin { background:rgba(96,165,250,.06);  border:1px solid rgba(96,165,250,.15);  color:rgba(96,165,250,.80); }
  .hint-sa    { background:rgba(249,165,27,.06);  border:1px solid rgba(249,165,27,.15);  color:rgba(249,165,27,.80); }

  /* ── Error box ── */
  .error-box {
    display: flex; align-items: flex-start; gap: .75rem;
    background: rgba(127,20,20,.25);
    border: 1px solid rgba(220,38,38,.30);
    border-radius: .625rem; padding: .875rem 1rem;
  }
  .error-box i  { color: #F87171; font-size: .875rem; flex-shrink: 0; margin-top: .1rem; }
  .error-box p  { font-size: .78rem; color: #FECACA; font-weight: 500; line-height: 1.4; }

  /* ── Footer ── */
  .card-footer {
    padding: 1.25rem 2.25rem 1.5rem;
    border-top: 1px solid rgba(255,255,255,.05);
    display: flex; align-items: center; justify-content: space-between;
    font-size: .7rem; color: rgba(255,255,255,.20);
  }
  .card-footer button {
    background: none; border: none; cursor: pointer;
    color: rgba(255,255,255,.20); font-size: .7rem;
    transition: color .2s; padding: 0;
  }
  .card-footer button:hover { color: rgba(255,255,255,.50); }

  /* ── Bottom note ── */
  .bottom-note {
    text-align: center; font-size: .68rem;
    color: rgba(255,255,255,.18); margin-top: 1.5rem;
    line-height: 1.5;
  }

  /* ── Misc ── */
  .pulse-dot { animation: pulseDot 2.5s infinite; }
  @keyframes pulseDot {
    0%,100% { box-shadow: 0 0 0 0 rgba(52,211,153,.5); }
    60%      { box-shadow: 0 0 0 5px rgba(52,211,153,0); }
  }
  .version-badge {
    background: rgba(88,44,131,.40);
    color: rgba(196,168,240,.80);
    border: 1px solid rgba(88,44,131,.50);
    font-size: .58rem; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    padding: .18rem .5rem; border-radius: .3rem;
  }
  .campus-dot { transition: all .3s; }

  @keyframes shake {
    0%,100% { transform: translateX(0); }
    20%,60%  { transform: translateX(-6px); }
    40%,80%  { transform: translateX(6px); }
  }
  .shake { animation: shake .4s ease; }

  /* ── Space helpers ── */
  .space-y-4 > * + * { margin-top: 1rem; }
  .space-y-5 > * + * { margin-top: 1.25rem; }
</style>
</head>
<body>
<div class="bg-mesh"></div>
<div class="bg-grid"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>

<div class="page-wrapper card-enter">

  <!-- Top bar -->
  <div class="top-bar">
    <div style="display:flex;align-items:center;gap:.5rem;">
      <span class="w-2 h-2 rounded-full bg-emerald-400 pulse-dot" style="width:.5rem;height:.5rem;border-radius:50%;background:#34D399;display:inline-block;"></span>
      <span style="font-size:.72rem;color:rgba(255,255,255,.40);font-weight:500;">All systems operational</span>
    </div>
    <div style="display:flex;align-items:center;gap:.375rem;">
      <span style="font-size:.72rem;color:rgba(255,255,255,.30);">UiVent</span>
      <span class="version-badge">v2.4.1</span>
    </div>
  </div>

  <div class="login-card">

    <!-- Header -->
    <div class="header-strip">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
        <div style="display:flex;align-items:center;">
          <div style="background:#F9A51B;color:#27134A;padding:.25rem .625rem;border-radius:.375rem;font-weight:800;font-size:1.125rem;letter-spacing:.05em;margin-right:.5rem;line-height:1.25;">Ui</div>
          <span style="font-weight:700;color:white;font-size:1.125rem;letter-spacing:.05em;">Vent</span>
        </div>
        <div style="width:1px;height:1.25rem;background:rgba(255,255,255,.20);"></div>
        <span id="roleBadge" class="role-badge badge-user"><i class="fas fa-user" style="font-size:.6rem;"></i>&nbsp;Student / Staff</span>
      </div>

      <div style="margin-bottom:.75rem;">
        <span style="font-size:.65rem;font-weight:700;color:rgba(249,165,27,.85);text-transform:uppercase;letter-spacing:.18em;">Universiti Teknologi MARA</span>
        <span style="display:block;font-size:.6rem;color:rgba(255,255,255,.30);letter-spacing:.12em;margin-top:.2rem;">Event Management System</span>
      </div>

      <h1 style="font-size:1.5rem;font-weight:700;color:white;letter-spacing:-.01em;" id="headerTitle">Welcome Back</h1>
      <p style="font-size:.8rem;color:rgba(196,168,240,.65);margin-top:.25rem;" id="headerSub">Sign in to access your UiVent portal.</p>

      <!-- Campus dots -->
      <div style="display:flex;align-items:center;gap:.5rem;margin-top:1rem;">
        <span style="font-size:.7rem;color:rgba(255,255,255,.30);">Campuses:</span>
        <div style="display:flex;gap:.375rem;align-items:center;">
          <div class="campus-dot" style="width:.5rem;height:.5rem;border-radius:50%;background:#34D399;" title="Shah Alam"></div>
          <div class="campus-dot" style="width:.5rem;height:.5rem;border-radius:50%;background:#34D399;" title="Johor Bahru"></div>
          <div class="campus-dot" style="width:.5rem;height:.5rem;border-radius:50%;background:#FBBF24;" title="Penang"></div>
          <div class="campus-dot" style="width:.5rem;height:.5rem;border-radius:50%;background:#34D399;" title="Pahang"></div>
          <div class="campus-dot" style="width:.5rem;height:.5rem;border-radius:50%;background:#F87171;" title="Sabah"></div>
        </div>
        <span style="font-size:.7rem;color:rgba(255,255,255,.25);">5 portals monitored</span>
      </div>
    </div>

    <!-- Role tabs -->
    <div class="tabs-bar">
      <button class="role-tab active" onclick="switchRole('user')"       id="tab-user">
        <i class="fas fa-user" style="margin-right:.25rem;"></i> User
      </button>
      <button class="role-tab"        onclick="switchRole('admin')"      id="tab-admin">
        <i class="fas fa-user-shield" style="margin-right:.25rem;"></i> Admin
      </button>
      <button class="role-tab"        onclick="switchRole('superadmin')" id="tab-superadmin">
        <i class="fas fa-crown" style="margin-right:.25rem;"></i> Super Admin
      </button>
    </div>

    <!-- Login form -->
    <form method="POST" action="index.php" class="form-body space-y-5" id="loginForm">

      <?php if ($error): ?>
      <div class="error-box shake" id="errorBox">
        <i class="fas fa-circle-exclamation"></i>
        <p><?= htmlspecialchars($error) ?></p>
      </div>
      <?php endif; ?>

      <!-- Email -->
      <div>
        <div class="field-label">
          <label id="emailLabel">Email / Student ID</label>
        </div>
        <div class="input-wrap">
          <span class="input-icon-left"><i class="fas fa-id-badge"></i></span>
          <input type="text" name="email" id="emailInput"
                 placeholder="e.g. student@uitm.edu.my"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 class="input-field"
                 autocomplete="username">
        </div>
      </div>

      <!-- Password -->
      <div>
        <div class="field-label">
          <label>Password</label>
          <button type="button" class="forgot-link"
                  onclick="openForgot()">Forgot password?</button>
        </div>
        <div class="input-wrap">
          <span class="input-icon-left"><i class="fas fa-lock"></i></span>
          <input type="password" name="password" id="passInput"
                 placeholder="Enter your password"
                 class="input-field pr-icon"
                 autocomplete="current-password">
          <button type="button" class="input-icon-right" onclick="togglePassword()">
            <i class="fas fa-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <!-- Remember + MFA -->
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <label class="custom-checkbox-wrap">
          <input type="checkbox" name="remember">
          <span>Keep me signed in</span>
        </label>
        <div class="mfa-tag" id="mfaTag" style="display:none;">
          <i class="fas fa-shield-halved"></i>
          <span>MFA active</span>
        </div>
      </div>

      <!-- Submit -->
      <button type="submit" class="btn-primary" id="submitBtn">
        <i class="fas fa-right-to-bracket"></i>
        <span id="btnLabel">Sign In</span>
      </button>

      <!-- Hint -->
      <div class="hint-box hint-user" id="hintBox">
        <i class="fas fa-circle-info" style="margin-right:.25rem;"></i>
        <strong>Demo:</strong> Use your UiTM email + registered password for student/staff access.
      </div>
    </form>

    <!-- Footer -->
    <div class="card-footer">
      <span>UiTM © 2026</span>
      <div style="display:flex;align-items:center;gap:.75rem;">
        <button onclick="alert('Contact: ict-helpdesk@uitm.edu.my')">Support</button>
        <span style="color:rgba(255,255,255,.15);">·</span>
        <button>Privacy</button>
      </div>
    </div>
  </div>

  <p class="bottom-note">
    <i class="fas fa-lock" style="margin-right:.25rem;color:rgba(255,255,255,.15);"></i>
    All sessions are encrypted, monitored, and logged in the audit trail.
  </p>
</div>

<!-- Forgot Password Modal -->
<div id="forgotModal" class="hidden" style="position:fixed;inset:0;background:rgba(0,0,0,.70);backdrop-filter:blur(6px);z-index:50;display:none;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#27134A;border:1px solid rgba(255,255,255,.10);border-radius:1rem;padding:1.5rem;width:100%;max-width:22rem;box-shadow:0 24px 60px rgba(0,0,0,.6);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
      <h3 style="color:white;font-weight:700;font-size:1rem;">Reset Password</h3>
      <button onclick="closeForgot()" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.35);font-size:1rem;line-height:1;padding:0;" aria-label="Close">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <p style="font-size:.75rem;color:rgba(255,255,255,.50);margin-bottom:1rem;line-height:1.5;">Enter your UiTM email. A reset link will be sent if the account exists.</p>
    <input type="email" placeholder="your.email@uitm.edu.my"
           style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:white;width:100%;border-radius:.5rem;padding:.65rem 1rem;font-size:.875rem;margin-bottom:1rem;"
           class="input-field">
    <button onclick="closeForgot();alert('Reset link sent — check your UiTM email.')"
            class="btn-primary">Send Reset Link</button>
  </div>
</div>

<script>
// ── Role configuration ───────────────────────────────────────
const roles = {
  user: {
    badge:       '<i class="fas fa-user" style="font-size:.6rem;"></i>&nbsp;Student / Staff',
    badgeClass:  'badge-user',
    title:       'Welcome Back',
    sub:         'Sign in to browse and register for events.',
    emailLabel:  'Email / Student ID',
    placeholder: 'e.g. student@uitm.edu.my',
    btnIcon:     'fa-right-to-bracket',
    btnLabel:    'Sign In',
    hint:        '<i class="fas fa-circle-info" style="margin-right:.25rem;"></i><strong>Demo:</strong> Use your UiTM email + registered password.',
    hintClass:   'hint-user',
    mfa:         false,
  },
  admin: {
    badge:       '<i class="fas fa-user-shield" style="font-size:.6rem;"></i>&nbsp;Campus Admin',
    badgeClass:  'badge-admin',
    title:       'Admin Portal',
    sub:         'Manage events and approvals for your campus.',
    emailLabel:  'Staff Email',
    placeholder: 'e.g. hep.admin@uitm.edu.my',
    btnIcon:     'fa-right-to-bracket',
    btnLabel:    'Sign In as Admin',
    hint:        '<i class="fas fa-circle-info" style="margin-right:.25rem;"></i><strong>Note:</strong> Use your registered staff email and password.',
    hintClass:   'hint-admin',
    mfa:         true,
  },
  superadmin: {
    badge:       '<i class="fas fa-crown" style="font-size:.6rem;"></i>&nbsp;Super Admin',
    badgeClass:  'badge-sa',
    title:       'Command Centre',
    sub:         'Restricted access. Authorised personnel only.',
    emailLabel:  'Staff ID / Email',
    placeholder: 'e.g. rashid.malik@uitm.edu.my',
    btnIcon:     'fa-shield-halved',
    btnLabel:    'Sign In to Command Centre',
    hint:        '<i class="fas fa-crown" style="margin-right:.25rem;"></i><strong>Note:</strong> Use your registered super admin credentials.',
    hintClass:   'hint-sa',
    mfa:         true,
  }
};

function switchRole(role) {
  const cfg = roles[role];

  // tabs
  ['user','admin','superadmin'].forEach(r => {
    document.getElementById('tab-' + r).classList.toggle('active', r === role);
  });

  // badge
  const badge = document.getElementById('roleBadge');
  badge.innerHTML = cfg.badge;
  badge.className = 'role-badge ' + cfg.badgeClass;

  // header text
  document.getElementById('headerTitle').textContent = cfg.title;
  document.getElementById('headerSub').textContent   = cfg.sub;

  // email field
  document.getElementById('emailLabel').textContent      = cfg.emailLabel;
  document.getElementById('emailInput').placeholder      = cfg.placeholder;

  // button — update icon + label separately to keep structure clean
  document.getElementById('submitBtn').querySelector('i').className = 'fas ' + cfg.btnIcon;
  document.getElementById('btnLabel').textContent = cfg.btnLabel;

  // hint
  const hintBox = document.getElementById('hintBox');
  hintBox.innerHTML = cfg.hint;
  hintBox.className = 'hint-box ' + cfg.hintClass;

  // MFA badge — only visible for admin roles
  document.getElementById('mfaTag').style.display = cfg.mfa ? 'flex' : 'none';
}

function togglePassword() {
  const inp = document.getElementById('passInput');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    inp.type = 'password';
    ico.classList.replace('fa-eye-slash', 'fa-eye');
  }
}

function closeForgot() {
  const m = document.getElementById('forgotModal');
  m.style.display = 'none';
  m.classList.add('hidden');
}

function openForgot() {
  const m = document.getElementById('forgotModal');
  m.classList.remove('hidden');
  m.style.display = 'flex';
}

document.getElementById('forgotModal').addEventListener('click', function(e) {
  if (e.target === this) closeForgot();
});

// Shake on error
<?php if ($error): ?>
document.addEventListener('DOMContentLoaded', () => {
  const box = document.getElementById('errorBox');
  if (box) {
    box.classList.add('shake');
    setTimeout(() => box.classList.remove('shake'), 500);
  }
});
<?php endif; ?>
</script>
</body>
</html>