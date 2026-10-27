<?php
/**
 * FindIt - Core Database, Configuration, Authentication & Helper Services
 * Consolidates configuration, database operations, session authentication,
 * utility functions, and layout renderers into a unified, high-performance module.
 */

// 1. Error Reporting & Environment Settings
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Application Constants
define('APP_NAME', 'FindIt Portal');
define('APP_TAGLINE', 'Reconnecting people with their lost belongings');
define('APP_URL', '');

// Database Configuration (Default: SQLite for zero-configuration, MySQL optional)
define('DB_DRIVER', 'sqlite');
define('DB_HOST', 'localhost');
define('DB_NAME', 'lost_and_found');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);
define('SQLITE_FILE', __DIR__ . '/lost_and_found.db');

// Upload directory & file upload limits
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_WEB_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp', 'gif']);

// Ensure uploads folder exists
if (!file_exists(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0777, true);
}

// 3. Start PHP Session Safely
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// ==========================================
// DATABASE CONNECTION & INITIALIZATION
// ==========================================

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'sqlite') {
            $pdo = new PDO('sqlite:' . SQLITE_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA foreign_keys = ON;");
            initSqliteDatabase($pdo);
        } else {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        }
    } catch (PDOException $e) {
        die("<div style='font-family:sans-serif;padding:24px;background:#fef2f2;border:1px solid #f87171;color:#991b1b;border-radius:10px;margin:30px auto;max-width:650px;box-shadow:0 4px 6px rgba(0,0,0,0.05);'>
            <h2 style='margin-top:0;'>Database Connection Failed</h2>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p>Please make sure SQLite or MySQL is properly configured.</p>
        </div>");
    }

    return $pdo;
}

function initSqliteDatabase($pdo) {
    // 1. Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            phone TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 2. Create items table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            type TEXT NOT NULL,
            title TEXT NOT NULL,
            category TEXT NOT NULL,
            description TEXT NOT NULL,
            location TEXT NOT NULL,
            item_date TEXT NOT NULL,
            image_path TEXT,
            contact_name TEXT NOT NULL,
            contact_email TEXT NOT NULL,
            contact_phone TEXT,
            status TEXT DEFAULT 'active',
            security_secret TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        );
    ");

    // 3. Create claims table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS claims (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id INTEGER NOT NULL,
            claimant_id INTEGER,
            claimant_name TEXT NOT NULL,
            claimant_email TEXT NOT NULL,
            claimant_phone TEXT,
            claim_message TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
            FOREIGN KEY (claimant_id) REFERENCES users(id) ON DELETE SET NULL
        );
    ");

    // Seed default users if empty
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount == 0) {
        seedUsersAndData($pdo);
    }
}

