<?php
/**
 * FindIt - User Registration Page (Session & INSERT User)
 */
$pageTitle = 'Create an Account';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/header.php';

// If already logged in, redirect
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$pdo = getDbConnection();
$name = '';
$email = '';
$phone = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['csrf_token']) && !verify_csrf_token($_POST['csrf_token'])) {
        $errors['csrf'] = 'Invalid form security token. Please try again.';
    }

    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validations
    if (empty($name) || strlen($name) < 2) {
        $errors['name'] = 'Full name is required (minimum 2 characters).';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    } else {
        // Check uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'An account with this email address already exists. Please log in.';
        }
    }

    if (empty($password) || strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Insert user into database
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insertStmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, phone)
            VALUES (:name, :email, :password, 'user', :phone)
        ");

        $success = $insertStmt->execute([
            ':name'     => $name,
            ':email'    => $email,
            ':password' => $hashedPassword,
            ':phone'    => $phone
        ]);

        if ($success) {
            $userId = $pdo->lastInsertId();
            $newUser = [
                'id'    => $userId,
                'name'  => $name,
                'email' => $email,
                'role'  => 'user'
            ];

            // Establish session
            login_user($newUser);
            set_flash('success', 'Your account has been created successfully! Welcome to FindIt.');
            header("Location: dashboard.php");
            exit;
        } else {
            $errors['db'] = 'Failed to create user account. Please try again.';
        }
    }
}
?>

<main class="main-wrapper">
    <div class="form-container" style="max-width: 520px; margin-top: 2.5rem;">
        <div class="form-header">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">👋</div>
            <h1>Create Free Account</h1>
            <p>Join FindIt to report items, track status updates, and verify claims.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">⚠️</span>
                <div class="alert-content">
                    <strong>Registration incomplete:</strong>
                    <ul style="margin-top: 0.4rem; padding-left: 1.25rem;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="needs-validation" novalidate>
            <?= csrf_field() ?>

            <!-- Full Name -->
            <div class="form-group">
                <label class="form-label" for="name">Full Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" placeholder="e.g. John Doe" value="<?= htmlspecialchars($name) ?>" required minlength="2">
                <?php if (isset($errors['name'])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors['name']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Email Address -->
            <div class="form-group">
                <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" placeholder="name@example.com" value="<?= htmlspecialchars($email) ?>" required>
                <?php if (isset($errors['email'])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors['email']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Phone Number -->
            <div class="form-group">
                <label class="form-label" for="phone">Phone Number (Optional)</label>
                <input type="tel" id="phone" name="phone" class="form-control" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($phone) ?>">
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" placeholder="Minimum 6 characters" required minlength="6">
                <?php if (isset($errors['password'])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors['password']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" placeholder="Repeat your password" required minlength="6">
                <?php if (isset($errors['confirm_password'])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors['confirm_password']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Submit Button -->
            <div style="margin-top: 1.75rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem;">
                    Complete Registration
                </button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); font-size: 0.9rem;">
            Already have an account? <a href="login.php" style="color: var(--primary); font-weight: 700;">Sign in here</a>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
