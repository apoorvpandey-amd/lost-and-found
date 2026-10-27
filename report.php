<?php
/**
 * FindIt - Report & Edit Item Form Module
 * Consolidates creating new reports (INSERT) and modifying existing listings (UPDATE).
 * Enforces compulsory login: no one can post or edit information without an authenticated account.
 */
require_once __DIR__ . '/db.php';

// Enforce Compulsory Login
require_login();

$pdo = getDbConnection();
$currentUser = current_user();
$categories = get_categories();

// Check if in Edit Mode (by ID parameter)
$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$isEdit = false;
$item = null;

if ($itemId) {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        set_flash('danger', 'The requested item could not be found.');
        header("Location: dashboard.php");
        exit;
    }

    // Authorization: User must own the item or be an administrator
    if (!can_manage_item($item)) {
        set_flash('danger', 'Unauthorized. You do not have permission to edit this listing.');
        header("Location: item.php?id=" . $itemId);
        exit;
    }

    $isEdit = true;
}

// Form Initial Values
$type          = $isEdit ? $item['type'] : ($_GET['type'] ?? 'lost');
if (!in_array($type, ['lost', 'found'])) $type = 'lost';

$title         = $isEdit ? $item['title'] : '';
$category      = $isEdit ? $item['category'] : '';
$status        = $isEdit ? $item['status'] : 'active';
$item_date     = $isEdit ? $item['item_date'] : date('Y-m-d');
$location      = $isEdit ? $item['location'] : '';
$description   = $isEdit ? $item['description'] : '';
$contact_name  = $isEdit ? $item['contact_name'] : ($currentUser['name'] ?? '');
$contact_email = $isEdit ? $item['contact_email'] : ($currentUser['email'] ?? '');
$contact_phone = $isEdit ? $item['contact_phone'] : '';
$current_image = $isEdit ? $item['image_path'] : null;

$errors = [];

// Handle Form Submission (INSERT or UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid or expired form security token. Please try again.';
    }

    // 2. Collect and sanitize input
    $type          = in_array($_POST['type'] ?? '', ['lost', 'found']) ? $_POST['type'] : 'lost';
    $title         = sanitize($_POST['title'] ?? '');
    $category      = sanitize($_POST['category'] ?? '');
    $status        = in_array($_POST['status'] ?? '', ['active', 'claimed', 'returned']) ? $_POST['status'] : 'active';
    $item_date     = sanitize($_POST['item_date'] ?? '');
    $location      = sanitize($_POST['location'] ?? '');
    $description   = sanitize($_POST['description'] ?? '');
    $contact_name  = sanitize($_POST['contact_name'] ?? '');
    $contact_email = sanitize($_POST['contact_email'] ?? '');
    $contact_phone = sanitize($_POST['contact_phone'] ?? '');

    // 3. Field Validations
    if (empty($title) || strlen($title) < 3) {
        $errors['title'] = 'Item title must be at least 3 characters long.';
    }

    if (empty($category) || !array_key_exists($category, $categories)) {
        $errors['category'] = 'Please select a valid category from the list.';
    }

    if (empty($item_date)) {
        $errors['item_date'] = 'Please specify the date this item was lost or found.';
    } elseif (strtotime($item_date) > strtotime(date('Y-m-d'))) {
        $errors['item_date'] = 'The date cannot be in the future.';
    }

    if (empty($location) || strlen($location) < 3) {
        $errors['location'] = 'Please provide a specific location or landmark.';
    }

    if (empty($description) || strlen($description) < 10) {
        $errors['description'] = 'Please provide a detailed description (at least 10 characters).';
    }

    if (empty($contact_name)) {
        $errors['contact_name'] = 'Your contact name is required.';
    }

    if (empty($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $errors['contact_email'] = 'A valid contact email address is required.';
    }

    // 4. Handle Image Upload
    $uploaded_image_name = $current_image;
    if (empty($errors) && isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = handle_image_upload('item_image');
        if ($uploadResult['success']) {
            // Remove old image file if replacing an existing upload
            if ($isEdit && !empty($current_image) && file_exists(UPLOAD_DIR . $current_image)) {
                @unlink(UPLOAD_DIR . $current_image);
            }
            $uploaded_image_name = $uploadResult['filename'];
        } else {
            $errors['item_image'] = $uploadResult['error'];
        }
    }

    // 5. Database Execution (INSERT or UPDATE)
    if (empty($errors)) {
        if ($isEdit) {
            // UPDATE existing listing
            $sql = "
                UPDATE items SET 
                    type = :type,
                    title = :title,
                    category = :category,
                    status = :status,
                    item_date = :item_date,
                    location = :location,
                    description = :description,
                    contact_name = :contact_name,
                    contact_email = :contact_email,
                    contact_phone = :contact_phone,
                    image_path = :image_path,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':type'          => $type,
                ':title'         => $title,
                ':category'      => $category,
                ':status'        => $status,
                ':item_date'     => $item_date,
                ':location'      => $location,
                ':description'   => $description,
                ':contact_name'  => $contact_name,
                ':contact_email' => $contact_email,
                ':contact_phone' => $contact_phone,
                ':image_path'    => $uploaded_image_name,
                ':id'            => $itemId
            ]);

            set_flash('success', 'The item "' . htmlspecialchars($title) . '" has been updated successfully!');
            header("Location: item.php?id=" . $itemId);
            exit;
        } else {
            // INSERT new listing
            $securitySecret = bin2hex(random_bytes(16));
            $sql = "
                INSERT INTO items (
                    user_id, type, title, category, description, location, 
                    item_date, image_path, contact_name, contact_email, 
                    contact_phone, status, security_secret
                ) VALUES (
                    :user_id, :type, :title, :category, :description, :location, 
                    :item_date, :image_path, :contact_name, :contact_email, 
                    :contact_phone, 'active', :security_secret
                )
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id'         => $currentUser['id'],
                ':type'            => $type,
                ':title'           => $title,
                ':category'        => $category,
                ':description'     => $description,
                ':location'        => $location,
                ':item_date'       => $item_date,
                ':image_path'      => $uploaded_image_name,
                ':contact_name'    => $contact_name,
                ':contact_email'   => $contact_email,
                ':contact_phone'   => $contact_phone,
                ':security_secret' => $securitySecret
            ]);

            $newItemId = $pdo->lastInsertId();
            set_flash('success', 'Your item report has been published successfully!');
            header("Location: item.php?id=" . $newItemId);
            exit;
        }
    }
}

