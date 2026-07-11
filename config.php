<?php
// ============================================================
//  config.php — Database connection & shared helpers
//  Place this file in your project root (same folder as pages)
//  Access at: http://localhost/uivent/config.php  (not directly)
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // ← change to your MySQL username
define('DB_PASS', '');             // ← change to your MySQL password
define('DB_NAME', 'uivent_db');
define('DB_CHARSET', 'utf8mb4');

// ── PDO connection (singleton) ──────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── Session helpers ──────────────────────────────────────────
session_start();

// ── Super Admin helpers ──────────────────────────────────────
function requireSuperAdmin(): void {
    if (empty($_SESSION['super_admin_id'])) {
        header('Location: index_sa.php');
        exit;
    }
}

function currentAdmin(): array {
    return $_SESSION['super_admin'] ?? ['name' => 'Super Admin', 'email' => ''];
}

// ── Student helpers ──────────────────────────────────────────
function requireUser(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
        header('Location: /UiVent/index.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}


// ── Student helpers (student portal) ────────────────────────
function requireStudent(): void {
    if (empty($_SESSION['student_id']) || empty($_SESSION['student'])) {
        header('Location: /UiVent/index.php');
        exit;
    }
}

function currentStudent(): array {
    return $_SESSION['student'] ?? [];
}
// ── Club Admin helpers ────────────────────────────────────────
function requireAdmin(): void {
    if (empty($_SESSION['admin_id']) || empty($_SESSION['admin'])) {
        header('Location: /UiVent/index.php');
        exit;
    }
}

function currentClubAdmin(): array {
    return $_SESSION['admin'] ?? [];
}

// ── Audit logger (Super Admin) ───────────────────────────────
function logAction(string $action, string $target = '', ?int $campusId = null): void {
    $admin = currentAdmin();
    $ip    = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt  = db()->prepare(
        'INSERT INTO audit_log (actor_name, actor_type, action, target, campus_id, ip_address)
         VALUES (:actor, "super_admin", :action, :target, :campus, :ip)'
    );
    $stmt->execute([
        'actor'  => $admin['name'],
        'action' => strtoupper($action),
        'target' => $target,
        'campus' => $campusId,
        'ip'     => substr($ip, 0, 45),
    ]);
}

// ── Audit logger (Club Admin) ────────────────────────────────
function logAdminAction(string $action, string $target = '', ?int $campusId = null): void {
    $admin = currentClubAdmin();
    $ip    = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt  = db()->prepare(
        'INSERT INTO audit_log (actor_name, actor_type, action, target, campus_id, ip_address)
         VALUES (:actor, "admin", :action, :target, :campus, :ip)'
    );
    $stmt->execute([
        'actor'  => $admin['name'] ?? 'Club Admin',
        'action' => strtoupper($action),
        'target' => $target,
        'campus' => $campusId,
        'ip'     => substr($ip, 0, 45),
    ]);
}

// ── JSON response helper ─────────────────────────────────────
function jsonResponse(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// ── Password hash helper (run once in setup) ─────────────────
// echo password_hash('admin123', PASSWORD_BCRYPT);
?>