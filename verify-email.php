<?php
require_once 'includes/db.php';
require_once 'includes/reminder_scheduler.php';

$message = '';
$message_type = '';

if (isset($_GET['token'])) {
    $token = sanitizeInput($_GET['token']);
    
    $scheduler = new ReminderScheduler();
    if ($scheduler->verifyEmail($token)) {
        $message = 'Email verified successfully! You can now receive medication reminders.';
        $message_type = 'success';
    } else {
        $message = 'Invalid or expired verification link. Please request a new verification email.';
        $message_type = 'error';
    }
} else {
    $message = 'No verification token provided.';
    $message_type = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - MedTrackr</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">MedTrackr</h1>
            <h2 class="text-xl font-semibold text-gray-700">Email Verification</h2>
        </div>
        
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <div class="text-center">
            <a href="index.php?page=dashboard" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                Go to Dashboard
            </a>
        </div>
    </div>
</body>
</html> 