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
$medication_id = sanitizeInput($data['medication_id'] ?? '', 'int');

if (!$medication_id || !validateInteger($medication_id, 1)) {
    logSecurityEvent('Invalid medication ID in unarchive', "ID: $medication_id");
    echo json_encode(['success' => false, 'message' => 'Invalid medication ID.']);
    exit;
}

// Check that medication belongs to user and is currently archived
$med = fetchOne('SELECT * FROM medications WHERE id = ? AND user_id = ? AND is_active = 0', [$medication_id, $_SESSION['user_id']]);
if (!$med) {
    echo json_encode(['success' => false, 'message' => 'Medication not found or already active.']);
    exit;
}

try {
    executeQuery('UPDATE medications SET is_active = 1 WHERE id = ? AND user_id = ?', [$medication_id, $_SESSION['user_id']]);
    logSecurityEvent('Medication unarchived', "User ID: {$_SESSION['user_id']}, Medication ID: $medication_id");
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    logSecurityEvent('Medication unarchive failed', $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to unarchive medication.']);
} 