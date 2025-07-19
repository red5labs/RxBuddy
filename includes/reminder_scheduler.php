<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_service_alternative.php';
require_once __DIR__ . '/validation.php';

class ReminderScheduler {
    private $emailService;
    
    public function __construct() {
        $this->emailService = new AlternativeEmailService();
    }
    
    /**
     * Schedule reminders for all active medications
     */
    public function scheduleReminders() {
        // Get all active medications with reminders enabled
        $medications = fetchAll('
            SELECT m.*, s.time_of_day, s.interval_hours, u.email, u.email_reminders, u.email_verified
            FROM medications m
            JOIN schedules s ON m.id = s.medication_id
            JOIN users u ON m.user_id = u.id
            WHERE m.is_active = 1 
            AND m.reminder_enabled = 1
            AND u.email_reminders = 1
            AND u.email_verified = 1
        ');
        
        foreach ($medications as $med) {
            $next_reminder = $this->calculateNextReminderTime($med);
            if ($next_reminder) {
                $this->scheduleReminder($med, $next_reminder);
            }
        }
    }
    
    /**
     * Calculate the next reminder time for a medication
     */
    private function calculateNextReminderTime($medication) {
        if ($medication['time_of_day']) {
            // Daily at specific time
            $today = date('Y-m-d') . ' ' . $medication['time_of_day'];
            $reminder_time = date('Y-m-d H:i:s', strtotime($today) - ($medication['reminder_offset_minutes'] * 60));
            
            // If time has passed today, schedule for tomorrow
            if (strtotime($reminder_time) < time()) {
                $reminder_time = date('Y-m-d H:i:s', strtotime($reminder_time) + 86400);
            }
            
            return $reminder_time;
        } elseif ($medication['interval_hours']) {
            // Interval-based - get last dose and calculate next
            $last_dose = fetchOne('
                SELECT taken_at FROM logs 
                WHERE medication_id = ? 
                ORDER BY taken_at DESC 
                LIMIT 1
            ', [$medication['id']]);
            
            if ($last_dose) {
                $next_dose = date('Y-m-d H:i:s', strtotime($last_dose['taken_at']) + ($medication['interval_hours'] * 3600));
                $reminder_time = date('Y-m-d H:i:s', strtotime($next_dose) - ($medication['reminder_offset_minutes'] * 60));
                
                // Only schedule if reminder time is in the future
                if (strtotime($reminder_time) > time()) {
                    return $reminder_time;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Schedule a reminder in the database
     */
    private function scheduleReminder($medication, $reminder_time) {
        // Check if reminder already exists for this time
        $existing = fetchOne('
            SELECT id FROM reminders 
            WHERE medication_id = ? 
            AND scheduled_time = ? 
            AND status = "pending"
        ', [$medication['id'], $reminder_time]);
        
        if (!$existing) {
            executeQuery('
                INSERT INTO reminders (medication_id, user_id, scheduled_time, reminder_type)
                VALUES (?, ?, ?, ?)
            ', [$medication['id'], $medication['user_id'], $reminder_time, 'email']);
        }
    }
    
    /**
     * Send pending reminders
     */
    public function sendPendingReminders() {
        $reminders = fetchAll('
            SELECT r.*, m.name as medication_name, m.dosage, u.name as user_name, u.email
            FROM reminders r
            JOIN medications m ON r.medication_id = m.id
            JOIN users u ON r.user_id = u.id
            WHERE r.status = "pending" 
            AND r.scheduled_time <= NOW()
            AND r.reminder_type = "email"
        ');
        
        foreach ($reminders as $reminder) {
            $this->sendReminder($reminder);
        }
    }
    
    /**
     * Send a single reminder
     */
    private function sendReminder($reminder) {
        try {
            $success = $this->emailService->sendMedicationReminder(
                $reminder['email'],
                $reminder['user_name'],
                $reminder['medication_name'],
                $reminder['dosage'],
                $reminder['scheduled_time']
            );
            
            if ($success) {
                // Mark as sent
                executeQuery('
                    UPDATE reminders 
                    SET status = "sent", sent_at = NOW() 
                    WHERE id = ?
                ', [$reminder['id']]);
                
                // Log email
                executeQuery('
                    INSERT INTO email_logs (user_id, email_address, subject, status)
                    VALUES (?, ?, ?, ?)
                ', [
                    $reminder['user_id'],
                    $reminder['email'],
                    "Medication Reminder: " . $reminder['medication_name'],
                    'sent'
                ]);
                
                logSecurityEvent('Email reminder sent', "User ID: {$reminder['user_id']}, Medication: {$reminder['medication_name']}");
            } else {
                throw new Exception('Email service returned false');
            }
        } catch (Exception $e) {
            // Mark as failed
            executeQuery('
                UPDATE reminders 
                SET status = "failed", error_message = ? 
                WHERE id = ?
            ', [$e->getMessage(), $reminder['id']]);
            
            // Log failed email
            executeQuery('
                INSERT INTO email_logs (user_id, email_address, subject, status, error_message)
                VALUES (?, ?, ?, ?, ?)
            ', [
                $reminder['user_id'],
                $reminder['email'],
                "Medication Reminder: " . $reminder['medication_name'],
                'failed',
                $e->getMessage()
            ]);
            
            logSecurityEvent('Email reminder failed', $e->getMessage());
        }
    }
    
    /**
     * Send verification email
     */
    public function sendVerificationEmail($user_id, $email, $user_name) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours
        
        executeQuery('
            UPDATE users 
            SET email_verification_token = ?, email_verification_expires = ? 
            WHERE id = ?
        ', [$token, $expires, $user_id]);
        
        try {
            $success = $this->emailService->sendVerificationEmail($email, $user_name, $token);
            
            if ($success) {
                logSecurityEvent('Verification email sent', "User ID: $user_id");
                return true;
            } else {
                throw new Exception('Email service returned false');
            }
        } catch (Exception $e) {
            logSecurityEvent('Verification email failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send a generic email
     */
    public function sendEmail($to_email, $subject, $body) {
        try {
            $success = $this->emailService->sendEmail($to_email, $subject, $body);
            
            if ($success) {
                logSecurityEvent('Email sent', "To: $to_email, Subject: $subject");
                return true;
            } else {
                throw new Exception('Email service returned false');
            }
        } catch (Exception $e) {
            logSecurityEvent('Email failed', "To: $to_email, Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify email token
     */
    public function verifyEmail($token) {
        $user = fetchOne('
            SELECT id, email_verification_expires 
            FROM users 
            WHERE email_verification_token = ?
        ', [$token]);
        
        if (!$user) {
            return false;
        }
        
        if (strtotime($user['email_verification_expires']) < time()) {
            return false; // Token expired
        }
        
        executeQuery('
            UPDATE users 
            SET email_verified = 1, email_verification_token = NULL, email_verification_expires = NULL 
            WHERE id = ?
        ', [$user['id']]);
        
        logSecurityEvent('Email verified', "User ID: {$user['id']}");
        return true;
    }
}
?> 