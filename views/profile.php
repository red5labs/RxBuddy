<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/reminder_scheduler.php';
require_once __DIR__ . '/../includes/sharing_service.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Initialize sharing service
$sharingService = new SharingService();

// Get current user info
$user = fetchOne('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);

// Handle email verification request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_verification'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        logSecurityEvent('CSRF attack attempted', 'Email verification form');
        $_SESSION['flash']['error'] = ['Security validation failed. Please try again.'];
        regenerateCSRFToken();
    } else {
        try {
            $scheduler = new ReminderScheduler();
            if ($scheduler->sendVerificationEmail($_SESSION['user_id'], $user['email'], $user['name'])) {
                $_SESSION['flash']['success'][] = 'Verification email sent! Please check your inbox.';
            } else {
                $_SESSION['flash']['error'][] = 'Failed to send verification email. Please try again.';
            }
        } catch (Exception $e) {
            $_SESSION['flash']['error'][] = 'Email service error: ' . $e->getMessage();
            error_log('Verification email error: ' . $e->getMessage());
        }
        regenerateCSRFToken();
    }
}

// Handle email reminder toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_reminders'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        logSecurityEvent('CSRF attack attempted', 'Email reminder toggle');
        $_SESSION['flash']['error'] = ['Security validation failed. Please try again.'];
        regenerateCSRFToken();
    } else {
        try {
            $enabled = isset($_POST['email_reminders']) ? 1 : 0;
            executeQuery('UPDATE users SET email_reminders = ? WHERE id = ?', [$enabled, $_SESSION['user_id']]);
            $user['email_reminders'] = $enabled;
            $_SESSION['flash']['success'][] = $enabled ? 'Email reminders enabled!' : 'Email reminders disabled.';
        } catch (Exception $e) {
            $_SESSION['flash']['error'][] = 'Failed to update reminder settings. Please try again.';
        }
        regenerateCSRFToken();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        logSecurityEvent('CSRF attack attempted', 'Password change form');
        $_SESSION['flash']['error'] = ['Security validation failed. Please try again.'];
        regenerateCSRFToken();
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $errors = [];

        // Validate current password
        if (empty($current_password)) {
            $errors[] = 'Current password is required.';
        } elseif (!password_verify($current_password, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }

        // Validate new password
        if (empty($new_password)) {
            $errors[] = 'New password is required.';
        } elseif (!validatePassword($new_password)) {
            $errors[] = 'New password must be at least 6 characters and contain only valid characters.';
        }

        if ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match.';
        }

        if (empty($errors)) {
            try {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                executeQuery('UPDATE users SET password_hash = ? WHERE id = ?', [$new_hash, $_SESSION['user_id']]);
                logSecurityEvent('Password changed', "User ID: {$_SESSION['user_id']}");
                $_SESSION['flash']['success'][] = 'Password changed successfully!';
                regenerateCSRFToken();
            } catch (Exception $e) {
                logSecurityEvent('Password change failed', $e->getMessage());
                $errors[] = 'Failed to change password. Please try again.';
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['flash']['error'] = $errors;
            regenerateCSRFToken();
        }
    }
}

// Handle shared profile creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_share'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        logSecurityEvent('CSRF attack attempted', 'Create shared profile form');
        $_SESSION['flash']['error'] = ['Security validation failed. Please try again.'];
        regenerateCSRFToken();
    } else {
        try {
            $share_name = $_POST['share_name'] ?? '';
            $share_email = $_POST['share_email'] ?? '';
            $share_type = $_POST['share_type'] ?? '';
            $permissions = [
                'view_medications' => isset($_POST['view_medications']),
                'view_logs' => isset($_POST['view_logs']),
                'view_calendar' => isset($_POST['view_calendar']),
                'receive_alerts' => isset($_POST['receive_alerts'])
            ];
            $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] . ' 23:59:59' : null;
            
            $share_token = $sharingService->createSharedProfile(
                $_SESSION['user_id'],
                $share_name,
                $share_email,
                $share_type,
                $permissions,
                $expires_at
            );
            
            $_SESSION['flash']['success'][] = 'Shared profile created successfully! An email has been sent to ' . htmlspecialchars($share_email) . ' with the share link.';
            regenerateCSRFToken();
        } catch (Exception $e) {
            $_SESSION['flash']['error'][] = $e->getMessage();
            regenerateCSRFToken();
        }
    }
}

