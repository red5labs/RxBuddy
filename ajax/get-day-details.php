<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/calendar.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['date'])) {
    echo json_encode(['success' => false, 'message' => 'Date parameter required']);
    exit;
}

$date = sanitizeInput($input['date'], 'string');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

try {
    // Initialize calendar
    $calendar = new Calendar($_SESSION['user_id']);
    
    // Get day data
    $day_data = $calendar->getDayData($date);
    
    // Get detailed statistics for this day
    $stats = [
        'total_doses' => count($day_data['taken_doses']),
        'scheduled_doses' => count($day_data['scheduled_doses']),
        'taken_doses' => count($day_data['taken_doses']),
        'missed_doses' => count($day_data['missed_doses'])
    ];
    
    // Format scheduled doses for display
    $scheduled_doses = [];
    foreach ($day_data['scheduled_doses'] as $dose) {
        $scheduled_doses[] = [
            'medication_id' => $dose['medication_id'],
            'medication_name' => $dose['medication_name'],
            'dosage' => $dose['dosage'],
            'scheduled_time' => date('Y-m-d H:i', strtotime($dose['scheduled_time']))
        ];
    }
    
    // Format taken doses for display
    $taken_doses = [];
    foreach ($day_data['taken_doses'] as $dose) {
        $taken_doses[] = [
            'medication_id' => $dose['medication_id'],
            'medication_name' => $dose['medication_name'],
            'dosage' => $dose['dosage'],
            'taken_at' => date('Y-m-d H:i', strtotime($dose['taken_at'])),
            'note' => $dose['note'] ?? ''
        ];
    }
    
    // Format missed doses for display
    $missed_doses = [];
    foreach ($day_data['missed_doses'] as $dose) {
        $missed_doses[] = [
            'medication_id' => $dose['medication_id'],
            'medication_name' => $dose['medication_name'],
            'dosage' => $dose['dosage'],
            'scheduled_time' => date('Y-m-d H:i', strtotime($dose['scheduled_time']))
        ];
    }
    
    $response_data = [
        'date' => $date,
        'stats' => $stats,
        'scheduled_doses' => $scheduled_doses,
        'taken_doses' => $taken_doses,
        'missed_doses' => $missed_doses
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $response_data
    ]);
    
} catch (Exception $e) {
    error_log('Error in get-day-details.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching day details'
    ]);
}
?> 