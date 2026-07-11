<?php
require_once '../config.php';
requireAdmin();

$activePage = 'profile';
$pageTitle  = 'Club Profile';
$adminId    = $_SESSION['admin_id'];
$errors     = [];
$success    = '';

// ── Load current admin record ──────────────────────────────────
$stmt = db()->prepare("SELECT * FROM admins WHERE id=?");
$stmt->execute([$adminId]);
$adminRec = $stmt->fetch();
if (!$adminRec) { header('Location: ../index.php'); exit; }

// ── Handle profile update ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $name            = trim($_POST['name']            ?? '');
    $email           = trim($_POST['email']           ?? '');
    $club_name       = trim($_POST['club_name']       ?? '');
    $phone           = trim($_POST['phone']           ?? '');
    $office_location = trim($_POST['office_location'] ?? '');

    // Validation
    if (!$name)  $errors[] = 'Account name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (!$club_name) $errors[] = 'Club / Organisation name is required.';
    if ($phone && !preg_match('/^[\+0-9\s\-\(\)]{7,20}$/', $phone))
        $errors[] = 'Phone number format is invalid (e.g. +60 12-3456789).';

    // Check email uniqueness (excluding self)
    $dup = db()->prepare("SELECT id FROM admins WHERE email=? AND id != ?");
    $dup->execute([$email, $adminId]);
    if ($dup->fetch()) $errors[] = 'That email address is already taken.';

    if (empty($errors)) {
        db()->prepare("
            UPDATE admins
            SET name=?, email=?, club_name=?, phone=?, office_location=?
            WHERE id=?
        ")->execute([$name, $email, $club_name, $phone ?: null, $office_location ?: null, $adminId]);

        // Refresh session + local record
        $_SESSION['admin']['name']  = $name;
        $_SESSION['admin']['email'] = $email;
        $adminRec['name']            = $name;
        $adminRec['email']           = $email;
        $adminRec['club_name']       = $club_name;
        $adminRec['phone']           = $phone;
        $adminRec['office_location'] = $office_location;
        $success = 'Profile updated successfully.';
    }
}

// ── Handle password change ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $adminRec['password'])) $errors[] = 'Current password is incorrect.';
    if (strlen($new) < 8)    $errors[] = 'New password must be at least 8 characters.';
    if ($new !== $confirm)   $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        db()->prepare("UPDATE admins SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_BCRYPT), $adminId]);
        $success = 'Password changed successfully.';
    }
}

