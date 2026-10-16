<?php
/**
 * Authentication & Session Management Module
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role'] ?? 'user'
    ];
}

function is_admin() {
    return is_logged_in() && (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
}

function require_login($redirect = null) {
    if (!is_logged_in()) {
        $target = $redirect ? urlencode($redirect) : urlencode($_SERVER['REQUEST_URI']);
        set_flash('danger', 'You must log in to access this page.');
        header("Location: login.php?redirect=" . $target);
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'Access denied. Administrator privileges required.');
        header("Location: index.php");
        exit;
    }
}

function login_user($user) {
    // Regenerate session id to prevent session fixation
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'] ?? 'user';
}

function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function can_manage_item($item) {
    if (!is_logged_in()) {
        return false;
    }
    $user = current_user();
    if ($user['role'] === 'admin') {
        return true;
    }
    return isset($item['user_id']) && ((int)$item['user_id'] === (int)$user['id']);
}
