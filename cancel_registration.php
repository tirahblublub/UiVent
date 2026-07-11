<?php
require_once __DIR__ . '/../config.php';
requireStudent();
header('Content-Type: application/json');

$regId = (int) ($_POST['reg_id'] ?? 0);
$sid   = (int) $_SESSION['student_id'];

if (!$regId) jsonResponse(false, 'Invalid request.');

// Verify ownership
$stmt = db()->prepare("SELECT id, status FROM registrations WHERE id = ? AND student_id = ? LIMIT 1");
$stmt->execute([$regId, $sid]);
$reg = $stmt->fetch();

if (!$reg)                        jsonResponse(false, 'Registration not found.');
if ($reg['status'] === 'cancelled') jsonResponse(false, 'Already cancelled.');

db()->prepare("UPDATE registrations SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?")
    ->execute([$regId]);

jsonResponse(true, 'Registration cancelled successfully.');
