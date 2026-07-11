<?php
require_once __DIR__ . '/../config.php';
requireStudent();
header('Content-Type: application/json');

$eventId = (int) ($_POST['event_id'] ?? 0);
$sid     = (int) $_SESSION['student_id'];

// Debug log — remove after confirming fix
error_log('[UiVent] register attempt: event_id=' . $eventId . ' student_id=' . $sid);

if (!$eventId) jsonResponse(false, 'Invalid event.');

$pdo = db();

// Fetch event
$stmt = $pdo->prepare("SELECT id, title, capacity, registered_count, status, registration_fee, club_id FROM events WHERE id = ? LIMIT 1");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) jsonResponse(false, 'Event not found.');
if (in_array($event['status'], ['closed','cancelled','archived'], true)) jsonResponse(false, 'Registration is closed for this event.');

// Already registered?
$stmt = $pdo->prepare("SELECT id FROM registrations WHERE event_id = ? AND student_id = ? AND status = 'registered' LIMIT 1");
$stmt->execute([$eventId, $sid]);
if ($row = $stmt->fetch()) {
    $regId  = $row['id'];
    $ref    = 'UV-' . str_pad($regId, 6, '0', STR_PAD_LEFT);
    $qrData = "UiVent|{$ref}|{$event['title']}";

    // Fetch existing txn_id if any
    $txnStmt = $pdo->prepare("SELECT id FROM payment_transactions WHERE event_id = ? AND student_id = ? AND payment_status != 'Failed' LIMIT 1");
    $txnStmt->execute([$eventId, $sid]);
    $txnRow = $txnStmt->fetch();

    jsonResponse(true, 'You are already registered for this event.', [
        'already'         => true,
        'registration_id' => $regId,
        'event_title'     => $event['title'],
        'qr_url'          => $qrData,
        'ref'             => $ref,
        'txn_id'          => $txnRow ? (int)$txnRow['id'] : null,
        'fee'             => (float) $event['registration_fee'],
    ]);
}

// Capacity check
if ($event['capacity'] > 0 && $event['registered_count'] >= $event['capacity']) {
    jsonResponse(false, 'This event is full.');
}

// campus_id from session, fall back to 1 (UiTM Machang)
$campusId = (int) ($_SESSION['student']['campus_id'] ?? 1);

try {
    $pdo->prepare("
        INSERT INTO registrations (event_id, student_id, campus_id, status)
        VALUES (?, ?, ?, 'registered')
    ")->execute([$eventId, $sid, $campusId]);

    $regId = (int) $pdo->lastInsertId();
    error_log('[UiVent] registration inserted: reg_id=' . $regId);

    // If the event has a fee, create a pending payment transaction
    $txnId = null;
    if ($event['registration_fee'] > 0) {
        $pdo->prepare("
            INSERT INTO payment_transactions (student_id, event_id, registration_id, club_id, amount, payment_status)
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ")->execute([$sid, $eventId, $regId, (int)$event['club_id'], $event['registration_fee']]);
        $txnId = (int) $pdo->lastInsertId();
        error_log('[UiVent] payment_transaction inserted: txn_id=' . $txnId);
    }

} catch (\PDOException $ex) {
    if ($ex->getCode() === '23000') {
        jsonResponse(true, 'You are already registered.', ['already' => true]);
    }
    error_log('[UiVent] register_event PDO error: ' . $ex->getMessage());
    jsonResponse(false, 'Registration failed. Please try again.');
}

// Build QR payload — raw text for QRCodeJS
$ref    = 'UV-' . str_pad($regId, 6, '0', STR_PAD_LEFT);
$qrData = "UiVent|{$ref}|{$event['title']}";

jsonResponse(true, "Registered for \"{$event['title']}\" successfully!", [
    'already'         => false,
    'registration_id' => $regId,
    'event_title'     => $event['title'],
    'qr_url'          => $qrData,
    'ref'             => $ref,
    'txn_id'          => $txnId,
    'fee'             => (float) $event['registration_fee'],
]);