// Handle shared profile deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_share'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        logSecurityEvent('CSRF attack attempted', 'Deactivate shared profile form');
        $_SESSION['flash']['error'] = ['Security validation failed. Please try again.'];
        regenerateCSRFToken();
    } else {
        try {
            $share_id = (int)($_POST['share_id'] ?? 0);
            $sharingService->deactivateSharedProfile($share_id, $_SESSION['user_id']);
            $_SESSION['flash']['success'][] = 'Shared profile deactivated successfully!';
            regenerateCSRFToken();
        } catch (Exception $e) {
            $_SESSION['flash']['error'][] = $e->getMessage();
            regenerateCSRFToken();
        }
    }
}

// Get shared profiles and emergency contacts
$shared_profiles = $sharingService->getSharedProfiles($_SESSION['user_id']);
$emergency_contacts = $sharingService->getEmergencyContacts($_SESSION['user_id']);

include __DIR__ . '/../includes/templates/flash.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Profile</h1>
    <p class="text-gray-600">Manage your account information and security settings.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- User Information -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold mb-4 text-gray-800">Account Information</h2>
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <p class="text-sm text-gray-900 mt-1"><?php echo htmlspecialchars($user['name']); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <p class="text-sm text-gray-900 mt-1"><?php echo htmlspecialchars($user['email']); ?></p>
                <?php if (!isset($user['email_verified']) || !$user['email_verified']): ?>
                    <div class="mt-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            Unverified
                        </span>
                        <form method="post" class="inline ml-2">
                            <?php echo getCSRFTokenField(); ?>
                            <button type="submit" name="send_verification" class="text-xs text-indigo-600 hover:text-indigo-800">
                                Send verification email
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="mt-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Verified
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Member Since</label>
                <p class="text-sm text-gray-900 mt-1"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
            </div>
        </div>
    </div>

    <!-- Email Reminders -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold mb-4 text-gray-800">Email Reminders</h2>
        <form method="post">
            <?php echo getCSRFTokenField(); ?>
            <div class="space-y-4">
                <div class="flex items-center">
                    <input type="checkbox" name="email_reminders" id="email_reminders" 
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                           <?= (isset($user['email_reminders']) && $user['email_reminders']) ? 'checked' : '' ?>
                           <?= (!isset($user['email_verified']) || !$user['email_verified']) ? 'disabled' : '' ?>>
                    <label for="email_reminders" class="ml-2 block text-sm text-gray-900">
                        Enable email reminders for medications
                    </label>
                </div>
                <?php if (!isset($user['email_verified']) || !$user['email_verified']): ?>
                    <p class="text-sm text-yellow-600">
                        Please verify your email address to enable reminders.
                    </p>
                <?php endif; ?>
                <button type="submit" name="toggle_reminders" 
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded"
                        <?= (!isset($user['email_verified']) || !$user['email_verified']) ? 'disabled' : '' ?>>
                    Update Reminder Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Change Password -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold mb-4 text-gray-800">Change Password</h2>
        <form method="post" autocomplete="off">
            <?php echo getCSRFTokenField(); ?>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                    <input type="password" name="current_password" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" name="new_password" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200" required>
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200" required>
                </div>
                <button type="submit" name="change_password" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">
                    Change Password
                </button>
            </div>
        </form>
    </div>

    <!-- Profile Sharing -->
    <div class="bg-white rounded-lg shadow-sm p-6 mt-6">
        <h2 class="text-lg font-semibold mb-4 text-gray-800">Profile Sharing</h2>
        <form method="post" class="mb-6">
            <?php echo getCSRFTokenField(); ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recipient Name</label>
                    <input type="text" name="share_name" class="border border-gray-300 rounded px-3 py-2 w-full" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recipient Email</label>
                    <input type="email" name="share_email" class="border border-gray-300 rounded px-3 py-2 w-full" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                    <select name="share_type" class="border border-gray-300 rounded px-3 py-2 w-full" required>
                        <option value="caregiver">Caregiver</option>
                        <option value="family">Family</option>
                        <option value="healthcare_provider">Healthcare Provider</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expires (optional)</label>
                    <input type="date" name="expires_at" class="border border-gray-300 rounded px-3 py-2 w-full">
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-2">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="view_medications" class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                    <span class="ml-2 text-sm text-gray-700">View Medications</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="checkbox" name="view_logs" class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                    <span class="ml-2 text-sm text-gray-700">View Dose Logs</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="checkbox" name="view_calendar" class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                    <span class="ml-2 text-sm text-gray-700">View Calendar</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="checkbox" name="receive_alerts" class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                    <span class="ml-2 text-sm text-gray-700">Receive Alerts</span>
                </label>
            </div>
            <button type="submit" name="create_share" class="mt-4 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">
                Create Share Link
            </button>
        </form>
        <h3 class="text-md font-semibold mb-2 text-gray-700">Active Shares</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr>
                        <th class="px-2 py-1 text-left">Recipient</th>
                        <th class="px-2 py-1 text-left">Email</th>
                        <th class="px-2 py-1 text-left">Type</th>
                        <th class="px-2 py-1 text-left">Permissions</th>
                        <th class="px-2 py-1 text-left">Link</th>
                        <th class="px-2 py-1 text-left">Expires</th>
                        <th class="px-2 py-1"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shared_profiles as $share): ?>
                    <tr>
                        <td class="px-2 py-1"><?php echo htmlspecialchars($share['share_name']); ?></td>
                        <td class="px-2 py-1"><?php echo htmlspecialchars($share['share_email']); ?></td>
                        <td class="px-2 py-1"><?php echo htmlspecialchars(ucfirst($share['share_type'])); ?></td>
                        <td class="px-2 py-1">
                            <?php $perms = json_decode($share['permissions'], true); ?>
                            <span class="inline-block mr-1"><?php if ($perms['view_medications']) echo 'Medications '; ?></span>
                            <span class="inline-block mr-1"><?php if ($perms['view_logs']) echo 'Logs '; ?></span>
                            <span class="inline-block mr-1"><?php if ($perms['view_calendar']) echo 'Calendar '; ?></span>
                            <span class="inline-block mr-1"><?php if ($perms['receive_alerts']) echo 'Alerts '; ?></span>
                        </td>
                        <td class="px-2 py-1">
                            <input type="text" readonly class="w-40 border rounded px-1 text-xs" value="<?php echo htmlspecialchars($sharingService->getShareUrl($share['share_token'])); ?>">
                            <button type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($sharingService->getShareUrl($share['share_token'])); ?>'); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy', 1200);" class="ml-1 text-xs text-indigo-600 hover:underline">Copy</button>
                        </td>
                        <td class="px-2 py-1"><?php echo $share['expires_at'] ? date('Y-m-d', strtotime($share['expires_at'])) : 'Never'; ?></td>
                        <td class="px-2 py-1">
                            <form method="post" onsubmit="return confirm('Deactivate this share?');" class="inline">
                                <?php echo getCSRFTokenField(); ?>
                                <input type="hidden" name="share_id" value="<?php echo $share['id']; ?>">
                                <button type="submit" name="deactivate_share" class="text-xs text-red-600 hover:underline">Deactivate</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($shared_profiles)): ?>
                    <tr><td colspan="7" class="text-center text-gray-400 py-2">No active shares</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Account Statistics -->
