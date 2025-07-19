<?php
// Public shared profile view
require_once 'includes/db.php';
require_once 'includes/validation.php';
require_once 'includes/sharing_service.php';

// Start session for CSRF protection
session_start();

// Get share token from URL
$share_token = $_GET['token'] ?? '';

if (empty($share_token)) {
    http_response_code(404);
    die('Share link not found or invalid.');
}

try {
    // Initialize sharing service
    $sharingService = new SharingService();
    
    // Validate and get shared profile
    $shared_profile = $sharingService->getSharedProfileByToken($share_token);
    
    if (!$shared_profile) {
        http_response_code(404);
        die('Share link not found or has expired.');
    }
    
    // Check if share is active and not expired
    if (!$sharingService->isShareActive($shared_profile)) {
        http_response_code(410);
        die('This share link has expired or been deactivated.');
    }
    
    // Get user information
    $user = fetchOne('SELECT name, email FROM users WHERE id = ?', [$shared_profile['user_id']]);
    if (!$user) {
        http_response_code(404);
        die('User not found.');
    }
    
    // Get permissions (already decoded by getSharedProfileByToken)
    $permissions = $shared_profile['permissions'];
    
    // Log access
    $sharingService->logAccess($shared_profile['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    // Get medications if permission granted
    $medications = [];
    if ($permissions['view_medications']) {
        $medications = fetchAll('
            SELECT m.*, s.time_of_day, s.interval_hours
            FROM medications m
            LEFT JOIN schedules s ON m.id = s.medication_id
            WHERE m.user_id = ? AND m.is_active = 1
            ORDER BY m.name
        ', [$shared_profile['user_id']]);
    }
    
    // Get recent logs if permission granted
    $recent_logs = [];
    if ($permissions['view_logs']) {
        $recent_logs = fetchAll('
            SELECT l.*, m.name as medication_name, m.dosage
            FROM logs l
            JOIN medications m ON l.medication_id = m.id
            WHERE l.user_id = ?
            ORDER BY l.taken_at DESC
            LIMIT 10
        ', [$shared_profile['user_id']]);
    }
    
    // Get adherence data if permission granted
    $adherence_data = [];
    if ($permissions['view_calendar']) {
        // Get last 30 days of adherence
        $adherence_data = fetchAll('
            SELECT 
                DATE(l.taken_at) as date,
                COUNT(*) as doses_taken,
                COUNT(DISTINCT l.medication_id) as medications_taken
            FROM logs l
            WHERE l.user_id = ? 
            AND l.taken_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(l.taken_at)
            ORDER BY date DESC
        ', [$shared_profile['user_id']]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    die('An error occurred while loading the shared profile.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['name']); ?>'s Shared Profile - RxBuddy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-indigo-600 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold">RxBuddy</h1>
                    <span class="ml-4 text-indigo-200">Shared Profile</span>
                </div>
                <div class="text-right">
                    <p class="text-sm text-indigo-200">Shared by</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($user['name']); ?></p>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Share Info -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">
                        <?php echo htmlspecialchars($user['name']); ?>'s Medication Profile
                    </h2>
                    <p class="text-gray-600">
                        Shared as: <span class="font-medium"><?php echo ucfirst($shared_profile['share_type']); ?></span>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Last updated</p>
                    <p class="text-sm font-medium"><?php echo date('M j, Y', strtotime($shared_profile['created_at'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Permissions Info -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
            <h3 class="text-lg font-medium text-blue-900 mb-2">What you can access:</h3>
            <ul class="text-blue-800 space-y-1">
                <?php if ($permissions['view_medications']): ?>
                    <li class="flex items-center">
                        <svg class="w-4 h-4 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        View medications and dosages
                    </li>
                <?php endif; ?>
                <?php if ($permissions['view_logs']): ?>
                    <li class="flex items-center">
                        <svg class="w-4 h-4 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        View dose logs and history
                    </li>
                <?php endif; ?>
                <?php if ($permissions['view_calendar']): ?>
                    <li class="flex items-center">
                        <svg class="w-4 h-4 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        View calendar and adherence
                    </li>
                <?php endif; ?>
                <?php if ($permissions['receive_alerts']): ?>
                    <li class="flex items-center">
                        <svg class="w-4 h-4 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        Receive medication alerts
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Medications Section -->
        <?php if ($permissions['view_medications'] && !empty($medications)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Current Medications</h3>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($medications as $med): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($med['name']); ?></h4>
                    <p class="text-sm text-gray-600 mb-2">
                        <strong>Dosage:</strong> <?php echo htmlspecialchars($med['dosage']); ?>
                    </p>
                    <?php if ($med['time_of_day']): ?>
                        <p class="text-sm text-gray-600">
                            <strong>Time:</strong> <?php echo htmlspecialchars($med['time_of_day']); ?>
                        </p>
                    <?php elseif ($med['interval_hours']): ?>
                        <p class="text-sm text-gray-600">
                            <strong>Interval:</strong> Every <?php echo $med['interval_hours']; ?> hours
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($med['notes'])): ?>
                        <p class="text-sm text-gray-600 mt-2">
                            <strong>Notes:</strong> <?php echo htmlspecialchars($med['notes']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Logs Section -->
        <?php if ($permissions['view_logs'] && !empty($recent_logs)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Dose Logs</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medication</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dosage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Taken At</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($log['medication_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($log['dosage']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M j, Y g:i A', strtotime($log['taken_at'])); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($log['notes'] ?? ''); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Adherence Section -->
        <?php if ($permissions['view_calendar'] && !empty($adherence_data)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Adherence (Last 30 Days)</h3>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <?php foreach (array_slice($adherence_data, 0, 6) as $day): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-2">
                        <?php echo date('M j, Y', strtotime($day['date'])); ?>
                    </h4>
                    <p class="text-sm text-gray-600">
                        <strong>Doses taken:</strong> <?php echo $day['doses_taken']; ?>
                    </p>
                    <p class="text-sm text-gray-600">
                        <strong>Medications:</strong> <?php echo $day['medications_taken']; ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="bg-gray-100 rounded-lg p-6 text-center">
            <p class="text-gray-600 mb-2">
                This is a read-only view of <?php echo htmlspecialchars($user['name']); ?>'s medication information.
            </p>
            <p class="text-sm text-gray-500">
                If you have any questions, please contact <?php echo htmlspecialchars($user['name']); ?> directly.
            </p>
            <?php if ($shared_profile['expires_at']): ?>
            <p class="text-sm text-gray-500 mt-2">
                This share link expires on <?php echo date('F j, Y', strtotime($shared_profile['expires_at'])); ?>.
            </p>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-gray-400">
                RxBuddy - Personal Medication Tracking
            </p>
        </div>
    </footer>
</body>
</html> 