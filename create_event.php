<?php
require_once '../config.php';
requireAdmin();

$activePage = 'create_event';
$pageTitle  = 'Create Event';
$adminId    = $_SESSION['admin_id'];
$errors     = [];

// ── Load existing event for editing ──────────────────────────
$editId = (int)($_GET['edit'] ?? 0);
$event  = null;
if ($editId) {
    $stmt = db()->prepare("SELECT * FROM events WHERE id=? AND created_by=?");
    $stmt->execute([$editId, $adminId]);
    $event = $stmt->fetch();
    if (!$event) { header('Location: my_events.php'); exit; }
    $pageTitle  = 'Edit Event';
    $activePage = 'my_events';
}

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title            = trim($_POST['title'] ?? '');
    $venue            = trim($_POST['venue'] ?? '');
    $category         = $_POST['category'] ?? 'Other';
    $capacity         = (int)($_POST['capacity'] ?? 0);
    $start_date       = $_POST['start_date'] ?? '';
    $end_date         = $_POST['end_date'] ?? '';
    $description      = trim($_POST['description'] ?? '');
    $status           = $_POST['status'] ?? 'upcoming';

    // Payment fields
    $is_paid          = !empty($_POST['is_paid']) ? 1 : 0;
    $fee_amount       = $is_paid ? (float)($_POST['fee_amount'] ?? 0) : null;
    $payment_method   = $is_paid ? (trim($_POST['payment_method'] ?? 'Online Banking')) : null;
    $payment_details  = $is_paid ? trim($_POST['payment_details'] ?? '') : null;
    $payment_deadline = $is_paid ? ($_POST['payment_deadline'] ?? '') : null;

    // ── Handle banner image upload ──────────────────────────
    $image_url = null;
    if (!empty($_FILES['banner_image']['tmp_name'])) {
        $file      = $_FILES['banner_image'];
        $allowed   = ['image/jpeg','image/png','image/webp','image/gif'];
        $maxSize   = 5 * 1024 * 1024; // 5 MB
        if (!in_array($file['type'], $allowed)) {
            $errors[] = 'Banner must be a JPG, PNG, WEBP, or GIF image.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'Banner image must be under 5 MB.';
        } else {
            $uploadDir = '../uploads/banners/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
            $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename  = 'banner_' . uniqid() . '.' . strtolower($ext);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $image_url = 'uploads/banners/' . $filename;
            } else {
                $errors[] = 'Failed to save banner image. Please try again.';
            }
        }
    } elseif ($editId) {
        // Keep existing image if no new one uploaded
        $image_url = trim($_POST['existing_image_url'] ?? '') ?: null;
    }

    if (!$title)       $errors[] = 'Event title is required.';
    if (!$venue)       $errors[] = 'Venue is required.';
    if ($capacity < 1) $errors[] = 'Capacity must be at least 1.';
    if (!$start_date)  $errors[] = 'Start date is required.';
    if ($is_paid) {
        if ($fee_amount <= 0)      $errors[] = 'Registration fee must be greater than RM 0.00.';
        if (!$payment_details)     $errors[] = 'Payment instructions are required for paid events.';
    }

    $validCats    = ['Academic','Cultural','Sports','Other'];
    $validStatuses= ['upcoming','open','closed'];
    if (!in_array($category, $validCats))    $category = 'Other';
    if (!in_array($status, $validStatuses))  $status   = 'upcoming';

    if (empty($errors)) {
        if ($editId) {
            $stmt = db()->prepare("
                UPDATE events
                SET title=?,venue=?,category=?,capacity=?,start_date=?,end_date=?,description=?,status=?,image_url=?,
                    is_paid=?,registration_fee=?,fee_amount=?,payment_method=?,payment_details=?,payment_deadline=?
                WHERE id=? AND created_by=?
            ");
            $stmt->execute([
                $title,$venue,$category,$capacity,$start_date?:null,$end_date?:null,
                $description,$status,$image_url,
                $is_paid,
                $fee_amount,  // registration_fee — read by register_event.php & events.php
                $fee_amount,$payment_method,$payment_details,$payment_deadline?:null,
                $editId,$adminId
            ]);
            header('Location: my_events.php?msg=updated');
        } else {
            $stmt = db()->prepare("
                INSERT INTO events
                    (created_by,title,venue,category,capacity,start_date,end_date,description,status,image_url,
                     is_paid,registration_fee,fee_amount,payment_method,payment_details,payment_deadline)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $adminId,$title,$venue,$category,$capacity,$start_date?:null,$end_date?:null,
                $description,$status,$image_url,
                $is_paid,
                $fee_amount,  // registration_fee — read by register_event.php & events.php
                $fee_amount,$payment_method,$payment_details,$payment_deadline?:null
            ]);
            header('Location: my_events.php?msg=created');
        }
        exit;
    }
}

