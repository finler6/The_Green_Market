<?php
session_start();
require '../backend/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['event_id']) || empty($data['action'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$user_id = $_SESSION['user_id'];
$event_id = (int)$data['event_id'];
$action = $data['action'];

try {
    if ($action === 'add') {
        $checkQuery = "SELECT COUNT(*) FROM userinterests WHERE user_id = :user_id AND event_id = :event_id";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute(['user_id' => $user_id, 'event_id' => $event_id]);
        if ($checkStmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Event already in interests']);
            exit;
        }

        $query = "INSERT INTO userinterests (user_id, event_id) VALUES (:user_id, :event_id)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['user_id' => $user_id, 'event_id' => $event_id]);
        echo json_encode(['status' => 'success', 'message' => 'Added to interests']);
    }

    elseif ($action === 'remove') {
        $query = "DELETE FROM userinterests WHERE user_id = :user_id AND event_id = :event_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['user_id' => $user_id, 'event_id' => $event_id]);
        echo json_encode(['status' => 'success', 'message' => 'Removed from interests']);
    }
    else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit;
