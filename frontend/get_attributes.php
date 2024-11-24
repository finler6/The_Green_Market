<?php
require '../backend/db.php';

$categoryId = (int)$_GET['category_id'];

$query = "SELECT id, name, type, required FROM attributes WHERE category_id = :category_id";
$stmt = $pdo->prepare($query);
$stmt->execute(['category_id' => $categoryId]);
$attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($attributes);