$cats = ['Academic','Cultural','Sports','Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | <?= $pageTitle ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 max-w-3xl w-full mx-auto space-y-6">

  <!-- Header -->
  <div class="flex items-center gap-3">
    <a href="my_events.php" class="text-gray-400 hover:text-gray-700 transition-colors w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100">
      <i class="fas fa-arrow-left"></i>
    </a>
    <div>
      <h1 class="text-2xl font-bold text-gray-900"><?= $pageTitle ?></h1>
      <p class="text-sm text-gray-500 mt-0.5"><?= $editId ? 'Update event details below.' : 'Fill in the details to create a new event.' ?></p>
    </div>
  </div>

  <!-- Errors -->
  <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-5 py-4 text-sm space-y-1">
      <?php foreach ($errors as $e): ?>
        <p class="flex items-start gap-2"><i class="fas fa-circle-exclamation mt-0.5 shrink-0"></i><?= htmlspecialchars($e) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="space-y-6">

    <!-- Basic Info -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
      <h2 class="font-bold text-gray-800 flex items-center gap-2">
        <i class="fas fa-circle-info text-sm" style="color:#582C83;"></i> Basic Information
      </h2>

      <div>
        <label class="form-label" for="title">Event Title <span class="text-red-500">*</span></label>
        <input type="text" id="title" name="title" class="form-input"
               placeholder="e.g. Majlis Anugerah Kecemerlangan 2026"
               value="<?= htmlspecialchars($event['title'] ?? ($_POST['title'] ?? '')) ?>"
               maxlength="200" required>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
          <label class="form-label" for="category">Category <span class="text-red-500">*</span></label>
          <select id="category" name="category" class="form-input bg-white">
            <?php foreach ($cats as $c): ?>
              <option value="<?= $c ?>" <?= ($event['category']??($_POST['category']??''))===$c?'selected':'' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label" for="status">Status <span class="text-red-500">*</span></label>
          <select id="status" name="status" class="form-input bg-white">
            <option value="upcoming" <?= ($event['status']??'upcoming')==='upcoming'?'selected':'' ?>>Upcoming</option>
            <option value="open"     <?= ($event['status']??'')==='open'?'selected':'' ?>>Open (Accepting Registrations)</option>
            <option value="closed"   <?= ($event['status']??'')==='closed'?'selected':'' ?>>Closed</option>
          </select>
        </div>
      </div>

      <div>
        <label class="form-label" for="venue">Venue <span class="text-red-500">*</span></label>
        <input type="text" id="venue" name="venue" class="form-input"
               placeholder="e.g. Dewan Seri Budiman, UiTM Machang"
               value="<?= htmlspecialchars($event['venue'] ?? ($_POST['venue'] ?? '')) ?>"
               maxlength="200" required>
      </div>

      <div>
        <label class="form-label" for="description">Description</label>
        <textarea id="description" name="description" rows="4" class="form-input resize-none"
                  placeholder="Tell students what this event is about…"><?= htmlspecialchars($event['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
      </div>

      <!-- Banner image upload -->
      <div>
        <label class="form-label">
          Event Banner Image
          <span class="text-gray-400 font-normal text-xs ml-1">(optional — shown on event cards &amp; detail panel)</span>
        </label>

        <!-- Drop zone -->
        <div id="dropZone"
             class="mt-1 relative flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 hover:border-purple-400 hover:bg-purple-50 transition-colors cursor-pointer p-6 text-center"
             onclick="document.getElementById('bannerFile').click()"
             ondragover="handleDragOver(event)"
             ondragleave="handleDragLeave(event)"
             ondrop="handleDrop(event)">
          <i class="fas fa-cloud-arrow-up text-2xl text-gray-400" id="dropIcon"></i>
          <div>
            <p class="text-sm font-medium text-gray-700">Click to upload or drag &amp; drop</p>
            <p class="text-xs text-gray-400 mt-0.5">PNG, JPG, WEBP — recommended 1200 × 480 px, max 5 MB</p>
          </div>
          <input type="file" id="bannerFile" name="banner_image" accept="image/png,image/jpeg,image/webp,image/gif"
                 class="sr-only" onchange="previewUpload(this)">
        </div>

        <!-- Preview -->
        <div id="bannerPreview" class="mt-3 hidden relative">
          <img id="bannerPreviewImg" src="" alt="Banner preview"
               class="w-full h-40 object-cover rounded-xl border border-gray-200 shadow-sm">
          <button type="button" onclick="clearBanner()"
                  class="absolute top-2 right-2 bg-white/90 hover:bg-white text-gray-600 hover:text-red-600 rounded-lg w-7 h-7 flex items-center justify-center shadow transition-colors"
                  title="Remove image">
            <i class="fas fa-xmark text-xs"></i>
          </button>
        </div>

        <?php if (!empty($event['image_url'])): ?>
          <!-- Existing saved image (edit mode) -->
          <div id="existingBanner" class="mt-3">
            <p class="text-xs text-gray-500 mb-1">Current saved banner:</p>
            <div class="relative">
              <img src="<?= htmlspecialchars($event['image_url']) ?>" alt="Current banner"
                   class="w-full h-40 object-cover rounded-xl border border-gray-200 shadow-sm">
            </div>
            <input type="hidden" name="existing_image_url" value="<?= htmlspecialchars($event['image_url']) ?>">
            <p class="text-xs text-gray-400 mt-1">Upload a new image above to replace it.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Schedule & Capacity -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
      <h2 class="font-bold text-gray-800 flex items-center gap-2">
        <i class="fas fa-calendar-days text-sm" style="color:#582C83;"></i> Schedule & Capacity
      </h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
          <label class="form-label" for="start_date">Start Date & Time <span class="text-red-500">*</span></label>
          <input type="datetime-local" id="start_date" name="start_date" class="form-input"
                 value="<?= $event ? date('Y-m-d\TH:i', strtotime($event['start_date'])) : ($_POST['start_date'] ?? '') ?>"
                 required>
        </div>
        <div>
          <label class="form-label" for="end_date">End Date & Time</label>
          <input type="datetime-local" id="end_date" name="end_date" class="form-input"
                 value="<?= $event && $event['end_date'] ? date('Y-m-d\TH:i', strtotime($event['end_date'])) : ($_POST['end_date'] ?? '') ?>">
        </div>
      </div>

      <div>
        <label class="form-label" for="capacity">Max Capacity <span class="text-red-500">*</span></label>
        <input type="number" id="capacity" name="capacity" class="form-input"
               placeholder="e.g. 200"
               value="<?= htmlspecialchars($event['capacity'] ?? ($_POST['capacity'] ?? '')) ?>"
               min="1" max="5000" required>
        <p class="text-xs text-gray-400 mt-1">Maximum number of students who can register.</p>
      </div>
    </div>

    <!-- Payment Details -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
      <h2 class="font-bold text-gray-800 flex items-center gap-2">
        <i class="fas fa-credit-card text-sm" style="color:#582C83;"></i> Payment Details
      </h2>

      <!-- Toggle: free vs paid -->
      <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 border border-gray-200">
        <div>
          <p class="text-sm font-semibold text-gray-800">Paid Event</p>
          <p class="text-xs text-gray-500 mt-0.5">Enable to collect a registration fee from students.</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" id="isPaid" name="is_paid" value="1"
                 class="sr-only peer"
                 <?= ($event['is_paid'] ?? ($_POST['is_paid'] ?? '')) ? 'checked' : '' ?>
                 onchange="togglePayment(this.checked)">
          <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:bg-purple-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
        </label>
      </div>

      <!-- Payment fields (hidden when free) -->
      <div id="paymentFields" class="space-y-5 <?= ($event['is_paid'] ?? ($_POST['is_paid'] ?? '')) ? '' : 'hidden' ?>">

        <!-- Fee amount -->
        <div>
          <label class="form-label" for="fee_amount">Registration Fee (RM) <span class="text-red-500">*</span></label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-medium text-sm select-none">RM</span>
            <input type="number" id="fee_amount" name="fee_amount" class="form-input pl-10"
                   placeholder="0.00" step="0.01" min="0.01" max="9999.99"
                   value="<?= htmlspecialchars($event['fee_amount'] ?? ($_POST['fee_amount'] ?? '')) ?>">
          </div>
          <p class="text-xs text-gray-400 mt-1">Amount each student must pay to complete registration.</p>
        </div>

        <!-- Payment method -->
        <div>
          <label class="form-label">Accepted Payment Method <span class="text-red-500">*</span></label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-1">
            <?php
              $payMethods = ['Online Banking' => 'fa-building-columns', 'QR / DuitNow' => 'fa-qrcode', 'Cash' => 'fa-money-bill-wave', 'Other' => 'fa-ellipsis'];
              $savedMethod = $event['payment_method'] ?? ($_POST['payment_method'] ?? 'Online Banking');
            ?>
            <?php foreach ($payMethods as $method => $icon): ?>
              <label class="flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 cursor-pointer transition-all
                            <?= $savedMethod === $method ? 'border-purple-600 bg-purple-50' : 'border-gray-200 hover:border-purple-300' ?>">
                <input type="radio" name="payment_method" value="<?= $method ?>" class="sr-only"
                       <?= $savedMethod === $method ? 'checked' : '' ?>>
                <i class="fas <?= $icon ?> text-lg <?= $savedMethod === $method ? 'text-purple-600' : 'text-gray-400' ?>"></i>
                <span class="text-xs font-medium text-center <?= $savedMethod === $method ? 'text-purple-700' : 'text-gray-600' ?>"><?= $method ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Payment details / instructions -->
        <div>
          <label class="form-label" for="payment_details">Payment Instructions <span class="text-red-500">*</span></label>
          <textarea id="payment_details" name="payment_details" rows="4" class="form-input resize-none"
                    placeholder="e.g. Transfer to Maybank account 1234-5678-9012 (Ahmad Razif), then submit receipt via WhatsApp to 019-XXXXXXX. Reference: your full name."><?= htmlspecialchars($event['payment_details'] ?? ($_POST['payment_details'] ?? '')) ?></textarea>
          <p class="text-xs text-gray-400 mt-1">Shown to students after they register. Include account numbers, QR links, deadlines, or any proof-of-payment requirements.</p>
        </div>

        <!-- Payment deadline -->
        <div>
          <label class="form-label" for="payment_deadline">Payment Deadline</label>
          <input type="datetime-local" id="payment_deadline" name="payment_deadline" class="form-input"
                 value="<?= $event && !empty($event['payment_deadline']) ? date('Y-m-d\TH:i', strtotime($event['payment_deadline'])) : ($_POST['payment_deadline'] ?? '') ?>">
          <p class="text-xs text-gray-400 mt-1">Optional. Registrations not paid by this time may be cancelled.</p>
        </div>

      </div>
    </div>

    <!-- Submit -->
    <div class="flex items-center justify-end gap-3 pb-6">
      <a href="my_events.php" class="btn-secondary py-2.5 px-5">Cancel</a>
      <button type="submit" class="btn-primary py-2.5 px-6">
        <i class="fas <?= $editId ? 'fa-floppy-disk' : 'fa-plus-circle' ?>"></i>
        <?= $editId ? 'Save Changes' : 'Create Event' ?>
      </button>
    </div>

  </form>
</main>
</div>
<script>
/* ── Banner upload & preview ── */
function previewUpload(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  if (file.size > 5 * 1024 * 1024) {
    alert('Image must be under 5 MB.');
    input.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('bannerPreviewImg').src = e.target.result;
    document.getElementById('bannerPreview').classList.remove('hidden');
    document.getElementById('dropZone').classList.add('hidden');
  };
  reader.readAsDataURL(file);
}

function clearBanner() {
  document.getElementById('bannerFile').value = '';
  document.getElementById('bannerPreview').classList.add('hidden');
  document.getElementById('bannerPreviewImg').src = '';
  document.getElementById('dropZone').classList.remove('hidden');
}

function handleDragOver(e) {
  e.preventDefault();
  document.getElementById('dropZone').classList.add('border-purple-500','bg-purple-50');
}
function handleDragLeave(e) {
  document.getElementById('dropZone').classList.remove('border-purple-500','bg-purple-50');
}
function handleDrop(e) {
  e.preventDefault();
  handleDragLeave(e);
  const dt = e.dataTransfer;
  if (dt.files && dt.files[0]) {
    const input = document.getElementById('bannerFile');
    // Assign via DataTransfer to keep it in the form
    const dtp = new DataTransfer();
    dtp.items.add(dt.files[0]);
    input.files = dtp.files;
    previewUpload(input);
  }
}

/* ── Payment toggle ── */
function togglePayment(on) {
  const fields = document.getElementById('paymentFields');
  if (on) {
    fields.classList.remove('hidden');
    document.getElementById('fee_amount').required = true;
    document.getElementById('payment_details').required = true;
  } else {
    fields.classList.add('hidden');
    document.getElementById('fee_amount').required = false;
    document.getElementById('payment_details').required = false;
  }
}

/* ── Payment method card selector ── */
function selectPayMethod(selectedLabel) {
  // Reset all cards
  document.querySelectorAll('[name="payment_method"]').forEach(radio => {
    const lbl = radio.closest('label');
    lbl.classList.remove('border-purple-600', 'bg-purple-50');
    lbl.classList.add('border-gray-200');
    lbl.querySelector('i').classList.remove('text-purple-600');
    lbl.querySelector('i').classList.add('text-gray-400');
    lbl.querySelector('span').classList.remove('text-purple-700');
    lbl.querySelector('span').classList.add('text-gray-600');
  });
  // Activate selected card
  const radio = selectedLabel.querySelector('input[type="radio"]');
  radio.checked = true;
  selectedLabel.classList.add('border-purple-600', 'bg-purple-50');
  selectedLabel.classList.remove('border-gray-200');
  selectedLabel.querySelector('i').classList.remove('text-gray-400');
  selectedLabel.querySelector('i').classList.add('text-purple-600');
  selectedLabel.querySelector('span').classList.remove('text-gray-600');
  selectedLabel.querySelector('span').classList.add('text-purple-700');
}

// Attach via JS to avoid onclick/browser race condition
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[name="payment_method"]').forEach(radio => {
    radio.closest('label').addEventListener('click', function(e) {
      e.preventDefault();
      selectPayMethod(this);
    });
  });
});
</script>
</body>
</html>