// ── Event stats for this admin ─────────────────────────────────
$totalEvents = (int) db()->prepare("SELECT COUNT(*) FROM events WHERE created_by=?")->execute([$adminId]) ? 0 : 0;
$s1 = db()->prepare("SELECT COUNT(*) FROM events WHERE created_by=?"); $s1->execute([$adminId]); $totalEvents = (int)$s1->fetchColumn();
$s2 = db()->prepare("SELECT COUNT(*) FROM events WHERE created_by=? AND status IN ('open','upcoming')"); $s2->execute([$adminId]); $activeEvents = (int)$s2->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Club Profile</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
<style>
  .form-input { width:100%; padding:.625rem .875rem; border:1px solid #e5e7eb; border-radius:.5rem; font-size:.875rem; color:#374151; transition:border-color .15s,box-shadow .15s; outline:none; }
  .form-input:focus { border-color:#582C83; box-shadow:0 0 0 3px rgba(88,44,131,.12); }
  .form-label { display:block; font-size:.75rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.375rem; }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 max-w-3xl w-full mx-auto space-y-6">

  <h1 class="text-2xl font-bold text-gray-900">Club Profile</h1>

  <!-- Feedback -->
  <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-5 py-4 text-sm space-y-1">
      <?php foreach ($errors as $e): ?><p class="flex items-start gap-2"><i class="fas fa-circle-exclamation mt-0.5 shrink-0"></i><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
    </div>
  <?php elseif ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-5 py-4 text-sm flex items-center gap-2">
      <i class="fas fa-circle-check"></i> <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <!-- Club Summary Card -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center gap-5">
      <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-2xl font-extrabold shrink-0"
           style="background:#582C83;color:#F9A51B;">
        <?= strtoupper(substr($adminRec['name'], 0, 1)) ?>
      </div>
      <div class="flex-1 min-w-0">
        <h2 class="text-xl font-bold text-gray-900 truncate"><?= htmlspecialchars($adminRec['name']) ?></h2>
        <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($adminRec['email']) ?></p>
        <div class="flex items-center gap-3 mt-2 flex-wrap">
          <span class="badge <?= $adminRec['status']==='active'?'bg-green-100 text-green-800':'bg-amber-100 text-amber-800' ?>">
            <?= ucfirst($adminRec['status']) ?>
          </span>
          <span class="badge bg-purple-100 text-purple-800"><?= htmlspecialchars($adminRec['role'] ?? 'Club Admin') ?></span>
        </div>
      </div>
      <div class="hidden sm:grid grid-cols-2 gap-4 text-center shrink-0">
        <div>
          <p class="text-2xl font-bold text-gray-900"><?= $totalEvents ?></p>
          <p class="text-xs text-gray-400 font-medium">Total Events</p>
        </div>
        <div>
          <p class="text-2xl font-bold" style="color:#582C83;"><?= $activeEvents ?></p>
          <p class="text-xs text-gray-400 font-medium">Active</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Profile -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
    <h2 class="font-bold text-gray-800 flex items-center gap-2">
      <i class="fas fa-pen text-sm" style="color:#582C83;"></i> Edit Profile
    </h2>
    <form method="POST" class="space-y-5">
      <input type="hidden" name="action" value="update_profile">

      <!-- ── Account Details ── -->
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-purple-700 mb-3 pb-1 border-b border-purple-100">
          Account Details
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label" for="name">Account Name <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" class="form-input"
                   value="<?= htmlspecialchars($adminRec['name']) ?>" required maxlength="120"
                   placeholder="e.g. MPP UiTM Machang">
            <p class="text-xs text-gray-400 mt-1">Used for login and system records.</p>
          </div>
          <div>
            <label class="form-label" for="email">Login Email <span class="text-red-500">*</span></label>
            <input type="email" id="email" name="email" class="form-input"
                   value="<?= htmlspecialchars($adminRec['email']) ?>" required
                   placeholder="admin@uitm.edu.my">
          </div>
        </div>
      </div>

      <!-- ── Organizer Information ── -->
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-purple-700 mb-3 pb-1 border-b border-purple-100">
          Organizer Information
          <span class="text-gray-400 font-normal normal-case tracking-normal ml-1">— shown publicly on event pages</span>
        </p>
        <div class="space-y-4">

          <div>
            <label class="form-label" for="club_name">
              Club / Society Name <span class="text-red-500">*</span>
            </label>
            <input type="text" id="club_name" name="club_name" class="form-input"
                   value="<?= htmlspecialchars($adminRec['club_name'] ?? $adminRec['name']) ?>"
                   required maxlength="150"
                   placeholder="e.g. Information Management Society (IMSoc)">
            <p class="text-xs text-gray-400 mt-1">This is the name students will see on event detail pages.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="form-label" for="phone">Phone Number</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">
                  <i class="fas fa-phone"></i>
                </span>
                <input type="tel" id="phone" name="phone" class="form-input pl-8"
                       value="<?= htmlspecialchars($adminRec['phone'] ?? '') ?>"
                       maxlength="30" placeholder="+60 12-3456789">
              </div>
            </div>
            <div>
              <label class="form-label" for="office_location">Office Location</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">
                  <i class="fas fa-location-dot"></i>
                </span>
                <input type="text" id="office_location" name="office_location" class="form-input pl-8"
                       value="<?= htmlspecialchars($adminRec['office_location'] ?? '') ?>"
                       maxlength="255" placeholder="e.g. Student Affairs Building, Level 2">
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Preview card -->
      <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3">
        <p class="text-xs font-bold uppercase tracking-widest text-gray-500">
          <i class="fas fa-eye mr-1"></i> Preview — how students will see your organizer card
        </p>
        <div class="bg-white border border-purple-100 rounded-xl p-4 space-y-2">
          <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold shrink-0"
                 style="background:#582C83;color:#F9A51B;">
              <i class="fas fa-building"></i>
            </div>
            <span class="text-xs font-bold uppercase tracking-widest text-purple-700">Organizer Information</span>
          </div>
          <div class="grid grid-cols-1 gap-1.5 text-sm">
            <div class="flex gap-2">
              <span class="text-gray-400 w-28 shrink-0 text-xs font-semibold uppercase tracking-wide">Club Name</span>
              <span class="font-semibold text-gray-800" id="prev-club">
                <?= htmlspecialchars($adminRec['club_name'] ?? $adminRec['name']) ?>
              </span>
            </div>
            <div class="flex gap-2">
              <span class="text-gray-400 w-28 shrink-0 text-xs font-semibold uppercase tracking-wide">Email</span>
              <span class="font-semibold text-gray-800" id="prev-email">
                <?= htmlspecialchars($adminRec['email']) ?>
              </span>
            </div>
            <div class="flex gap-2">
              <span class="text-gray-400 w-28 shrink-0 text-xs font-semibold uppercase tracking-wide">Phone</span>
              <span class="font-semibold text-gray-800" id="prev-phone">
                <?= $adminRec['phone'] ? htmlspecialchars($adminRec['phone']) : '<span class="text-gray-400 font-normal">Not Available</span>' ?>
              </span>
            </div>
            <div class="flex gap-2">
              <span class="text-gray-400 w-28 shrink-0 text-xs font-semibold uppercase tracking-wide">Office</span>
              <span class="font-semibold text-gray-800" id="prev-office">
                <?= $adminRec['office_location'] ? htmlspecialchars($adminRec['office_location']) : '<span class="text-gray-400 font-normal">Not Available</span>' ?>
              </span>
            </div>
          </div>
        </div>
        <p class="text-xs text-gray-400">The preview above updates as you type.</p>
      </div>

      <div class="flex items-center justify-between pt-1">
        <p class="text-xs text-gray-400">Role and status are managed by HEP Super Admin.</p>
        <button type="submit" class="btn-primary flex items-center gap-2">
          <i class="fas fa-floppy-disk"></i> Save Changes
        </button>
      </div>
    </form>
  </div>

  <script>
  // Live preview — update organizer card as admin types
  function bindPreview(inputId, previewId, fallback) {
    const inp  = document.getElementById(inputId);
    const prev = document.getElementById(previewId);
    if (!inp || !prev) return;
    inp.addEventListener('input', () => {
      prev.innerHTML = inp.value.trim()
        ? inp.value.trim()
        : '<span class="text-gray-400 font-normal">' + fallback + '</span>';
    });
  }
  bindPreview('club_name',      'prev-club',   'Not Available');
  bindPreview('email',          'prev-email',  'Not Available');
  bindPreview('phone',          'prev-phone',  'Not Available');
  bindPreview('office_location','prev-office', 'Not Available');
  </script>

  <!-- Change Password -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
    <h2 class="font-bold text-gray-800 flex items-center gap-2">
      <i class="fas fa-lock text-sm" style="color:#582C83;"></i> Change Password
    </h2>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="change_password">
      <div>
        <label class="form-label" for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" class="form-input" required>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="form-label" for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" class="form-input" minlength="8" required>
        </div>
        <div>
          <label class="form-label" for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-input" minlength="8" required>
        </div>
      </div>
      <p class="text-xs text-gray-400">Minimum 8 characters.</p>
      <button type="submit" class="btn-primary flex items-center gap-2">
        <i class="fas fa-key"></i> Change Password
      </button>
    </form>
  </div>

  <!-- Account Info -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h2 class="font-bold text-gray-800 text-sm mb-4 flex items-center gap-2">
      <i class="fas fa-circle-info text-sm" style="color:#582C83;"></i> Account Information
    </h2>
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
      <div>
        <dt class="text-xs text-gray-400 font-semibold uppercase tracking-wide">Account ID</dt>
        <dd class="text-gray-700 font-mono">#<?= $adminRec['id'] ?></dd>
      </div>
      <div>
        <dt class="text-xs text-gray-400 font-semibold uppercase tracking-wide">Status</dt>
        <dd class="text-gray-700"><?= ucfirst($adminRec['status']) ?></dd>
      </div>
      <div>
        <dt class="text-xs text-gray-400 font-semibold uppercase tracking-wide">Role</dt>
        <dd class="text-gray-700"><?= htmlspecialchars($adminRec['role'] ?? '—') ?></dd>
      </div>
      <div>
        <dt class="text-xs text-gray-400 font-semibold uppercase tracking-wide">Last Active</dt>
        <dd class="text-gray-700"><?= $adminRec['last_active'] ? date('d M Y, H:i', strtotime($adminRec['last_active'])) : '—' ?></dd>
      </div>
      <div>
        <dt class="text-xs text-gray-400 font-semibold uppercase tracking-wide">Member Since</dt>
        <dd class="text-gray-700"><?= date('d M Y', strtotime($adminRec['created_at'])) ?></dd>
      </div>
    </dl>
  </div>

</main>
</div>
</body>
</html>