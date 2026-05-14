<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_role(['admin', 'counter']);

$conn = connect_database();

$current_user = current_user();

function table_exists(mysqli $conn, string $table_name): bool {
    $safe_name = mysqli_real_escape_string($conn, $table_name);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$safe_name'");
    return $result && mysqli_num_rows($result) > 0;
}

$has_orders_table = table_exists($conn, 'orders');
$has_order_items_table = table_exists($conn, 'order_items');

// Toggle menu item availability
if (isset($_POST['toggle_availability'])) {
    $menu_id = intval($_POST['menu_id']);
    $current_status = intval($_POST['current_status']);
    $new_status = $current_status ? 0 : 1;
    
    $stmt = $conn->prepare("UPDATE menu SET available = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $menu_id);
    if ($stmt->execute()) {
        $_SESSION['msg'] = 'Availability updated!';
    }
    $stmt->close();
    header('Location: counter.php');
    exit();
}

// Update order status
if ($has_orders_table && isset($_POST['update_order_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['order_status'];
    
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    if ($stmt->execute()) {
        $_SESSION['msg'] = 'Order status updated!';
    }
    $stmt->close();
    header('Location: counter.php');
    exit();
}

// Get menu items
$sql = "SELECT * FROM menu ORDER BY category, item_name";
$result = mysqli_query($conn, $sql);
$menu_items = mysqli_fetch_all($result, MYSQLI_ASSOC) ?? [];

// Get orders
if ($has_orders_table) {
    $sql = "SELECT * FROM orders ORDER BY created_at DESC";
    $result = mysqli_query($conn, $sql);
    $orders = mysqli_fetch_all($result, MYSQLI_ASSOC) ?? [];

    // Get daily sales
    $sql = "SELECT 
        DATE(created_at) as order_date,
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_order
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY order_date DESC";
    $result = mysqli_query($conn, $sql);
    $sales_data = mysqli_fetch_all($result, MYSQLI_ASSOC) ?? [];

    // Get stats
    $sql = "SELECT 
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue
    FROM orders 
    WHERE DATE(created_at) = CURDATE()";
    $result = mysqli_query($conn, $sql);
    $today_stats = mysqli_fetch_assoc($result);

    // Get total revenue
    $sql = "SELECT SUM(total_amount) as total_revenue FROM orders";
    $result = mysqli_query($conn, $sql);
    $revenue_stats = mysqli_fetch_assoc($result);
} else {
    $orders = [];
    $sales_data = [];
    $today_stats = ['total_orders' => 0, 'total_revenue' => 0];
    $revenue_stats = ['total_revenue' => 0];
}

// Get order items for a specific order
function get_order_items($conn, $order_id) {
    $stmt = $conn->prepare("SELECT oi.*, m.item_name FROM order_items oi 
                            JOIN menu m ON oi.menu_id = m.id 
                            WHERE oi.order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counter Staff Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-header {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 12px;
            text-transform: uppercase;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }

        .tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .availability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }

        .item-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #ddd;
            transition: all 0.3s ease;
        }

        .item-card:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
        }

        .item-card h4 {
            margin-bottom: 8px;
            color: #333;
        }

        .item-card .price {
            color: #667eea;
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .item-card .category {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .availability-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 24px;
            background: #ccc;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .toggle-switch.active {
            background: #28a745;
        }

        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: left 0.3s ease;
        }

        .toggle-switch.active::after {
            left: 28px;
        }

        .orders-grid {
            display: grid;
            gap: 15px;
        }

        .order-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .order-number {
            font-weight: 600;
            font-size: 16px;
            color: #333;
        }

        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status.Pending {
            background: #fff3cd;
            color: #856404;
        }

        .status.Processing {
            background: #cfe2ff;
            color: #084298;
        }

        .status.Ready {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status.Completed {
            background: #d4edda;
            color: #155724;
        }

        .status.Cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .order-details {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
            line-height: 1.6;
        }

        .order-items-list {
            background: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .order-items-list li {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }

        .order-items-list li:last-child {
            border-bottom: none;
        }

        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-total {
            font-weight: 600;
            color: #667eea;
            font-size: 16px;
        }

        .status-form {
            display: flex;
            gap: 8px;
        }

        .status-form select {
            padding: 6px 10px;
            font-size: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .status-form button {
            padding: 6px 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }

        .status-form button:hover {
            background: #5568d3;
        }

        .sales-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sales-table th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        .sales-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        .sales-table tbody tr:hover {
            background: #f5f5f5;
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
            <div>Signed in as <strong><?php echo htmlspecialchars($current_user['display_name'] ?? 'Staff'); ?></strong></div>
            <a href="logout.php">Logout</a>
        </div>
        <h1>🏪 Counter Staff Dashboard</h1>

        <?php if ($msg): ?>
            <div class="message success"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <?php if (!$has_orders_table || !$has_order_items_table): ?>
            <div class="message error">
                Order tables are missing from the database. Run <strong>restuarant.sql</strong> to create <strong>orders</strong> and <strong>order_items</strong>.
            </div>
        <?php endif; ?>

        <!-- Dashboard Stats -->
        <div class="dashboard-header">
            <div class="stat-card">
                <h3>Today's Orders</h3>
                <div class="value"><?php echo $today_stats['total_orders'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Today's Revenue</h3>
                <div class="value">₹<?php echo number_format($today_stats['total_revenue'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="value">₹<?php echo number_format($revenue_stats['total_revenue'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Available Items</h3>
                <div class="value"><?php echo count(array_filter($menu_items, fn($item) => $item['available'])); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Orders</h3>
                <div class="value"><?php echo count(array_filter($orders, fn($order) => $order['status'] === 'Pending')); ?></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab(event, 'orders')">📋 Active Orders</button>
            <button class="tab-btn" onclick="switchTab(event, 'menu')">🍽️ Menu Availability</button>
            <button class="tab-btn" onclick="switchTab(event, 'sales')">📊 Sales Report</button>
        </div>

        <!-- Orders Tab -->
        <div id="orders" class="tab-content active">
            <h2 style="margin-bottom: 20px;">Active Orders</h2>
            <?php if ($has_orders_table && count($orders) > 0): ?>
                <div class="orders-grid">
                    <?php foreach ($orders as $order): 
                        $order_items = $has_order_items_table ? get_order_items($conn, $order['id']) : [];
                    ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                                <span class="order-status status <?php echo $order['status']; ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </span>
                            </div>
                            <div class="order-details">
                                <div><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></div>
                                <div><strong>Time:</strong> <?php echo date('H:i', strtotime($order['created_at'])); ?></div>
                            </div>
                            <?php if (count($order_items) > 0): ?>
                                <ul class="order-items-list">
                                    <?php foreach ($order_items as $item): ?>
                                        <li>
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong> 
                                            x<?php echo $item['quantity']; ?> 
                                            (₹<?php echo number_format($item['item_price'] * $item['quantity'], 2); ?>)
                                            <?php if ($item['special_instructions']): ?>
                                                <div style="font-style: italic; color: #666; margin-top: 3px;">
                                                    Note: <?php echo htmlspecialchars($item['special_instructions']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <div class="order-footer">
                                <div class="order-total">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                                <?php if ($has_orders_table && $order['status'] !== 'Completed' && $order['status'] !== 'Cancelled'): ?>
                                    <form method="POST" class="status-form">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <select name="order_status">
                                            <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="Ready" <?php echo $order['status'] === 'Ready' ? 'selected' : ''; ?>>Ready</option>
                                            <option value="Completed" <?php echo $order['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="Cancelled">Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_order_status">Update</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($has_orders_table): ?>
                <div class="empty-state">No orders yet. Check back soon!</div>
            <?php else: ?>
                <div class="empty-state">Orders are not available until the database tables are created.</div>
            <?php endif; ?>
        </div>

        <!-- Menu Availability Tab -->
        <div id="menu" class="tab-content">
            <h2 style="margin-bottom: 20px;">Menu Item Availability</h2>
            <?php if (count($menu_items) > 0): ?>
                <div class="availability-grid">
                    <?php foreach ($menu_items as $item): ?>
                        <div class="item-card">
                            <h4><?php echo htmlspecialchars($item['item_name']); ?></h4>
                            <div class="price">₹<?php echo number_format($item['price'], 2); ?></div>
                            <span class="category"><?php echo htmlspecialchars($item['category'] ?? 'Misc'); ?></span>
                            
                            <form method="POST" style="margin-top: 10px;">
                                <input type="hidden" name="toggle_availability">
                                <input type="hidden" name="menu_id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $item['available']; ?>">
                                <div class="availability-toggle">
                                    <label style="margin: 0; cursor: pointer;">
                                        <button type="submit" style="background: none; border: none; cursor: pointer; padding: 0;">
                                            <div class="toggle-switch <?php echo $item['available'] ? 'active' : ''; ?>"></div>
                                        </button>
                                    </label>
                                    <span style="font-weight: 600; color: <?php echo $item['available'] ? '#28a745' : '#dc3545'; ?>">
                                        <?php echo $item['available'] ? 'Available' : 'Unavailable'; ?>
                                    </span>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">No menu items found. Add items in Admin Dashboard first.</div>
            <?php endif; ?>
        </div>

        <!-- Sales Report Tab -->
        <div id="sales" class="tab-content">
            <h2 style="margin-bottom: 20px;">7-Day Sales Report</h2>
            <?php if ($has_orders_table && count($sales_data) > 0): ?>
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total Orders</th>
                            <th>Total Revenue</th>
                            <th>Average Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales_data as $row): ?>
                            <tr>
                                <td><?php echo date('D, M d, Y', strtotime($row['order_date'])); ?></td>
                                <td><?php echo $row['total_orders']; ?></td>
                                <td><strong>₹<?php echo number_format($row['total_revenue'], 2); ?></strong></td>
                                <td>₹<?php echo number_format($row['avg_order'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($has_orders_table): ?>
                <div class="empty-state">No sales data available yet.</div>
            <?php else: ?>
                <div class="empty-state">Sales report will appear after the orders tables are created.</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function switchTab(event, tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));

            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => btn.classList.remove('active'));

            // Show selected tab
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
