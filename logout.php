<?php
// ============================================================
//  logout.php — UiVent Unified Logout (All Roles)
//  Place this file in: htdocs/uivent/logout.php
// ============================================================
require_once __DIR__ . '/config.php';

// Destroy session cleanly
session_unset();
session_destroy();

// Redirect to login page
header('Location: /uivent/index.php');
exit;