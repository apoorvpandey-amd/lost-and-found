<?php
/**
 * Database Connection & Initialization Helper
 */
require_once __DIR__ . '/config.php';

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
            <h2 style='margin-top:0;'>⚠️ Database Connection Failed</h2>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p>Please make sure the SQLite database or MySQL server is properly configured in <code>config.php</code>.</p>
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
            type TEXT NOT NULL,               -- 'lost' or 'found'
            title TEXT NOT NULL,
            category TEXT NOT NULL,
            description TEXT NOT NULL,
            location TEXT NOT NULL,
            item_date TEXT NOT NULL,
            image_path TEXT,
            contact_name TEXT NOT NULL,
            contact_email TEXT NOT NULL,
            contact_phone TEXT,
            status TEXT DEFAULT 'active',     -- 'active', 'claimed', 'returned'
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
            status TEXT DEFAULT 'pending',    -- 'pending', 'approved', 'rejected'
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
    // Seed Admin & Regular User
    // Admin: admin@findit.local / admin123
    // Regular: john@example.com / password123
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $userPassword = password_hash('password123', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['System Administrator', 'admin@findit.local', $adminPassword, 'admin', '+1 (555) 000-1111']);
    $adminId = $pdo->lastInsertId();

    $stmt->execute(['John Doe', 'john@example.com', $userPassword, 'user', '+1 (555) 234-5678']);
    $johnId = $pdo->lastInsertId();

    // Seed Realistic Items
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
            'description' => 'Found in a protective hard case on table 4 at the main café. Gold frames with polarized dark lenses.',
            'location' => 'Main Street Coffee Bar',
            'item_date' => date('Y-m-d', strtotime('-4 days')),
            'image_path' => '',
            'contact_name' => 'Café Manager',
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

    // Seed a sample claim on the iPhone item
    $firstItem = $pdo->query("SELECT id FROM items WHERE title LIKE '%iPhone%' LIMIT 1")->fetch();
    if ($firstItem) {
        $claimStmt = $pdo->prepare("
            INSERT INTO claims (item_id, claimant_id, claimant_name, claimant_email, claimant_phone, claim_message, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $claimStmt->execute([
            $firstItem['id'],
            $adminId,
            'Alex Morgan',
            'alex.morgan@example.com',
            '+1 (555) 998-1122',
            'I lost my blue iPhone 13 there! The lock screen wallpaper is a picture of a sunset in Maui, and the passcode starts with 49.',
            'pending'
        ]);
    }
}