function seedUsersAndData($pdo) {
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $userPassword = password_hash('password123', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['System Administrator', 'admin@findit.local', $adminPassword, 'admin', '+1 (555) 000-1111']);
    $adminId = $pdo->lastInsertId();

    $stmt->execute(['John Doe', 'john@example.com', $userPassword, 'user', '+1 (555) 234-5678']);
    $johnId = $pdo->lastInsertId();

    // Sample Items
    $items = [
        [
            'user_id' => $johnId,
            'type' => 'found',
            'title' => 'Apple iPhone 13 (Midnight Blue)',
            'category' => 'electronics',
            'description' => 'Found on a wooden bench near the central courtyard gazebo. Has a transparent silicone case with a holographic sticker. Battery is charged, currently stored safely.',
            'location' => 'Central Courtyard - East Pavilion',
            'item_date' => date('Y-m-d', strtotime('-2 days')),
            'image_path' => '',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com',
            'contact_phone' => '+1 (555) 234-5678',
            'status' => 'active',
            'security_secret' => bin2hex(random_bytes(8))
        ],
        [
            'user_id' => $johnId,
            'type' => 'lost',
            'title' => 'Brown Leather Bi-fold Wallet',
            'category' => 'wallets',
            'description' => 'Lost my vintage brown leather wallet during lunch hour. Contains driver license under name John Doe, metro pass, and some family photos. Huge sentimental value.',
            'location' => 'University Cafeteria, Booth 12',
            'item_date' => date('Y-m-d', strtotime('-3 days')),
            'image_path' => '',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com',
            'contact_phone' => '+1 (555) 234-5678',
            'status' => 'active',
            'security_secret' => bin2hex(random_bytes(8))
        ],
        [
            'user_id' => $adminId,
            'type' => 'found',
            'title' => 'Set of Car Keys with Honda Keychain',
            'category' => 'keys',
            'description' => 'Found black key fob with Honda logo and two silver keys attached to a red lanyard in parking sector B.',
            'location' => 'North Parking Garage Level 2',
            'item_date' => date('Y-m-d', strtotime('-1 day')),
            'image_path' => '',
            'contact_name' => 'Security Front Desk',
            'contact_email' => 'admin@findit.local',
            'contact_phone' => '+1 (555) 000-1111',
            'status' => 'active',
            'security_secret' => bin2hex(random_bytes(8))
        ],
        [
            'user_id' => $adminId,
            'type' => 'lost',
            'title' => 'Golden Retriever Puppy (Buddy)',
            'category' => 'pets',
            'description' => 'Buddy is 8 months old, wearing a blue collar with a bell. Last seen near Oakridge Park entrance. Very energetic and friendly.',
            'location' => 'Oakridge Neighborhood / Pine St',
            'item_date' => date('Y-m-d', strtotime('-1 day')),
            'image_path' => '',
            'contact_name' => 'Elena Rostova',
            'contact_email' => 'elena@example.com',
            'contact_phone' => '+1 (555) 012-7788',
            'status' => 'active',
            'security_secret' => bin2hex(random_bytes(8))
        ],
        [
            'user_id' => $adminId,
            'type' => 'found',
            'title' => 'Black Ray-Ban Aviator Sunglasses',
            'category' => 'clothing',
            'description' => 'Found in a protective hard case on table 4 at the main cafe. Gold frames with polarized dark lenses.',
            'location' => 'Main Street Coffee Bar',
            'item_date' => date('Y-m-d', strtotime('-4 days')),
            'image_path' => '',
            'contact_name' => 'Cafe Manager',
            'contact_email' => 'cafe@example.com',
            'contact_phone' => '+1 (555) 018-9900',
            'status' => 'active',
            'security_secret' => bin2hex(random_bytes(8))
        ],
        [
            'user_id' => $johnId,
            'type' => 'found',
            'title' => 'Calculus & Linear Algebra Textbook (9th Ed)',
            'category' => 'books',
            'description' => 'Hardcover university math textbook left on row 3 desk. Has yellow highlighter markings in Chapter 4.',
            'location' => 'Science Complex, Lecture Hall B',
            'item_date' => date('Y-m-d', strtotime('-5 days')),
            'image_path' => '',
            'contact_name' => 'Department Helpdesk',
            'contact_email' => 'helpdesk@example.com',
            'contact_phone' => '+1 (555) 011-3322',
            'status' => 'claimed',
            'security_secret' => bin2hex(random_bytes(8))
        ]
    ];

    $itemStmt = $pdo->prepare("
        INSERT INTO items (user_id, type, title, category, description, location, item_date, image_path, contact_name, contact_email, contact_phone, status, security_secret)
        VALUES (:user_id, :type, :title, :category, :description, :location, :item_date, :image_path, :contact_name, :contact_email, :contact_phone, :status, :security_secret)
    ");

    foreach ($items as $item) {
        $itemStmt->execute($item);
    }
}

// ==========================================
// AUTHENTICATION & ACCESS CONTROL HELPERS
// ==========================================

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role'] ?? 'user'
    ];
}

function is_admin() {
    return is_logged_in() && (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
}

/**
 * Enforces compulsory login. Redirects unauthenticated visitors to login.php.
 */
function require_login($redirect = null) {
    if (!is_logged_in()) {
        $target = $redirect ? urlencode($redirect) : urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        set_flash('danger', 'You must log in to access this page.');
        header("Location: login.php?redirect=" . $target);
        exit;
    }
}

/**
 * Enforces admin-only access.
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'Access denied. Administrator privileges required.');
        header("Location: index.php");
        exit;
    }
}

function login_user($user) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'] ?? 'user';
}

function logout_user() {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE && ini_get("session.use_cookies") && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Checks whether the current user is permitted to manage (edit/delete/update status) an item.
 */
function can_manage_item($item) {
    if (!is_logged_in()) {
        return false;
    }
    $user = current_user();
    if ($user['role'] === 'admin') {
        return true;
    }
    return isset($item['user_id']) && ((int)$item['user_id'] === (int)$user['id']);
}

// ==========================================
// FLASH NOTIFICATIONS & CSRF SECURITY
// ==========================================

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
        $icon = $type === 'success' ? '✓' : ($type === 'danger' ? '!' : 'i');
        echo "
        <div class='alert alert-{$type}'>
            <span class='alert-icon'>{$icon}</span>
            <div class='alert-content'>{$msg}</div>
            <button type='button' class='alert-close' onclick='this.parentElement.remove()'>&times;</button>
        </div>";
    }
}

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

