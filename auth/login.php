<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        logSecurityEvent('CSRF attack attempted', 'Login form');
        $_SESSION['flash']['error'] = ['Security validation failed. Please try again.'];
        regenerateCSRFToken();
    } else {
        // Sanitize and validate inputs
        $email = sanitizeInput($_POST['email'] ?? '', 'email');
        $password = $_POST['password'] ?? '';
        $errors = [];

        // Validate required fields
        if (empty($email) || empty($password)) {
            $errors[] = 'Email and password are required.';
        }

        // Validate email format
        if (!empty($email) && !validateEmail($email)) {
            $errors[] = 'Invalid email address format.';
        }

        if (empty($errors)) {
            try {
                $user = fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    logSecurityEvent('Failed login attempt', "Email: $email");
                    $errors[] = 'Invalid email or password.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    logSecurityEvent('User logged in successfully', "User ID: {$user['id']}");
                    $_SESSION['flash']['success'][] = 'Welcome, ' . htmlspecialchars($user['name']) . '!';
                    regenerateCSRFToken();
                    header('Location: index.php?page=dashboard');
                    exit;
                }
            } catch (Exception $e) {
                logSecurityEvent('Login error', $e->getMessage());
                $errors[] = 'Login failed. Please try again.';
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['flash']['error'] = $errors;
            regenerateCSRFToken();
        }
    }
}
include __DIR__ . '/../includes/templates/flash.php';
?>
<div class="max-w-md mx-auto bg-white rounded-lg shadow-sm p-8 mt-8">
    <h2 class="text-2xl font-bold mb-6 text-indigo-600">Login</h2>
    <form method="post" autocomplete="off">
        <?php echo getCSRFTokenField(); ?>
        <div class="mb-4">
            <label class="block mb-1 text-gray-700">Email</label>
            <input type="email" name="email" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200" required>
        </div>
        <div class="mb-6">
            <label class="block mb-1 text-gray-700">Password</label>
            <input type="password" name="password" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200" required>
        </div>
        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Login</button>
        <p class="mt-4 text-sm text-gray-500 text-center">Don't have an account? <a href="index.php?page=register" class="text-indigo-600 hover:underline">Register</a></p>
    </form>
</div> 