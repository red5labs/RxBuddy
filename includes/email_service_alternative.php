<?php
// Alternative Email Service - Works with various email providers
// This version can work with Gmail, Outlook, or local SMTP servers

// Use PHPMailer for reliable email sending
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class AlternativeEmailService {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name = 'RxBuddy';
    private $use_smtp = false;
    
    public function __construct() {
        // Load configuration
        require_once __DIR__ . '/../config.php';
        
        // Check if Gmail settings are configured
        if (defined('GMAIL_USERNAME') && defined('GMAIL_APP_PASSWORD')) {
            $this->use_smtp = true;
            $this->smtp_host = 'smtp.gmail.com';
            $this->smtp_port = 587;
            $this->smtp_username = GMAIL_USERNAME;
            $this->smtp_password = GMAIL_APP_PASSWORD;
            $this->from_email = GMAIL_USERNAME;
        } elseif (defined('SMTP_HOST') && defined('SMTP_USERNAME') && defined('SMTP_PASSWORD')) {
            // Alternative SMTP settings
            $this->use_smtp = true;
            $this->smtp_host = SMTP_HOST;
            $this->smtp_port = defined('SMTP_PORT') ? SMTP_PORT : 587;
            $this->smtp_username = SMTP_USERNAME;
            $this->smtp_password = SMTP_PASSWORD;
            $this->from_email = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : SMTP_USERNAME;
        } else {
            // Fallback to basic mail() function
            $this->from_email = defined('EMAIL_FROM_ADDRESS') ? EMAIL_FROM_ADDRESS : 'noreply@rxbuddy.com';
        }
        
        $this->from_name = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'RxBuddy';
    }
    
    public function sendMedicationReminder($user_email, $user_name, $medication_name, $dosage, $scheduled_time) {
        $subject = "Medication Reminder: $medication_name";
        $body = $this->getReminderEmailTemplate($user_name, $medication_name, $dosage, $scheduled_time);
        
        return $this->sendEmail($user_email, $subject, $body);
    }
    
    public function sendVerificationEmail($user_email, $user_name, $verification_token) {
        $subject = "Verify Your RxBuddy Email";
        $body = $this->getVerificationEmailTemplate($user_name, $verification_token);
        
        return $this->sendEmail($user_email, $subject, $body);
    }
    
    public function sendEmail($to_email, $subject, $body) {
        if ($this->use_smtp) {
            return $this->sendEmailViaSMTP($to_email, $subject, $body);
        } else {
            return $this->sendEmailViaMail($to_email, $subject, $body);
        }
    }
    
    private function sendEmailViaSMTP($to_email, $subject, $body) {
        try {
            // Create a new PHPMailer instance
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_port;
            
            // Recipients
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to_email);
            $mail->addReplyTo($this->from_email, $this->from_name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            // Send email
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendEmailViaMail($to_email, $subject, $body) {
        try {
            // Use PHPMailer even for basic mail() fallback
            $mail = new PHPMailer(true);
            
            // Use basic mail() settings
            $mail->isMail();
            
            // Recipients
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to_email);
            $mail->addReplyTo($this->from_email, $this->from_name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            // Send email
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function getReminderEmailTemplate($user_name, $medication_name, $dosage, $scheduled_time) {
        $formatted_time = date('g:i A', strtotime($scheduled_time));
        $login_url = defined('APP_URL') ? APP_URL . '/index.php?page=dashboard' : 'http://localhost/rxbuddy/index.php?page=dashboard';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4F46E5; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9fafb; }
                .medication-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #10B981; }
                .button { display: inline-block; background: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; }
                .footer { text-align: center; padding: 20px; color: #6B7280; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>RxBuddy</h1>
                    <p>Medication Reminder</p>
                </div>
                
                <div class='content'>
                    <h2>Hi $user_name,</h2>
                    <p>It's time to take your medication!</p>
                    
                    <div class='medication-box'>
                        <h3>$medication_name</h3>
                        <p><strong>Dosage:</strong> $dosage</p>
                        <p><strong>Scheduled for:</strong> $formatted_time</p>
                    </div>
                    
                    <p>Please log in to RxBuddy to mark this dose as taken.</p>
                    
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='$login_url' class='button'>Mark as Taken</a>
                    </p>
                    
                    <p>If you've already taken this medication, you can ignore this reminder.</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated reminder from RxBuddy.</p>
                    <p>You can manage your reminder settings in your profile.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getVerificationEmailTemplate($user_name, $verification_token) {
        $verification_url = defined('APP_URL') ? APP_URL . '/verify-email.php?token=' . $verification_token : 'http://localhost/rxbuddy/verify-email.php?token=' . $verification_token;
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4F46E5; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9fafb; }
                .button { display: inline-block; background: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; }
                .footer { text-align: center; padding: 20px; color: #6B7280; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>RxBuddy</h1>
                    <p>Email Verification</p>
                </div>
                
                <div class='content'>
                    <h2>Hi $user_name,</h2>
                    <p>Please verify your email address to enable medication reminders.</p>
                    
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='$verification_url' class='button'>Verify Email</a>
                    </p>
                    
                    <p>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #6B7280;'>$verification_url</p>
                </div>
                
                <div class='footer'>
                    <p>This verification link will expire in 24 hours.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?> 