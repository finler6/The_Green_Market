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
            $query = "INSERT INTO categories (name, parent_id) VALUES (:name, :parent_id)";
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
    $query = "SELECT name, parent_id FROM categoryproposals WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $proposal_id]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($proposal) {
        try {
            // Добавляем категорию в основную таблицу
            $query = "INSERT INTO categories (name, parent_id) VALUES (:name, :parent_id)";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['name' => $proposal['name'], 'parent_id' => $proposal['parent_id']]);

            // Удаляем предложение
            $query = "DELETE FROM categoryproposals WHERE id = :id";
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
    $query = "DELETE FROM categoryproposals WHERE id = :id";
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
        $query = "UPDATE categories SET name = :new_name WHERE id = :category_id";
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
    $query = "SELECT COUNT(*) FROM categories WHERE parent_id = :category_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['category_id' => $category_id]);
    $has_children = $stmt->fetchColumn() > 0;

    if ($has_children) {
        $error = 'Cannot delete category with subcategories.';
    } else {
        $query = "DELETE FROM categories WHERE id = :category_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['category_id' => $category_id]);
        $success = 'Category deleted successfully.';
    }
}

// Получение всех категорий
$query = "SELECT id, name, parent_id FROM categories ORDER BY parent_id ASC, name ASC";
$categories = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Построение дерева категорий
function updateCategoryHierarchy($pdo, $categoryId, $newParentId)
{
    // Обновляем текущую категорию
    $query = "UPDATE categories SET parent_id = :new_parent_id WHERE id = :category_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['new_parent_id' => $newParentId, 'category_id' => $categoryId]);

    // Находим всех дочерние категории
    $query = "SELECT id FROM categories WHERE parent_id = :category_id";
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
                <button class="btn btn-primary btn-sm manage-attributes-btn" 
                        data-category-id="' . $category['id'] . '" 
                        data-category-name="' . htmlspecialchars($category['name']) . '" 
                        data-bs-toggle="modal" 
                        data-bs-target="#manageAttributesModal">
                    Manage Attributes
                </button>
                <form method="POST" action="manage_categories.php" class="d-inline">
                    <input type="hidden" name="category_id" value="' . $category['id'] . '">
                    <button type="submit" name="delete_category" class="btn btn-danger btn-sm delete-category-btn">
                    Delete
                </button>
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

// Атрибуты --------------------------
// Добавление нового атрибута
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['type'], $_POST['category_id'])) {
    $categoryId = (int)$_POST['category_id'];
    $name = htmlspecialchars(trim($_POST['name']));
    $type = htmlspecialchars(trim($_POST['type']));
    $required = isset($_POST['required']) ? 1 : 0;

    if ($categoryId > 0 && !empty($name) && !empty($type)) {
        try {
            $query = "INSERT INTO attributes (category_id, name, type, required) VALUES (:category_id, :name, :type, :required)";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['category_id' => $categoryId, 'name' => $name, 'type' => $type, 'required' => $required]);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
    }
    exit;
}

// Удаление атрибута
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attribute'])) {
    $attributeId = (int)$_POST['attribute_id'];

    $query = "DELETE FROM attributes WHERE id = :attribute_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['attribute_id' => $attributeId]);
    $success = "Attribute deleted successfully.";
}

// Редактирование атрибута
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_attribute'])) {
    $attributeId = (int)$_POST['attribute_id'];
    $name = htmlspecialchars(trim($_POST['name']));
    $type = htmlspecialchars(trim($_POST['type']));
    $required = isset($_POST['required']) ? 1 : 0;

    $query = "UPDATE attributes SET name = :name, type = :type, required = :required WHERE id = :attribute_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['name' => $name, 'type' => $type, 'required' => $required, 'attribute_id' => $attributeId]);
    $success = "Attribute updated successfully.";
}