<div class="mt-6 bg-white rounded-lg shadow-sm p-6">
    <h2 class="text-lg font-semibold mb-4 text-gray-800">Account Statistics</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?php
        $current_meds = fetchOne('SELECT COUNT(*) as count FROM medications WHERE user_id = ? AND is_active = 1', [$_SESSION['user_id']]);
        $past_meds = fetchOne('SELECT COUNT(*) as count FROM medications WHERE user_id = ? AND is_active = 0', [$_SESSION['user_id']]);
        $total_logs = fetchOne('SELECT COUNT(*) as count FROM logs l JOIN medications m ON l.medication_id = m.id WHERE m.user_id = ?', [$_SESSION['user_id']]);
        ?>
        <div class="text-center p-4 bg-blue-50 rounded-lg">
            <div class="text-2xl font-bold text-blue-600"><?php echo $current_meds['count']; ?></div>
            <div class="text-sm text-gray-600">Current Medications</div>
        </div>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <div class="text-2xl font-bold text-gray-600"><?php echo $past_meds['count']; ?></div>
            <div class="text-sm text-gray-600">Past Medications</div>
        </div>
        <div class="text-center p-4 bg-green-50 rounded-lg">
            <div class="text-2xl font-bold text-green-600"><?php echo $total_logs['count']; ?></div>
            <div class="text-sm text-gray-600">Total Doses Logged</div>
        </div>
    </div>
</div> 