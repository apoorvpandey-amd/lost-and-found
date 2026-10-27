<?php
/**
 * FindIt - Administrator Control Panel
 * Full administrative oversight over users, reports, and claims across the platform.
 * Enforces compulsory administrator authentication.
 */
require_once __DIR__ . '/db.php';

// Enforce Compulsory Administrator Access
require_admin();

$pdo = getDbConnection();

// Fetch platform-wide metrics
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalItems = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
$activeLost = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE type = 'lost' AND status = 'active'")->fetchColumn();
$activeFound = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE type = 'found' AND status = 'active'")->fetchColumn();
$resolvedItems = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE status IN ('claimed', 'returned')")->fetchColumn();
$totalClaims = (int)$pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();

// Fetch all items with user info
$allItems = $pdo->query("
    SELECT i.*, u.name as user_account_name 
    FROM items i 
    LEFT JOIN users u ON i.user_id = u.id 
    ORDER BY i.created_at DESC
")->fetchAll();

// Fetch all claims with item info
$allClaims = $pdo->query("
    SELECT c.*, i.title as item_title, i.type as item_type 
    FROM claims c 
    JOIN items i ON c.item_id = i.id 
    ORDER BY c.created_at DESC
")->fetchAll();

// Fetch users
$users = $pdo->query("
    SELECT u.*, COUNT(i.id) as item_count 
    FROM users u 
    LEFT JOIN items i ON u.id = i.user_id 
    GROUP BY u.id 
    ORDER BY u.created_at DESC
")->fetchAll();

render_header('Administrator Control Panel', 'admin');
?>

<main class="main-wrapper">
    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <a href="index.php">Home</a>
        <span class="divider">/</span>
        <a href="dashboard.php">Dashboard</a>
        <span class="divider">/</span>
        <span>Admin Panel</span>
    </div>

    <!-- Admin Header Banner -->
    <div style="background: #0f172a; color: #ffffff; padding: 2rem 2.5rem; border-radius: var(--radius-lg); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 0.25rem;">Security &amp; Oversight</div>
            <h1 style="font-size: 2rem; font-weight: 800;">Administrator Control Center</h1>
            <p style="color: #cbd5e1; font-size: 0.95rem; margin-top: 0.25rem;">Complete administrative access to manage reports, claims, and accounts.</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary" style="background: rgba(255,255,255,0.1); color: #ffffff; border-color: transparent;">
                &larr; Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Platform Overview Statistics -->
    <div class="stats-container" style="margin-top: 0; margin-bottom: 2.5rem;">
        <div class="stat-card">
            <span class="stat-number"><?= $totalItems ?></span>
            <span class="stat-label">Total Listings</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color: var(--lost-color);"><?= $activeLost ?></span>
            <span class="stat-label">Active Lost</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color: var(--found-color);"><?= $activeFound ?></span>
            <span class="stat-label">Active Found</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color: var(--primary);"><?= $resolvedItems ?></span>
            <span class="stat-label">Resolved / Reunited</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color: var(--warning);"><?= $totalClaims ?></span>
            <span class="stat-label">Claims Filed</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color: #8b5cf6;"><?= $totalUsers ?></span>
            <span class="stat-label">Registered Users</span>
        </div>
    </div>

    <!-- Section: All Reported Items -->
    <div style="margin-bottom: 3.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 800;">📦 All System Reports (<?= count($allItems) ?>)</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem;">View, edit, toggle lifecycle state, or purge any listing across the entire platform.</p>
            </div>
            <a href="report.php" class="btn btn-primary btn-sm">+ Post New Item</a>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item Details</th>
                        <th>Type</th>
                        <th>Reporter</th>
                        <th>Date &amp; Location</th>
                        <th>Status</th>
                        <th style="text-align: right;">Admin Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allItems as $it): ?>
                        <tr>
                            <td>#<?= $it['id'] ?></td>
                            <td>
                                <a href="item.php?id=<?= $it['id'] ?>" style="font-weight: 700; color: var(--primary);">
                                    <?= htmlspecialchars($it['title']) ?>
                                </a>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?= htmlspecialchars(get_category_label($it['category'])) ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-pill" style="<?= ($it['type'] === 'lost') ? 'background: var(--lost-light); color: var(--lost-color);' : 'background: var(--found-light); color: var(--found-color);' ?>">
                                    <?= ucfirst($it['type']) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($it['contact_name']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($it['contact_email']) ?></small>
                            </td>
                            <td>
                                <small>📅 <?= htmlspecialchars($it['item_date']) ?><br>📍 <?= htmlspecialchars($it['location']) ?></small>
                            </td>
                            <td>
                                <span class="status-pill status-<?= ($it['status'] === 'active') ? 'active' : 'returned' ?>">
                                    <?= ucfirst($it['status']) ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 0.35rem;">
                                    <a href="item.php?id=<?= $it['id'] ?>" class="btn btn-secondary btn-sm" title="View details">👁️</a>
                                    <a href="report.php?id=<?= $it['id'] ?>" class="btn btn-secondary btn-sm" title="Edit item">✏️</a>
                                    
                                    <?php if ($it['status'] === 'active'): ?>
                                        <a href="item.php?id=<?= $it['id'] ?>&action=status&status=claimed&csrf=<?= generate_csrf_token() ?>" class="btn btn-secondary btn-sm" style="color: #059669;" title="Mark as resolved" onclick="return confirm('Mark this item as resolved?')">✓</a>
                                    <?php else: ?>
                                        <a href="item.php?id=<?= $it['id'] ?>&action=status&status=active&csrf=<?= generate_csrf_token() ?>" class="btn btn-secondary btn-sm" title="Reactivate" onclick="return confirm('Re-activate this item?')">🔄</a>
                                    <?php endif; ?>

                                    <a href="item.php?id=<?= $it['id'] ?>&action=delete&csrf=<?= generate_csrf_token() ?>" class="btn btn-danger btn-sm" title="Delete permanently" onclick="return confirm('Permanently remove this report?');">🗑️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section: All Claims / Inquiries -->
    <div style="margin-bottom: 3.5rem;">
        <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 1.25rem;">📬 All Claims &amp; Inquiries (<?= count($allClaims) ?>)</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Claim ID</th>
                        <th>Target Item</th>
                        <th>Claimant</th>
                        <th>Proof Description</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allClaims as $cl): ?>
                        <tr>
                            <td>#<?= $cl['id'] ?></td>
                            <td>
                                <a href="item.php?id=<?= $cl['item_id'] ?>" style="font-weight: 700; color: var(--primary);">
                                    <?= htmlspecialchars($cl['item_title']) ?>
                                </a>
                                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">(<?= htmlspecialchars($cl['item_type']) ?>)</div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($cl['claimant_name']) ?></strong><br>
                                <a href="mailto:<?= htmlspecialchars($cl['claimant_email']) ?>"><small><?= htmlspecialchars($cl['claimant_email']) ?></small></a>
                            </td>
                            <td style="max-width: 300px;">
                                <small><?= htmlspecialchars($cl['claim_message']) ?></small>
                            </td>
                            <td><small><?= time_ago($cl['created_at']) ?></small></td>
                            <td>
                                <span class="status-pill status-<?= ($cl['status'] === 'approved') ? 'returned' : (($cl['status'] === 'rejected') ? 'active' : 'claimed') ?>">
                                    <?= ucfirst($cl['status']) ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($cl['status'] === 'pending'): ?>
                                    <a href="item.php?action=claim_status&claim_id=<?= $cl['id'] ?>&claim_status=approved&csrf=<?= generate_csrf_token() ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve this claim?')">Approve</a>
                                    <a href="item.php?action=claim_status&claim_id=<?= $cl['id'] ?>&claim_status=rejected&csrf=<?= generate_csrf_token() ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Reject this claim?')">Reject</a>
                                <?php else: ?>
                                    <small style="color: var(--text-muted);"><?= ucfirst($cl['status']) ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section: Registered Users -->
    <div>
        <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 1.25rem;">👥 Registered Users (<?= count($users) ?>)</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Posts Created</th>
                        <th>Registered Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>#<?= $u['id'] ?></td>
                            <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= !empty($u['phone']) ? htmlspecialchars($u['phone']) : '<em style="color:#94a3b8;">None</em>' ?></td>
                            <td>
                                <span class="status-pill <?= ($u['role'] === 'admin') ? 'status-claimed' : 'status-active' ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td><?= $u['item_count'] ?> items</td>
                            <td><small><?= htmlspecialchars($u['created_at']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php render_footer(); ?>
