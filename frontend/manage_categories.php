<?php
session_start();
require '../backend/db.php';
require '../backend/auth.php';

// Убедимся, что пользователь имеет роль модератора или выше
ensureRole('moderator');

$title = 'Manage Categories';
$success = '';
$error = '';

// Функция для построения дерева категорий
function buildCategoryTree($categories, $parentId = null, $level = 0)
{
    $tree = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parentId) {
            $category['level'] = $level;
            $category['children'] = buildCategoryTree($categories, $category['id'], $level + 1);
            $tree[] = $category;
        }
    }
    return $tree;
}

// Обработка добавления новой категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = htmlspecialchars(trim($_POST['name']));
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (!empty($name)) {
        try {
            $query = "INSERT INTO Categories (name, parent_id) VALUES (:name, :parent_id)";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['name' => $name, 'parent_id' => $parent_id]);
            $success = "Category added successfully.";
        } catch (Exception $e) {
            $error = "Failed to add category: " . $e->getMessage();
        }
    } else {
        $error = "Category name cannot be empty.";
    }
}

// Принять предложение категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_proposal'])) {
    $proposal_id = (int)$_POST['proposal_id'];

    // Получаем данные предложения
    $query = "SELECT name, parent_id FROM CategoryProposals WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $proposal_id]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($proposal) {
        try {
            // Добавляем категорию в основную таблицу
            $query = "INSERT INTO Categories (name, parent_id) VALUES (:name, :parent_id)";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['name' => $proposal['name'], 'parent_id' => $proposal['parent_id']]);

            // Удаляем предложение
            $query = "DELETE FROM CategoryProposals WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['id' => $proposal_id]);

            $success = "Proposal accepted and category added.";
        } catch (Exception $e) {
            $error = "Failed to accept proposal: " . $e->getMessage();
        }
    } else {
        $error = "Proposal not found.";
    }
}

// Отклонить предложение категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_proposal'])) {
    $proposal_id = (int)$_POST['proposal_id'];

    // Удаляем предложение
    $query = "DELETE FROM CategoryProposals WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $proposal_id]);
    $success = "Proposal rejected.";
}

// Обработка добавления категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['category_id'];
    $new_name = htmlspecialchars(trim($_POST['new_name']));
    $new_parent_id = !empty($_POST['new_parent_id']) ? (int)$_POST['new_parent_id'] : null;

    try {
        // Обновление категории и иерархии
        updateCategoryHierarchy($pdo, $category_id, $new_parent_id);

        // Обновляем имя категории
        $query = "UPDATE Categories SET name = :new_name WHERE id = :category_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['new_name' => $new_name, 'category_id' => $category_id]);

        $success = "Category updated successfully.";
    } catch (Exception $e) {
        $error = "Failed to update category: " . $e->getMessage();
    }
}

// Обработка удаления категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $category_id = (int)$_POST['category_id'];

    // Проверка на потомков
    $query = "SELECT COUNT(*) FROM Categories WHERE parent_id = :category_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['category_id' => $category_id]);
    $has_children = $stmt->fetchColumn() > 0;

    if ($has_children) {
        $error = 'Cannot delete category with subcategories.';
    } else {
        $query = "DELETE FROM Categories WHERE id = :category_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['category_id' => $category_id]);
        $success = 'Category deleted successfully.';
    }
}

// Получение всех категорий
$query = "SELECT id, name, parent_id FROM Categories ORDER BY parent_id ASC, name ASC";
$categories = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Построение дерева категорий
function updateCategoryHierarchy($pdo, $categoryId, $newParentId)
{
    // Обновляем текущую категорию
    $query = "UPDATE Categories SET parent_id = :new_parent_id WHERE id = :category_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['new_parent_id' => $newParentId, 'category_id' => $categoryId]);

    // Находим всех дочерние категории
    $query = "SELECT id FROM Categories WHERE parent_id = :category_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['category_id' => $categoryId]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Рекурсивно обновляем потомков
    foreach ($children as $childId) {
        updateCategoryHierarchy($pdo, $childId, $categoryId);
    }
}

$categoryTree = buildCategoryTree($categories);

