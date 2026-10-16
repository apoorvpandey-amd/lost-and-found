<?php
/**
 * FindIt - Report an Item (INSERT Operation)
 * Handles both client-side and server-side validation with image upload.
 */
$pageTitle = 'Report Lost or Found Item';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/header.php';

$pdo = getDbConnection();
$categories = get_categories();
$currentUser = current_user();

// Form default values
$type = $_GET['type'] ?? 'lost';
if (!in_array($type, ['lost', 'found'])) $type = 'lost';

$title = '';
$category = '';
$item_date = date('Y-m-d');
$location = '';
$description = '';
$contact_name = $currentUser['name'] ?? '';
$contact_email = $currentUser['email'] ?? '';
$contact_phone = '';

$errors = [];

// Form Submission (INSERT)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid or expired form security token. Please try again.';
    }

    // 2. Collect and sanitize input
    $type = in_array($_POST['type'] ?? '', ['lost', 'found']) ? $_POST['type'] : 'lost';
    $title = sanitize($_POST['title'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $item_date = sanitize($_POST['item_date'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $contact_name = sanitize($_POST['contact_name'] ?? '');
    $contact_email = sanitize($_POST['contact_email'] ?? '');
    $contact_phone = sanitize($_POST['contact_phone'] ?? '');

    // 3. Server-side validations
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
    $uploaded_image_name = null;
    if (empty($errors) && isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = handle_image_upload('item_image');
        if ($uploadResult['success']) {
            $uploaded_image_name = $uploadResult['filename'];
        } else {
            $errors['item_image'] = $uploadResult['error'];
        }
    }

    // 5. Database INSERT if validation passes
    if (empty($errors)) {
        $userId = $currentUser['id'] ?? null;
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
        $success = $stmt->execute([
            ':user_id'         => $userId,
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

        if ($success) {
            $newItemId = $pdo->lastInsertId();
            set_flash('success', 'Your item report has been published successfully! Others can now view and claim it.');
            header("Location: item.php?id=" . $newItemId);
            exit;
        } else {
            $errors['db'] = 'Failed to save item to database. Please try again.';
        }
    }
}
?>

<main class="main-wrapper">
    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <a href="index.php">Home</a>
        <span class="divider">/</span>
        <a href="browse.php">Browse</a>
        <span class="divider">/</span>
        <span>Post a Report</span>
    </div>

    <div class="form-container">
        <div class="form-header">
            <h1>📢 Submit a Report</h1>
            <p>Provide accurate details to maximize chances of finding or returning the item.</p>
        </div>

        <?php if (!is_logged_in()): ?>
            <div class="alert alert-info" style="margin-bottom: 2rem;">
                <span class="alert-icon">💡</span>
                <div class="alert-content">
                    Posting as guest. <a href="login.php?redirect=report.php" style="font-weight: 700; text-decoration: underline;">Sign In</a> or <a href="register.php" style="font-weight: 700; text-decoration: underline;">Register an account</a> to easily edit, update, or remove your reports anytime.
                </div>
            </div>
        <?php endif; ?>

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

        <!-- Report Form with needs-validation class -->
        <form action="report.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?= csrf_field() ?>

            <!-- Item Type Selection -->
            <div class="type-selector">
                <label class="type-option-label lost-option">
                    <input type="radio" name="type" value="lost" <?= ($type === 'lost') ? 'checked' : '' ?> onchange="updateTypeColor(this)">
                    <span>🔴 I Lost Something</span>
                    <span class="sub-text">Report a lost personal belonging</span>
                </label>

                <label class="type-option-label found-option">
                    <input type="radio" name="type" value="found" <?= ($type === 'found') ? 'checked' : '' ?> onchange="updateTypeColor(this)">
                    <span>🟢 I Found Something</span>
                    <span class="sub-text">Report an item you discovered</span>
                </label>
            </div>

            <div class="form-grid">
                <!-- Title -->
                <div class="form-group full-width">
                    <label class="form-label" for="title">Item Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" placeholder="e.g. Blue Hydroflask Water Bottle, Black Leather Wallet" value="<?= htmlspecialchars($title) ?>" required minlength="3">
                    <?php if (isset($errors['title'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['title']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Category -->
                <div class="form-group">
                    <label class="form-label" for="category">Category <span class="required">*</span></label>
                    <select id="category" name="category" class="form-control <?= isset($errors['category']) ? 'is-invalid' : '' ?>" required>
                        <option value="">Select Category...</option>
                        <?php foreach ($categories as $catKey => $catData): ?>
                            <option value="<?= htmlspecialchars($catKey) ?>" <?= ($category === $catKey) ? 'selected' : '' ?>>
                                <?= $catData['icon'] ?> <?= htmlspecialchars($catData['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['category']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label class="form-label" for="item_date">Date Lost or Found <span class="required">*</span></label>
                    <input type="date" id="item_date" name="item_date" max="<?= date('Y-m-d') ?>" class="form-control <?= isset($errors['item_date']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($item_date) ?>" required>
                    <?php if (isset($errors['item_date'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['item_date']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Location -->
                <div class="form-group full-width">
                    <label class="form-label" for="location">Location / Landmark <span class="required">*</span></label>
                    <input type="text" id="location" name="location" class="form-control <?= isset($errors['location']) ? 'is-invalid' : '' ?>" placeholder="e.g. Science Library 2nd Floor, Starbucks Downtown, Bus Route 4" value="<?= htmlspecialchars($location) ?>" required minlength="3">
                    <?php if (isset($errors['location'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['location']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Detailed Description -->
                <div class="form-group full-width">
                    <label class="form-label" for="description">Detailed Description <span class="required">*</span></label>
                    <textarea id="description" name="description" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" rows="4" placeholder="Mention brand, color, distinguishing marks, scratches, accessories, or specific details..." required minlength="10"><?= htmlspecialchars($description) ?></textarea>
                    <?php if (isset($errors['description'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['description']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Image Upload with Live Preview -->
                <div class="form-group full-width">
                    <label class="form-label">Upload Photo (Optional)</label>
                    <div class="file-upload-box">
                        <div class="upload-icon">📷</div>
                        <p style="font-weight: 600; margin-bottom: 0.25rem;">Click or drag an image here to upload</p>
                        <p style="font-size: 0.85rem; color: var(--text-muted);">Supports JPG, PNG, WEBP, GIF (Max: 5MB)</p>
                        <input type="file" id="item_image" name="item_image" accept="image/jpeg,image/png,image/webp,image/gif">
                    </div>

                    <!-- Client-side preview box -->
                    <div class="image-preview-container" id="imagePreviewContainer">
                        <img id="imagePreview" src="" alt="Selected image preview">
                        <button type="button" class="remove-img-btn" id="removeImageBtn" title="Remove photo">&times;</button>
                    </div>

                    <?php if (isset($errors['item_image'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['item_image']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Contact Name -->
                <div class="form-group">
                    <label class="form-label" for="contact_name">Your Name <span class="required">*</span></label>
                    <input type="text" id="contact_name" name="contact_name" class="form-control <?= isset($errors['contact_name']) ? 'is-invalid' : '' ?>" placeholder="Full Name" value="<?= htmlspecialchars($contact_name) ?>" required>
                    <?php if (isset($errors['contact_name'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['contact_name']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Contact Email -->
                <div class="form-group">
                    <label class="form-label" for="contact_email">Email Address <span class="required">*</span></label>
                    <input type="email" id="contact_email" name="contact_email" class="form-control <?= isset($errors['contact_email']) ? 'is-invalid' : '' ?>" placeholder="name@example.com" value="<?= htmlspecialchars($contact_email) ?>" required>
                    <?php if (isset($errors['contact_email'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['contact_email']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Contact Phone -->
                <div class="form-group full-width">
                    <label class="form-label" for="contact_phone">Phone Number (Optional)</label>
                    <input type="tel" id="contact_phone" name="contact_phone" class="form-control <?= isset($errors['contact_phone']) ? 'is-invalid' : '' ?>" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($contact_phone) ?>">
                    <?php if (isset($errors['contact_phone'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['contact_phone']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Submit Button & Cancel -->
            <div style="margin-top: 2rem; display: flex; gap: 1rem; align-items: center;">
                <button type="submit" class="btn btn-primary" style="padding: 0.85rem 2rem; font-size: 1rem;">
                    🚀 Publish Item Report
                </button>
                <a href="index.php" class="btn btn-secondary" style="padding: 0.85rem 1.5rem;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