// Удаление атрибута
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attribute'])) {
    $attributeId = (int)$_POST['id'];

    try {
        $query = "DELETE FROM attributes WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['id' => $attributeId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

ob_start();
?>
    <h1 class="mb-4">Manage Categories</h1>
    <!-- Кнопка для добавления категории -->
    <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        Add New Category
    </button>
    <!-- Кнопка для изменения атрибутов -->
    <button type="button" class="btn btn-info mb-4" onclick="location.href='manage_all_attributes.php'">
        Manage All Attributes
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
    <!-- Модальное окно для управления атрибутами -->
    <div class="modal fade" id="manageAttributesModal" tabindex="-1" aria-labelledby="manageAttributesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="manageAttributesModalLabel">Manage Attributes for <span id="categoryName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Список атрибутов -->
                    <div id="attributesContainer">
                        <ul class="list-group">
                            <!-- Динамически заполняется через JavaScript -->
                        </ul>
                    </div>
                    <hr>
                    <!-- Форма для добавления атрибута -->
                    <h6>Add New Attribute</h6>
                    <form id="addAttributeForm">
                        <div class="row">
                            <div class="col-md-4">
                                <input type="text" name="name" class="form-control" placeholder="Attribute Name" required>
                            </div>
                            <div class="col-md-3">
                                <select name="type" class="form-control" required>
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="boolean">Boolean</option>
                                    <option value="date">Date</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="form-check">
                                    <input type="checkbox" name="is_required" class="form-check-input" id="isRequiredCheckbox">
                                    <label class="form-check-label" for="isRequiredCheckbox">Required</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-success">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
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
                    $query = "SELECT id, name, parent_id FROM categoryproposals ORDER BY id ASC";
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

            // Обработчик кнопок редактирования категории
            editButtons.forEach(button => {
                button.addEventListener('click', () => {
                    editCategoryId.value = button.dataset.id;
                    newName.value = button.dataset.name || '';
                    newParentId.value = button.dataset.parent || '';
                    editModal.show();
                });
            });

            // Обработчик кнопок управления атрибутами
            document.querySelectorAll('.manage-attributes-btn').forEach(button => {
                button.addEventListener('click', () => {
                    const categoryId = button.dataset.categoryId;
                    const categoryName = button.dataset.categoryName;
                    const attributesContainer = document.getElementById('attributesContainer');

                    if (!categoryId) {
                        console.error('Category ID is missing.');
                        return;
                    }

                    document.getElementById('categoryName').textContent = categoryName;

                    // Загрузка атрибутов категории
                    fetchAttributes(categoryId);
                });
            });

            // Функция загрузки атрибутов категории
            const fetchAttributes = (categoryId) => {
                const attributesContainer = document.getElementById('attributesContainer');
                fetch(`manage_attributes.php?category_id=${categoryId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.attributes && data.attributes.length > 0) {
                            attributesContainer.innerHTML = '<ul class="list-group">';
                            data.attributes.forEach(attr => {
                                attributesContainer.innerHTML += `
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    ${attr.name} (${attr.type})
                                    <div>
                                        <span>${attr.is_required ? 'Required' : 'Optional'}</span>
                                        <button class="btn btn-danger btn-sm delete-attribute-btn" data-id="${attr.id}">Delete</button>
                                    </div>
                                </li>
                            `;
                            });
                            attributesContainer.innerHTML += '</ul>';
                            addDeleteHandlers(categoryId); // Добавляем обработчики для удаления атрибутов
                        } else {
                            attributesContainer.innerHTML = '<p class="text-muted">No attributes for this category.</p>';
                        }
                    })
                    .catch(error => console.error('Error fetching attributes:', error));
            };

            // Обработчик формы добавления атрибута
            const addAttributeForm = document.getElementById('addAttributeForm');
            addAttributeForm.addEventListener('submit', (event) => {
                event.preventDefault();

                const formData = new FormData(addAttributeForm);
                const categoryId = document.querySelector('.manage-attributes-btn[data-category-id]').dataset.categoryId;
                formData.append('category_id', categoryId);

                // Отправка запроса на добавление атрибута
                fetch('manage_attributes.php', {
                    method: 'POST',
                    body: formData,
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            addAttributeForm.reset();
                            fetchAttributes(categoryId); // Обновляем список атрибутов
                        } else {
                            alert('Failed to add attribute: ' + data.error);
                        }
                    })
                    .catch(error => console.error('Error:', error));
            });

            // Обработчик удаления атрибутов
            const addDeleteHandlers = (categoryId) => {
                document.querySelectorAll('.delete-attribute-btn').forEach(button => {
                    button.addEventListener('click', () => {
                        const attributeId = button.dataset.id;

                        if (!attributeId) {
                            console.error('Attribute ID is missing.');
                            return;
                        }

                        if (confirm('Are you sure you want to remove this attribute from this category?')) {
                            fetch('manage_attributes.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=delete_attribute&id=${attributeId}`,
                            })
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error(`HTTP error! Status: ${response.status}`);
                                    }
                                    return response.json();
                                })
                                .then(data => {
                                    if (data.success) {
                                        fetchAttributes(categoryId); // Обновляем список атрибутов
                                    } else {
                                        alert('Failed to remove attribute: ' + data.error);
                                    }
                                })
                                .catch(error => console.error('Error removing attribute:', error));
                        }
                    });
                });
            };
        });
    </script>



<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
