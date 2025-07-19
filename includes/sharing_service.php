<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../config.php';

class SharingService {
    
    /**
     * Create a new shared profile
     */
    public function createSharedProfile($user_id, $share_name, $share_email, $share_type, $permissions, $expires_at = null) {
        // Validate inputs
        if (empty($share_name) || empty($share_email) || empty($share_type)) {
            throw new Exception('All fields are required');
        }
        
        if (!filter_var($share_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }
        
        if (!in_array($share_type, ['caregiver', 'family', 'healthcare_provider', 'other'])) {
            throw new Exception('Invalid share type');
        }
        
        // Generate unique share token
        $share_token = $this->generateShareToken();
        
        // Validate permissions structure
        $valid_permissions = $this->validatePermissions($permissions);
        
        try {
            executeQuery('
                INSERT INTO shared_profiles (user_id, share_name, share_email, share_type, permissions, share_token, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ', [
                $user_id,
                sanitizeInput($share_name, 'string'),
                sanitizeInput($share_email, 'email'),
                $share_type,
                json_encode($valid_permissions),
                $share_token,
                $expires_at
            ]);
            
            // Send email notification to recipient
            $this->sendShareNotification($user_id, $share_name, $share_email, $share_type, $valid_permissions, $share_token, $expires_at);
            
            return $share_token;
        } catch (Exception $e) {
            throw new Exception('Failed to create shared profile: ' . $e->getMessage());
        }
    }
    
    /**
     * Get shared profiles for a user
     */
    public function getSharedProfiles($user_id) {
        return fetchAll('
            SELECT * FROM shared_profiles 
            WHERE user_id = ? AND is_active = 1
            ORDER BY created_at DESC
        ', [$user_id]);
    }
    
    /**
     * Get shared profile by token
     */
    public function getSharedProfileByToken($share_token) {
        $profile = fetchOne('
            SELECT sp.*, u.name as user_name, u.email as user_email
            FROM shared_profiles sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.share_token = ? AND sp.is_active = 1
            AND (sp.expires_at IS NULL OR sp.expires_at > NOW())
        ', [$share_token]);
        
        if ($profile) {
            $profile['permissions'] = json_decode($profile['permissions'], true);
        }
        
        return $profile;
    }
    
    /**
     * Check if a shared profile is active and not expired
     */
    public function isShareActive($shared_profile) {
        if (!$shared_profile || !$shared_profile['is_active']) {
            return false;
        }
        
        // Check if expired
        if ($shared_profile['expires_at'] && strtotime($shared_profile['expires_at']) < time()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Update shared profile
     */
    public function updateSharedProfile($share_id, $user_id, $share_name, $share_email, $share_type, $permissions, $expires_at = null) {
        // Validate ownership
        $existing = fetchOne('SELECT id FROM shared_profiles WHERE id = ? AND user_id = ?', [$share_id, $user_id]);
        if (!$existing) {
            throw new Exception('Shared profile not found');
        }
        
        // Validate inputs
        if (empty($share_name) || empty($share_email) || empty($share_type)) {
            throw new Exception('All fields are required');
        }
        
        if (!filter_var($share_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }
        
        if (!in_array($share_type, ['caregiver', 'family', 'healthcare_provider', 'other'])) {
            throw new Exception('Invalid share type');
        }
        
        // Validate permissions structure
        $valid_permissions = $this->validatePermissions($permissions);
        
        try {
            executeQuery('
                UPDATE shared_profiles 
                SET share_name = ?, share_email = ?, share_type = ?, permissions = ?, expires_at = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ', [
                sanitizeInput($share_name, 'string'),
                sanitizeInput($share_email, 'email'),
                $share_type,
                json_encode($valid_permissions),
                $expires_at,
                $share_id,
                $user_id
            ]);
            
            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to update shared profile: ' . $e->getMessage());
        }
    }
    
    /**
     * Deactivate shared profile
     */
    public function deactivateSharedProfile($share_id, $user_id) {
        try {
            executeQuery('
                UPDATE shared_profiles 
                SET is_active = 0, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ', [$share_id, $user_id]);
            
            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to deactivate shared profile: ' . $e->getMessage());
        }
    }
    
    /**
     * Log access to shared profile
     */
    public function logAccess($shared_profile_id, $ip_address = null, $user_agent = null) {
        try {
            executeQuery('
                INSERT INTO shared_profile_access (shared_profile_id, ip_address, user_agent)
                VALUES (?, ?, ?)
            ', [$shared_profile_id, $ip_address, $user_agent]);
            
            return true;
        } catch (Exception $e) {
            // Don't throw error for logging failures
            error_log('Failed to log shared profile access: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get access logs for a shared profile
     */
    public function getAccessLogs($share_id, $user_id) {
        return fetchAll('
            SELECT spa.* FROM shared_profile_access spa
            JOIN shared_profiles sp ON spa.shared_profile_id = sp.id
            WHERE sp.id = ? AND sp.user_id = ?
            ORDER BY spa.accessed_at DESC
            LIMIT 50
        ', [$share_id, $user_id]);
    }
    
    /**
     * Generate unique share token
     */
    private function generateShareToken() {
        do {
            $token = bin2hex(random_bytes(32));
            $exists = fetchOne('SELECT id FROM shared_profiles WHERE share_token = ?', [$token]);
        } while ($exists);
        
        return $token;
    }
    
    /**
     * Validate permissions structure
     */
    private function validatePermissions($permissions) {
        $default_permissions = [
            'view_medications' => false,
            'view_logs' => false,
            'view_calendar' => false,
            'receive_alerts' => false
        ];
        
        if (is_array($permissions)) {
            return array_merge($default_permissions, $permissions);
        }
        
        return $default_permissions;
    }
    
    /**
     * Create emergency contact
     */
    public function createEmergencyContact($user_id, $name, $relationship, $phone = null, $email = null, $is_primary = false) {
        if (empty($name) || empty($relationship)) {
            throw new Exception('Name and relationship are required');
        }
        
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }
        
        try {
            // If this is primary, unset other primary contacts
            if ($is_primary) {
                executeQuery('UPDATE emergency_contacts SET is_primary = 0 WHERE user_id = ?', [$user_id]);
            }
            
            executeQuery('
                INSERT INTO emergency_contacts (user_id, name, relationship, phone, email, is_primary)
                VALUES (?, ?, ?, ?, ?, ?)
            ', [
                $user_id,
                sanitizeInput($name, 'string'),
                sanitizeInput($relationship, 'string'),
                $phone ? sanitizeInput($phone, 'string') : null,
                $email ? sanitizeInput($email, 'email') : null,
                $is_primary ? 1 : 0
            ]);
            
            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to create emergency contact: ' . $e->getMessage());
        }
    }
    
    /**
     * Get emergency contacts for a user
     */
    public function getEmergencyContacts($user_id) {
        return fetchAll('
            SELECT * FROM emergency_contacts 
            WHERE user_id = ?
            ORDER BY is_primary DESC, name ASC
        ', [$user_id]);
    }
    
    /**
     * Update emergency contact
     */
    public function updateEmergencyContact($contact_id, $user_id, $name, $relationship, $phone = null, $email = null, $is_primary = false) {
        // Validate ownership
        $existing = fetchOne('SELECT id FROM emergency_contacts WHERE id = ? AND user_id = ?', [$contact_id, $user_id]);
        if (!$existing) {
            throw new Exception('Emergency contact not found');
        }
        
        if (empty($name) || empty($relationship)) {
            throw new Exception('Name and relationship are required');
        }
        
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }
        
        try {
            // If this is primary, unset other primary contacts
            if ($is_primary) {
                executeQuery('UPDATE emergency_contacts SET is_primary = 0 WHERE user_id = ? AND id != ?', [$user_id, $contact_id]);
            }
            
            executeQuery('
                UPDATE emergency_contacts 
                SET name = ?, relationship = ?, phone = ?, email = ?, is_primary = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ', [
                sanitizeInput($name, 'string'),
                sanitizeInput($relationship, 'string'),
                $phone ? sanitizeInput($phone, 'string') : null,
                $email ? sanitizeInput($email, 'email') : null,
                $is_primary ? 1 : 0,
                $contact_id,
                $user_id
            ]);
            
            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to update emergency contact: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete emergency contact
     */
    public function deleteEmergencyContact($contact_id, $user_id) {
        try {
            executeQuery('DELETE FROM emergency_contacts WHERE id = ? AND user_id = ?', [$contact_id, $user_id]);
            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to delete emergency contact: ' . $e->getMessage());
        }
    }
    
    /**
     * Get shared profile URL
     */
    public function getShareUrl($share_token) {
        // Use APP_URL from config if available, otherwise fallback to detection
        if (defined('APP_URL')) {
            return APP_URL . '/share.php?token=' . $share_token;
        }
        
        // Fallback: detect the path
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        
        // Get the current script path to determine the directory
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        $path_parts = explode('/', trim($script_name, '/'));
        
        // Remove the current file name (e.g., 'index.php') to get the directory
        array_pop($path_parts);
        
        // Build the path to the RxBuddy directory
        $rxbuddy_path = !empty($path_parts) ? '/' . implode('/', $path_parts) : '';
        
        return $base_url . $rxbuddy_path . '/share.php?token=' . $share_token;
    }
    
    /**
     * Send email notification for shared profile
     */
    private function sendShareNotification($user_id, $share_name, $share_email, $share_type, $permissions, $share_token, $expires_at) {
        try {
            // Get user information
            $user = fetchOne('SELECT name, email FROM users WHERE id = ?', [$user_id]);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Get share URL
            $share_url = $this->getShareUrl($share_token);
            
            // Build permissions list
            $permission_list = [];
            if ($permissions['view_medications']) $permission_list[] = 'View medications and dosages';
            if ($permissions['view_logs']) $permission_list[] = 'View dose logs and history';
            if ($permissions['view_calendar']) $permission_list[] = 'View calendar and adherence';
            if ($permissions['receive_alerts']) $permission_list[] = 'Receive medication alerts';
            
            if (empty($permission_list)) {
                $permission_list[] = 'No specific permissions granted';
            }
            
            // Email subject
            $subject = $user['name'] . ' has shared their RxBuddy profile with you';
            
            // Email body
            $body = $this->buildShareEmailBody($user['name'], $share_name, $share_type, $permission_list, $share_url, $expires_at);
            
            // Send email using the reminder scheduler's email service
            require_once __DIR__ . '/reminder_scheduler.php';
            $scheduler = new ReminderScheduler();
            
            if ($scheduler->sendEmail($share_email, $subject, $body)) {
                // Log successful email
                error_log("Share notification email sent to: $share_email for user: $user_id");
            } else {
                // Log email failure but don't throw exception
                error_log("Failed to send share notification email to: $share_email for user: $user_id");
            }
            
        } catch (Exception $e) {
            // Log error but don't throw exception to avoid breaking the share creation
            error_log('Error sending share notification email: ' . $e->getMessage());
        }
    }
    
    /**
     * Build email body for share notification
     */
    private function buildShareEmailBody($user_name, $share_name, $share_type, $permission_list, $share_url, $expires_at) {
        $expires_text = $expires_at ? 'This link will expire on ' . date('F j, Y', strtotime($expires_at)) . '.' : 'This link does not expire.';
        
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h1 style='margin: 0; font-size: 24px;'>RxBuddy Profile Shared</h1>
            </div>
            
            <div style='background-color: #f9fafb; padding: 20px; border-radius: 0 0 8px 8px;'>
                <p style='font-size: 16px; color: #374151; margin-bottom: 20px;'>
                    Hello {$share_name},
                </p>
                
                <p style='font-size: 16px; color: #374151; margin-bottom: 20px;'>
                    <strong>{$user_name}</strong> has shared their RxBuddy medication profile with you as their <strong>" . ucfirst($share_type) . "</strong>.
                </p>
                
                <div style='background-color: white; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #4F46E5;'>
                    <h3 style='margin: 0 0 10px 0; color: #374151;'>What you can access:</h3>
                    <ul style='margin: 0; padding-left: 20px; color: #6B7280;'>
        ";
        
        foreach ($permission_list as $permission) {
            $body .= "<li style='margin-bottom: 5px;'>{$permission}</li>";
        }
        
        $body .= "
                    </ul>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$share_url}' style='background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>
                        View Shared Profile
                    </a>
                </div>
                
                <p style='font-size: 14px; color: #6B7280; margin-bottom: 10px;'>
                    <strong>Important:</strong> {$expires_text}
                </p>
                
                <p style='font-size: 14px; color: #6B7280; margin-bottom: 10px;'>
                    This is a read-only view of {$user_name}'s medication information. You cannot make changes to their profile.
                </p>
                
                <p style='font-size: 14px; color: #6B7280; margin-bottom: 20px;'>
                    If you have any questions, please contact {$user_name} directly.
                </p>
                
                <hr style='border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;'>
                
                <p style='font-size: 12px; color: #9CA3AF; text-align: center; margin: 0;'>
                    This email was sent from RxBuddy - Personal Medication Tracking
                </p>
            </div>
        </div>
        ";
        
        return $body;
    }
}
?> 