function sanitize($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

// ==========================================
// CATEGORIES & FORMATTING UTILITIES
// ==========================================

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

function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $time);
}

function handle_image_upload($file_input_name) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => null];
    }

    $file = $_FILES[$file_input_name];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . $file['error']];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File size exceeds maximum limit of 5MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Invalid file format. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS)];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowedMimes)) {
        return ['success' => false, 'error' => 'Invalid image content type.'];
    }

    $uniqueName = 'item_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $targetPath = UPLOAD_DIR . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded image to destination directory.'];
    }

    return ['success' => true, 'filename' => $uniqueName];
}

// ==========================================
// GLOBAL LAYOUT RENDERERS (Header & Footer)
// ==========================================

function render_header($pageTitle = '', $activeTab = '') {
    $fullTitle = !empty($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' . APP_NAME : APP_NAME;
    $user = current_user();
    $currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $fullTitle ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Main Navigation Header -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">F</div>
                <span>FindIt</span>
            </a>

            <!-- Mobile Navigation Toggle -->
            <button class="mobile-toggle" id="mobileMenuBtn" aria-label="Toggle navigation">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="nav-menu" id="navMenu">
                <ul class="nav-links">
                    <li>
                        <a href="index.php" class="nav-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">Home</a>
                    </li>
                    <li>
                        <a href="browse.php" class="nav-link <?= ($currentPage === 'browse.php') ? 'active' : '' ?>">Browse & Search</a>
                    </li>
                    <li>
                        <a href="report.php" class="btn btn-primary btn-sm">
                            <span>+</span> Report an Item
                        </a>
                    </li>

                    <?php if (is_logged_in()): ?>
                        <li>
                            <a href="dashboard.php" class="nav-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                                My Dashboard
                            </a>
                        </li>
                        <?php if (is_admin()): ?>
                            <li>
                                <a href="admin.php" class="nav-link <?= ($currentPage === 'admin.php') ? 'active' : '' ?>">
                                    <span class="admin-badge">Admin</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="user-pill-container">
                            <span class="user-greeting">Hi, <strong><?= htmlspecialchars($user['name']) ?></strong></span>
                            <a href="login.php?action=logout" class="btn btn-secondary btn-sm" title="Log Out">Log Out</a>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="login.php" class="nav-link <?= ($currentPage === 'login.php' && (!isset($_GET['action']) || $_GET['action'] !== 'register')) ? 'active' : '' ?>">Sign In</a>
                        </li>
                        <li>
                            <a href="login.php?action=register" class="btn btn-secondary btn-sm <?= ($currentPage === 'login.php' && isset($_GET['action']) && $_GET['action'] === 'register') ? 'active' : '' ?>">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Global Notification Banner Container -->
    <div class="flash-wrapper">
        <?php render_flash(); ?>
    </div>
    <?php
}

function render_footer() {
    $categories = get_categories();
    ?>
    <!-- Global Footer -->
    <footer class="footer">
        <div class="footer-top">
            <div class="footer-col">
                <div class="footer-logo">
                    <span class="logo-icon">F</span>
                    <strong>FindIt Portal</strong>
                </div>
                <p class="footer-tagline">
                    An open community platform dedicated to helping people recover lost items, report discovered belongings, and connect securely.
                </p>
            </div>

            <div class="footer-col">
                <h4>Quick Navigation</h4>
                <ul class="footer-links">
                    <li><a href="index.php">Home Feed</a></li>
                    <li><a href="browse.php">Search &amp; Browse Catalog</a></li>
                    <li><a href="report.php">Report Lost/Found Item</a></li>
                    <li><a href="dashboard.php">User Dashboard</a></li>
                    <?php if (is_admin()): ?>
                        <li><a href="admin.php">Admin Management</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Top Categories</h4>
                <ul class="footer-links">
                    <?php 
                    $counter = 0;
                    foreach ($categories as $slug => $cat): 
                        if (++$counter > 5) break;
                    ?>
                        <li>
                            <a href="browse.php?category=<?= urlencode($slug) ?>">
                                <?= htmlspecialchars($cat['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Safety &amp; Security</h4>
                <p class="footer-text">
                    Always arrange exchanges in public, well-lit spaces like security desks or public lobbies. Verify ownership proof before handing over valuables.
                </p>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-container">
                <p>&copy; <?= date('Y') ?> FindIt Lost &amp; Found Portal. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="browse.php?type=lost">Lost Items</a>
                    <a href="browse.php?type=found">Found Items</a>
                    <a href="browse.php?type=claimed">Resolved</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Global Scripts -->
    <script src="js/validation.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
    <?php
}
