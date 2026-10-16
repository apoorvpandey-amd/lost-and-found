<?php
/**
 * FindIt - Edit Item (UPDATE Operation)
 * Allows the owner or administrator to modify item details, change status, or update image.
 */
$pageTitle = 'Edit Item';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/header.php';

require_login(); // Session check

$pdo = getDbConnection();
$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$itemId) {
    set_flash('danger', 'Invalid item ID.');
    header("Location: dashboard.php");
    exit;
}

// Fetch item from database
$stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
$stmt->execute([':id' => $itemId]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('danger', 'Item not found.');
    header("Location: dashboard.php");
    exit;
}

// Authorization check: User must own this item or be an admin
if (!can_manage_item($item)) {
    set_flash('danger', 'You do not have permission to edit this item.');
    header("Location: item.php?id=" . $itemId);
    exit;
}

$categories = get_categories();
$errors = [];

// Values initialized from DB
$title = $item['title'];
$type = $item['type'];
$category = $item['category'];
$status = $item['status'];
$item_date = $item['item_date'];
$location = $item['location'];
$description = $item['description'];
$contact_name = $item['contact_name'];
$contact_email = $item['contact_email'];
$contact_phone = $item['contact_phone'];
$current_image = $item['image_path'];

// Handle POST (UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid form security token. Please try again.';
    }

    // 2. Collect inputs
    $title = sanitize($_POST['title'] ?? '');
    $type = in_array($_POST['type'] ?? '', ['lost', 'found']) ? $_POST['type'] : $item['type'];
    $category = sanitize($_POST['category'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'claimed', 'returned']) ? $_POST['status'] : 'active';
    $item_date = sanitize($_POST['item_date'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $contact_name = sanitize($_POST['contact_name'] ?? '');
    $contact_email = sanitize($_POST['contact_email'] ?? '');
    $contact_phone = sanitize($_POST['contact_phone'] ?? '');

    // 3. Validations
    if (empty($title) || strlen($title) < 3) {
        $errors['title'] = 'Item title must be at least 3 characters long.';
    }

    if (empty($category) || !array_key_exists($category, $categories)) {
        $errors['category'] = 'Please select a valid category.';
    }

    if (empty($item_date)) {
        $errors['item_date'] = 'Date is required.';
    } elseif (strtotime($item_date) > strtotime(date('Y-m-d'))) {
        $errors['item_date'] = 'Date cannot be in the future.';
    }

    if (empty($location) || strlen($location) < 3) {
        $errors['location'] = 'Please provide a valid location.';
    }

    if (empty($description) || strlen($description) < 10) {
        $errors['description'] = 'Description must be at least 10 characters.';
    }

    if (empty($contact_name)) {
        $errors['contact_name'] = 'Contact name is required.';
    }

    if (empty($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $errors['contact_email'] = 'A valid email is required.';
    }

    // 4. Handle replacement image upload if provided
    $new_image_name = $current_image;
    if (empty($errors) && isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = handle_image_upload('item_image');
        if ($uploadResult['success']) {
            $new_image_name = $uploadResult['filename'];
            // Optionally delete old image if replaced
            if (!empty($current_image) && file_exists(UPLOAD_DIR . $current_image)) {
                @unlink(UPLOAD_DIR . $current_image);
            }
        } else {
            $errors['item_image'] = $uploadResult['error'];
        }
    }

    // 5. Execute UPDATE
    if (empty($errors)) {
        $sql = "
            UPDATE items SET
                type = :type,
                title = :title,
                category = :category,
                status = :status,
                description = :description,
                location = :location,
                item_date = :item_date,
                image_path = :image_path,
                contact_name = :contact_name,
                contact_email = :contact_email,
                contact_phone = :contact_phone,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ";

        $updateStmt = $pdo->prepare($sql);
        $res = $updateStmt->execute([
            ':type'          => $type,
            ':title'         => $title,
            ':category'      => $category,
            ':status'        => $status,
            ':description'   => $description,
            ':location'      => $location,
            ':item_date'     => $item_date,
            ':image_path'    => $new_image_name,
            ':contact_name'  => $contact_name,
            ':contact_email' => $contact_email,
            ':contact_phone' => $contact_phone,
            ':id'            => $itemId
        ]);

        if ($res) {
            set_flash('success', 'Item details updated successfully!');
            header("Location: item.php?id=" . $itemId);
            exit;
        } else {
            $errors['db'] = 'Failed to update record in database.';
        }
    }
}
?>

<main class="main-wrapper">
    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <a href="index.php">Home</a>
        <span class="divider">/</span>
        <a href="dashboard.php">Dashboard</a>
        <span class="divider">/</span>
        <a href="item.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
        <span class="divider">/</span>
        <span>Edit Item</span>
    </div>

    <div class="form-container">
        <div class="form-header">
            <h1>✏️ Edit Item Report</h1>
            <p>Update specifications, status, or contact details for this report.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">⚠️</span>
                <div class="alert-content">
                    <strong>Please correct the following errors:</strong>
                    <ul style="margin-top: 0.5rem; padding-left: 1.25rem;">
                        <?php foreach ($errors as $field => $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form action="edit_item.php?id=<?= $itemId ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?= csrf_field() ?>

            <!-- Type & Status Row -->
            <div class="form-grid" style="margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Report Type</label>
                    <select name="type" class="form-control">
                        <option value="lost" <?= ($type === 'lost') ? 'selected' : '' ?>>🔴 Lost Item</option>
                        <option value="found" <?= ($type === 'found') ? 'selected' : '' ?>>🟢 Found Item</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?= ($status === 'active') ? 'selected' : '' ?>>Active (Searching / Unresolved)</option>
                        <option value="claimed" <?= ($status === 'claimed') ? 'selected' : '' ?>>Claimed</option>
                        <option value="returned" <?= ($status === 'returned') ? 'selected' : '' ?>>Returned to Owner / Reunited</option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <!-- Title -->
                <div class="form-group full-width">
                    <label class="form-label" for="title">Item Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($title) ?>" required minlength="3">
                </div>

                <!-- Category -->
                <div class="form-group">
                    <label class="form-label" for="category">Category <span class="required">*</span></label>
                    <select id="category" name="category" class="form-control" required>
                        <?php foreach ($categories as $catKey => $catData): ?>
                            <option value="<?= htmlspecialchars($catKey) ?>" <?= ($category === $catKey) ? 'selected' : '' ?>>
                                <?= $catData['icon'] ?> <?= htmlspecialchars($catData['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label class="form-label" for="item_date">Date Lost or Found <span class="required">*</span></label>
                    <input type="date" id="item_date" name="item_date" max="<?= date('Y-m-d') ?>" class="form-control" value="<?= htmlspecialchars($item_date) ?>" required>
                </div>

                <!-- Location -->
                <div class="form-group full-width">
                    <label class="form-label" for="location">Location / Landmark <span class="required">*</span></label>
                    <input type="text" id="location" name="location" class="form-control" value="<?= htmlspecialchars($location) ?>" required minlength="3">
                </div>

                <!-- Detailed Description -->
                <div class="form-group full-width">
                    <label class="form-label" for="description">Detailed Description <span class="required">*</span></label>
                    <textarea id="description" name="description" class="form-control" rows="4" required minlength="10"><?= htmlspecialchars($description) ?></textarea>
                </div>

                <!-- Image Management -->
                <div class="form-group full-width">
                    <label class="form-label">Item Photo</label>
                    <?php if (!empty($current_image) && file_exists(UPLOAD_DIR . $current_image)): ?>
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.75rem; background: #f8fafc; padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                            <img src="<?= UPLOAD_WEB_PATH . htmlspecialchars($current_image) ?>" alt="Current image" style="width: 70px; height: 70px; object-fit: cover; border-radius: var(--radius-sm);">
                            <div>
                                <span style="font-size: 0.85rem; font-weight: 600;">Current Photo</span>
                                <p style="font-size: 0.8rem; color: var(--text-muted);">Upload a new file below to replace this photo.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="file-upload-box">
                        <div class="upload-icon">📷</div>
                        <p style="font-weight: 600; margin-bottom: 0.25rem;">Upload new photo to replace</p>
                        <p style="font-size: 0.85rem; color: var(--text-muted);">Supports JPG, PNG, WEBP, GIF (Max: 5MB)</p>
                        <input type="file" id="item_image" name="item_image" accept="image/jpeg,image/png,image/webp,image/gif">
                    </div>

                    <div class="image-preview-container" id="imagePreviewContainer">
                        <img id="imagePreview" src="" alt="Selected image preview">
                        <button type="button" class="remove-img-btn" id="removeImageBtn" title="Remove">&times;</button>
                    </div>
                </div>

                <!-- Contact Name -->
                <div class="form-group">
                    <label class="form-label" for="contact_name">Your Name <span class="required">*</span></label>
                    <input type="text" id="contact_name" name="contact_name" class="form-control" value="<?= htmlspecialchars($contact_name) ?>" required>
                </div>

                <!-- Contact Email -->
                <div class="form-group">
                    <label class="form-label" for="contact_email">Email Address <span class="required">*</span></label>
                    <input type="email" id="contact_email" name="contact_email" class="form-control" value="<?= htmlspecialchars($contact_email) ?>" required>
                </div>

                <!-- Contact Phone -->
                <div class="form-group full-width">
                    <label class="form-label" for="contact_phone">Phone Number (Optional)</label>
                    <input type="tel" id="contact_phone" name="contact_phone" class="form-control" value="<?= htmlspecialchars($contact_phone) ?>">
                </div>
            </div>

            <!-- Submit Button & Cancel -->
            <div style="margin-top: 2rem; display: flex; gap: 1rem; align-items: center;">
                <button type="submit" class="btn btn-primary" style="padding: 0.85rem 2rem; font-size: 1rem;">
                    💾 Save Changes (Update)
                </button>
                <a href="item.php?id=<?= $itemId ?>" class="btn btn-secondary" style="padding: 0.85rem 1.5rem;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
