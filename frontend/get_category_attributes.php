<?php
session_start();
require '../backend/db.php';

function getCategoryAncestors($pdo, $category_id) {
    $categories = [];
    while ($category_id) {
        $stmt = $pdo->prepare("SELECT id, name, parent_id FROM categories WHERE id = :id");
        $stmt->execute(['id' => $category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($category) {
            $categories[] = $category;
            $category_id = $category['parent_id'];
        } else {
            break;
        }
    }
    return $categories;
}

function getAttributesForCategories($pdo, $categories) {
    $category_ids = array_column($categories, 'id');
    if (empty($category_ids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM attributes WHERE category_id IN ($placeholders)");
    $stmt->execute($category_ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$data = json_decode(file_get_contents('php://input'), true);
$category_id = (int)($data['category_id'] ?? 0);

if (!$category_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid category ID.']);
    exit;
}

$categories = getCategoryAncestors($pdo, $category_id);

$attributes = getAttributesForCategories($pdo, $categories);

ob_start();
if (!empty($attributes)) {
    foreach ($attributes as $attribute) {
        $inputType = $attribute['type'] === 'number' ? 'number' : ($attribute['type'] === 'date' ? 'date' : 'text');
        ?>
        <div class="mb-3">
            <label for="attribute_<?= htmlspecialchars($attribute['id']) ?>" class="form-label">
                <?= htmlspecialchars($attribute['name']) ?>
                <?php if ($attribute['is_required']): ?><span class="text-danger">*</span><?php endif; ?>
            </label>
            <input
                type="<?= $inputType ?>"
                class="form-control"
                id="attribute_<?= htmlspecialchars($attribute['id']) ?>"
                name="attributes[<?= htmlspecialchars($attribute['id']) ?>]"
                value=""
                <?= $attribute['is_required'] ? 'required' : '' ?>
            >
        </div>
        <?php
    }
}
$html = ob_get_clean();

echo json_encode(['success' => true, 'html' => $html]);
