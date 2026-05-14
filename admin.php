<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_role(['admin']);

$conn = connect_database();

$current_user = current_user();


if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM menu WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header('Location: admin.php?msg=deleted');
        exit();
    } else {
        $error = "Error deleting record: " . $stmt->error;
    }
    $stmt->close();
}

$edit_id = null;
$edit_data = null;

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM menu WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_data = $result->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_name = $_POST['item_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $category = $_POST['category'] ?? '';
    $image_url = $_POST['image_url'] ?? '';
    $available = isset($_POST['available']) ? 1 : 0;

    if (isset($_POST['update_id']) && !empty($_POST['update_id'])) {
        // Update existing item
        $update_id = intval($_POST['update_id']);
        $stmt = $conn->prepare("UPDATE menu SET item_name=?, description=?, price=?, category=?, image_url=?, available=? WHERE id=?");
        $stmt->bind_param("ssdssii", $item_name, $description, $price, $category, $image_url, $available, $update_id);
        $msg = "updated";
    } else {
        // Add new item
        $stmt = $conn->prepare("INSERT INTO menu (item_name, description, price, category, image_url, available) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdssi", $item_name, $description, $price, $category, $image_url, $available);
        $msg = "added";
    }

    if ($stmt->execute()) {
        header('Location: admin.php?msg=' . $msg);
        exit();
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
}
$sql = "SELECT * FROM menu ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
$menu_items = mysqli_fetch_all($result, MYSQLI_ASSOC) ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Menu Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .message {
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 2px solid #667eea;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="number"],
        input[type="url"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }
        
        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="url"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        button {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-submit {
            background: #667eea;
            color: white;
            flex: 1;
        }
        
        .btn-submit:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            flex: 1;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        .table-section h2 {
            color: #333;
            margin-top: 30px;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table thead {
            background: #667eea;
            color: white;
        }
        
        table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        table td {
            padding: 12px 15px;
            border-bottom: 1px solid #ddd;
        }
        
        table tbody tr:hover {
            background: #f5f5f5;
        }
        
        .btn-edit,
        .btn-delete {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background: #28a745;
            color: white;
        }
        
        .btn-edit:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        .action-cell {
            display: flex;
            gap: 10px;
        }
        
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status.available {
            background: #d4edda;
            color: #155724;
        }
        
        .status.unavailable {
            background: #f8d7da;
            color: #721c24;
        }
        
        .price-cell {
            color: #667eea;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .auth-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            padding: 12px 16px;
            border-radius: 10px;
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            color: #374151;
            font-size: 14px;
        }

        .auth-bar a {
            color: #dc3545;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-bar">
            <div>Signed in as <strong><?php echo htmlspecialchars($current_user['display_name'] ?? 'Admin'); ?></strong></div>
            <a href="logout.php">Logout</a>
        </div>
        <h1>🍽️ Restaurant Menu Management</h1>
        
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'added'): ?>
                <div class="message success">✓ Menu item added successfully!</div>
            <?php elseif ($_GET['msg'] == 'updated'): ?>
                <div class="message success">✓ Menu item updated successfully!</div>
            <?php elseif ($_GET['msg'] == 'deleted'): ?>
                <div class="message success">✓ Menu item deleted successfully!</div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error">✗ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Add/Edit Form -->
        <div class="form-section">
            <h2><?php echo $edit_id ? '✏️ Edit Menu Item' : '➕ Add New Menu Item'; ?></h2>
            <form method="POST">
                <?php if ($edit_id): ?>
                    <input type="hidden" name="update_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="item_name">Item Name *</label>
                        <input type="text" id="item_name" name="item_name" required value="<?php echo $edit_data['item_name'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="Appetizer" <?php echo (($edit_data['category'] ?? '') == 'Appetizer') ? 'selected' : ''; ?>>Appetizer</option>
                            <option value="Main Course" <?php echo (($edit_data['category'] ?? '') == 'Main Course') ? 'selected' : ''; ?>>Main Course</option>
                            <option value="Dessert" <?php echo (($edit_data['category'] ?? '') == 'Dessert') ? 'selected' : ''; ?>>Dessert</option>
                            <option value="Beverage" <?php echo (($edit_data['category'] ?? '') == 'Beverage') ? 'selected' : ''; ?>>Beverage</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?php echo $edit_data['description'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price ($) *</label>
                        <input type="number" id="price" name="price" step="0.01" required value="<?php echo $edit_data['price'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="image_url">Image URL</label>
                        <input type="url" id="image_url" name="image_url" value="<?php echo $edit_data['image_url'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="available" name="available" <?php echo (($edit_data['available'] ?? 1) == 1) ? 'checked' : ''; ?>>
                        <label for="available" style="margin-bottom: 0;">Available</label>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn-submit"><?php echo $edit_id ? '💾 Update Item' : '➕ Add Item'; ?></button>
                    <?php if ($edit_id): ?>
                        <a href="admin.php" class="btn-cancel">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Menu Items Table -->
        <div class="table-section">
            <h2>📋 Menu Items</h2>
            <?php if (!empty($menu_items)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menu_items as $item): ?>
                            <tr>
                                <td><?php echo $item['id']; ?></td>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                <td class="price-cell">$<?php echo number_format($item['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars(substr($item['description'], 0, 50)); ?><?php echo strlen($item['description']) > 50 ? '...' : ''; ?></td>
                                <td>
                                    <span class="status <?php echo $item['available'] ? 'available' : 'unavailable'; ?>">
                                        <?php echo $item['available'] ? 'Available' : 'Unavailable'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <a href="admin.php?edit=<?php echo $item['id']; ?>" class="btn-edit">✏️ Edit</a>
                                        <a href="admin.php?delete=<?php echo $item['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this item?');">🗑️ Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No menu items yet. Add your first item above!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
