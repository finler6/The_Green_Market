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

// Функция для проверки, что пользователь имеет конкретную роль
function ensureRole($required_role) {

    if (empty($_SESSION['user_role'])) {
        header('Location: ../frontend/login.php');
        exit;
    }

    // Администратор имеет доступ ко всем ролям
    if ($_SESSION['user_role'] === 'admin') {
        return;
    }

    // Проверка роли для остальных пользователей
    if ($_SESSION['user_role'] !== $required_role) {
        header('Location: ../frontend/login.php');
        exit;
    }
}

// Функция для получения текущего пользователя
function getCurrentUser() {
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_name']) && !empty($_SESSION['user_role'])) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role']
        ];
    }
    return null; // Если пользователь не авторизован
}

// Функция для проверки, что пользователь является администратором
function ensureAdmin() {
    ensureRole('admin');
}

// Функция для проверки, что пользователь является модератором
function ensureModerator() {
    ensureRole('moderator');
}

// Функция для проверки, что пользователь является фермером
function ensureFarmer() {
    ensureRole('farmer');
}

// Функция для проверки, что пользователь является клиентом
function ensureCustomer() {
    ensureRole('customer');
}
