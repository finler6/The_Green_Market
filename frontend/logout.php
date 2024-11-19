<?php
session_start(); // Инициализация сессии

// Завершение сессии
session_unset(); // Удаляет все переменные сессии
session_destroy(); // Уничтожает сессию

// Перенаправление на страницу входа
header('Location: login.php');
exit;
