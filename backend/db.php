<?php
$host = 'localhost';
$socket = '/var/run/mysql/mysql.sock';
$dbname = 'xlitvi02';
$user = 'xlitvi02';
$password = 'or9ponhe';

try {
    // Создаем подключение с использованием PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;unix_socket=$socket;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "Database connection successful!";
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
    die();
}
?>
