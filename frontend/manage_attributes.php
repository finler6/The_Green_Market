<?php
require '../backend/db.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['type'], $_POST['category_id'])) {
        $categoryId = (int)$_POST['category_id'];
        $name = htmlspecialchars(trim($_POST['name']));
        $type = htmlspecialchars(trim($_POST['type']));
        $required = isset($_POST['is_required']) ? 1 : 0;

        if ($categoryId > 0 && !empty($name) && !empty($type)) {
            try {
                $query = "INSERT INTO attributes (category_id, name, type, is_required) VALUES (:category_id, :name, :type, :is_required)";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    'category_id' => $categoryId,
                    'name' => $name,
                    'type' => $type,
                    'is_required' => $required,
                ]);

                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_attribute') {
        $attributeId = (int)$_POST['id'];

        if ($attributeId > 0) {
            $query = "DELETE FROM attributes WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['id' => $attributeId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete attribute.']);
            }
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Invalid attribute ID.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['category_id'])) {
        $categoryId = (int)$_GET['category_id'];

        if ($categoryId > 0) {
            $query = "SELECT id, name, type, is_required FROM attributes WHERE category_id = :category_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['category_id' => $categoryId]);
            $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['attributes' => $attributes]);
        } else {
            echo json_encode(['attributes' => []]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid request']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
