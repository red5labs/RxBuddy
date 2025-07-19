<?php
require_once __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}

// Get filter parameters (same as logs view)
$medication_filter = $_GET['medication'] ?? null;
$date_from = $_GET['date_from'] ?? null;
$date_to = $_GET['date_to'] ?? null;

// Build query with filters
$where_conditions = ['l.medication_id = m.id', 'm.user_id = ?'];
$params = [$_SESSION['user_id']];

if ($medication_filter) {
    $where_conditions[] = 'm.id = ?';
    $params[] = $medication_filter;
}
if ($date_from) {
    $where_conditions[] = 'DATE(l.taken_at) >= ?';
    $params[] = $date_from;
}
if ($date_to) {
    $where_conditions[] = 'DATE(l.taken_at) <= ?';
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

$logs = fetchAll("
    SELECT l.*, m.name as medication_name, m.dosage
    FROM logs l
    JOIN medications m ON $where_clause
    ORDER BY l.taken_at DESC
", $params);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=medication_logs_' . date('Y-m-d') . '.csv');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');
// Add CSV headers
fputcsv($output, ['Date & Time', 'Medication', 'Dosage', 'Method', 'Notes']);

// Add data rows
foreach ($logs as $log) {
    fputcsv($output, [
        date('Y-m-d H:i:s', strtotime($log['taken_at'])),
        $log['medication_name'],
        $log['dosage'],
        $log['method'],
        $log['notes'] ?? ''
    ]);
}

fclose($output);
exit; 