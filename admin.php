<?php
/**
 * FindIt - Administrator Control Panel
 * Full administrative oversight over users, reports, and claims across the platform.
 */
$pageTitle = 'Administrator Panel';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/header.php';

require_admin(); // Restrict to admin role

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
            <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: #334155; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem;">
                🛡️ Platform Oversight
            </div>
            <h1 style="font-size: 2rem; font-weight: 800;">Administrator Control Center</h1>
            <p style="color: #94a3b8; font-size: 0.95rem;">Manage all database records, resolve disputed claims, and moderate posted content.</p>
        </div>
        <div>
            <a href="report.php" class="btn btn-primary">+ Create Official Notice</a>
        </div>
    </div>

    <!-- Metrics Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2.5rem;">
        <div class="stat-card">
            <span class="stat-number"><?= $totalItems ?></span>
            <span class="stat-label">Total Items</span>
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
            <span class="stat-label">Reunited / Resolved</span>
        </div>
        <div class="stat-card">
            <span class="stat-number" style="color: #f59e0b;"><?= $totalClaims ?></span>
            <span class="stat-label">Total Claims</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= $totalUsers ?></span>
            <span class="stat-label">Registered Users</span>
        </div>
    </div>

    <!-- Section: Manage All Items (UPDATE & DELETE) -->
    <div style="margin-bottom: 3.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 800;">📦 All Database Reports (<?= count($allItems) ?>)</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Full database CRUD access to any item report.</p>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item Title</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Reporter</th>
                        <th>Date &amp; Place</th>
                        <th>Status</th>
                        <th style="text-align: right;">Moderation Actions</th>
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
                            </td>
                            <td>
                                <span class="status-pill" style="<?= ($it['type'] === 'lost') ? 'background: var(--lost-light); color: var(--lost-color);' : 'background: var(--found-light); color: var(--found-color);' ?>">
                                    <?= ucfirst($it['type']) ?>
                                </span>
                            </td>
                            <td><small><?= htmlspecialchars(get_category_label($it['category'])) ?></small></td>
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
                                    <a href="edit_item.php?id=<?= $it['id'] ?>" class="btn btn-secondary btn-sm" title="Edit item">✏️</a>
                                    
                                    <?php if ($it['status'] === 'active'): ?>
                                        <a href="update_status.php?id=<?= $it['id'] ?>&status=claimed&csrf=<?= generate_csrf_token() ?>" class="btn btn-secondary btn-sm" style="color: #059669;" title="Mark as resolved">✓</a>
                                    <?php else: ?>
                                        <a href="update_status.php?id=<?= $it['id'] ?>&status=active&csrf=<?= generate_csrf_token() ?>" class="btn btn-secondary btn-sm" title="Reactivate">🔄</a>
                                    <?php endif; ?>

                                    <a href="delete_item.php?id=<?= $it['id'] ?>&csrf=<?= generate_csrf_token() ?>" class="btn btn-danger btn-sm confirm-delete" data-title="<?= htmlspecialchars($it['title']) ?>" title="Delete permanently">🗑️</a>
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
                        <th>Message / Proof</th>
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
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($cl['claimant_name']) ?></strong><br>
                                <small><a href="mailto:<?= htmlspecialchars($cl['claimant_email']) ?>"><?= htmlspecialchars($cl['claimant_email']) ?></a></small>
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
                                    <a href="update_status.php?claim_id=<?= $cl['id'] ?>&claim_status=approved&csrf=<?= generate_csrf_token() ?>" class="btn btn-success btn-sm">Approve</a>
                                    <a href="update_status.php?claim_id=<?= $cl['id'] ?>&claim_status=rejected&csrf=<?= generate_csrf_token() ?>" class="btn btn-secondary btn-sm">Reject</a>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
