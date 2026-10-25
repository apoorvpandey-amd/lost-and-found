<?php
/**
 * FindIt - User Login Page (Session Management)
 */
$pageTitle = 'Sign In';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/header.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$pdo = getDbConnection();
$email = '';
$errors = [];
$redirect = $_GET['redirect'] ?? 'dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['csrf_token']) && !verify_csrf_token($_POST['csrf_token'])) {
        $errors['csrf'] = 'Session expired or invalid security token. Please try again.';
    }

    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = $_POST['redirect'] ?? 'dashboard.php';

    // Field Validations
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Login user into session
            login_user($user);
            set_flash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');

            // Redirect safely
            $cleanRedirect = (strpos($redirect, 'http') === false) ? $redirect : 'dashboard.php';
            header("Location: " . $cleanRedirect);
            exit;
        } else {
            $errors['login'] = 'Invalid email address or password. Please verify credentials.';
        }
    }
}
?>

<main class="main-wrapper">
    <div class="form-container" style="max-width: 480px; margin-top: 3rem;">
        <div class="form-header">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔐</div>
            <h1>Welcome Back</h1>
            <p>Log in to manage your reports, respond to claims, and track lost belongings.</p>
        </div>


        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">⚠️</span>
                <div class="alert-content">
                    <?php if (isset($errors['login'])): ?>
                        <?= htmlspecialchars($errors['login']) ?>
                    <?php else: ?>
                        Please correct the errors indicated below.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <!-- Email Address -->
            <div class="form-group">
                <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" placeholder="name@example.com" value="<?= htmlspecialchars($email) ?>" required>
                <?php if (isset($errors['email'])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors['email']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" placeholder="••••••••" required>
                <?php if (isset($errors['password'])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors['password']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Submit Button -->
            <div style="margin-top: 1.75rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem;">
                    Sign In to Account
                </button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); font-size: 0.9rem;">
            Don't have an account yet? <a href="register.php" style="color: var(--primary); font-weight: 700;">Create an account</a>
        </div>
    </div>
</main>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
