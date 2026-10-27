<?php
/**
 * FindIt - Item Details, Management & Claims Resolution Module
 * Consolidates item viewing, claim processing, status updates, and deletions.
 * Enforces compulsory login to protect member data and ensure verified ownership changes.
 */
require_once __DIR__ . '/db.php';

// Enforce Compulsory Login
require_login();

$pdo = getDbConnection();
$currentUser = current_user();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ==========================================
// 1. ACTION: DELETE ITEM
// ==========================================
if ($action === 'delete') {
    $itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $csrfToken = $_GET['csrf'] ?? $_POST['csrf_token'] ?? '';

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

    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        set_flash('danger', 'Item not found or already deleted.');
        header("Location: dashboard.php");
        exit;
    }

    if (!can_manage_item($item)) {
        set_flash('danger', 'Unauthorized. You do not have permission to delete this report.');
        header("Location: item.php?id=" . $itemId);
        exit;
    }

    if (!empty($item['image_path']) && file_exists(UPLOAD_DIR . $item['image_path'])) {
        @unlink(UPLOAD_DIR . $item['image_path']);
    }

    $deleteStmt = $pdo->prepare("DELETE FROM items WHERE id = :id");
    $deleteStmt->execute([':id' => $itemId]);

    set_flash('success', 'The item "' . htmlspecialchars($item['title']) . '" has been permanently removed.');
    header("Location: dashboard.php");
    exit;
}

// ==========================================
// 2. ACTION: UPDATE ITEM STATUS
// ==========================================
if ($action === 'status') {
    $itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $newStatus = $_GET['status'] ?? $_POST['status'] ?? '';
    $csrfToken = $_GET['csrf'] ?? $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Security token validation failed.');
        header("Location: dashboard.php");
        exit;
    }

    if (!$itemId || !in_array($newStatus, ['active', 'claimed', 'returned'])) {
        set_flash('danger', 'Invalid status parameters provided.');
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
    header("Location: item.php?id=" . $itemId);
    exit;
}

