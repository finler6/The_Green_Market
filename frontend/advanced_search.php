<?php
session_start();
require '../backend/db.php';
require '../backend/auth.php';

$title = 'Advanced Search';

$searchQuery = $_GET['q'] ?? '';

// Получаем фильтры
$selectedCategories = $_GET['categories'] ?? [];
$selectedFarmers = $_GET['farmers'] ?? [];
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$inStockOnly = isset($_GET['in_stock']);

$sortBy = $_GET['sort_by'] ?? '';

$sql = "SELECT p.id, p.name, p.price, p.price_unit, p.quantity, p.quantity_unit, p.farmer_id, c.name AS category, u.name AS farmer_name,
        (SELECT AVG(rating) FROM reviews WHERE product_id = p.id) AS average_rating
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN users u ON p.farmer_id = u.id
        WHERE 1";

$params = [];

if (!empty(trim($searchQuery))) {
    $searchGroups = parseSearchQuery($searchQuery);

    $searchConditions = [];
    foreach ($searchGroups as $groupIndex => $group) {
        $groupConditions = [];
        foreach ($group['conditions'] as $conditionIndex => $condition) {
            $paramPlaceholder = ':param_' . $groupIndex . '_' . $conditionIndex;
            if ($condition['field'] === 'name') {
                $groupConditions[] = "p.name LIKE $paramPlaceholder";
                $params[$paramPlaceholder] = '%' . $condition['value'] . '%';
            } elseif ($condition['field'] === 'seller') {
                $groupConditions[] = "u.name LIKE $paramPlaceholder";
                $params[$paramPlaceholder] = '%' . $condition['value'] . '%';
            } elseif ($condition['field'] === 'category') {
                $groupConditions[] = "c.name LIKE $paramPlaceholder";
                $params[$paramPlaceholder] = '%' . $condition['value'] . '%';
            } elseif ($condition['field'] === 'attribute') {
                $attributeCondition = "EXISTS (
                    SELECT 1 FROM productattributes pa
                    JOIN attributes a ON pa.attribute_id = a.id
                    WHERE pa.product_id = p.id
                    AND (a.name LIKE $paramPlaceholder OR pa.value LIKE $paramPlaceholder)
                )";
                $groupConditions[] = $attributeCondition;
                $params[$paramPlaceholder] = '%' . $condition['value'] . '%';
            }
        }
        $groupSql = '(' . implode(' AND ', $groupConditions) . ')';
        $searchConditions[] = ['operator' => $group['operator'], 'sql' => $groupSql];
    }
    $finalConditions = [];
    foreach ($searchConditions as $index => $condition) {
        if ($index > 0) {
            $finalConditions[] = $condition['operator'];
        }
        $finalConditions[] = $condition['sql'];
    }
    if (!empty($finalConditions)) {
        $sql .= ' AND (' . implode(' ', $finalConditions) . ')';
    }
}

if (!empty($selectedCategories)) {
    $placeholders = [];
    foreach ($selectedCategories as $index => $categoryId) {
        $paramPlaceholder = ':cat_' . $index;
        $placeholders[] = $paramPlaceholder;
        $params[$paramPlaceholder] = $categoryId;
    }
    $sql .= " AND p.category_id IN (" . implode(',', $placeholders) . ")";
}

if (!empty($selectedFarmers)) {
    $placeholders = [];
    foreach ($selectedFarmers as $index => $farmerId) {
        $paramPlaceholder = ':farmer_' . $index;
        $placeholders[] = $paramPlaceholder;
        $params[$paramPlaceholder] = $farmerId;
    }
    $sql .= " AND p.farmer_id IN (" . implode(',', $placeholders) . ")";
}

if (isset($_GET['enable_price_range'])) {
    if ($minPrice !== null) {
        $sql .= " AND p.price >= :min_price";
        $params[':min_price'] = $minPrice;
    }
    if ($maxPrice !== null) {
        $sql .= " AND p.price <= :max_price";
        $params[':max_price'] = $maxPrice;
    }
}

