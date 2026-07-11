<?php
require_once '../config.php';
requireAdmin();

$activePage = 'merchandise';
$pageTitle  = 'Merchandise & Payments';
$adminId    = $_SESSION['admin_id'];
$errors     = [];

// ── CSRF token ────────────────────────────────────────────────
// FIX: Generate and store a CSRF token for all POST actions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ── Helper: validate CSRF ─────────────────────────────────────
function validateCsrf(): void {
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        jsonResponse(false, 'Invalid or expired request. Please refresh the page.');
    }
}

// ── AJAX: Delete merch ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_merch') {
    validateCsrf(); // FIX: CSRF check added
    $id = (int)$_POST['id'];
    db()->prepare("DELETE FROM merchandise WHERE id=? AND admin_id=?")->execute([$id, $adminId]);
    jsonResponse(true, 'Item deleted.');
}

// ── AJAX: Update order status ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_order') {
    validateCsrf(); // FIX: CSRF check added
    $orderId = (int)$_POST['order_id'];
    $status  = $_POST['status'] ?? '';
    if (!in_array($status, ['pending','paid','cancelled','refunded'])) jsonResponse(false, 'Invalid status.');

    // FIX: Preserve original paid_at if order was already paid; don't overwrite with current time
    if ($status === 'paid') {
        $existing = db()->prepare("SELECT paid_at FROM merch_orders WHERE id=? AND admin_id=?")->execute([$orderId, $adminId]);
        $row = $existing->fetch();
        $paidAt = $row && $row['paid_at'] ? $row['paid_at'] : date('Y-m-d H:i:s');
    } else {
        $paidAt = null;
    }

    db()->prepare("UPDATE merch_orders SET status=?, paid_at=? WHERE id=? AND admin_id=?")
       ->execute([$status, $paidAt, $orderId, $adminId]);
    jsonResponse(true, 'Order updated.');
}

// ── Handle create/edit merch ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create_merch','edit_merch'])) {
    validateCsrf(); // FIX: CSRF check added
    $action  = $_POST['action'];
    $name    = trim($_POST['name'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $price   = (float)($_POST['price'] ?? 0);
    $stock   = (int)($_POST['stock'] ?? 0);
    $eventId  = (int)($_POST['event_id'] ?? 0) ?: null;
    $category = in_array($_POST['category'] ?? '', ['Apparel','Accessories','Stationery','Other']) ? $_POST['category'] : 'Other';

    if (!$name)     $errors[] = 'Item name is required.';
    if ($price < 0) $errors[] = 'Price must be 0 or more.';
    if ($stock < 0) $errors[] = 'Stock must be 0 or more.';

    // ── Image upload ──────────────────────────────────────────
    $imageUrl = trim($_POST['existing_image'] ?? '');
    if (!empty($_FILES['merch_image']['name'])) {
        $file    = $_FILES['merch_image'];
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        $maxSize = 3 * 1024 * 1024;
        if (!in_array($file['type'], $allowed)) {
            $errors[] = 'Image must be JPG, PNG, WebP or GIF.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'Image must be under 3 MB.';
        } else {
            $uploadDir = __DIR__ . '/uploads/merch/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'merch_' . $adminId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                // Delete old image if replacing
                if ($imageUrl && file_exists(__DIR__ . '/' . ltrim($imageUrl, '/'))) {
                    @unlink(__DIR__ . '/' . ltrim($imageUrl, '/'));
                }
                // FIX: Store as root-relative path so it resolves correctly in the browser
                $imageUrl = 'admin/uploads/merch/' . $filename;
            } else {
                $errors[] = 'Failed to save image. Check folder permissions (should be 775).';
            }
        }
    }

    if (empty($errors)) {
        if ($action === 'create_merch') {
            db()->prepare("INSERT INTO merchandise (admin_id, event_id, name, description, price, stock, image_url, category, is_active) VALUES (?,?,?,?,?,?,?,?,1)")
               ->execute([$adminId, $eventId, $name, $desc, $price, $stock, $imageUrl ?: null, $category]);
        } else {
            $editId = (int)$_POST['edit_id'];
            db()->prepare("UPDATE merchandise SET name=?,description=?,price=?,stock=?,event_id=?,image_url=?,category=? WHERE id=? AND admin_id=?")
               ->execute([$name, $desc, $price, $stock, $eventId, $imageUrl ?: null, $category, $editId, $adminId]);
        }
        header('Location: merchandise.php?tab=merch&msg=saved');
        exit;
    }
}

