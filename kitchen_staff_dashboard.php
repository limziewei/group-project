<?php
// Kitchen Staff Dashboard
// Minimal self-contained page to list orders and update their status.

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'restaurant'; // change to your DB name

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    http_response_code(500);
    echo "Database connection failed: " . $conn->connect_error;
    exit;
}

// Handle AJAX POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'update_status') {
        $orderId = intval($_POST['order_id'] ?? 0);
        $status = $conn->real_escape_string($_POST['status'] ?? '');
        if ($orderId <= 0 || $status === '') {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE orders SET status = ?, completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_at END WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('ssi', $status, $status, $orderId);
            $ok = $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => (bool)$ok]);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Prepare failed']);
        exit;
    }

        // Mark order as completed (and remove from queue)
        if ($action === 'delete_order' || $action === 'delete') {
            $orderId = intval($_POST['order_id'] ?? 0);
            if ($orderId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid order id']);
                exit;
            }
            $stmt = $conn->prepare("UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $orderId);
                $ok = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => (bool)$ok]);
                exit;
            }
            echo json_encode(['success' => false, 'error' => 'Prepare failed']);
            exit;
        }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// Helper: check if a table exists
function table_exists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
}

// Helper: check if column exists on orders table
function column_exists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

$hasOrderItemsTable = table_exists($conn, 'order_items');
$ordersHasItemsColumn = column_exists($conn, 'orders', 'items');

$orders = [];
$res = $conn->query("SELECT * FROM orders WHERE status IN ('pending', 'preparing') ORDER BY id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $order = $row;

        // Load items
        $items = [];
        if ($hasOrderItemsTable) {
            $stmt = $conn->prepare("SELECT oi.quantity, oi.line_total, oi.remarks, mi.name, mi.price FROM order_items oi LEFT JOIN menu_items mi ON mi.id = oi.item_id WHERE oi.order_id = ? ORDER BY oi.id ASC");
            if ($stmt) {
                $stmt->bind_param('i', $order['id']);
                $stmt->execute();
                $r = $stmt->get_result();
                while ($it = $r->fetch_assoc()) {
                    $items[] = $it;
                }
                $stmt->close();
            }
        } elseif ($ordersHasItemsColumn && !empty($order['items'])) {
            // assume JSON in `items` column
            $decoded = json_decode($order['items'], true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }

        $order['items_list'] = $items;
        $orders[] = $order;
    }
    $res->free();
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kitchen Staff Dashboard</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;margin:20px;background:#f6f7fb}
        .ticket{background:#fff;border-radius:6px;padding:12px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
        .ticket h3{margin:0 0 6px}
        .meta{color:#555;font-size:0.95em;margin-bottom:8px}
        .items{margin:8px 0;padding-left:16px}
        .btn{display:inline-block;padding:6px 10px;margin-right:6px;border-radius:4px;border:0;cursor:pointer}
        .btn-prep{background:#ffca28}
        .btn-ready{background:#66bb6a;color:#fff}
        .btn-complete{background:#ef5350;color:#fff}
        .small{font-size:0.9em;color:#333}
    </style>
</head>
<body>
    <h1>Kitchen Staff Dashboard</h1>
    <p class="small">Orders will appear here. Click actions to update status.</p>

    <div id="tickets">
    <?php if (count($orders) === 0): ?>
        <div class="ticket"><em>No orders found.</em></div>
    <?php endif; ?>

    <?php foreach ($orders as $o): ?>
        <div class="ticket" data-order-id="<?php echo htmlspecialchars($o['id']); ?>">
            <h3>Order #<?php echo htmlspecialchars($o['id']); ?> <span style="float:right">Status: <?php echo htmlspecialchars($o['status'] ?? 'new'); ?></span></h3>
            <div class="meta">Table: <?php echo htmlspecialchars($o['table_no'] ?? $o['table'] ?? 'Takeaway'); ?> &nbsp;|&nbsp; Total: <?php echo htmlspecialchars($o['total_price'] ?? $o['total'] ?? '0.00'); ?></div>

            <div class="items">
                <?php if (!empty($o['items_list'])): ?>
                    <ul>
                    <?php foreach ($o['items_list'] as $it): ?>
                        <li><?php echo htmlspecialchars($it['name'] ?? $it['title'] ?? ($it[0] ?? 'Item')); ?> — Qty: <?php echo htmlspecialchars($it['quantity'] ?? $it['qty'] ?? 1); ?><?php if (isset($it['line_total'])): ?>, Line Total: <?php echo htmlspecialchars($it['line_total']); ?><?php endif; ?><?php if(!empty($it['remarks'] ?? $it['remark'] ?? '')) echo ' ('.htmlspecialchars($it['remarks'] ?? $it['remark']).')'; ?></li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <em>No item details available.</em>
                <?php endif; ?>
            </div>

            <div>
                <button class="btn btn-prep" data-status="preparing">Mark Preparing</button>
                <button class="btn btn-ready" data-status="ready">Mark Ready</button>
                <button class="btn btn-complete" data-status="completed">Mark Completed</button>
                <button class="btn" style="background:#999;color:#fff" data-action="delete">Delete</button>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <script>
    async function postAction(orderId, status){
        const fd = new FormData();
        fd.append('action','update_status');
        fd.append('order_id', orderId);
        fd.append('status', status);
        const res = await fetch(location.href, {method:'POST', body:fd});
        return res.json();
    }

    async function postDelete(orderId){
        const fd = new FormData();
        fd.append('action','delete_order');
        fd.append('order_id', orderId);
        const res = await fetch(location.href, {method:'POST', body:fd});
        return res.json();
    }

    document.getElementById('tickets').addEventListener('click', function(e){
        const btn = e.target.closest('button');
        if (!btn) return;
        const ticket = btn.closest('.ticket');
        const orderId = ticket.getAttribute('data-order-id');
        const status = btn.getAttribute('data-status');
        const action = btn.getAttribute('data-action');
        btn.disabled = true;
        if (action === 'delete') {
            postDelete(orderId).then(data => {
                btn.disabled = false;
                if (data && data.success) {
                    ticket.parentNode.removeChild(ticket);
                } else {
                    alert('Delete failed');
                }
            }).catch(err => { btn.disabled = false; alert('Network error'); });
            return;
        }

        postAction(orderId, status).then(data => {
            btn.disabled = false;
            if (data && data.success) {
                // simple UI update
                ticket.querySelector('h3').querySelector('span').textContent = 'Status: ' + status;
                if (status === 'completed') ticket.style.opacity = 0.6;
            } else {
                alert('Update failed');
            }
        }).catch(err => { btn.disabled = false; alert('Network error'); });
    });
    </script>
</body>
</html>

<?php
$conn->close();
?>
