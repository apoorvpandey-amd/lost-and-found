<?php
/**
 * FindIt - Item Details & Claim Submission Page
 * Supports viewing details, claiming items (INSERT claim), and managing (EDIT/DELETE).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/header.php';

$pdo = getDbConnection();
$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$itemId) {
    set_flash('danger', 'Invalid item ID requested.');
    header("Location: browse.php");
    exit;
}

// Fetch item details
$stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
$stmt->execute([':id' => $itemId]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('danger', 'Item not found or may have been deleted.');
    header("Location: browse.php");
    exit;
}

$pageTitle = htmlspecialchars($item['title']);
$categories = get_categories();
$canManage = can_manage_item($item);
$currentUser = current_user();

// Handle Claim Submission (INSERT into claims)
$claimErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_claim') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $claimErrors['csrf'] = 'Invalid form security token. Please try again.';
    }

    $claimantName = sanitize($_POST['claimant_name'] ?? '');
    $claimantEmail = sanitize($_POST['claimant_email'] ?? '');
    $claimantPhone = sanitize($_POST['claimant_phone'] ?? '');
    $claimMessage = sanitize($_POST['claim_message'] ?? '');

    if (empty($claimantName) || strlen($claimantName) < 2) {
        $claimErrors['claimant_name'] = 'Your name is required (at least 2 characters).';
    }

    if (empty($claimantEmail) || !filter_var($claimantEmail, FILTER_VALIDATE_EMAIL)) {
        $claimErrors['claimant_email'] = 'A valid email address is required.';
    }

    if (empty($claimMessage) || strlen($claimMessage) < 10) {
        $claimErrors['claim_message'] = 'Please provide sufficient proof of ownership or identifying details (at least 10 characters).';
    }

    if (empty($claimErrors)) {
        $claimantId = $currentUser['id'] ?? null;
        $insertClaimStmt = $pdo->prepare("
            INSERT INTO claims (item_id, claimant_id, claimant_name, claimant_email, claimant_phone, claim_message, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $insertClaimStmt->execute([
            $itemId,
            $claimantId,
            $claimantName,
            $claimantEmail,
            $claimantPhone,
            $claimMessage
        ]);

        set_flash('success', 'Your claim inquiry has been submitted! The reporter will review your proof of ownership.');
        header("Location: item.php?id=" . $itemId);
        exit;
    }
}

// If owner or admin, fetch claims for this item
$claims = [];
if ($canManage) {
    $claimsStmt = $pdo->prepare("SELECT * FROM claims WHERE item_id = :item_id ORDER BY created_at DESC");
    $claimsStmt->execute([':item_id' => $itemId]);
    $claims = $claimsStmt->fetchAll();
}
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

    <!-- Management Action Bar for Owner / Admin -->
    <?php if ($canManage): ?>
        <div style="background: #ffffff; border-radius: var(--radius-md); border: 1px solid #c7d2fe; padding: 1rem 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 1.2rem;">🛠️</span>
                <strong>Management Controls:</strong>
                <span style="color: var(--text-muted); font-size: 0.9rem;">You are the reporter or administrator of this item.</span>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <!-- Edit Button -->
                <a href="edit_item.php?id=<?= $item['id'] ?>" class="btn btn-secondary btn-sm">
                    ✏️ Edit Item
                </a>

                <!-- Quick Status Change -->
                <?php if ($item['status'] === 'active'): ?>
                    <a href="update_status.php?id=<?= $item['id'] ?>&status=claimed&csrf=<?= generate_csrf_token() ?>" class="btn btn-primary btn-sm" onclick="return confirm('Mark this item as resolved/claimed?')">
                        ✓ Mark as Resolved
                    </a>
                <?php else: ?>
                    <a href="update_status.php?id=<?= $item['id'] ?>&status=active&csrf=<?= generate_csrf_token() ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Re-activate this item?')">
                        🔄 Re-activate
                    </a>
                <?php endif; ?>

                <!-- Delete Button -->
                <a href="delete_item.php?id=<?= $item['id'] ?>&csrf=<?= generate_csrf_token() ?>" class="btn btn-danger btn-sm confirm-delete" data-title="<?= htmlspecialchars($item['title']) ?>">
                    🗑️ Delete Item
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Item Main Details Layout -->
    <div class="detail-layout">
        <!-- Left: Image Gallery / Icon -->
        <div class="detail-img-box">
            <?php if (!empty($item['image_path']) && file_exists(UPLOAD_DIR . $item['image_path'])): ?>
                <img src="<?= UPLOAD_WEB_PATH . htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
            <?php else: ?>
                <div style="font-size: 5rem; text-align: center; color: #94a3b8;">
                    <?= get_category_icon($item['category']) ?>
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
                    Posted <?= time_ago($item['created_at']) ?>
                </span>
            </div>

            <h1 class="detail-title"><?= htmlspecialchars($item['title']) ?></h1>

            <div class="detail-meta-list">
                <div class="detail-meta-item">
                    <strong>Category</strong>
                    <span><?= get_category_icon($item['category']) ?> <?= htmlspecialchars(get_category_label($item['category'])) ?></span>
                </div>
                <div class="detail-meta-item">
                    <strong>Date Occurred</strong>
                    <span>📅 <?= htmlspecialchars($item['item_date']) ?></span>
                </div>
                <div class="detail-meta-item" style="grid-column: 1 / -1;">
                    <strong>Location</strong>
                    <span>📍 <?= htmlspecialchars($item['location']) ?></span>
                </div>
            </div>

            <div class="detail-desc-block">
                <h3>Detailed Description</h3>
                <p style="white-space: pre-line; color: #334155; line-height: 1.7;"><?= htmlspecialchars($item['description']) ?></p>
            </div>

            <!-- Contact / Claim Action Box -->
            <div class="claim-box" id="claim">
                <?php if ($item['status'] === 'claimed' || $item['status'] === 'returned'): ?>
                    <h4>🎉 This item has been resolved!</h4>
                    <p>The owner and finder have successfully reconnected. Further claims are closed.</p>
                <?php elseif ($item['type'] === 'found'): ?>
                    <h4>Is this item yours?</h4>
                    <p>Submit a claim with unique identifying details (serial number, distinctive marks, contents) to prove ownership.</p>
                    <button type="button" class="btn btn-primary" data-open-modal="claimModal">
                        📋 File Ownership Claim
                    </button>
                <?php else: ?>
                    <h4>Did you find this item?</h4>
                    <p>If you have found or have information about this lost item, please contact the owner or send a message.</p>
                    <button type="button" class="btn btn-success" data-open-modal="claimModal">
                        🤝 I Found This / Contact Reporter
                    </button>
                <?php endif; ?>

                <div style="margin-top: 1rem; font-size: 0.85rem; color: #4338ca; border-top: 1px dashed #c7d2fe; padding-top: 0.75rem;">
                    <strong>Reporter Contact:</strong> <?= htmlspecialchars($item['contact_name']) ?>
                    <?php if (is_logged_in()): ?>
                        (<?= htmlspecialchars($item['contact_email']) ?> <?= !empty($item['contact_phone']) ? '• ' . htmlspecialchars($item['contact_phone']) : '' ?>)
                    <?php else: ?>
                        <em>(<a href="login.php?redirect=item.php?id=<?= $item['id'] ?>" style="text-decoration: underline; color: #312e81;">Log in</a> to view direct email and phone)</em>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Owner / Admin Claims Section -->
    <?php if ($canManage): ?>
        <section style="margin-top: 3rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2 style="font-size: 1.4rem; font-weight: 800;">
                    📬 Inquiries &amp; Claims Received (<?= count($claims) ?>)
                </h2>
                <a href="dashboard.php" class="btn btn-secondary btn-sm">Go to Dashboard</a>
            </div>

            <?php if (empty($claims)): ?>
                <div style="background: #ffffff; padding: 2rem; border-radius: var(--radius-md); border: 1px dashed var(--border-color); text-align: center; color: var(--text-muted);">
                    No claims or inquiries received yet for this report.
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Claimant</th>
                                <th>Contact Email &amp; Phone</th>
                                <th>Proof / Message</th>
                                <th>Submitted</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($claims as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['claimant_name']) ?></strong></td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($c['claimant_email']) ?>"><?= htmlspecialchars($c['claimant_email']) ?></a>
                                        <?php if (!empty($c['claimant_phone'])): ?>
                                            <br><small style="color: var(--text-muted);"><?= htmlspecialchars($c['claimant_phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 300px;"><?= htmlspecialchars($c['claim_message']) ?></td>
                                    <td><small><?= time_ago($c['created_at']) ?></small></td>
                                    <td>
                                        <span class="status-pill status-<?= ($c['status'] === 'approved') ? 'returned' : (($c['status'] === 'rejected') ? 'active' : 'claimed') ?>">
                                            <?= ucfirst($c['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>

<!-- Claim Modal Dialog -->
<div class="modal-overlay" id="claimModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><?= ($item['type'] === 'found') ? 'File an Ownership Claim' : 'Contact Reporter' ?></h3>
            <button type="button" class="modal-close-btn" data-close-modal>&times;</button>
        </div>

        <form action="item.php?id=<?= $item['id'] ?>" method="POST" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="submit_claim">

            <div class="modal-body">
                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                    Please enter your contact details and explain your proof of ownership or where the item can be retrieved.
                </p>

                <!-- Claimant Name -->
                <div class="form-group">
                    <label class="form-label" for="claimant_name">Your Full Name <span class="required">*</span></label>
                    <input type="text" id="claimant_name" name="claimant_name" class="form-control" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" placeholder="e.g. Jane Doe" required minlength="2">
                </div>

                <!-- Claimant Email -->
                <div class="form-group">
                    <label class="form-label" for="claimant_email">Email Address <span class="required">*</span></label>
                    <input type="email" id="claimant_email" name="claimant_email" class="form-control" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" placeholder="e.g. jane@example.com" required>
                </div>

                <!-- Claimant Phone -->
                <div class="form-group">
                    <label class="form-label" for="claimant_phone">Phone Number (Optional)</label>
                    <input type="tel" id="claimant_phone" name="claimant_phone" class="form-control" placeholder="+1 (555) 000-0000">
                </div>

                <!-- Proof / Message -->
                <div class="form-group">
                    <label class="form-label" for="claim_message">
                        <?= ($item['type'] === 'found') ? 'Proof of Ownership Details' : 'Message to Owner' ?> <span class="required">*</span>
                    </label>
                    <textarea id="claim_message" name="claim_message" rows="4" class="form-control" placeholder="Describe specific details not visible in photos (e.g. serial numbers, wallpaper, contents, exact marks)..." required minlength="10"></textarea>
                </div>

                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Claim</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