// ── Fetch merchandise ─────────────────────────────────────────
$merch = db()->prepare("
    SELECT m.id, m.name, m.description, m.price, m.stock, m.image_url, m.category,
           m.event_id,
           e.title AS event_title,
           COUNT(o.id) AS total_orders,
           SUM(CASE WHEN o.status='paid' THEN 1 ELSE 0 END) AS paid_orders,
           SUM(CASE WHEN o.status='paid' THEN o.total_price ELSE 0 END) AS revenue
    FROM merchandise m
    LEFT JOIN events e ON e.id = m.event_id
    LEFT JOIN merch_orders o ON o.merch_id = m.id
    WHERE m.admin_id = ?
    GROUP BY m.id
    ORDER BY m.created_at DESC
");
$merch->execute([$adminId]);
$merch = $merch->fetchAll();

// ── Fetch orders ──────────────────────────────────────────────
$orders = db()->prepare("
    SELECT o.*, m.name AS merch_name, s.name AS student_name, s.matric_no
    FROM merch_orders o
    JOIN merchandise m ON m.id = o.merch_id
    JOIN students s ON s.id = o.student_id
    WHERE o.admin_id = ?
    ORDER BY o.ordered_at DESC
    LIMIT 100
");
$orders->execute([$adminId]);
$orders = $orders->fetchAll();

// ── Summary ───────────────────────────────────────────────────
$totalRevenue  = array_sum(array_column($merch, 'revenue'));
$totalOrders   = array_sum(array_column($merch, 'total_orders'));
$pendingOrders = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));

// Events dropdown
$evStmt = db()->prepare("SELECT id, title FROM events WHERE created_by=? ORDER BY start_date DESC");
$evStmt->execute([$adminId]);
$myEvents = $evStmt->fetchAll();

$activeTab = $_GET['tab'] ?? 'merch';

