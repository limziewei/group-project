<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "restaurant";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
    $item_id = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    $remarks = htmlspecialchars($_POST['remarks']);
    
    // Fetch menu item details
    $query = "SELECT id, name, price FROM menu_items WHERE id = " . $item_id;
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $item = $result->fetch_assoc();
        
        // Create cart item
        $cart_item = array(
            'item_id' => $item_id,
            'name' => $item['name'],
            'price' => $item['price'],
            'quantity' => $quantity,
            'remarks' => $remarks
        );
        
        // Add to cart
        $_SESSION['cart'][] = $cart_item;
        $_SESSION['success_message'] = $item['name'] . " added to cart!";
    }
}

// Handle Remove from Cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'remove_item') {
    $index = intval($_POST['index']);
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        // Reindex array
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        $_SESSION['success_message'] = "Item removed from cart!";
    }
}

// Handle Clear Cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'clear_cart') {
    $_SESSION['cart'] = array();
    $_SESSION['success_message'] = "Cart cleared!";
}

// Handle Checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'checkout') {
    if (empty($_SESSION['cart'])) {
        $_SESSION['error_message'] = "Cart is empty!";
    } else {
        $table_number = intval($_POST['table_number']);
        
        if ($table_number <= 0) {
            $_SESSION['error_message'] = "Please enter a valid table number!";
        } else {
            // Create order
            $total_price = calculateTotalPrice($_SESSION['cart']);
            $order_date = date('Y-m-d H:i:s');
            
            $order_query = "INSERT INTO orders (table_number, total_price, order_date, status) 
                          VALUES ($table_number, $total_price, '$order_date', 'pending')";
            
            if ($conn->query($order_query)) {
                $order_id = $conn->insert_id;
                
                // Insert order items
                foreach ($_SESSION['cart'] as $item) {
                    $item_id = $item['item_id'];
                    $qty = $item['quantity'];
                    $line_total = $item['price'] * $qty;
                    $remarks = addslashes($item['remarks']);
                    
                    $item_query = "INSERT INTO order_items (order_id, item_id, quantity, line_total, remarks) 
                                 VALUES ($order_id, $item_id, $qty, $line_total, '$remarks')";
                    
                    $conn->query($item_query);
                }
                
                // Clear cart
                $_SESSION['cart'] = array();
                $_SESSION['success_message'] = "Order #" . $order_id . " placed successfully! Sent to Kitchen Dashboard.";
                $_SESSION['order_id'] = $order_id;
            } else {
                $_SESSION['error_message'] = "Error placing order: " . $conn->error;
            }
        }
    }
}

// Function to calculate total price
function calculateTotalPrice($cart) {
    $total = 0;
    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

// Determine current view
$view = isset($_GET['view']) ? $_GET['view'] : 'menu';

// Fetch menu items if viewing menu
$menu_items = array();
if ($view == 'menu') {
    $query = "SELECT id, name, description, price FROM menu_items ORDER BY id";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $menu_items[] = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Customer View</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
            border-radius: 8px;
        }
        
        header h1 {
            margin-bottom: 10px;
        }
        
        .nav-buttons {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .nav-buttons a, .nav-buttons button {
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .nav-buttons a:hover, .nav-buttons button:hover {
            background-color: #2980b9;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: <?php echo (isset($_SESSION['success_message']) || isset($_SESSION['error_message'])) ? 'block' : 'none'; ?>;
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
        
        .menu-section {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .menu-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .menu-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .menu-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            min-height: 40px;
        }
        
        .menu-card .price {
            font-size: 18px;
            color: #e74c3c;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 10px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 13px;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 50px;
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #27ae60;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #229954;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .cart-table thead {
            background-color: #2c3e50;
            color: white;
        }
        
        .cart-table th {
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }
        
        .cart-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .cart-table tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        .cart-summary {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .total-row {
            font-size: 18px;
            font-weight: bold;
            color: #e74c3c;
            text-align: right;
            padding: 15px 0;
            border-top: 2px solid #eee;
        }
        
        .checkout-form {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        
        .checkout-form h2 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px;
            background-color: white;
            border-radius: 8px;
            color: #666;
        }
        
        .empty-cart p {
            font-size: 18px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🍽️ Restaurant Management System</h1>
            <p>Customer View - Place Your Order</p>
        </header>
        
        <!-- Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>
        
        <!-- Navigation Buttons -->
        <div class="nav-buttons">
            <a href="?view=menu">📋 Back to Menu</a>
            <a href="?view=cart">🛒 View Cart (<?php echo count($_SESSION['cart']); ?> items)</a>
        </div>
        
        <!-- STEP 3.1: Browse Menu -->
        <?php if ($view == 'menu'): ?>
            <div>
                <h2 style="margin-bottom: 20px; color: #2c3e50;">Step 3.1: Browse the Menu</h2>
                <div class="menu-section">
                    <?php if (count($menu_items) > 0): ?>
                        <?php foreach ($menu_items as $item): ?>
                            <div class="menu-card">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p><?php echo htmlspecialchars($item['description']); ?></p>
                                <div class="price">$<?php echo number_format($item['price'], 2); ?></div>
                                
                                <!-- STEP 3.2: Add Items to Cart -->
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="qty_<?php echo $item['id']; ?>">Quantity:</label>
                                        <input type="number" id="qty_<?php echo $item['id']; ?>" name="quantity" value="1" min="1" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="remarks_<?php echo $item['id']; ?>">Remarks (Optional):</label>
                                        <textarea id="remarks_<?php echo $item['id']; ?>" name="remarks" placeholder="e.g., No onions, Extra spicy"></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">➕ Add to Cart</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-cart">
                            <p>No menu items available yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        
        <!-- STEP 3.3: Review Cart & Checkout -->
        <?php elseif ($view == 'cart'): ?>
            <div>
                <h2 style="margin-bottom: 20px; color: #2c3e50;">Step 3.3: Review Cart & Checkout</h2>
                
                <?php if (count($_SESSION['cart']) > 0): ?>
                    <!-- Cart Table -->
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Remark</th>
                                <th>Line Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($item['remarks']) ?: '-'; ?></td>
                                    <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="remove_item">
                                            <input type="hidden" name="index" value="<?php echo $index; ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Cart Summary -->
                    <div class="cart-summary">
                        <div class="total-row">
                            Total Price: $<?php echo number_format(calculateTotalPrice($_SESSION['cart']), 2); ?>
                        </div>
                    </div>
                    
                    <!-- Checkout Form -->
                    <div class="checkout-form">
                        <h2>Complete Your Order</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="checkout">
                            
                            <div class="form-group">
                                <label for="table_number">Table Number (required for dine-in):</label>
                                <input type="number" id="table_number" name="table_number" min="1" required>
                            </div>
                            
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-primary" style="flex: 1;">✓ Proceed to Checkout</button>
                                <a href="?view=menu" class="btn btn-secondary" style="flex: 1; text-align: center; text-decoration: none;">← Back to Menu</a>
                            </div>
                        </form>
                        
                        <form method="POST" style="margin-top: 10px;">
                            <input type="hidden" name="action" value="clear_cart">
                            <button type="submit" class="btn btn-secondary" style="width: 100%; margin-top: 10px;">🗑️ Clear Cart</button>
                        </form>
                    </div>
                    
                <?php else: ?>
                    <div class="empty-cart">
                        <p>Your cart is empty.</p>
                        <a href="?view=menu" class="btn btn-primary">← Continue Shopping</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>
