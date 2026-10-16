<?php
/**
 * FindIt - Session Logout Handler
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();

// Re-start clean session for flash feedback
session_start();
set_flash('info', 'You have been successfully logged out. Have a great day!');

header("Location: login.php");
exit;
