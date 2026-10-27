<?php
/**
 * FindIt - Main Portal Home Page
 * Displays portal hero, live statistics counters, category explorer, and recent reports.
 * Enforces compulsory login: users must log in to access the portal and its contents.
 */
require_once __DIR__ . '/db.php';

// Enforce Compulsory Login
require_login();

$pdo = getDbConnection();

// Fetch live statistics
$stats = [
    'total'    => (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn(),
    'lost'     => (int)$pdo->query("SELECT COUNT(*) FROM items WHERE type = 'lost' AND status = 'active'")->fetchColumn(),
    'found'    => (int)$pdo->query("SELECT COUNT(*) FROM items WHERE type = 'found' AND status = 'active'")->fetchColumn(),
    'resolved' => (int)$pdo->query("SELECT COUNT(*) FROM items WHERE status IN ('claimed', 'returned')")->fetchColumn()
];

// Fetch recent items (latest 6 records)
$recentStmt = $pdo->query("SELECT * FROM items ORDER BY created_at DESC LIMIT 6");
$recentItems = $recentStmt->fetchAll();
$categories = get_categories();

render_header('Home - Community Lost & Found');
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-inner">
        <div class="hero-badge">✨ Community Recovery Network</div>
        <h1 class="hero-title">Lost Something? <span>We Help You Find It.</span></h1>
        <p class="hero-desc">
            Browse active lost and found reports, or quickly publish a report to reunite belongings with their rightful owners.
        </p>

        <!-- Search Bar Form (Submits to browse.php) -->
        <form action="browse.php" method="GET" class="search-box-wrapper">
            <input type="text" name="q" class="search-input" placeholder="What did you lose or find? (e.g. iPhone, Keys, Wallet...)" aria-label="Search items">
            
            <select name="category" class="search-category-select" aria-label="Select category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $slug => $cat): ?>
                    <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($cat['label']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">
                Search
            </button>
        </form>

        <!-- Quick Action Buttons -->
        <div style="margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="report.php?type=lost" class="btn btn-danger">
                Report a Lost Item
            </a>
            <a href="report.php?type=found" class="btn btn-success">
                Report a Found Item
            </a>
        </div>

        <!-- Live Statistics Counter -->
        <div class="stats-container">
            <div class="stat-card">
                <span class="stat-number"><?= $stats['total'] ?></span>
                <span class="stat-label">Total Reports</span>
            </div>
            <div class="stat-card">
                <span class="stat-number" style="color: var(--lost-color);"><?= $stats['lost'] ?></span>
                <span class="stat-label">Active Lost</span>
            </div>
            <div class="stat-card">
                <span class="stat-number" style="color: var(--found-color);"><?= $stats['found'] ?></span>
                <span class="stat-label">Active Found</span>
            </div>
            <div class="stat-card">
                <span class="stat-number" style="color: var(--primary);"><?= $stats['resolved'] ?></span>
                <span class="stat-label">Reunited / Resolved</span>
            </div>
        </div>
    </div>
</section>

<!-- Main Feed Container -->
<main class="main-wrapper">
    <!-- Category Quick Filters -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
        <h2 style="font-size: 1.4rem; font-weight: 800;">Explore by Category</h2>
        <a href="browse.php" style="color: var(--primary); font-weight: 600; font-size: 0.9rem;">View All Catalog &rarr;</a>
    </div>

    <div class="category-pills">
        <a href="browse.php" class="category-pill active">
            All Categories
        </a>
        <?php foreach ($categories as $slug => $cat): ?>
            <a href="browse.php?category=<?= urlencode($slug) ?>" class="category-pill">
                <?= htmlspecialchars($cat['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Section Title & Filter Tabs -->
    <div class="filter-bar">
        <div class="type-tabs">
            <a href="browse.php" class="tab-btn active">All Recent</a>
            <a href="browse.php?type=lost" class="tab-btn">Lost Items</a>
            <a href="browse.php?type=found" class="tab-btn">Found Items</a>
            <a href="browse.php?type=claimed" class="tab-btn">Resolved</a>
        </div>
        <div class="sort-wrapper">
            <span>Showing recent activity</span>
        </div>
    </div>

    <!-- Items Grid -->
    <div class="items-grid">
        <?php if (empty($recentItems)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>No Items Reported Yet</h3>
                <p>Be the first to publish a lost or found report in your area.</p>
                <a href="report.php" class="btn btn-primary" style="margin-top: 1rem;">Report an Item</a>
            </div>
        <?php else: ?>
            <?php foreach ($recentItems as $item): ?>
                <div class="item-card">
                    <div class="card-img-wrapper">
                        <?php if (!empty($item['image_path']) && file_exists(UPLOAD_DIR . $item['image_path'])): ?>
                            <img src="<?= UPLOAD_WEB_PATH . htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="card-img">
                        <?php else: ?>
                            <div class="card-placeholder-icon" style="font-size: 3rem; color: #94a3b8; display: flex; align-items: center; justify-content: center; height: 180px; background: #f1f5f9;">
                                📦
                            </div>
                        <?php endif; ?>

                        <!-- Status Badges -->
                        <?php if ($item['status'] === 'claimed' || $item['status'] === 'returned'): ?>
                            <span class="badge badge-claimed">Resolved</span>
                        <?php elseif ($item['type'] === 'lost'): ?>
                            <span class="badge badge-lost">Lost</span>
                        <?php else: ?>
                            <span class="badge badge-found">Found</span>
                        <?php endif; ?>

                        <span class="category-tag">
                            <?= htmlspecialchars(get_category_label($item['category'])) ?>
                        </span>
                    </div>

                    <div class="card-body">
                        <h3 class="card-title">
                            <a href="item.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
                        </h3>
                        <p class="card-desc"><?= htmlspecialchars($item['description']) ?></p>

                        <div class="card-meta">
                            <div class="meta-item">
                                <span>📍</span>
                                <span><?= htmlspecialchars($item['location']) ?></span>
                            </div>
                            <div class="meta-item">
                                <span>📅</span>
                                <span><?= htmlspecialchars($item['item_date']) ?> (<?= time_ago($item['created_at']) ?>)</span>
                            </div>
                        </div>

                        <div class="card-actions">
                            <a href="item.php?id=<?= $item['id'] ?>" class="btn btn-secondary btn-sm" style="flex: 1;">
                                View Details &rarr;
                            </a>
                            <?php if ($item['status'] === 'active' && $item['type'] === 'found'): ?>
                                <a href="item.php?id=<?= $item['id'] ?>#claim" class="btn btn-primary btn-sm">
                                    Claim
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Bottom Call-to-Action -->
    <div style="text-align: center; margin-top: 3rem; padding: 2.5rem; background: #ffffff; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">Can't find what you are looking for?</h3>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Use our multi-filter search catalog or report a missing belonging directly.</p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="browse.php" class="btn btn-secondary">Open Advanced Search</a>
            <a href="report.php" class="btn btn-primary">+ Post a New Report</a>
        </div>
    </div>
</main>

<?php render_footer(); ?>
