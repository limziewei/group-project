USE restaurant;

-- Sample kitchen queue data for the dashboard
-- Import this after menu_items has been loaded.

INSERT INTO orders (id, table_number, total_price, order_date, status, notes, completed_at) VALUES
(1, 3, 25.98, NOW(), 'pending', 'No onions on burger', NULL),
(2, 7, 36.47, NOW(), 'preparing', 'Extra sauce', NULL),
(3, 0, 18.98, NOW(), 'pending', 'Takeaway', NULL);

INSERT INTO order_items (id, order_id, item_id, quantity, line_total, remarks, status) VALUES
(1, 1, 1, 2, 25.98, 'No onions', 'pending'),
(2, 2, 2, 1, 14.99, 'Well done', 'preparing'),
(3, 2, 4, 1, 9.99, '', 'preparing'),
(4, 2, 10, 3, 8.97, '', 'preparing'),
(5, 3, 3, 1, 11.99, 'Extra cheese', 'pending'),
(6, 3, 8, 1, 6.99, '', 'pending');

INSERT INTO orders (table_number, total_price, status, notes) VALUES (5, 19.99, 'pending', 'No salt');
SET @order_id = LAST_INSERT_ID();

-- replace 11 with the actual menu item id returned above
INSERT INTO order_items (order_id, item_id, quantity, line_total, remarks)
VALUES (@order_id, 11, 1, 19.99, 'No salt');

INSERT INTO orders (table_number, total_price, status, notes)
VALUES (8, 24.98, 'pending', 'Less spicy');

SET @new_order_id = LAST_INSERT_ID();

INSERT INTO order_items (order_id, item_id, quantity, line_total, remarks, status)
VALUES
(@new_order_id, 1, 1, 12.99, 'Less spicy', 'pending'),
(@new_order_id, 5, 1, 11.99, '', 'pending');