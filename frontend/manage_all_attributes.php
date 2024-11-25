<?php
require '../backend/db.php';
require '../backend/auth.php';

ensureRole('moderator');

$title = 'Manage All Attributes';

$query = "SELECT attributes.id, attributes.name, attributes.type, attributes.is_required, categories.name AS category_name
          FROM attributes
          LEFT JOIN categories ON attributes.category_id = categories.id
          ORDER BY categories.name ASC, attributes.name ASC";
$attributes = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<a href="manage_categories.php" class="btn btn-secondary mb-3">
    <i class="fas fa-arrow-left"></i> Back to Categories
</a>

<h1 class="mb-4">Manage All Attributes</h1>

<div class="mb-3">
    <input type="text" id="attributeSearch" class="form-control" placeholder="Search attributes by name or category...">
</div>

<table class="table" id="attributesTable">
    <thead>
    <tr>
        <th>Name</th>
        <th>Type</th>
        <th>Required</th>
        <th>Category</th>
        <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($attributes as $attribute): ?>
        <tr>
            <td><?= htmlspecialchars($attribute['name']) ?></td>
            <td><?= htmlspecialchars($attribute['type']) ?></td>
            <td><?= $attribute['is_required'] ? 'Yes' : 'No' ?></td>
            <td><?= htmlspecialchars($attribute['category_name'] ?? 'None') ?></td>
            <td>
                <form method="POST" action="manage_attributes.php" class="d-inline">
                    <input type="hidden" name="action" value="delete_attribute">
                    <input type="hidden" name="id" value="<?= $attribute['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('attributeSearch');
        const table = document.getElementById('attributesTable');
        const rows = table.querySelectorAll('tbody tr');

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase();

            rows.forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                const category = row.cells[3].textContent.toLowerCase();

                if (name.includes(query) || category.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
</script>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
