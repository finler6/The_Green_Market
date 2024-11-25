<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function ensureLoggedIn() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ../frontend/login.php');
        exit;
    }
}

function ensureRole($required_role) {

    if (empty($_SESSION['user_role'])) {
        header('Location: ../frontend/login.php');
        exit;
    }

    if ($_SESSION['user_role'] === 'admin') {
        return;
    }

    if ($_SESSION['user_role'] !== $required_role) {
        header('Location: ../frontend/login.php');
        exit;
    }
}

function getCurrentUser() {
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_name']) && !empty($_SESSION['user_role'])) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role']
        ];
    }
    return null;
}

function ensureAdmin() {
    ensureRole('admin');
}

function ensureModerator() {
    ensureRole('moderator');
}

function ensureFarmer() {
    ensureRole('farmer');
}

function ensureCustomer() {
    ensureRole('customer');
}