$pageTitle = $isEdit ? 'Edit Item: ' . htmlspecialchars($title) : 'Report an Item';
render_header($pageTitle);
?>

<main class="main-wrapper">
    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <a href="index.php">Home</a>
        <span class="divider">/</span>
        <?php if ($isEdit): ?>
            <a href="item.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
            <span class="divider">/</span>
            <span>Edit Report</span>
        <?php else: ?>
            <a href="browse.php">Browse</a>
            <span class="divider">/</span>
            <span>Report Item</span>
        <?php endif; ?>
    </div>

    <div class="form-container">
        <div class="form-header">
            <h1><?= $isEdit ? '✏️ Edit Item Report' : '📢 Report a Lost or Found Item' ?></h1>
            <p>
                <?= $isEdit ? 'Update details, upload a revised photo, or change the lifecycle status of your item.' : 'Provide accurate details and photos to help reconnect items with their rightful owners quickly.' ?>
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <div class="alert-content">
                    <?php if (isset($errors['csrf'])): ?>
                        <?= htmlspecialchars($errors['csrf']) ?>
                    <?php else: ?>
                        Please correct the errors indicated below and submit again.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <form action="report.php<?= $isEdit ? '?id=' . $itemId : '' ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= $itemId ?>">
            <?php endif; ?>

            <!-- Item Type Selection -->
            <div class="form-group">
                <label class="form-label">Report Category Type <span class="required">*</span></label>
                <div class="radio-card-group">
                    <label class="radio-card" style="border-color: <?= ($type === 'lost') ? 'var(--lost-color)' : 'var(--border-color)' ?>;">
                        <input type="radio" name="type" value="lost" <?= ($type === 'lost') ? 'checked' : '' ?> onchange="this.form.submit()">
                        <div class="radio-card-content">
                            <span class="radio-card-title" style="color: var(--lost-color);">🔴 I Lost Something</span>
                            <span class="radio-card-desc">Post details of an item you misplaced to help finders identify it.</span>
                        </div>
                    </label>

                    <label class="radio-card" style="border-color: <?= ($type === 'found') ? 'var(--found-color)' : 'var(--border-color)' ?>;">
                        <input type="radio" name="type" value="found" <?= ($type === 'found') ? 'checked' : '' ?> onchange="this.form.submit()">
                        <div class="radio-card-content">
                            <span class="radio-card-title" style="color: var(--found-color);">🟢 I Found Something</span>
                            <span class="radio-card-desc">Help return discovered belongings safely to the rightful owner.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Status Dropdown (Edit Mode Only) -->
            <?php if ($isEdit): ?>
                <div class="form-group" style="background: #f8fafc; padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <label class="form-label" for="status">
                        Listing Lifecycle Status <span class="required">*</span>
                    </label>
                    <select id="status" name="status" class="form-control" style="font-weight: 600;">
                        <option value="active" <?= ($status === 'active') ? 'selected' : '' ?>>🟢 Active (Searching / Unresolved)</option>
                        <option value="claimed" <?= ($status === 'claimed') ? 'selected' : '' ?>>✓ Claimed / Reunited (Resolved)</option>
                        <option value="returned" <?= ($status === 'returned') ? 'selected' : '' ?>>🤝 Handed Over to Security / Desk</option>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Two-Column Layout for Item Metadata -->
            <div class="form-grid">
                <!-- Title -->
                <div class="form-group full-width">
                    <label class="form-label" for="title">Item Title / Headline <span class="required">*</span></label>
                    <input type="text" id="title" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" placeholder="e.g. Apple iPhone 13 in Midnight Blue" value="<?= htmlspecialchars($title) ?>" required>
                    <?php if (isset($errors['title'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['title']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Category -->
                <div class="form-group">
                    <label class="form-label" for="category">Category <span class="required">*</span></label>
                    <select id="category" name="category" class="form-control <?= isset($errors['category']) ? 'is-invalid' : '' ?>" required>
                        <option value="">-- Choose Category --</option>
                        <?php foreach ($categories as $slug => $cat): ?>
                            <option value="<?= htmlspecialchars($slug) ?>" <?= ($category === $slug) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['category']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label class="form-label" for="item_date">Date Lost / Found <span class="required">*</span></label>
                    <input type="date" id="item_date" name="item_date" class="form-control <?= isset($errors['item_date']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($item_date) ?>" max="<?= date('Y-m-d') ?>" required>
                    <?php if (isset($errors['item_date'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['item_date']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Location -->
                <div class="form-group full-width">
                    <label class="form-label" for="location">Location / Landmark <span class="required">*</span></label>
                    <input type="text" id="location" name="location" class="form-control <?= isset($errors['location']) ? 'is-invalid' : '' ?>" placeholder="e.g. Science Library, 2nd Floor Study Room 204" value="<?= htmlspecialchars($location) ?>" required>
                    <?php if (isset($errors['location'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['location']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="form-group full-width">
                    <label class="form-label" for="description">Detailed Description <span class="required">*</span></label>
                    <textarea id="description" name="description" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" rows="4" placeholder="Describe color, brand, distinct scratches, contents, or circumstances..." required><?= htmlspecialchars($description) ?></textarea>
                    <?php if (isset($errors['description'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['description']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Image Upload & Preview -->
                <div class="form-group full-width">
                    <label class="form-label">Photo Upload (Optional, max 5MB)</label>
                    
                    <?php if (!empty($current_image) && file_exists(UPLOAD_DIR . $current_image)): ?>
                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.85rem; background: #f8fafc; padding: 0.75rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                            <img src="<?= UPLOAD_WEB_PATH . htmlspecialchars($current_image) ?>" alt="Current Image" style="width: 70px; height: 70px; object-fit: cover; border-radius: var(--radius-sm);">
                            <div>
                                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">Current Photo Attached</span>
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Upload a new image below if you would like to replace this photo.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="file-drop-area" id="dropArea">
                        <span class="drop-icon">📷</span>
                        <span class="drop-text">Click to choose image or drag &amp; drop file here</span>
                        <span class="drop-hint">Supported formats: JPG, PNG, WEBP, GIF (Up to 5MB)</span>
                        <input type="file" id="item_image" name="item_image" accept="image/jpeg,image/png,image/webp,image/gif" class="file-input">
                    </div>
                    <?php if (isset($errors['item_image'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['item_image']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contact Information Section -->
            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 0.25rem;">Contact Information</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                    This contact info will be visible to logged-in members to coordinate recovery.
                </p>

                <div class="form-grid">
                    <!-- Contact Name -->
                    <div class="form-group">
                        <label class="form-label" for="contact_name">Your Name <span class="required">*</span></label>
                        <input type="text" id="contact_name" name="contact_name" class="form-control <?= isset($errors['contact_name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($contact_name) ?>" required>
                        <?php if (isset($errors['contact_name'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['contact_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Contact Email -->
                    <div class="form-group">
                        <label class="form-label" for="contact_email">Email Address <span class="required">*</span></label>
                        <input type="email" id="contact_email" name="contact_email" class="form-control <?= isset($errors['contact_email']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($contact_email) ?>" required>
                        <?php if (isset($errors['contact_email'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['contact_email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Contact Phone -->
                    <div class="form-group full-width">
                        <label class="form-label" for="contact_phone">Phone Number (Optional)</label>
                        <input type="tel" id="contact_phone" name="contact_phone" class="form-control" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($contact_phone) ?>">
                    </div>
                </div>
            </div>

            <!-- Submit & Cancel Buttons -->
            <div style="margin-top: 2rem; display: flex; gap: 1rem; align-items: center;">
                <button type="submit" class="btn btn-primary" style="padding: 0.85rem 2.25rem; font-size: 1rem;">
                    <?= $isEdit ? '💾 Save Changes' : '🚀 Publish Item Report' ?>
                </button>
                <a href="<?= $isEdit ? 'item.php?id=' . $itemId : 'index.php' ?>" class="btn btn-secondary" style="padding: 0.85rem 1.5rem;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</main>

<?php render_footer(); ?>
