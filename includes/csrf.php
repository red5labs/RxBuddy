<?php
// CSRF Protection System

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_created'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Check if token is too old (24 hours)
    if (isset($_SESSION['csrf_token_created']) && (time() - $_SESSION['csrf_token_created']) > 86400) {
        regenerateCSRFToken();
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

function getCSRFTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function regenerateCSRFToken() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_created'] = time();
    return $_SESSION['csrf_token'];
}
?> 