<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate CSRF token (for AJAX, we'll use a different approach since no form token)
// In a production app, you might want to implement AJAX-specific CSRF protection
$medication_id = sanitizeInput($data['medication_id'] ?? '', 'int');
$note = sanitizeInput($data['note'] ?? '', 'string');

if (!$medication_id || !validateInteger($medication_id, 1)) {
    logSecurityEvent('Invalid medication ID in mark-taken', "ID: $medication_id");
    echo json_encode(['success' => false, 'message' => 'Invalid medication ID.']);
    exit;
}

// Validate note length
if (!empty($note) && !validateNotes($note)) {
    echo json_encode(['success' => false, 'message' => 'Note is too long.']);
    exit;
}

// Check that medication belongs to user
$med = fetchOne('SELECT * FROM medications WHERE id = ? AND user_id = ?', [$medication_id, $_SESSION['user_id']]);
if (!$med) {
    echo json_encode(['success' => false, 'message' => 'Medication not found.']);
    exit;
}

// Log the dose
try {
    executeQuery('INSERT INTO logs (user_id, medication_id, taken_at, method, notes) VALUES (?, ?, NOW(), ?, ?)', [$_SESSION['user_id'], $medication_id, 'manual', $note]);
    logSecurityEvent('Dose logged', "User ID: {$_SESSION['user_id']}, Medication ID: $medication_id");
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    logSecurityEvent('Dose logging failed', $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to log dose.']);
} 