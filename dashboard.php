<?php
/**
 * FindIt - User Dashboard
 * Complete management portal: view my posts, edit, delete, toggle status, and review claims.
 */
$pageTitle = 'My Dashboard';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/header.php';

require_login(); // Session guard

$pdo = getDbConnection();
$user = current_user();
$userId = $user['id'];

// Fetch user's reported items
$itemsStmt = $pdo->prepare("SELECT * FROM items WHERE user_id = :user_id ORDER BY created_at DESC");
$itemsStmt->execute([':user_id' => $userId]);
$myItems = $itemsStmt->fetchAll();

// Fetch claims submitted on user's items
$claimsStmt = $pdo->prepare("
    SELECT c.*, i.title as item_title, i.type as item_type, i.id as item_id
    FROM claims c
    JOIN items i ON c.item_id = i.id
    WHERE i.user_id = :user_id
    ORDER BY c.created_at DESC
");
$claimsStmt->execute([':user_id' => $userId]);
$incomingClaims = $claimsStmt->fetchAll();

// Calculate user statistics
$totalPosted = count($myItems);
$activeItems = 0;
$resolvedItems = 0;
foreach ($myItems as $item) {
    if ($item['status'] === 'active') $activeItems++;
    else $resolvedItems++;
}
$pendingClaims = 0;
foreach ($incomingClaims as $c) {
    if ($c['status'] === 'pending') $pendingClaims++;
}
?>

<main class="main-wrapper">
    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <a href="index.php">Home</a>
        <span class="divider">/</span>
        <span>My Dashboard</span>
    </div>

    <!-- User Welcome Banner -->
    <div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: #ffffff; padding: 2rem 2.5rem; border-radius: var(--radius-lg); margin-bottom: 2rem; box-shadow: var(--shadow-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
        <div>
            <div style="font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8; margin-bottom: 0.25rem;">Member Dashboard</div>
            <h1 style="font-size: 2rem; font-weight: 800;"><?= htmlspecialchars($user['name']) ?></h1>
            <p style="opacity: 0.9; font-size: 0.95rem; margin-top: 0.25rem;"><?= htmlspecialchars($user['email']) ?> • Account Role: <span style="text-transform: capitalize; background: rgba(255,255,255,0.2); padding: 0.2rem 0.5rem; border-radius: 4px;"><?= htmlspecialchars($user['role']) ?></span></p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="report.php" class="btn btn-primary" style="background: #ffffff; color: #312e81; font-weight: 700;">
                + Report New Item
            </a>
            <?php if (is_admin()): ?>
                <a href="admin.php" class="btn btn-secondary" style="background: rgba(255,255,255,0.15); color: #ffffff; border-color: transparent;">
                    🛡️ Admin Panel
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="stats-container" style="margin-top: 0; margin-bottom: 2.5rem;">
        <div class="stat-card">
            <span class="stat-number"><?= $totalPosted ?></span>
            <span class="stat-label">My Reports</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color: var(--lost-color);"><?= $activeItems ?></span>
            <span class="stat-label">Active Items</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color: var(--found-color);"><?= $resolvedItems ?></span>
            <span class="stat-label">Resolved / Reunited</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color: #f59e0b;"><?= $pendingClaims ?></span>
            <span class="stat-label">Pending Inquiries</span>
        </div>
    </div>

    <!-- Section 1: My Reported Items Table (UPDATE & DELETE) -->
    <div style="margin-bottom: 3.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 800;">📋 My Reported Items</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem;">View, edit, toggle status, or remove items you've submitted.</p>
            </div>
            <a href="report.php" class="btn btn-primary btn-sm">+ Post New Item</a>
        </div>

        <?php if (empty($myItems)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3>You Haven't Reported Any Items Yet</h3>
                <p>Have you lost a personal possession or found someone else's belonging?</p>
                <a href="report.php" class="btn btn-primary">Report an Item Now</a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Details</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Date / Place</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myItems as $item): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.85rem;">
                                        <?php if (!empty($item['image_path']) && file_exists(UPLOAD_DIR . $item['image_path'])): ?>
                                            <img src="<?= UPLOAD_WEB_PATH . htmlspecialchars($item['image_path']) ?>" alt="" style="width: 46px; height: 46px; object-fit: cover; border-radius: var(--radius-sm);">
                                        <?php else: ?>
                                            <div style="width: 46px; height: 46px; background: #e2e8f0; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                                                <?= get_category_icon($item['category']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="item.php?id=<?= $item['id'] ?>" style="font-weight: 700; color: var(--text-main);">
                                                <?= htmlspecialchars($item['title']) ?>
                                            </a>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);">
                                                Reported <?= time_ago($item['created_at']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-pill" style="<?= ($item['type'] === 'lost') ? 'background: var(--lost-light); color: var(--lost-color);' : 'background: var(--found-light); color: var(--found-color);' ?>">
                                        <?= ucfirst($item['type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?= htmlspecialchars(get_category_label($item['category'])) ?></small>
                                </td>
                                <td>
                                    <small>📅 <?= htmlspecialchars($item['item_date']) ?><br>📍 <?= htmlspecialchars($item['location']) ?></small>
                                </td>
                                <td>
                                    <span class="status-pill status-<?= ($item['status'] === 'active') ? 'active' : 'returned' ?>">
                                        <?= ucfirst($item['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 0.35rem;">
                                        <a href="item.php?id=<?= $item['id'] ?>" class="btn btn-secondary btn-sm" title="View details">
                                            👁️ View
                                        </a>
                                        <a href="edit_item.php?id=<?= $item['id'] ?>" class="btn btn-secondary btn-sm" title="Edit details">
                                            ✏️ Edit
                                        </a>

                                        <?php if ($item['status'] === 'active'): ?>
                                            <a href="update_status.php?id=<?= $item['id'] ?>&status=claimed&csrf=<?= generate_csrf_token() ?>" class="btn btn-secondary btn-sm" style="color: #059669;" title="Mark as resolved">
                                                ✓ Resolve
                                            </a>
                                        <?php else: ?>
                                            <a href="update_status.php?id=<?= $item['id'] ?>&status=active&csrf=<?= generate_csrf_token() ?>" class="btn btn-secondary btn-sm" title="Reactivate item">
                                                🔄 Reactivate
                                            </a>
                                        <?php endif; ?>

                                        <a href="delete_item.php?id=<?= $item['id'] ?>&csrf=<?= generate_csrf_token() ?>" class="btn btn-danger btn-sm confirm-delete" data-title="<?= htmlspecialchars($item['title']) ?>" title="Delete record">
                                            🗑️
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section 2: Incoming Claims / Inquiries (UPDATE & RESOLVE) -->
    <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 800;">📬 Inquiries &amp; Claims Received (<?= count($incomingClaims) ?>)</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Review proof of ownership submitted by others for your posted items.</p>
            </div>
        </div>

        <?php if (empty($incomingClaims)): ?>
            <div class="empty-state" style="padding: 2.5rem 1rem;">
                <div class="empty-state-icon">📫</div>
                <h3>No Inquiries Received Yet</h3>
                <p>When someone files a claim or sends a message regarding your items, it will appear here.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Related Item</th>
                            <th>Claimant Contact</th>
                            <th>Proof Details / Message</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incomingClaims as $claim): ?>
                            <tr>
                                <td>
                                    <a href="item.php?id=<?= $claim['item_id'] ?>" style="font-weight: 700; color: var(--primary);">
                                        <?= htmlspecialchars($claim['item_title']) ?>
                                    </a>
                                    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                        (<?= htmlspecialchars($claim['item_type']) ?>)
                                    </div>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($claim['claimant_name']) ?></strong><br>
                                    <a href="mailto:<?= htmlspecialchars($claim['claimant_email']) ?>"><small><?= htmlspecialchars($claim['claimant_email']) ?></small></a>
                                    <?php if (!empty($claim['claimant_phone'])): ?>
                                        <br><small><?= htmlspecialchars($claim['claimant_phone']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 320px;">
                                    <div style="background: #f8fafc; padding: 0.6rem 0.8rem; border-radius: 6px; font-size: 0.9rem; border: 1px solid var(--border-color);">
                                        <?= nl2br(htmlspecialchars($claim['claim_message'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <small><?= time_ago($claim['created_at']) ?></small>
                                </td>
                                <td>
                                    <span class="status-pill status-<?= ($claim['status'] === 'approved') ? 'returned' : (($claim['status'] === 'rejected') ? 'active' : 'claimed') ?>">
                                        <?= ucfirst($claim['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if ($claim['status'] === 'pending'): ?>
                                        <a href="update_status.php?claim_id=<?= $claim['id'] ?>&claim_status=approved&csrf=<?= generate_csrf_token() ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve this claim? This will resolve the item.')">
                                            ✓ Approve
                                        </a>
                                        <a href="update_status.php?claim_id=<?= $claim['id'] ?>&claim_status=rejected&csrf=<?= generate_csrf_token() ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Reject this claim?')">
                                            ✕ Reject
                                        </a>
                                    <?php else: ?>
                                        <small style="color: var(--text-muted);">Processed</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
