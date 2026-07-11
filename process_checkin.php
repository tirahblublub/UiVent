<?php
require_once __DIR__ . '/../config.php';
requireStudent();
header('Content-Type: application/json');

$sid    = (int) $_SESSION['student_id'];
$qrData = trim($_POST['qr_data'] ?? '');

if (!$qrData) jsonResponse(false, 'No QR data received.');

// Expected format: UiVent|Event Title|UV-XXXXXX|Venue|Date
$parts = explode('|', $qrData);
if (count($parts) < 3 || $parts[0] !== 'UiVent') {
    jsonResponse(false, 'Invalid QR code — not a UiVent ticket.');
}

// Extract reg_id from ref like "UV-000123"
$ref   = trim($parts[2]);
$regId = (int) ltrim(str_replace('UV-', '', $ref), '0');

if (!$regId) jsonResponse(false, 'Could not parse ticket reference.');

// Verify ownership
$stmt = db()->prepare("SELECT id, status, attendance_status FROM registrations WHERE id = ? AND student_id = ? LIMIT 1");
$stmt->execute([$regId, $sid]);
$reg = $stmt->fetch();

if (!$reg)                                   jsonResponse(false, 'Ticket not found or does not belong to your account.');
if ($reg['status'] === 'cancelled')          jsonResponse(false, 'This registration has been cancelled.');
if ($reg['attendance_status'] === 'attended') jsonResponse(false, 'You have already checked in to this event.');

// Mark attended
db()->prepare("UPDATE registrations SET attendance_status='attended', attended_at=NOW(), status='attended' WHERE id=?")
   ->execute([$regId]);

jsonResponse(true, 'Checked in successfully! Your attendance has been recorded.');
