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

// Delete all logs for the user (via JOIN with medications table)
try {
    $result = executeQuery('
        DELETE l FROM logs l 
        JOIN medications m ON l.medication_id = m.id 
        WHERE m.user_id = ?
    ', [$_SESSION['user_id']]);
    
    logSecurityEvent('Logs purged', "User ID: {$_SESSION['user_id']}");
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    logSecurityEvent('Log purge failed', $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to purge logs.']);
} 