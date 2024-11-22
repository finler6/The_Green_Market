<?php
session_start();
require '../backend/db.php';

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Неавторизован
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Проверяем, является ли запрос методом POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Неверный метод
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Читаем входящие данные JSON
$data = json_decode(file_get_contents('php://input'), true);

// Проверяем наличие необходимых полей
if (empty($data['event_id']) || empty($data['action'])) {
    http_response_code(400); // Неверный запрос
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$user_id = $_SESSION['user_id'];
$event_id = (int)$data['event_id'];
$action = $data['action'];

try {
    if ($action === 'add') {
        // Проверяем, существует ли уже запись
        $checkQuery = "SELECT COUNT(*) FROM UserInterests WHERE user_id = :user_id AND event_id = :event_id";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute(['user_id' => $user_id, 'event_id' => $event_id]);
        if ($checkStmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Event already in interests']);
            exit;
        }

        // Добавляем запись, если её ещё нет
        $query = "INSERT INTO UserInterests (user_id, event_id) VALUES (:user_id, :event_id)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['user_id' => $user_id, 'event_id' => $event_id]);
        echo json_encode(['status' => 'success', 'message' => 'Added to interests']);
    }

    // Удаление из интересов
    elseif ($action === 'remove') {
        $query = "DELETE FROM UserInterests WHERE user_id = :user_id AND event_id = :event_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['user_id' => $user_id, 'event_id' => $event_id]);
        echo json_encode(['status' => 'success', 'message' => 'Removed from interests']);
    }
    // Неверное действие
    else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500); // Внутренняя ошибка сервера
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit;
