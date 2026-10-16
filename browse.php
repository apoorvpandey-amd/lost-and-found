<?php
/**
 * FindIt - Browse & Search Catalog
 * Implements full database SEARCH functionality across keywords, types, and categories.
 */
$pageTitle = 'Browse & Search Items';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/header.php';

$pdo = getDbConnection();

// Get filter parameters
$q = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? 'all');
$category = trim($_GET['category'] ?? 'all');
$sort = trim($_GET['sort'] ?? 'newest');

// Build SQL WHERE conditions
$where = [];
$params = [];

// 1. Search Query
if ($q !== '') {
    $where[] = "(title LIKE :q OR description LIKE :q OR location LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

// 2. Type Filter
if ($type === 'lost') {
    $where[] = "type = 'lost' AND status = 'active'";
} elseif ($type === 'found') {
    $where[] = "type = 'found' AND status = 'active'";
} elseif ($type === 'claimed') {
    $where[] = "status IN ('claimed', 'returned')";
}

// 3. Category Filter
if ($category !== 'all' && !empty($category)) {
    $where[] = "category = :category";
    $params[':category'] = $category;
}

// Query Execution
$sql = "SELECT * FROM items";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

if ($sort === 'oldest') {
    $sql .= " ORDER BY item_date ASC, id ASC";
} else {
    $sql .= " ORDER BY item_date DESC, id DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
$totalFound = count($items);

$categories = get_categories();
?>

<main class="main-wrapper" style="margin-top: 1.5rem;">
    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <a href="index.php">Home</a>
        <span class="divider">/</span>
        <span>Search &amp; Browse</span>
    </div>

    <!-- Search & Filter Controls Card -->
    <div style="background: #ffffff; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); padding: 1.75rem; margin-bottom: 2rem;">
        <h1 style="font-size: 1.75rem; font-weight: 800; margin-bottom: 1.25rem;">
            🔍 Search &amp; Filter Database
        </h1>

        <form action="browse.php" method="GET" class="search-form">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 0.85rem; align-items: end;">
                <!-- Keyword Query -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Keyword / Description / Place</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Search keywords...">
                </div>

                <!-- Type Selector -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Item Type</label>
                    <select name="type" class="form-control">
                        <option value="all" <?= ($type === 'all') ? 'selected' : '' ?>>All Items</option>
                        <option value="lost" <?= ($type === 'lost') ? 'selected' : '' ?>>Lost Only</option>
                        <option value="found" <?= ($type === 'found') ? 'selected' : '' ?>>Found Only</option>
                        <option value="claimed" <?= ($type === 'claimed') ? 'selected' : '' ?>>Resolved Only</option>
                    </select>
                </div>

                <!-- Category Selector -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <option value="all" <?= ($category === 'all') ? 'selected' : '' ?>>All Categories</option>
                        <?php foreach ($categories as $catKey => $catData): ?>
                            <option value="<?= htmlspecialchars($catKey) ?>" <?= ($category === $catKey) ? 'selected' : '' ?>>
                                <?= $catData['icon'] ?> <?= htmlspecialchars($catData['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Sorting -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-control">
                        <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= ($sort === 'oldest') ? 'selected' : '' ?>>Oldest First</option>
                    </select>
                </div>

                <!-- Submit / Reset Buttons -->
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="height: 42px;">
                        Filter
                    </button>
                    <?php if ($q !== '' || $type !== 'all' || $category !== 'all'): ?>
                        <a href="browse.php" class="btn btn-secondary" style="height: 42px;" title="Reset filters">
                            ✕
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <p style="font-size: 1rem; color: var(--text-muted);">
            Showing <strong><?= $totalFound ?></strong> <?= ($totalFound === 1) ? 'result' : 'results' ?> 
            <?php if ($q !== ''): ?>for "<strong><?= htmlspecialchars($q) ?></strong>"<?php endif; ?>
        </p>
        <a href="report.php" class="btn btn-secondary btn-sm">+ Post Missing Item</a>
    </div>

    <!-- Items Grid -->
    <div class="items-grid">
        <?php if ($totalFound === 0): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔎</div>
                <h3>No Matching Records Found</h3>
                <p>We couldn't find any reports matching your search criteria. Try different keywords or reset your filters.</p>
                <div style="display: flex; gap: 0.75rem; justify-content: center;">
                    <a href="browse.php" class="btn btn-secondary">Clear Search Filters</a>
                    <a href="report.php" class="btn btn-primary">Report This Item</a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="item-card">
                    <div class="card-img-wrapper">
                        <?php if (!empty($item['image_path']) && file_exists(UPLOAD_DIR . $item['image_path'])): ?>
                            <img src="<?= UPLOAD_WEB_PATH . htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="card-img">
                        <?php else: ?>
                            <div class="card-placeholder-icon">
                                <?= get_category_icon($item['category']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Badges -->
                        <?php if ($item['status'] === 'claimed' || $item['status'] === 'returned'): ?>
                            <span class="badge badge-claimed">Resolved</span>
                        <?php elseif ($item['type'] === 'lost'): ?>
                            <span class="badge badge-lost">Lost</span>
                        <?php else: ?>
                            <span class="badge badge-found">Found</span>
                        <?php endif; ?>

                        <span class="category-tag">
                            <?= get_category_icon($item['category']) ?> <?= htmlspecialchars(get_category_label($item['category'])) ?>
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
                                <span>Date: <?= htmlspecialchars($item['item_date']) ?> (<?= time_ago($item['created_at']) ?>)</span>
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
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
