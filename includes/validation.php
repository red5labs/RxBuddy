<?php
// Input Validation and Sanitization System

function sanitizeInput($input, $type = 'string') {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    $input = trim($input);
    
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
        case 'url':
            return filter_var($input, FILTER_SANITIZE_URL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'string':
        default:
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateLength($input, $min = 0, $max = null) {
    $length = mb_strlen($input);
    if ($length < $min) {
        return false;
    }
    if ($max !== null && $length > $max) {
        return false;
    }
    return true;
}

function validateDate($date) {
    if (empty($date)) {
        return true; // Allow empty dates
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function validateTime($time) {
    if (empty($time)) {
        return true; // Allow empty times
    }
    
    // Try H:i format first (hours:minutes)
    $t = DateTime::createFromFormat('H:i', $time);
    if ($t && $t->format('H:i') === $time) {
        return true;
    }
    
    // Try H:i:s format (hours:minutes:seconds)
    $t = DateTime::createFromFormat('H:i:s', $time);
    if ($t && $t->format('H:i:s') === $time) {
        return true;
    }
    
    return false;
}

function validateInteger($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return false;
    }
    $int = (int)$value;
    if ($min !== null && $int < $min) {
        return false;
    }
    if ($max !== null && $int > $max) {
        return false;
    }
    return true;
}

function validateMedicationName($name) {
    // Allow letters, numbers, spaces, hyphens, and common medication characters
    return preg_match('/^[a-zA-Z0-9\s\-\.\(\)\/]+$/', $name) && validateLength($name, 1, 100);
}

function validateDosage($dosage) {
    // Allow common dosage formats: 500mg, 10ml, 2 tablets, etc.
    return preg_match('/^[0-9]+\.?[0-9]*\s*[a-zA-Z]+$/', $dosage) && validateLength($dosage, 1, 50);
}

function validateFrequency($frequency) {
    // Allow common frequency descriptions
    return validateLength($frequency, 1, 100);
}

function validateNotes($notes) {
    // Allow text with line breaks, but limit length
    return validateLength($notes, 0, 1000);
}

function validatePassword($password) {
    // Minimum 6 characters, allow letters, numbers, and common symbols
    return preg_match('/^[a-zA-Z0-9!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]{6,}$/', $password);
}

function validateName($name) {
    // Allow letters, spaces, hyphens, apostrophes
    return preg_match('/^[a-zA-Z\s\-\']+$/', $name) && validateLength($name, 1, 100);
}

function logSecurityEvent($event, $details = '') {
    // Log security events (in production, this would go to a security log)
    error_log("SECURITY: $event - $details - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " - User: " . ($_SESSION['user_id'] ?? 'not logged in'));
}
?> 