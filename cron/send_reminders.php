<?php
/**
 * Cron job script for sending medication reminders
 * Run this every 5 minutes: 0,5,10,15,20,25,30,35,40,45,50,55 * * * * php /path/to/rxbuddy/cron/send_reminders.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cron_errors.log');

// Include required files
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/reminder_scheduler.php';

// Start execution log
$log_file = __DIR__ . '/../logs/cron_execution.log';
$start_time = date('Y-m-d H:i:s');
file_put_contents($log_file, "[$start_time] Starting reminder cron job\n", FILE_APPEND);

try {
    $scheduler = new ReminderScheduler();
    
    // Send pending reminders
    $scheduler->sendPendingReminders();
    
    // Schedule new reminders (run less frequently - could be separate cron)
    if (date('i') % 15 == 0) { // Every 15 minutes
        $scheduler->scheduleReminders();
    }
    
    $end_time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$end_time] Reminder cron job completed successfully\n", FILE_APPEND);
    
} catch (Exception $e) {
    $error_time = date('Y-m-d H:i:s');
    $error_msg = "[$error_time] Error in reminder cron job: " . $e->getMessage() . "\n";
    file_put_contents($log_file, $error_msg, FILE_APPEND);
    error_log($error_msg);
}
?> 