// ==========================================
// 3. ACTION: UPDATE CLAIM STATUS
// ==========================================
if ($action === 'claim_status') {
    $claimId = filter_input(INPUT_GET, 'claim_id', FILTER_VALIDATE_INT);
    $newClaimStatus = $_GET['claim_status'] ?? '';
    $csrfToken = $_GET['csrf'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Security token validation failed.');
        header("Location: dashboard.php");
        exit;
    }

    if (!$claimId || !in_array($newClaimStatus, ['approved', 'rejected', 'pending'])) {
        set_flash('danger', 'Invalid claim status requested.');
        header("Location: dashboard.php");
        exit;
    }

    $claimStmt = $pdo->prepare("
        SELECT c.*, i.user_id 
        FROM claims c 
        JOIN items i ON c.item_id = i.id 
        WHERE c.id = :id
    ");
    $claimStmt->execute([':id' => $claimId]);
    $claim = $claimStmt->fetch();

    if (!$claim || (!is_admin() && (int)$claim['user_id'] !== (int)$currentUser['id'])) {
        set_flash('danger', 'Unauthorized to modify this claim.');
        header("Location: dashboard.php");
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE claims SET status = :status WHERE id = :id");
    $updateStmt->execute([':status' => $newClaimStatus, ':id' => $claimId]);

    if ($newClaimStatus === 'approved') {
        $pdo->prepare("UPDATE items SET status = 'claimed' WHERE id = :id")->execute([':id' => $claim['item_id']]);
        set_flash('success', 'Claim approved! Item has been marked as resolved.');
    } else {
        set_flash('info', 'Claim marked as ' . htmlspecialchars($newClaimStatus) . '.');
    }

    header("Location: item.php?id=" . $claim['item_id']);
    exit;
}

// ==========================================
// 4. ACTION: SUBMIT CLAIM INQUIRY
// ==========================================
$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$itemId) {
    set_flash('danger', 'Invalid item identifier requested.');
    header("Location: browse.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
$stmt->execute([':id' => $itemId]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('danger', 'Item not found or may have been deleted.');
    header("Location: browse.php");
    exit;
}

$canManage = can_manage_item($item);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit_claim') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid form security token. Please try again.');
        header("Location: item.php?id=" . $itemId);
        exit;
    }

    $claimantName = sanitize($_POST['claimant_name'] ?? '');
    $claimantEmail = sanitize($_POST['claimant_email'] ?? '');
    $claimantPhone = sanitize($_POST['claimant_phone'] ?? '');
    $claimMessage = sanitize($_POST['claim_message'] ?? '');

    if (empty($claimantName) || strlen($claimantName) < 2) {
        set_flash('danger', 'Your name is required (at least 2 characters).');
        header("Location: item.php?id=" . $itemId . '#claim');
        exit;
    }

    if (empty($claimantEmail) || !filter_var($claimantEmail, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'A valid email address is required.');
        header("Location: item.php?id=" . $itemId . '#claim');
        exit;
    }

    if (empty($claimMessage) || strlen($claimMessage) < 10) {
        set_flash('danger', 'Please provide sufficient proof of ownership or identifying details (at least 10 characters).');
        header("Location: item.php?id=" . $itemId . '#claim');
        exit;
    }

    $insertClaimStmt = $pdo->prepare("
        INSERT INTO claims (item_id, claimant_id, claimant_name, claimant_email, claimant_phone, claim_message, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $insertClaimStmt->execute([
        $itemId,
        $currentUser['id'],
        $claimantName,
        $claimantEmail,
        $claimantPhone,
        $claimMessage
    ]);

    set_flash('success', 'Your claim inquiry has been submitted! The reporter will review your proof of ownership.');
    header("Location: item.php?id=" . $itemId);
    exit;
}

// Fetch claims for this item if owner or admin
$claims = [];
if ($canManage) {
    $claimsStmt = $pdo->prepare("SELECT * FROM claims WHERE item_id = :item_id ORDER BY created_at DESC");
    $claimsStmt->execute([':item_id' => $itemId]);
    $claims = $claimsStmt->fetchAll();
}

$pageTitle = htmlspecialchars($item['title']);
render_header($pageTitle);
?>

<main class="main-wrapper">
    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <a href="index.php">Home</a>
        <span class="divider">/</span>
        <a href="browse.php">Browse</a>
        <span class="divider">/</span>
        <a href="browse.php?category=<?= urlencode($item['category']) ?>"><?= htmlspecialchars(get_category_label($item['category'])) ?></a>
        <span class="divider">/</span>
        <span><?= htmlspecialchars($item['title']) ?></span>
    </div>

    <!-- Management Controls Bar (Reporter / Admin Only) -->
    <?php if ($canManage): ?>
        <div style="background: #ffffff; border-radius: var(--radius-md); border: 1px solid #c7d2fe; padding: 1rem 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 1.2rem;">🛠️</span>
                <strong>Management Controls:</strong>
                <span style="color: var(--text-muted); font-size: 0.9rem;">You are the reporter or administrator of this item.</span>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <!-- Edit Button (links to consolidated report.php in edit mode) -->
                <a href="report.php?id=<?= $item['id'] ?>" class="btn btn-secondary btn-sm">
                    ✏️ Edit Item
                </a>

                <!-- Quick Status Change -->
                <?php if ($item['status'] === 'active'): ?>
                    <a href="item.php?id=<?= $item['id'] ?>&action=status&status=claimed&csrf=<?= generate_csrf_token() ?>" class="btn btn-primary btn-sm" onclick="return confirm('Mark this item as resolved/claimed?')">
                        ✓ Mark as Resolved
                    </a>
                <?php else: ?>
                    <a href="item.php?id=<?= $item['id'] ?>&action=status&status=active&csrf=<?= generate_csrf_token() ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Re-activate this item?')">
                        🔄 Re-activate
                    </a>
                <?php endif; ?>

                <!-- Delete Button -->
                <a href="item.php?id=<?= $item['id'] ?>&action=delete&csrf=<?= generate_csrf_token() ?>" class="btn btn-danger btn-sm" onclick="return confirm('Permanently delete this report? This cannot be undone.');">
                    🗑️ Delete Item
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Item Details Layout -->
    <div class="detail-layout">
        <!-- Left: Image Display -->
        <div class="detail-img-box">
            <?php if (!empty($item['image_path']) && file_exists(UPLOAD_DIR . $item['image_path'])): ?>
                <img src="<?= UPLOAD_WEB_PATH . htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
            <?php else: ?>
                <div style="font-size: 5rem; text-align: center; color: #94a3b8; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%;">
                    📦
                    <p style="font-size: 1rem; color: var(--text-muted); margin-top: 0.5rem;">No photo uploaded</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Information & Claims -->
        <div class="detail-info">
            <div class="detail-badges">
                <?php if ($item['status'] === 'claimed' || $item['status'] === 'returned'): ?>
                    <span class="status-pill status-returned" style="font-size: 0.85rem; padding: 0.35rem 0.85rem;">
                        ✓ Reunited / Resolved
                    </span>
                <?php elseif ($item['type'] === 'lost'): ?>
                    <span class="status-pill" style="background: var(--lost-light); color: var(--lost-color); font-size: 0.85rem; padding: 0.35rem 0.85rem;">
                        🔴 Lost Item
                    </span>
                <?php else: ?>
                    <span class="status-pill" style="background: var(--found-light); color: var(--found-color); font-size: 0.85rem; padding: 0.35rem 0.85rem;">
                        🟢 Found Item
                    </span>
                <?php endif; ?>
                <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                    <?= htmlspecialchars(get_category_label($item['category'])) ?>
                </span>
            </div>

            <h1 class="detail-title"><?= htmlspecialchars($item['title']) ?></h1>

            <div class="detail-meta-list">
                <div class="detail-meta-row">
                    <span class="detail-label">Location</span>
                    <span class="detail-val">📍 <?= htmlspecialchars($item['location']) ?></span>
                </div>
                <div class="detail-meta-row">
                    <span class="detail-label">Date Recorded</span>
                    <span class="detail-val">📅 <?= htmlspecialchars($item['item_date']) ?> (<?= time_ago($item['created_at']) ?>)</span>
                </div>
                <div class="detail-meta-row">
                    <span class="detail-label">Contact Person</span>
                    <span class="detail-val">👤 <?= htmlspecialchars($item['contact_name']) ?></span>
                </div>
                <div class="detail-meta-row">
                    <span class="detail-label">Contact Email</span>
                    <span class="detail-val">
                        <a href="mailto:<?= htmlspecialchars($item['contact_email']) ?>" style="color: var(--primary); font-weight: 600;">
                            <?= htmlspecialchars($item['contact_email']) ?>
                        </a>
                    </span>
                </div>
                <?php if (!empty($item['contact_phone'])): ?>
                    <div class="detail-meta-row">
                        <span class="detail-label">Contact Phone</span>
                        <span class="detail-val">
                            <a href="tel:<?= htmlspecialchars($item['contact_phone']) ?>" style="color: var(--primary);">
                                <?= htmlspecialchars($item['contact_phone']) ?>
                            </a>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 1.5rem;">
                <h3 style="font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.5rem;">
                    Full Description
                </h3>
                <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1.25rem; font-size: 0.95rem; line-height: 1.7; color: var(--text-main);">
                    <?= nl2br(htmlspecialchars($item['description'])) ?>
                </div>
            </div>

            <!-- Ownership Claims Review (Reporter / Admin View) -->
            <?php if ($canManage): ?>
                <div style="margin-top: 2rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 1rem;">
                        Ownership Claims Received (<?= count($claims) ?>)
                    </h3>

                    <?php if (empty($claims)): ?>
                        <div style="background: #f8fafc; border: 1px dashed var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                            No claims inquiries have been submitted for this item yet.
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($claims as $claim): ?>
                                <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; box-shadow: var(--shadow-sm);">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                                        <div>
                                            <strong><?= htmlspecialchars($claim['claimant_name']) ?></strong>
                                            <span style="color: var(--text-muted); font-size: 0.85rem;"> &bull; <?= time_ago($claim['created_at']) ?></span>
                                        </div>
                                        <span class="status-pill status-<?= htmlspecialchars($claim['status']) ?>">
                                            <?= ucfirst($claim['status']) ?>
                                        </span>
                                    </div>

                                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                                        Email: <a href="mailto:<?= htmlspecialchars($claim['claimant_email']) ?>"><?= htmlspecialchars($claim['claimant_email']) ?></a>
                                        <?php if (!empty($claim['claimant_phone'])): ?>
                                            &bull; Phone: <?= htmlspecialchars($claim['claimant_phone']) ?>
                                        <?php endif; ?>
                                    </div>

                                    <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: var(--radius-sm); font-size: 0.9rem; line-height: 1.5; margin-bottom: 0.75rem;">
                                        <strong>Proof Provided:</strong> <?= nl2br(htmlspecialchars($claim['claim_message'])) ?>
                                    </div>

                                    <!-- Claim Actions -->
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <?php if ($claim['status'] === 'pending'): ?>
                                            <a href="item.php?action=claim_status&claim_id=<?= $claim['id'] ?>&claim_status=approved&csrf=<?= generate_csrf_token() ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve this claim? This will mark the item as claimed.')">
                                                ✓ Approve Claim
                                            </a>
                                            <a href="item.php?action=claim_status&claim_id=<?= $claim['id'] ?>&claim_status=rejected&csrf=<?= generate_csrf_token() ?>" class="btn btn-danger btn-sm" onclick="return confirm('Reject this claim inquiry?')">
                                                ✕ Reject Claim
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">
                                                Claim marked as <?= htmlspecialchars($claim['status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Claim Submission Form (Only for Found items when user is not the reporter) -->
            <?php if (!$canManage && $item['type'] === 'found' && $item['status'] === 'active'): ?>
                <div id="claim" style="margin-top: 2rem; background: #ffffff; border: 1px solid #c7d2fe; border-radius: var(--radius-lg); padding: 1.75rem; box-shadow: var(--shadow-sm);">
                    <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem;">
                        🤝 Is This Your Belonging? Submit a Claim
                    </h3>
                    <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                        Provide identifying marks, serial numbers, wallpapers, or proof of purchase so the finder can verify your ownership.
                    </p>

                    <form action="item.php?id=<?= $item['id'] ?>" method="POST" class="needs-validation" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="submit_claim">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label" for="claimant_name">Your Full Name <span class="required">*</span></label>
                                <input type="text" id="claimant_name" name="claimant_name" class="form-control" value="<?= htmlspecialchars($currentUser['name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="claimant_email">Contact Email <span class="required">*</span></label>
                                <input type="email" id="claimant_email" name="claimant_email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="claimant_phone">Phone Number (Optional)</label>
                            <input type="tel" id="claimant_phone" name="claimant_phone" class="form-control" placeholder="+1 (555) 000-0000">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="claim_message">Detailed Proof of Ownership <span class="required">*</span></label>
                            <textarea id="claim_message" name="claim_message" class="form-control" rows="4" placeholder="Mention specific details not visible in photos (passcode hints, distinctive scratches, contents, serials, etc.)" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.75rem;">
                            Submit Ownership Claim
                        </button>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>
</main>

<?php render_footer(); ?>
