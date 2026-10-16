<?php
/**
 * FindIt - Delete Item Handler (DELETE Operation)
 * Verifies authorization, CSRF token, cleans up media files, and deletes item from database.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth.php';

require_login(); // Must be logged in

$pdo = getDbConnection();
$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$csrfToken = $_GET['csrf'] ?? $_POST['csrf_token'] ?? '';

// 1. Verify CSRF Token
if (!verify_csrf_token($csrfToken)) {
    set_flash('danger', 'Security validation failed (invalid token). Unable to delete item.');
    header("Location: dashboard.php");
    exit;
}

if (!$itemId) {
    set_flash('danger', 'Invalid item identifier.');
    header("Location: dashboard.php");
    exit;
}

// 2. Fetch item to verify ownership
$stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
$stmt->execute([':id' => $itemId]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('danger', 'Item not found or already deleted.');
    header("Location: dashboard.php");
    exit;
}

// 3. Authorization check
if (!can_manage_item($item)) {
    set_flash('danger', 'Unauthorized. You do not have permission to delete this report.');
    header("Location: item.php?id=" . $itemId);
    exit;
}

// 4. Remove image file from server if it exists
if (!empty($item['image_path']) && file_exists(UPLOAD_DIR . $item['image_path'])) {
    @unlink(UPLOAD_DIR . $item['image_path']);
}

// 5. Execute DELETE from database (claims will cascade or delete)
$deleteStmt = $pdo->prepare("DELETE FROM items WHERE id = :id");
$success = $deleteStmt->execute([':id' => $itemId]);

if ($success) {
    set_flash('success', 'The item "' . htmlspecialchars($item['title']) . '" has been permanently removed.');
} else {
    set_flash('danger', 'Could not delete the item from database.');
}

// Redirect appropriately
$referer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
if (strpos($referer, 'item.php') !== false) {
    header("Location: browse.php");
} else {
    header("Location: dashboard.php");
}
exit;
