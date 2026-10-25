<?php
/**
 * Global Helper Functions & Utilities
 */
require_once __DIR__ . '/../config.php';

// Flash Notification Helpers
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function render_flash() {
    $flash = get_flash();
    if ($flash) {
        $type = htmlspecialchars($flash['type']);
        $msg = htmlspecialchars($flash['message']);
        $icon = $type === 'success' ? '✓' : ($type === 'danger' ? '⚠' : 'ℹ');
        echo "
        <div class='alert alert-{$type}'>
            <span class='alert-icon'>{$icon}</span>
            <div class='alert-content'>{$msg}</div>
            <button type='button' class='alert-close' onclick='this.parentElement.remove()'>&times;</button>
        </div>";
    }
}

// CSRF Protection Helpers
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Input Sanitization
function sanitize($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

// Categories List
function get_categories() {
    return [
        'electronics' => ['label' => 'Electronics & Gadgets', 'icon' => ''],
        'wallets'     => ['label' => 'Wallets & Purses',     'icon' => ''],
        'keys'        => ['label' => 'Keys & Keychains',     'icon' => ''],
        'documents'   => ['label' => 'Cards & Documents',    'icon' => ''],
        'clothing'    => ['label' => 'Clothing & Eyewear',   'icon' => ''],
        'pets'        => ['label' => 'Pets & Animals',       'icon' => ''],
        'books'       => ['label' => 'Books & Notebooks',    'icon' => ''],
        'jewelry'     => ['label' => 'Jewelry & Watches',    'icon' => ''],
        'other'       => ['label' => 'Other Belongings',     'icon' => '']
    ];
}

function get_category_label($key) {
    $cats = get_categories();
    return $cats[$key]['label'] ?? ucfirst($key);
}

function get_category_icon($key) {
    return '';
}

// Time Ago Formatter
function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $time);
}

// Secure Image Upload Handler
function handle_image_upload($file_input_name) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => null];
    }

    $file = $_FILES[$file_input_name];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . $file['error']];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'Image size exceeds maximum limit of 5MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed formats: JPG, PNG, WEBP, GIF.'];
    }

    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed_mimes)) {
        return ['success' => false, 'error' => 'The uploaded file is not a valid image format.'];
    }

    // Unique filename
    $new_filename = 'item_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destination = UPLOAD_DIR . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to save uploaded image. Check folder permissions.'];
    }

    return ['success' => true, 'filename' => $new_filename];
}
