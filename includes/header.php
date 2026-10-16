<?php
/**
 * Global Header Component
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME;
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Site Navigation -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">🔍</div>
                <span>FindIt</span>
            </a>

            <!-- Mobile menu button -->
            <button class="mobile-toggle" id="mobileMenuBtn" aria-label="Toggle navigation">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="nav-menu" id="navMenu">
                <ul class="nav-links">
                    <li>
                        <a href="index.php" class="nav-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">Home</a>
                    </li>
                    <li>
                        <a href="browse.php" class="nav-link <?= ($currentPage === 'browse.php') ? 'active' : '' ?>">Browse & Search</a>
                    </li>
                    <li>
                        <a href="report.php" class="btn btn-primary btn-sm">
                            <span>+</span> Report an Item
                        </a>
                    </li>

                    <?php if (is_logged_in()): ?>
                        <li>
                            <a href="dashboard.php" class="nav-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                                My Dashboard
                            </a>
                        </li>
                        <?php if (is_admin()): ?>
                            <li>
                                <a href="admin.php" class="nav-link <?= ($currentPage === 'admin.php') ? 'active' : '' ?>">
                                    <span class="admin-badge">Admin</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="user-pill-container">
                            <span class="user-greeting">Hi, <strong><?= htmlspecialchars($user['name']) ?></strong></span>
                            <a href="logout.php" class="btn btn-secondary btn-sm" title="Log Out">Log Out</a>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="login.php" class="nav-link <?= ($currentPage === 'login.php') ? 'active' : '' ?>">Sign In</a>
                        </li>
                        <li>
                            <a href="register.php" class="btn btn-secondary btn-sm <?= ($currentPage === 'register.php') ? 'active' : '' ?>">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Global Flash Notification Container -->
    <div class="flash-wrapper">
        <?php render_flash(); ?>
    </div>