function renderCategoryTree($tree)
{
    $html = '<ul class="category-tree">';
    foreach ($tree as $category) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $category['level']);
        $html .= '<li>';
        $html .= '<div class="category-item">';
        $html .= '<span class="category-name">' . $indent . htmlspecialchars($category['name']) . '</span>';
        $html .= '
            <div class="category-actions">
                <button class="btn btn-warning btn-sm edit-category-btn" 
                        data-id="' . $category['id'] . '" 
                        data-name="' . htmlspecialchars($category['name']) . '" 
                        data-parent="' . ($category['parent_id'] ?? '') . '">
                    Edit
                </button>
                <form method="POST" action="manage_categories.php" class="d-inline">
                    <input type="hidden" name="category_id" value="' . $category['id'] . '">
                    <button type="submit" name="delete_category" class="btn btn-danger btn-sm delete-category-btn">Delete</button>
                </form>
            </div>';
        $html .= '</div>';
        if (!empty($category['children'])) {
            $html .= renderCategoryTree($category['children']);
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}

ob_start();
?>
    <h1 class="mb-4">Manage Categories</h1>
    <!-- Кнопка для добавления категории -->
    <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        Add New Category
    </button>
    <!-- Кнопка для просмотра предложений -->
    <button type="button" class="btn btn-secondary mb-4" data-bs-toggle="modal" data-bs-target="#reviewProposalsModal">
        Review Category Proposals
    </button>
    <!-- Список категорий -->
    <div class="mb-4">
        <?= renderCategoryTree($categoryTree) ?>
    </div>

    <!-- Модальное окно для редактирования -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="manage_categories.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="category_id" id="editCategoryId">
                        <div class="mb-3">
                            <label for="new_name" class="form-label">New Name</label>
                            <input type="text" id="newName" name="new_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_parent_id" class="form-label">New Parent Category</label>
                            <select id="newParentId" name="new_parent_id" class="form-control">
                                <option value="">None</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="edit_category" class="btn btn-success">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Модальное окно для добавления новой категории -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="manage_categories.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="categoryName" class="form-label">Category Name</label>
                            <input type="text" id="categoryName" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="parentCategory" class="form-label">Parent Category</label>
                            <select id="parentCategory" name="parent_id" class="form-control">
                                <option value="">None</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="add_category" class="btn btn-success">Add Category</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Модальное окно для рассмотрения предложений -->
    <div class="modal fade" id="reviewProposalsModal" tabindex="-1" aria-labelledby="reviewProposalsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewProposalsModalLabel">Category Proposals</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php
                    // Получение предложений категорий
                    $query = "SELECT id, name, parent_id FROM CategoryProposals ORDER BY id ASC";
                    $proposals = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php if (empty($proposals)): ?>
                        <p class="text-muted">No category proposals to review.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Proposed Parent</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($proposals as $proposal): ?>
                                <tr>
                                    <td><?= htmlspecialchars($proposal['id']) ?></td>
                                    <td><?= htmlspecialchars($proposal['name']) ?></td>
                                    <td>
                                        <?= $proposal['parent_id']
                                            ? htmlspecialchars(getCategoryName($pdo, $proposal['parent_id']))
                                            : 'None'
                                        ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="manage_categories.php" class="d-inline">
                                            <input type="hidden" name="proposal_id" value="<?= $proposal['id'] ?>">
                                            <button type="submit" name="accept_proposal" class="btn btn-success btn-sm">Accept</button>
                                        </form>
                                        <form method="POST" action="manage_categories.php" class="d-inline">
                                            <input type="hidden" name="proposal_id" value="<?= $proposal['id'] ?>">
                                            <button type="submit" name="reject_proposal" class="btn btn-danger btn-sm">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const editButtons = document.querySelectorAll('.edit-category-btn');
            const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            const editCategoryId = document.getElementById('editCategoryId');
            const newName = document.getElementById('newName');
            const newParentId = document.getElementById('newParentId');

            editButtons.forEach(button => {
                button.addEventListener('click', () => {
                    editCategoryId.value = button.dataset.id;
                    newName.value = button.dataset.name;
                    newParentId.value = button.dataset.parent || '';
                    editModal.show();
                });
            });
        });
    </script>
<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