if ($inStockOnly) {
    $sql .= " AND p.quantity > 0";
}

$orderClause = '';
if ($sortBy) {
    switch ($sortBy) {
        case 'price_asc':
            $orderClause = 'ORDER BY p.price ASC';
            break;
        case 'price_desc':
            $orderClause = 'ORDER BY p.price DESC';
            break;
        case 'quantity_asc':
            $orderClause = 'ORDER BY p.quantity ASC';
            break;
        case 'quantity_desc':
            $orderClause = 'ORDER BY p.quantity DESC';
            break;
        case 'popularity_desc':
            $orderClause = 'ORDER BY average_rating DESC';
            break;
        default:
            break;
    }
}

if ($orderClause) {
    $sql .= ' ' . $orderClause;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getCategoriesTree($pdo)
{
    $query = "SELECT id, name, parent_id FROM categories";
    $stmt = $pdo->query($query);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tree = [];
    $indexedCategories = [];
    foreach ($categories as $category) {
        $category['children'] = [];
        $indexedCategories[$category['id']] = $category;
    }
    foreach ($indexedCategories as $id => $category) {
        if ($category['parent_id']) {
            $indexedCategories[$category['parent_id']]['children'][] = &$indexedCategories[$id];
        } else {
            $tree[] = &$indexedCategories[$id];
        }
    }
    return $tree;
}

function parseSearchQuery($query)
{
    $parts = [];
    $tokens = preg_split('/\s+/', $query);
    $currentGroup = [];
    $currentOperator = 'AND';

    foreach ($tokens as $token) {
        $tokenUpper = strtoupper($token);
        if ($tokenUpper === 'AND' || $tokenUpper === 'OR') {
            if (!empty($currentGroup)) {
                $parts[] = ['operator' => $currentOperator, 'conditions' => $currentGroup];
                $currentGroup = [];
            }
            $currentOperator = $tokenUpper;
        } else {
            $field = 'name';
            $value = $token;
            if (strpos($token, 'name:') === 0) {
                $field = 'name';
                $value = substr($token, 5);
            } elseif (strpos($token, 'seller:') === 0) {
                $field = 'seller';
                $value = substr($token, 7);
            } elseif (strpos($token, 'category:') === 0) {
                $field = 'category';
                $value = substr($token, 9);
            } elseif (strpos($token, 'attr:') === 0) {
                $field = 'attribute';
                $value = substr($token, 5);
            }
            $currentGroup[] = ['field' => $field, 'value' => $value];
        }
    }
    if (!empty($currentGroup)) {
        $parts[] = ['operator' => $currentOperator, 'conditions' => $currentGroup];
    }
    return $parts;
}

ob_start();
?>
<form id="searchForm" method="GET" action="advanced_search.php">
    <div class="input-group mb-3">
        <input type="text" class="form-control" name="q" id="searchInput" list="searchSuggestions" placeholder="Search for products, sellers, categories..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        <datalist id="searchSuggestions"></datalist>
        <button class="btn btn-primary" type="submit">Search</button>
    </div>


    <div class="alert alert-info" role="alert">
        <strong>Search Tips:</strong> Use prefixes to refine your search.
        <ul class="mb-0">
            <li><code>name:</code> Product name (e.g., <code>name:apple</code>)</li>
            <li><code>seller:</code> Seller name (e.g., <code>seller:john</code>)</li>
            <li><code>category:</code> Category name (e.g., <code>category:fruit</code>)</li>
            <li><code>attr:</code> Attribute name or value (e.g., <code>attr:organic</code>)</li>
            <li>Use <code>AND</code> or <code>OR</code> to combine queries (e.g., <code>name:apple AND attr:organic</code>)</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="filters">
                <h5>Filters</h5>
                <h6>Sort By</h6>
                    <select class="form-select" name="sort_by">
                        <option value="">-- Select --</option>
                        <option value="price_asc" <?= (isset($_GET['sort_by']) && $_GET['sort_by'] == 'price_asc') ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= (isset($_GET['sort_by']) && $_GET['sort_by'] == 'price_desc') ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="quantity_asc" <?= (isset($_GET['sort_by']) && $_GET['sort_by'] == 'quantity_asc') ? 'selected' : '' ?>>Quantity: Low to High</option>
                        <option value="quantity_desc" <?= (isset($_GET['sort_by']) && $_GET['sort_by'] == 'quantity_desc') ? 'selected' : '' ?>>Quantity: High to Low</option>
                        <option value="popularity_desc" <?= (isset($_GET['sort_by']) && $_GET['sort_by'] == 'popularity_desc') ? 'selected' : '' ?>>Popularity</option>
                    </select>
                <div class="filter-section">
                    <h6>Categories</h6>
                    <div class="category-tree">
                        <?php
                        $categories = getCategoriesTree($pdo);
                        $selectedCategories = $_GET['categories'] ?? [];
                        echo renderCategoryTree($categories, $selectedCategories);
                        ?>
                    </div>
                </div>

                <div class="filter-section">
                    <h6>Farmers</h6>
                    <?php
                    $farmersQuery = "SELECT id, name FROM users WHERE role = 'farmer' ORDER BY name ASC";
                    $farmersStmt = $pdo->query($farmersQuery);
                    $farmers = $farmersStmt->fetchAll(PDO::FETCH_ASSOC);
                    $selectedFarmers = $_GET['farmers'] ?? [];
                    ?>
                    <?php foreach ($farmers as $farmer): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="farmers[]" value="<?= $farmer['id'] ?>" <?= in_array($farmer['id'], $selectedFarmers) ? 'checked' : '' ?>>
                            <label class="form-check-label">
                                <?= htmlspecialchars($farmer['name']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="filter-section">
                    <h6>Price Range</h6>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="enablePriceRange" name="enable_price_range" value="1" <?= isset($_GET['enable_price_range']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enablePriceRange">Enable Price Range</label>
                    </div>
                    <div id="priceRangeInputs" <?= !isset($_GET['enable_price_range']) ? 'style="display: none;"' : '' ?>>
                        <div class="mb-3">
                            <label for="minPrice" class="form-label">Min Price</label>
                            <input type="number" class="form-control" name="min_price" id="minPrice" value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label for="maxPrice" class="form-label">Max Price</label>
                            <input type="number" class="form-control" name="max_price" id="maxPrice" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>" step="0.01">
                        </div>
                    </div>
                </div>

                <div class="filter-section">
                    <h6>Availability</h6>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="in_stock" id="inStockCheckbox" value="1" <?= isset($_GET['in_stock']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="inStockCheckbox">
                            In Stock
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Apply Filters</button>
            </div>
        </div>

        <div class="col-md-9">
            <div class="products-container">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <a href="product.php?id=<?= $product['id'] ?>" class="product-link">
                                <h3><?= htmlspecialchars($product['name']) ?></h3>
                                <p>Category: <?= htmlspecialchars($product['category']) ?></p>
                                <p>Farmer: <?= htmlspecialchars($product['farmer_name']) ?></p>
                                <p>$<?= number_format($product['price'], 2) ?>/kg</p>
                                <?php if ($product['quantity'] > 0): ?>
                                    <p>Available: <?= htmlspecialchars($product['quantity']) ?> units</p>
                                <?php else: ?>
                                    <p class="text-danger">Out of stock</p>
                                <?php endif; ?>
                            </a>

                            <?php if (isset($_SESSION['user_role']) && $product['quantity'] > 0): ?>
                                <?php if ($_SESSION['user_role'] !== 'farmer' || $product['farmer_id'] !== $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-success btn-add-to-cart" data-bs-toggle="modal"
                                            data-bs-target="#addToCartModal"
                                            data-product-id="<?= $product['id'] ?>"
                                            data-product-name="<?= htmlspecialchars($product['name']) ?>"
                                            data-product-max="<?= $product['quantity'] ?>">Add to Cart
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-add-to-cart" disabled>Cannot Add Own Product</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-add-to-cart" disabled>
                                    <?= isset($_SESSION['user_id']) ? 'Out of Stock' : 'Log in to Add to Cart' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No products found matching your criteria.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>
    <div class="modal fade" id="addToCartModal" tabindex="-1" aria-labelledby="addToCartModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addToCartModalLabel">Add to Cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="add_to_cart.php">
                        <input type="hidden" id="modal-product-id" name="product_id" value="">
                        <div class="mb-3">
                            <label for="modal-product-name" class="form-label">Product</label>
                            <input type="text" id="modal-product-name" class="form-control" value="" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="modal-quantity" class="form-label">Quantity</label>
                            <input type="number" id="modal-quantity" name="quantity" class="form-control" min="1" value="1">
                        </div>
                        <button type="submit" class="btn btn-success">Add to Cart</button>
                    </form>
                </div>
            </div>
        </div>
    </div>


<?php
function renderCategoryTree($categories, $selectedCategories = [], $level = 0)
{
    $html = '<ul class="list-unstyled">';
    foreach ($categories as $category) {
        $isChecked = in_array($category['id'], $selectedCategories) ? 'checked' : '';
        $hasChildren = !empty($category['children']);
        $categoryId = 'cat_' . $category['id'];
        $html .= '<li>';
        $html .= '<div class="form-check">';
        $html .= '<input class="form-check-input" type="checkbox" name="categories[]" value="' . $category['id'] . '" ' . $isChecked . ' id="' . $categoryId . '">';
        $html .= '<label class="form-check-label" for="' . $categoryId . '">';
        $html .= htmlspecialchars($category['name']);
        $html .= '</label>';
        $html .= '</div>';
        if ($hasChildren) {
            $html .= '<div class="ml-3">';
            $html .= renderCategoryTree($category['children'], $selectedCategories, $level + 1);
            $html .= '</div>';
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}
?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const prefixes = ['name:', 'seller:', 'category:', 'attr:'];

    searchInput.addEventListener('input', () => {
        const value = searchInput.value;
        const tokens = value.split(/\s+/);
        const lastWord = tokens[tokens.length - 1];
        const suggestions = prefixes.filter(prefix => prefix.startsWith(lastWord));

        const datalist = document.getElementById('searchSuggestions');
        datalist.innerHTML = '';
        suggestions.forEach(suggestion => {
            const option = document.createElement('option');
            option.value = tokens.slice(0, -1).join(' ') + ' ' + suggestion;
            datalist.appendChild(option);
        });
    });
    const enablePriceRange = document.getElementById('enablePriceRange');
    const priceRangeInputs = document.getElementById('priceRangeInputs');

    enablePriceRange.addEventListener('change', () => {
        if (enablePriceRange.checked) {
            priceRangeInputs.style.display = '';
        } else {
            priceRangeInputs.style.display = 'none';
        }
    });  
    const addToCartButtons = document.querySelectorAll('.btn-add-to-cart');
    const addToCartModal = document.getElementById('addToCartModal');

    addToCartButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productMax = this.getAttribute('data-product-max');
            const productQuantityUnit = this.getAttribute('data-product-quantity-unit');

            document.getElementById('modal-product-id').value = productId;
            document.getElementById('modal-product-name').value = productName;
            const quantityInput = document.getElementById('modal-quantity');
            quantityInput.value = 1;
            quantityInput.setAttribute('max', productMax);

            document.getElementById('modal-quantity-label').innerText = 'Quantity (' + productQuantityUnit + ')';
        });
    });
});
</script>
<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
