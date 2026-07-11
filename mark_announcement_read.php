<?php
require_once __DIR__ . '/../config.php';
requireUser();
header('Content-Type: application/json');

$annId  = (int) ($_POST['announcement_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
if (!$annId) jsonResponse(false, 'Invalid ID.');

try {
    db()->prepare("INSERT IGNORE INTO announcement_reads (user_id, announcement_id) VALUES (?,?)")
       ->execute([$userId, $annId]);
    jsonResponse(true, 'Marked as read.');
} catch (\Throwable $e) {
    jsonResponse(false, 'Error.');
}
