<?php
session_start();

// Simple routing
$page = $_GET['page'] ?? 'dashboard';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Handle logout before any output
if ($page === 'logout') {
    include 'auth/logout.php';
    exit;
}

// Handle edit-medication form processing before any output
if ($page === 'edit-medication' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db.php';
    require_once 'includes/csrf.php';
    require_once 'includes/validation.php';
    require_once 'includes/upload_handler.php';
    
    $med_id = sanitizeInput($_GET['id'] ?? '', 'int');
    if (!$med_id || !validateInteger($med_id, 1)) {
        logSecurityEvent('Invalid medication ID in edit', "ID: $med_id");
        $_SESSION['flash']['error'][] = 'Invalid medication ID.';
        header('Location: index.php?page=dashboard');
        exit;
    }

    // Fetch medication and schedule
    $med = fetchOne('SELECT * FROM medications WHERE id = ? AND user_id = ?', [$med_id, $_SESSION['user_id']]);
    $schedule = fetchOne('SELECT * FROM schedules WHERE medication_id = ?', [$med_id]);
    if (!$med) {
        $_SESSION['flash']['error'][] = 'Medication not found.';
        header('Location: index.php?page=dashboard');
        exit;
    }

    // Process form submission
    if (isset($_POST['update'])) {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            logSecurityEvent('CSRF attack attempted', 'Edit medication form');
            $_SESSION['flash']['error'] = ['Security validation failed. Please try again.'];
            regenerateCSRFToken();
            header('Location: index.php?page=edit-medication&id=' . $med_id);
            exit;
        }

        $errors = [];
        
        // Sanitize inputs
        $name = sanitizeInput($_POST['name'] ?? '', 'string');
        $dosage = sanitizeInput($_POST['dosage'] ?? '', 'string');
        $frequency = sanitizeInput($_POST['frequency'] ?? '', 'string');
        $start_date = sanitizeInput($_POST['start_date'] ?? '', 'string');
        $end_date = sanitizeInput($_POST['end_date'] ?? '', 'string');
        $notes = sanitizeInput($_POST['notes'] ?? '', 'string');
        $reminder_enabled = isset($_POST['reminder_enabled']) ? 1 : 0;
        $reminder_offset = sanitizeInput($_POST['reminder_offset'] ?? '0', 'int');
        
        // Handle schedule
        $schedule_type = sanitizeInput($_POST['schedule_type'] ?? $_POST['schedule_type_fallback'] ?? 'time', 'string');
        $time_of_day = sanitizeInput($_POST['time_of_day'] ?? '', 'string');
        $interval_hours = sanitizeInput($_POST['interval_hours'] ?? '', 'int');
        
        // Handle photo upload
        $photo_url = $med['photo_url']; // Keep existing photo by default
        if (isset($_FILES['pill_photo']) && $_FILES['pill_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_handler = new UploadHandler();
            $upload_result = $upload_handler->uploadPillPhoto($_FILES['pill_photo'], $_SESSION['user_id']);
            
            if (!$upload_result['success']) {
                $errors[] = $upload_result['error'];
            } else {
                // Delete old photo if it exists
                if (!empty($med['photo_url'])) {
                    $upload_handler->deletePillPhoto($med['photo_url']);
                }
                $photo_url = $upload_result['url'];
            }
        }
        
        // Handle photo removal
        if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
            if (!empty($med['photo_url'])) {
                $upload_handler = new UploadHandler();
                $upload_handler->deletePillPhoto($med['photo_url']);
            }
            $photo_url = null;
        }

        // Validate inputs
        if (empty($name) || !validateLength($name, 1, 100)) {
            $errors[] = 'Please enter a valid medication name (1-100 characters).';
        }
        if (empty($dosage) || !validateLength($dosage, 1, 50)) {
            $errors[] = 'Please enter a valid dosage (1-50 characters).';
        }
        if (empty($frequency) || !validateLength($frequency, 1, 50)) {
            $errors[] = 'Please enter a valid frequency (1-50 characters).';
        }
        if (!empty($start_date) && !validateDate($start_date)) {
            $errors[] = 'Please enter a valid start date.';
        }
        if (!empty($end_date) && !validateDate($end_date)) {
            $errors[] = 'Please enter a valid end date.';
        }
        if (!empty($notes) && !validateLength($notes, 0, 1000)) {
            $errors[] = 'Notes are too long. Maximum 1000 characters.';
        }
        if (!validateInteger($reminder_offset, 0, 1440)) {
            $errors[] = 'Invalid reminder offset.';
        }

        // Validate schedule
        if ($schedule_type === 'time') {
            if (empty($time_of_day) || !validateTime($time_of_day)) {
                $errors[] = 'Please select a valid time of day.';
            }
        } elseif ($schedule_type === 'interval') {
            if (empty($interval_hours) || !validateInteger($interval_hours, 1, 168)) {
                $errors[] = 'Please enter a valid interval (1-168 hours).';
            }
        } else {
            $errors[] = 'Please select a valid schedule type.';
        }

        if (empty($errors)) {
            try {
                executeQuery('UPDATE medications SET name=?, dosage=?, frequency=?, start_date=?, end_date=?, notes=?, photo_url=?, reminder_enabled=?, reminder_offset_minutes=? WHERE id=? AND user_id=?', [
                    $name, $dosage, $frequency, $start_date ?: null, $end_date ?: null, $notes, $photo_url, $reminder_enabled, $reminder_offset, $med_id, $_SESSION['user_id']
                ]);
                // Update schedule
                if ($schedule_type === 'time') {
                    executeQuery('UPDATE schedules SET time_of_day=?, interval_hours=NULL WHERE medication_id=?', [$time_of_day, $med_id]);
                } else {
                    executeQuery('UPDATE schedules SET interval_hours=?, time_of_day=NULL WHERE medication_id=?', [$interval_hours, $med_id]);
                }
                logSecurityEvent('Medication updated', "User ID: {$_SESSION['user_id']}, Medication ID: $med_id");
                $_SESSION['flash']['success'][] = 'Medication updated.';
                regenerateCSRFToken();
                header('Location: index.php?page=dashboard');
                exit;
            } catch (Exception $e) {
                logSecurityEvent('Medication update failed', $e->getMessage());
                $errors[] = 'Failed to update medication. Please try again.';
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['flash']['error'] = $errors;
            regenerateCSRFToken();
            header('Location: index.php?page=edit-medication&id=' . $med_id);
            exit;
        }
    }
    
    // Handle archive
    if (isset($_POST['archive'])) {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            logSecurityEvent('CSRF attack attempted', 'Archive medication form');
            $_SESSION['flash']['error'] = ['Security validation failed. Please try again.'];
            regenerateCSRFToken();
            header('Location: index.php?page=edit-medication&id=' . $med_id);
            exit;
        } else {
            $stop_reason = sanitizeInput($_POST['stop_reason'] ?? '', 'string');
            
            // Validate stop reason length
            if (!empty($stop_reason) && !validateLength($stop_reason, 0, 500)) {
                $_SESSION['flash']['error'] = ['Stop reason is too long. Maximum 500 characters.'];
                header('Location: index.php?page=edit-medication&id=' . $med_id);
                exit;
            } else {
                try {
                    executeQuery('UPDATE medications SET is_active=0, notes=CONCAT(IFNULL(notes, ""), ?) WHERE id=? AND user_id=?', [
                        $stop_reason ? "\nStopped: $stop_reason" : '', $med_id, $_SESSION['user_id']
                    ]);
                    logSecurityEvent('Medication archived', "User ID: {$_SESSION['user_id']}, Medication ID: $med_id");
                    $_SESSION['flash']['success'][] = 'Medication archived.';
                    regenerateCSRFToken();
                    header('Location: index.php?page=past-medications');
                    exit;
                } catch (Exception $e) {
                    logSecurityEvent('Medication archive failed', $e->getMessage());
                    $_SESSION['flash']['error'] = ['Failed to archive medication. Please try again.'];
                    header('Location: index.php?page=edit-medication&id=' . $med_id);
                    exit;
                }
            }
        }
    }
}

// Redirect to login if not authenticated (except for auth pages)
if (!$isLoggedIn && !in_array($page, ['login', 'register'])) {
    header('Location: index.php?page=login');
    exit;
}

// Redirect to dashboard if already logged in and trying to access auth pages
if ($isLoggedIn && in_array($page, ['login', 'register'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Include header
include 'includes/header.php';

// Route to appropriate page
switch ($page) {
    case 'login':
        include 'auth/login.php';
        break;
    case 'register':
        include 'auth/register.php';
        break;
    case 'dashboard':
        include 'views/dashboard.php';
        break;
    case 'add-medication':
        include 'views/add-medication.php';
        break;
    case 'edit-medication':
        include 'views/edit-medication.php';
        break;
    case 'logs':
        include 'views/logs.php';
        break;
    case 'past-medications':
        include 'views/past-medications.php';
        break;
    case 'calendar':
        include 'views/calendar.php';
        break;
    case 'profile':
        include 'views/profile.php';
        break;
    default:
        include 'views/dashboard.php';
}

// Include footer
include 'includes/footer.php';
?> 