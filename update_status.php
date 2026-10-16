<?php
/**
 * FindIt - Quick Status Update Handler (UPDATE Operation)
 * Toggles status between Active, Claimed, Returned, or Claim resolutions.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$pdo = getDbConnection();
$csrfToken = $_GET['csrf'] ?? $_POST['csrf_token'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    set_flash('danger', 'Security validation failed (invalid token).');
    header("Location: dashboard.php");
    exit;
}

// 1. Check if updating a Claim Status
if (isset($_GET['claim_id']) && isset($_GET['claim_status'])) {
    $claimId = filter_input(INPUT_GET, 'claim_id', FILTER_VALIDATE_INT);
    $newClaimStatus = $_GET['claim_status'];

    if (!in_array($newClaimStatus, ['approved', 'rejected', 'pending'])) {
        set_flash('danger', 'Invalid claim status requested.');
        header("Location: dashboard.php");
        exit;
    }

    // Verify user owns the item for this claim or is admin
    $claimStmt = $pdo->prepare("
        SELECT c.*, i.user_id 
        FROM claims c 
        JOIN items i ON c.item_id = i.id 
        WHERE c.id = :id
    ");
    $claimStmt->execute([':id' => $claimId]);
    $claim = $claimStmt->fetch();

    if (!$claim || (!is_admin() && $claim['user_id'] != current_user()['id'])) {
        set_flash('danger', 'Unauthorized to modify this claim.');
        header("Location: dashboard.php");
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE claims SET status = :status WHERE id = :id");
    $updateStmt->execute([':status' => $newClaimStatus, ':id' => $claimId]);

    // If approved, also mark the item as claimed
    if ($newClaimStatus === 'approved') {
        $pdo->prepare("UPDATE items SET status = 'claimed' WHERE id = :id")->execute([':id' => $claim['item_id']]);
        set_flash('success', 'Claim approved! Item has been marked as resolved.');
    } else {
        set_flash('info', 'Claim marked as ' . htmlspecialchars($newClaimStatus) . '.');
    }

    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
    exit;
}

// 2. Check if updating an Item Status
$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$newStatus = $_GET['status'] ?? '';

if (!$itemId || !in_array($newStatus, ['active', 'claimed', 'returned'])) {
    set_flash('danger', 'Invalid parameters provided for status update.');
    header("Location: dashboard.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
$stmt->execute([':id' => $itemId]);
$item = $stmt->fetch();

if (!$item || !can_manage_item($item)) {
    set_flash('danger', 'Unauthorized to update this item.');
    header("Location: dashboard.php");
    exit;
}

$updateStmt = $pdo->prepare("UPDATE items SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
$updateStmt->execute([':status' => $newStatus, ':id' => $itemId]);

set_flash('success', 'Item status successfully changed to "' . ucfirst($newStatus) . '".');
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? ('item.php?id=' . $itemId)));
exit;
