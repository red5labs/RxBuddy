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

// Get search query
$query = $_GET['q'] ?? '';

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $lookup = new MedicationLookup();
    $results = $lookup->searchMedications($query, 10);
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'query' => $query
    ]);
    
} catch (Exception $e) {
    error_log('Medication search error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Search failed',
        'results' => []
    ]);
}
?> 