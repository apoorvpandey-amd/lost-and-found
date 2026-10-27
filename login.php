<?php
/**
 * FindIt - Unified Authentication Portal
 * Handles User Sign In, User Registration, and Logout within a single cohesive module.
 * Enforces compulsory authentication to protect portal data and prevent unauthorized edits.
 */
require_once __DIR__ . '/db.php';

// 1. Handle Logout Action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout_user();
    session_start();
    set_flash('info', 'You have been logged out successfully. Sign in anytime to continue.');
    header("Location: login.php");
    exit;
}

// 2. If Already Authenticated, Redirect to Main Portal
if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$pdo = getDbConnection();
$mode = $_GET['action'] ?? $_POST['action'] ?? 'login';
if ($mode !== 'register') {
    $mode = 'login';
}

$errors = [];
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'index.php';
// Clean redirect parameter to prevent open redirect vulnerabilities
if (strpos($redirect, 'http://') !== false || strpos($redirect, 'https://') !== false) {
    $redirect = 'index.php';
}

// Form fields state
$loginEmail = '';
$regName = '';
$regEmail = '';
$regPhone = '';

// 3. Process Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Session expired or security token invalid. Please submit again.';
    }

    if ($mode === 'login') {
        $loginEmail = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($loginEmail)) {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($loginEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $loginEmail]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                login_user($user);
                set_flash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');
                header("Location: " . $redirect);
                exit;
            } else {
                $errors['login'] = 'Invalid email address or password. Please verify your credentials.';
            }
        }
    } elseif ($mode === 'register') {
        $regName = sanitize($_POST['name'] ?? '');
        $regEmail = sanitize($_POST['email'] ?? '');
        $regPhone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($regName) || strlen($regName) < 2) {
            $errors['name'] = 'Full name is required (at least 2 characters).';
        }

        if (empty($regEmail) || !filter_var($regEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        } else {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $checkStmt->execute([':email' => $regEmail]);
            if ($checkStmt->fetch()) {
                $errors['email'] = 'An account with this email address already exists. Please sign in instead.';
            }
        }

        if (empty($password) || strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters long.';
        }

        if ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role, phone)
                VALUES (:name, :email, :password, 'user', :phone)
            ");
            $insertStmt->execute([
                ':name'     => $regName,
                ':email'    => $regEmail,
                ':password' => $hashedPassword,
                ':phone'    => $regPhone
            ]);

            $newUserId = $pdo->lastInsertId();
            $newUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
            $newUserStmt->execute([':id' => $newUserId]);
            $newUser = $newUserStmt->fetch();

            login_user($newUser);
            set_flash('success', 'Your account has been created! Welcome to FindIt, ' . htmlspecialchars($newUser['name']) . '.');
            header("Location: " . $redirect);
            exit;
        }
    }
}

$pageTitle = ($mode === 'register') ? 'Create Account' : 'Sign In Required';
render_header($pageTitle);
?>

<main class="main-wrapper">
    <div class="form-container" style="max-width: 480px; margin-top: 2.5rem; margin-bottom: 3.5rem;">
        
        <!-- Compulsory Login Notice -->
        <div style="background: #eef2ff; border: 1px solid #c7d2fe; border-radius: var(--radius-md); padding: 0.85rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 1.25rem;">🔒</span>
            <div style="font-size: 0.85rem; color: #3730a3; line-height: 1.4;">
                <strong>Authentication Required:</strong> Please sign in to access the portal. All editing and reporting operations require an active login session.
            </div>
        </div>

        <!-- Mode Navigation Tabs -->
        <div style="display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 1.5rem;">
            <a href="login.php?action=login<?= $redirect !== 'index.php' ? '&redirect=' . urlencode($redirect) : '' ?>" 
               style="flex: 1; text-align: center; padding: 0.75rem; font-weight: 700; font-size: 0.95rem; text-decoration: none; border-bottom: 3px solid <?= ($mode === 'login') ? 'var(--primary)' : 'transparent' ?>; color: <?= ($mode === 'login') ? 'var(--primary)' : 'var(--text-muted)' ?>; margin-bottom: -2px;">
                Sign In
            </a>
            <a href="login.php?action=register<?= $redirect !== 'index.php' ? '&redirect=' . urlencode($redirect) : '' ?>" 
               style="flex: 1; text-align: center; padding: 0.75rem; font-weight: 700; font-size: 0.95rem; text-decoration: none; border-bottom: 3px solid <?= ($mode === 'register') ? 'var(--primary)' : 'transparent' ?>; color: <?= ($mode === 'register') ? 'var(--primary)' : 'var(--text-muted)' ?>; margin-bottom: -2px;">
                Create Account
            </a>
        </div>

        <!-- Error Notifications -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
                <div class="alert-content">
                    <?php if (isset($errors['login'])): ?>
                        <?= htmlspecialchars($errors['login']) ?>
                    <?php elseif (isset($errors['csrf'])): ?>
                        <?= htmlspecialchars($errors['csrf']) ?>
                    <?php else: ?>
                        Please check the highlighted errors below and try again.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ======================= -->
        <!-- SIGN IN FORM            -->
        <!-- ======================= -->
        <?php if ($mode === 'login'): ?>
            <form action="login.php?action=login" method="POST" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <!-- Email Field -->
                <div class="form-group">
                    <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" placeholder="name@example.com" value="<?= htmlspecialchars($loginEmail) ?>" required autofocus>
                    <?php if (isset($errors['email'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['email']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Password Field -->
                <div class="form-group">
                    <label class="form-label" for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" placeholder="••••••••" required>
                    <?php if (isset($errors['password'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['password']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Submit Button -->
                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem;">
                        Sign In to Portal
                    </button>
                </div>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.85rem; color: var(--text-muted);">
                New to FindIt? <a href="login.php?action=register" style="color: var(--primary); font-weight: 600;">Create an account here</a>
            </div>

        <!-- ======================= -->
        <!-- REGISTRATION FORM       -->
        <!-- ======================= -->
        <?php else: ?>
            <form action="login.php?action=register" method="POST" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <!-- Full Name -->
                <div class="form-group">
                    <label class="form-label" for="name">Full Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" placeholder="e.g. Jane Doe" value="<?= htmlspecialchars($regName) ?>" required autofocus>
                    <?php if (isset($errors['name'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['name']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Email Address -->
                <div class="form-group">
                    <label class="form-label" for="reg_email">Email Address <span class="required">*</span></label>
                    <input type="email" id="reg_email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" placeholder="jane@example.com" value="<?= htmlspecialchars($regEmail) ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['email']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Phone Number -->
                <div class="form-group">
                    <label class="form-label" for="reg_phone">Phone Number (Optional)</label>
                    <input type="tel" id="reg_phone" name="phone" class="form-control" placeholder="+1 (555) 123-4567" value="<?= htmlspecialchars($regPhone) ?>">
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="reg_password">Password (min 6 characters) <span class="required">*</span></label>
                    <input type="password" id="reg_password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" placeholder="••••••••" required>
                    <?php if (isset($errors['password'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['password']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" placeholder="••••••••" required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['confirm_password']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Submit Button -->
                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem;">
                        Create Account &amp; Continue
                    </button>
                </div>
            </form>

            <div style="text-align: center; margin-top: 1.25rem; font-size: 0.85rem; color: var(--text-muted);">
                Already have an account? <a href="login.php?action=login" style="color: var(--primary); font-weight: 600;">Sign in here</a>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php render_footer(); ?>
