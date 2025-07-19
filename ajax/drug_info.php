<?php
require_once '../includes/db.php';
require_once '../includes/validation.php';
require_once '../includes/medication_lookup.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get drug name
$drug_name = $_GET['drug'] ?? '';

if (empty($drug_name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Drug name is required']);
    exit;
}

try {
    $lookup = new MedicationLookup();
    $drug_info = $lookup->getDrugInfo($drug_name);
    
    if ($drug_info) {
        echo json_encode([
            'success' => true,
            'drug_info' => $drug_info
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Drug information not found',
            'drug_info' => null
        ]);
    }
    
} catch (Exception $e) {
    error_log('Drug info error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve drug information',
        'drug_info' => null
    ]);
}
?> 