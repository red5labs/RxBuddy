<?php
if (session_status() === PHP_SESSION_NONE) session_start();
session_unset();
session_destroy();
session_start();
$_SESSION['flash']['success'][] = 'You have been logged out.';
header('Location: index.php?page=login');
exit; 