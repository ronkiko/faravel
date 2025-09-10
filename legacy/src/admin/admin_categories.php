<?php # admin/admin_categories.php

require dirname(__DIR__) . '/bootstrap.php';

if (!IS_LOGGED_IN) {
    http_response_code(403);
    exit('Forbidden: Not logged in');
}

if ($_SESSION['user']['name'] !== 'admin') {
    // http_response_code(403);
    // exit('Forbidden: Not admin');
}

$pdo = getPDO();
$edit_form_html = '';

/* === Обработка GET-запроса редактирования === */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($edit_data) {
        $id = htmlspecialchars($edit_data['id']);
        $slug = htmlspecialchars($edit_data['slug']);
        $title = htmlspecialchars($edit_data['title']);
        $description = htmlspecialchars($edit_data['description']);
        $is_visible = (int)$edit_data['is_visible'];
        $min_group = (int)$edit_data['min_group'];
        $order_id = (int)$edit_data['order_id'];
        $csrf_token = csrf_token();
        $checked = $is_visible ? 'checked' : '';

        $edit_form_html = <<<HTML
<form method="POST" class="simple-form edit-form">
  <h2>Edit Category</h2>

  <input type="hidden" name="edit_category" value="1">
  <input type="hidden" name="csrf_token" value="{$csrf_token}">
  <input type="hidden" name="id" value="{$id}">

  <label for="cat_id">ID (readonly)</label>
  <input type="text" id="cat_id" value="{$id}" readonly disabled>

  <label for="cat_slug">Slug</label>
  <input type="text" id="cat_slug" name="slug" value="{$slug}" required>

  <label for="cat_title">Title</label>
  <input type="text" id="cat_title" name="title" value="{$title}" required>

  <label for="cat_description">Description</label>
  <textarea id="cat_description" name="description" rows="3">{$description}</textarea>

  <label for="cat_is_visible">
    <input type="checkbox" id="cat_is_visible" name="is_visible" {$checked}> Visible
  </label>

  <label for="cat_min_group">Minimum Group</label>
  <input type="number" id="cat_min_group" name="min_group" value="{$min_group}" min="0">

  <label for="cat_order_id">Order ID</label>
  <input type="number" id="cat_order_id" name="order_id" value="{$order_id}" min="0">

  <div class="form-actions">
    <button type="submit" class="btn">💾 Save Changes</button>
    <a href="?" class="btn cancel">✖ Cancel</a>
  </div>
</form>
HTML;
    }
}

/* === Обработка GET-запроса создания новой категории === */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['new'])) {
    $csrf_token = csrf_token();

    // Получить максимальный order_id и прибавить 1
    $stmt = $pdo->query("SELECT MAX(order_id) AS max_order FROM categories");
    $max_order = (int)($stmt->fetchColumn() ?? 0);
    $next_order_id = $max_order + 1;

    $edit_form_html = <<<HTML
<form method="POST" class="simple-form edit-form">
  <h2>Add New Category</h2>

  <input type="hidden" name="add_category" value="1">
  <input type="hidden" name="csrf_token" value="{$csrf_token}">

  <label for="cat_title">Title</label>
  <input type="text" id="cat_title" name="title" required>

  <label for="cat_description">Description</label>
  <textarea id="cat_description" name="description" rows="3"></textarea>

  <label for="cat_is_visible">
    <input type="checkbox" id="cat_is_visible" name="is_visible" checked> Visible
  </label>

  <label for="cat_min_group">Minimum Group</label>
  <input type="number" id="cat_min_group" name="min_group" value="1" min="0">

  <label for="cat_order_id">Order ID</label>
  <input type="number" id="cat_order_id" name="order_id" value="{$next_order_id}" min="0">

  <div class="form-actions">
    <button type="submit" class="btn">💾 Create Category</button>
    <a href="?" class="btn cancel">✖ Cancel</a>
  </div>
</form>
HTML;
}

/* === Обработка POST-запросов === */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $id = generateUUID();
        $title = trim($_POST['title'] ?? '');
        $slug = slugify($title);
        $description = trim($_POST['description'] ?? '');
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        $min_group = max(0, (int)($_POST['min_group'] ?? 1));
        $order_id = (int)($_POST['order_id'] ?? 0);

        if ($slug && $title) {
            $stmt = $pdo->prepare("INSERT INTO categories (id, slug, title, description, is_visible, min_group, order_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $slug, $title, $description, $is_visible, $min_group, $order_id]);
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
    }

    if (isset($_POST['edit_category'])) {
        $id = $_POST['id'];
        $slug = trim($_POST['slug'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        $min_group = max(0, (int)($_POST['min_group'] ?? 1));
        $order_id = (int)($_POST['order_id'] ?? 0);

        if ($slug && $title && $id) {
            $stmt = $pdo->prepare("UPDATE categories SET slug = ?, title = ?, description = ?, is_visible = ?, min_group = ?, order_id = ? WHERE id = ?");
            $stmt->execute([$slug, $title, $description, $is_visible, $min_group, $order_id, $id]);
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
    }
}

/* === Удаление категории === */
if (isset($_GET['delete']) && isset($_GET['confirm'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* === Получение списка категорий === */
$stmt = $pdo->query("SELECT * FROM categories ORDER BY order_id ASC, title ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* === HTML-таблица === */
$rows = '';
foreach ($categories as $cat) {
    $title = htmlspecialchars($cat['title']);
    $description = htmlspecialchars($cat['description'] ?? '');
    $min_group = (int)$cat['min_group'];
    $order_id = (int)$cat['order_id'];
    $status = $cat['is_visible'] ? '✅' : '❌';

    $edit_link = '?edit=' . urlencode($cat['id']);
    $del_link = '?delete=' . urlencode($cat['id']) . '&confirm=1';

    $rows .= "<tr>";
    $rows .= "<td style=\"text-align:center;\">{$status}</td>";
    $rows .= "<td style=\"width:70%\"><strong>{$title}</strong><br><small>{$description}</small></td>";
    $rows .= "<td style=\"text-align:center;\">{$min_group}</td>";
    $rows .= "<td style=\"text-align:center;\">{$order_id}</td>";
    $rows .= "<td style=\"text-align:center; white-space:nowrap;\">";
    $rows .= "<a href=\"{$edit_link}\" title=\"Edit\">✏️</a> ";
    $rows .= "<a href=\"{$del_link}\" title=\"Delete\">🗑️</a>";
    $rows .= "</td>";
    $rows .= "</tr>\n";
}

/* === Финальный вывод === */
add_style('admin');

$content = '';

if ($edit_form_html) {
    $content .= $edit_form_html;
} else {
    $new_cat_url = strtok($_SERVER['REQUEST_URI'], '?') . '?new=1';
    $content .= "<h1>Admin: Categories</h1>\n";
    $content .= "<p><a href=\"{$new_cat_url}\" class=\"btn\">➕ Add New Category</a></p>";
    $content .= <<<HTML
<table class="simple-table">
  <thead>
    <tr>
      <th>Status</th>
      <th>Title & Description</th>
      <th>Min Group</th>
      <th>Order</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    {$rows}
  </tbody>
</table>
HTML;
}

draw('Admin Categories', $content);
