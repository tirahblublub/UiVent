<?php
define('TOYYIB_SANDBOX', true);
define('TOYYIB_SECRET_KEY', 'c3v7f6q1-zl6y-9rc4-copo-1idgo4a31ro5');
define('TOYYIB_CATEGORY_CODE_EVENT',       'syo7cbx3');
define('TOYYIB_CATEGORY_CODE_MERCHANDISE', '8c0kytur');
define('TOYYIB_BASE_URL', TOYYIB_SANDBOX
    ? 'https://dev.toyyibpay.com'
    : 'https://toyyibpay.com'
);

// ── APP BASE URL ───────────────────────────────────────────────────────────
//
// LOCALHOST (no ngrok):
//   ToyyibPay CANNOT reach your callback URL, so toyyib_callback.php
//   will never fire. This is fine — toyyib_return.php now force-updates
//   the DB when status_id=1, so payment still gets marked as Paid.
//   Leave as localhost below.
//
// PRODUCTION / ngrok:
//   Replace with your public URL so ToyyibPay's server can hit the callback.
//   e.g. define('APP_BASE_URL', 'https://xxxx.ngrok-free.app/UiVent');
//   e.g. define('APP_BASE_URL', 'https://yourdomain.com/UiVent');
//
// ─────────────────────────────────────────────────────────────────────────
define('APP_BASE_URL', 'http://localhost/UiVent');

define('TOYYIB_RETURN_URL',   APP_BASE_URL . '/toyyib_return.php');
define('TOYYIB_CALLBACK_URL', APP_BASE_URL . '/toyyib_callback.php');