<?php
// Secure file upload handler for pill photos
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';

class UploadHandler {
    private $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    private $max_file_size = 5242880; // 5MB
    private $upload_dir;
    private $temp_dir;
    
    public function __construct() {
        // Use absolute paths
        $this->upload_dir = __DIR__ . '/../uploads/pills/';
        $this->temp_dir = __DIR__ . '/../uploads/temp/';
        
        // Ensure upload directories exist
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
        if (!is_dir($this->temp_dir)) {
            mkdir($this->temp_dir, 0755, true);
        }
    }
    
    /**
     * Handle pill photo upload
     */
    public function uploadPillPhoto($file, $user_id) {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'pill_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $extension;
            $filepath = $this->upload_dir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return ['success' => false, 'error' => 'Failed to save uploaded file'];
            }
            
            // Process image (resize if needed)
            $this->processImage($filepath);
            
            // Return success with file path
            return [
                'success' => true,
                'filename' => $filename,
                'url' => 'uploads/pills/' . $filename
            ];
            
        } catch (Exception $e) {
            error_log('Upload error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
                UPLOAD_ERR_PARTIAL => 'File upload was incomplete',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            return ['success' => false, 'error' => $errors[$file['error']] ?? 'Unknown upload error'];
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $this->allowed_types)) {
            return ['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, and WebP images are allowed.'];
        }
        
        // Additional security check
        if (!$this->isValidImage($file['tmp_name'])) {
            return ['success' => false, 'error' => 'Invalid image file'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Verify file is actually an image
     */
    private function isValidImage($filepath) {
        $image_info = getimagesize($filepath);
        if ($image_info === false) {
            return false;
        }
        
        // Check if it's a valid image type
        $valid_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
        return in_array($image_info[2], $valid_types);
    }
    
    /**
     * Process uploaded image (resize if needed)
     */
    private function processImage($filepath) {
        $image_info = getimagesize($filepath);
        if (!$image_info) {
            return false;
        }
        
        $width = $image_info[0];
        $height = $image_info[1];
        $type = $image_info[2];
        
        // If image is larger than 1200x1200, resize it
        $max_size = 1200;
        if ($width > $max_size || $height > $max_size) {
            $this->resizeImage($filepath, $type, $width, $height, $max_size);
        }
        
        return true;
    }
    
    /**
     * Resize image to fit within max dimensions
     */
    private function resizeImage($filepath, $type, $width, $height, $max_size) {
        // Calculate new dimensions
        if ($width > $height) {
            $new_width = $max_size;
            $new_height = floor($height * ($max_size / $width));
        } else {
            $new_height = $max_size;
            $new_width = floor($width * ($max_size / $height));
        }
        
        // Create new image
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        // Load original image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $original = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $original = imagecreatefrompng($filepath);
                // Preserve transparency
                imagealphablending($new_image, false);
                imagesavealpha($new_image, true);
                break;
            case IMAGETYPE_WEBP:
                $original = imagecreatefromwebp($filepath);
                break;
            default:
                return false;
        }
        
        if (!$original) {
            return false;
        }
        
        // Resize
        imagecopyresampled($new_image, $original, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        // Save resized image
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($new_image, $filepath, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($new_image, $filepath, 8);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($new_image, $filepath, 85);
                break;
        }
        
        // Clean up
        imagedestroy($original);
        imagedestroy($new_image);
        
        return true;
    }
    
    /**
     * Delete pill photo
     */
    public function deletePillPhoto($filename) {
        if (empty($filename)) {
            return true;
        }
        
        $filepath = $this->upload_dir . basename($filename);
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        
        return true;
    }
    
    /**
     * Get photo URL
     */
    public function getPhotoUrl($filename) {
        if (empty($filename)) {
            return null;
        }
        
        return 'uploads/pills/' . basename($filename);
    }
}
?> 