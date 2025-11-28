<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function current_user_role() {
    return $_SESSION['role'] ?? null;
}

function require_role(array $roles) {
    $role = current_user_role();
    if (!$role || !in_array($role, $roles)) {
        http_response_code(403);
        echo "<h3>Access denied</h3>";
        exit;
    }
}