// FIX: Build the upload base URL once from config, used in both PHP and JS
// Adjust BASE_URL to match your config.php constant name if different
$uploadBaseUrl = rtrim(defined('BASE_URL') ? BASE_URL : '/UiVent/', '/') . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UiVent | Merchandise</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'partials/head_styles.php'; ?>
<style>
  .form-input { width:100%; padding:.625rem .875rem; border:1px solid #e5e7eb; border-radius:.5rem; font-size:.875rem; color:#374151; transition:border-color .15s; outline:none; }
  .form-input:focus { border-color:#582C83; box-shadow:0 0 0 3px rgba(88,44,131,.12); }
  .form-label { display:block; font-size:.75rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.375rem; }
  .tab-btn { padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; transition:all .15s; }
  .tab-btn.active { background:#582C83; color:#fff; }
  .tab-btn:not(.active) { color:#582C83; background:#f0ebfa; }
  .status-pending   { background:#FEF3C7; color:#92400E; }
  .status-paid      { background:#D1FAE5; color:#065F46; }
  .status-cancelled { background:#FEE2E2; color:#991B1B; }
  .status-refunded  { background:#E0E7FF; color:#3730A3; }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 flex h-screen overflow-hidden">
<?php include 'partials/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-y-auto">
<?php include 'partials/topbar.php'; ?>
<main class="p-6 md:p-8 space-y-6 max-w-7xl w-full mx-auto">

  <?php if (isset($_GET['msg'])): ?>
  <div class="flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-medium bg-green-50 text-green-800 border border-green-200">
    <i class="fas fa-check-circle"></i> Saved successfully!
  </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
  <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-5 py-4 text-sm space-y-1">
    <?php foreach ($errors as $e): ?>
      <p class="flex items-start gap-2"><i class="fas fa-circle-exclamation mt-0.5"></i><?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Header -->
  <div class="flex items-center justify-between flex-wrap gap-4">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Merchandise & Payments</h1>
      <p class="text-sm text-gray-500 mt-0.5">Manage club merchandise and track payments.</p>
    </div>
    <button onclick="document.getElementById('merchAction').value='create_merch'; document.getElementById('merchForm').classList.add('hidden'); toggleMerchForm(true);" class="btn-primary flex items-center gap-2">
      <i class="fas fa-plus"></i> Add Item
    </button>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <?php $cards = [
      ['label'=>'Total Items',    'val'=>count($merch),                       'icon'=>'fa-shirt',          'color'=>'#582C83','bg'=>'#f0ebfa'],
      ['label'=>'Total Orders',   'val'=>$totalOrders,                        'icon'=>'fa-bag-shopping',   'color'=>'#0284c7','bg'=>'#e0f2fe'],
      ['label'=>'Pending Orders', 'val'=>$pendingOrders,                      'icon'=>'fa-clock',          'color'=>'#d97706','bg'=>'#fef3c7'],
      ['label'=>'Total Revenue',  'val'=>'RM '.number_format($totalRevenue,2),'icon'=>'fa-money-bill-wave','color'=>'#059669','bg'=>'#d1fae5'],
    ];
    foreach ($cards as $c): ?>
    <div class="stat-card bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;">
        <i class="fas <?= $c['icon'] ?>"></i>
      </div>
      <div>
        <p class="text-lg font-extrabold text-gray-900"><?= $c['val'] ?></p>
        <p class="text-xs font-semibold text-gray-400"><?= $c['label'] ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Add/Edit Merch Form -->
  <div id="merchForm" class="hidden bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h2 class="font-bold text-gray-800 mb-5 flex items-center gap-2">
      <i class="fas fa-shirt text-sm" style="color:#582C83;"></i>
      <span id="merchFormTitle">Add Merchandise Item</span>
    </h2>
    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-2 gap-5">
      <!-- FIX: CSRF token hidden field -->
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="action" id="merchAction" value="create_merch">
      <input type="hidden" name="edit_id" id="merchEditId" value="">
      <div>
        <label class="form-label">Item Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" id="merchName" class="form-input" placeholder="e.g. Club T-Shirt" required>
      </div>
      <div>
        <label class="form-label">Price (RM) <span class="text-red-500">*</span></label>
        <input type="number" name="price" id="merchPrice" class="form-input" placeholder="0.00" min="0" step="0.01">
      </div>
      <div>
        <label class="form-label">Stock Quantity</label>
        <input type="number" name="stock" id="merchStock" class="form-input" placeholder="0" min="0">
      </div>
      <div>
        <label class="form-label">Link to Event (optional)</label>
        <select name="event_id" id="merchEventId" class="form-input bg-white">
          <option value="">— No specific event —</option>
          <?php foreach ($myEvents as $ev): ?>
            <option value="<?= $ev['id'] ?>"><?= htmlspecialchars($ev['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Category</label>
        <select name="category" id="merchCategory" class="form-input bg-white">
          <option value="Apparel">Apparel</option>
          <option value="Accessories">Accessories</option>
          <option value="Stationery">Stationery</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="sm:col-span-2">
        <label class="form-label">Description</label>
        <textarea name="description" id="merchDesc" rows="2" class="form-input resize-none" placeholder="Brief description of the item…"></textarea>
      </div>
      <div class="sm:col-span-2">
        <label class="form-label">Item Image <span class="text-gray-400 font-normal normal-case">(JPG, PNG, WebP · max 3 MB)</span></label>
        <input type="hidden" name="existing_image" id="merchExistingImage" value="">
        <div class="flex items-start gap-4">
          <label for="merchImageInput" id="merchImageLabel"
            class="flex flex-col items-center justify-center w-32 h-32 rounded-xl border-2 border-dashed border-gray-200 cursor-pointer hover:border-purple-400 transition-colors shrink-0 overflow-hidden bg-gray-50"
            style="position:relative;">
            <img id="merchImagePreview" src="" alt="" class="hidden w-full h-full object-cover absolute inset-0">
            <div id="merchImagePlaceholder" class="flex flex-col items-center gap-1 text-gray-400">
              <i class="fas fa-cloud-arrow-up text-2xl"></i>
              <span class="text-xs font-medium">Upload photo</span>
            </div>
            <input type="file" name="merch_image" id="merchImageInput" accept="image/*" class="hidden" onchange="previewMerchImage(this)">
          </label>
          <div class="text-xs text-gray-400 pt-2 space-y-1">
            <p>Upload a clear photo of the item.</p>
            <p>Recommended: square image, at least 400×400 px.</p>
            <p>This image will appear on the user-facing shop.</p>
            <button type="button" onclick="clearMerchImage()" id="clearImageBtn" class="hidden mt-2 text-red-500 hover:text-red-700 font-semibold">
              <i class="fas fa-times mr-1"></i> Remove image
            </button>
          </div>
        </div>
      </div>
      <div class="sm:col-span-2 flex gap-3">
        <button type="submit" class="btn-primary flex items-center gap-2">
          <i class="fas fa-floppy-disk"></i> <span id="merchSubmitLabel">Add Item</span>
        </button>
        <button type="button" onclick="toggleMerchForm(false)" class="btn-secondary">Cancel</button>
      </div>
    </form>
  </div>

  <!-- Tabs -->
  <div class="flex gap-2">
    <button class="tab-btn <?= $activeTab==='merch'?'active':'' ?>" onclick="switchTab('merch',this)">
      <i class="fas fa-shirt mr-1"></i> Items
    </button>
    <button class="tab-btn <?= $activeTab==='orders'?'active':'' ?>" onclick="switchTab('orders',this)">
      <i class="fas fa-bag-shopping mr-1"></i> Orders
      <?php if ($pendingOrders > 0): ?>
        <span class="ml-1 bg-amber-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full"><?= $pendingOrders ?></span>
      <?php endif; ?>
    </button>
  </div>

  <!-- Merch Items Tab -->
  <div id="tab-merch" class="<?= $activeTab!=='merch'?'hidden':'' ?>">
    <?php if (empty($merch)): ?>
    <div class="bg-white rounded-xl border border-gray-100 p-16 text-center text-gray-400">
      <i class="fas fa-shirt text-4xl mb-3 block" style="color:#D1BBF0;"></i>
      <p class="font-semibold text-gray-600">No merchandise items yet.</p>
      <p class="text-xs mt-1">Click "Add Item" to create your first item.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($merch as $m):
        // Build the JSON payload as a PHP variable FIRST — keeps the HTML attribute on one clean line.
        // htmlspecialchars encodes &, ", <, > so the value is safe inside an HTML attribute.
        $merchJson = htmlspecialchars(json_encode([
          'id'          => $m['id']          ?? 0,
          'name'        => $m['name']        ?? '',
          'description' => $m['description'] ?? '',
          'price'       => $m['price']       ?? 0,
          'stock'       => $m['stock']       ?? 0,
          'event_id'    => $m['event_id']    ?? null,   // was missing from SELECT — now included
          'image_url'   => $m['image_url']   ?? '',
          'category'    => $m['category']    ?? 'Other',
        ]), ENT_QUOTES, 'UTF-8');
        $merchName   = htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8');
        $imgSrc      = '/'. ltrim($m['image_url'] ?? '', '/');
        $imgAlt      = htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8');
      ?>
      <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-start justify-between mb-3">
          <?php if (!empty($m['image_url'])): ?>
          <div class="w-16 h-16 rounded-xl overflow-hidden border border-gray-100 shrink-0">
            <img src="<?= $imgSrc ?>" alt="<?= $imgAlt ?>" class="w-full h-full object-cover"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <div style="display:none;background:#f0ebfa;color:#582C83;" class="w-full h-full items-center justify-center text-2xl">
              <i class="fas fa-shirt"></i>
            </div>
          </div>
          <?php else: ?>
          <div class="w-16 h-16 rounded-xl flex items-center justify-center text-2xl shrink-0" style="background:#f0ebfa;color:#582C83;">
            <i class="fas fa-shirt"></i>
          </div>
          <?php endif; ?>
          <div class="flex gap-1">
            <button type="button" data-merch="<?= $merchJson ?>" class="merch-edit-btn w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(88,44,131,0.1);color:#582C83;"><i class="fas fa-pen text-xs"></i></button>
            <button type="button" data-id="<?= (int)$m['id'] ?>" data-name="<?= $merchName ?>" class="merch-delete-btn w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(239,68,68,0.1);color:#991B1B;"><i class="fas fa-trash-can text-xs"></i></button>
          </div>
        </div>
        <h3 class="font-bold text-gray-800"><?= htmlspecialchars($m['name']) ?></h3>
        <?php if ($m['description']): ?>
          <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($m['description']) ?></p>
        <?php endif; ?>
        <?php if ($m['event_title']): ?>
          <p class="text-xs mt-1 font-medium" style="color:#582C83;"><i class="fas fa-calendar-alt mr-1"></i><?= htmlspecialchars($m['event_title']) ?></p>
        <?php endif; ?>
        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
          <div>
            <p class="text-xl font-extrabold" style="color:#582C83;">RM <?= number_format($m['price'], 2) ?></p>
            <p class="text-xs text-gray-400"><?= (int)$m['stock'] ?> in stock</p>
          </div>
          <div class="text-right">
            <p class="text-sm font-bold text-gray-800"><?= (int)$m['paid_orders'] ?> sold</p>
            <p class="text-xs text-emerald-600 font-semibold">RM <?= number_format($m['revenue'], 2) ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Orders Tab -->
  <div id="tab-orders" class="<?= $activeTab!=='orders'?'hidden':'' ?>">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-800 text-sm">Orders</h2>
        <span class="text-xs text-gray-400"><?= count($orders) ?> orders</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Student</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Item</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php if (empty($orders)): ?>
            <tr><td colspan="6" class="px-6 py-12 text-center text-gray-400">
              <i class="fas fa-bag-shopping text-3xl mb-3 block" style="color:#D1BBF0;"></i>
              No orders yet.
            </td></tr>
            <?php else: foreach ($orders as $o): ?>
            <tr class="hover-row" id="order-<?= (int)$o['id'] ?>">
              <td class="px-6 py-4">
                <p class="font-semibold text-gray-800"><?= htmlspecialchars($o['student_name']) ?></p>
                <p class="text-xs text-gray-400"><?= htmlspecialchars($o['matric_no']) ?></p>
              </td>
              <td class="px-4 py-4 text-gray-700"><?= htmlspecialchars($o['merch_name']) ?></td>
              <td class="px-4 py-4 text-center text-gray-700"><?= (int)$o['quantity'] ?></td>
              <td class="px-4 py-4 text-center font-bold" style="color:#582C83;">RM <?= number_format($o['total_price'],2) ?></td>
              <td class="px-4 py-4 text-center">
                <span class="badge text-xs font-bold px-2.5 py-1 rounded-full status-<?= htmlspecialchars($o['status']) ?>">
                  <?= ucfirst(htmlspecialchars($o['status'])) ?>
                </span>
              </td>
              <td class="px-4 py-4 text-center">
                <select onchange='updateOrder(<?= (int)$o["id"] ?>, this.value)'
                  class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white text-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-300">
                  <option value="pending"   <?= $o['status']==='pending'   ?'selected':'' ?>>Pending</option>
                  <option value="paid"      <?= $o['status']==='paid'      ?'selected':'' ?>>Paid</option>
                  <option value="cancelled" <?= $o['status']==='cancelled' ?'selected':'' ?>>Cancelled</option>
                  <option value="refunded"  <?= $o['status']==='refunded'  ?'selected':'' ?>>Refunded</option>
                </select>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</main>
</div>

<?php include 'partials/modals_js.php'; ?>
<script>
// FIX: Single source of truth for upload base URL, derived from PHP
// Images stored as "admin/uploads/merch/..." are served from site root
const UPLOAD_BASE = '/';
const CSRF_TOKEN  = <?= json_encode($csrfToken) ?>;

// ── Tabs ──────────────────────────────────────────────────────
function switchTab(name, btn) {
  document.querySelectorAll('[id^="tab-"]').forEach(t => t.classList.add('hidden'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.remove('hidden');
  btn.classList.add('active');
}

// ── Merch form ────────────────────────────────────────────────
function toggleMerchForm(show) {
  const f = document.getElementById('merchForm');
  if (show === undefined) show = f.classList.contains('hidden');
  f.classList.toggle('hidden', !show);
  if (show) {
    // Only reset to blank "create" state when explicitly opening for a new item
    // editMerch() handles its own field population and calls f.classList.remove('hidden') directly
    if (document.getElementById('merchAction').value === 'create_merch') {
      document.getElementById('merchName').value              = '';
      document.getElementById('merchPrice').value             = '';
      document.getElementById('merchStock').value             = '';
      document.getElementById('merchDesc').value              = '';
      document.getElementById('merchEventId').value           = '';
      document.getElementById('merchCategory').value          = 'Apparel';
      document.getElementById('merchEditId').value            = '';
      document.getElementById('merchFormTitle').textContent   = 'Add Merchandise Item';
      document.getElementById('merchSubmitLabel').textContent = 'Add Item';
      clearMerchImage();
    }
    setTimeout(() => f.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
  }
}

function editMerch(m) {
  // Populate ALL fields first, then open — prevents stale state if form was already open
  document.getElementById('merchAction').value            = 'edit_merch';
  document.getElementById('merchEditId').value            = m.id;
  document.getElementById('merchName').value              = m.name;
  document.getElementById('merchPrice').value             = m.price;
  document.getElementById('merchStock').value             = m.stock;
  document.getElementById('merchDesc').value              = m.description || '';
  document.getElementById('merchEventId').value           = m.event_id || '';
  document.getElementById('merchCategory').value          = m.category || 'Other';
  document.getElementById('merchFormTitle').textContent   = 'Edit Item';
  document.getElementById('merchSubmitLabel').textContent = 'Save Changes';
  document.getElementById('merchExistingImage').value     = m.image_url || '';

  const preview     = document.getElementById('merchImagePreview');
  const placeholder = document.getElementById('merchImagePlaceholder');
  const clearBtn    = document.getElementById('clearImageBtn');

  if (m.image_url) {
    preview.src = UPLOAD_BASE + m.image_url.replace(/^\/+/, '');
    preview.classList.remove('hidden');
    placeholder.classList.add('hidden');
    clearBtn.classList.remove('hidden');
  } else {
    preview.src = '';
    preview.classList.add('hidden');
    placeholder.classList.remove('hidden');
    clearBtn.classList.add('hidden');
  }

  // Force-show the form (always open for edit, never toggle)
  const f = document.getElementById('merchForm');
  f.classList.remove('hidden');
  // Small delay lets DOM paint before scrolling so fields are visible
  setTimeout(() => f.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
}

// ── Edit & Delete via event delegation (no inline onclick JSON) ───
// Reads data from data-merch / data-id / data-name attributes — immune to
// special characters in names, descriptions, or image paths.
document.addEventListener('click', function(e) {
  // Edit button
  const editBtn = e.target.closest('.merch-edit-btn');
  if (editBtn) {
    const m = JSON.parse(editBtn.dataset.merch);
    editMerch(m);
    return;
  }
  // Delete button
  const delBtn = e.target.closest('.merch-delete-btn');
  if (delBtn) {
    const id   = delBtn.dataset.id;
    const name = delBtn.dataset.name;
    deleteMerch(id, name);
  }
});

function deleteMerch(id, name) {
  openConfirm('del', 'Delete Item', `Delete "${name}"? This cannot be undone.`, 'Delete', 'red', () => {
    const fd = new FormData();
    fd.append('action',     'delete_merch');
    fd.append('id',         id);
    fd.append('csrf_token', CSRF_TOKEN);
    fetch('merchandise.php', {method:'POST', body:fd})
      .then(r => r.json())
      .then(d => { showToast(d.message, !d.success); if (d.success) setTimeout(() => location.reload(), 800); });
  });
}

function updateOrder(orderId, status) {
  const fd = new FormData();
  fd.append('action',     'update_order');
  fd.append('order_id',   orderId);
  fd.append('status',     status);
  fd.append('csrf_token', CSRF_TOKEN); // FIX: attach CSRF token to AJAX update
  fetch('merchandise.php', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => showToast(d.message, !d.success))
    .catch(() => showToast('Network error.', true));
}

function previewMerchImage(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const preview = document.getElementById('merchImagePreview');
    preview.src = e.target.result;
    preview.classList.remove('hidden');
    document.getElementById('merchImagePlaceholder').classList.add('hidden');
    document.getElementById('clearImageBtn').classList.remove('hidden');
  };
  reader.readAsDataURL(input.files[0]);
}

function clearMerchImage() {
  document.getElementById('merchImageInput').value    = '';
  document.getElementById('merchExistingImage').value = '';
  const preview = document.getElementById('merchImagePreview');
  preview.src = '';
  preview.classList.add('hidden');
  document.getElementById('merchImagePlaceholder').classList.remove('hidden');
  document.getElementById('clearImageBtn').classList.add('hidden');
}
</script>
</body>
</html>