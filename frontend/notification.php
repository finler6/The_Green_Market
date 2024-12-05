<?php
session_start();
require '../backend/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? 'fetch';

if ($action === 'fetch') {
    $query = "
        SELECT n.id, e.name AS event_name, e.date AS event_date, e.id AS event_id
        FROM notifications n
        JOIN events e ON n.event_id = e.id
        WHERE n.user_id = :user_id AND n.is_read = 0
        ORDER BY e.date ASC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'notifications' => $notifications]);
    exit;
}

if ($action === 'mark_as_read') {
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;
