CREATE DATABASE restaurant;

USE restaurant;

-- Menu Items Table
CREATE TABLE menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    category VARCHAR(50),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Orders Table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_number INT NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'preparing', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Order Items Table (linking orders to menu items)
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    line_total DECIMAL(10, 2) NOT NULL,
    remarks TEXT,
    status ENUM('pending', 'preparing', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES menu_items(id)
);

-- Sample Menu Items
INSERT INTO menu_items (name, description, price, category) VALUES
('Burger Deluxe', 'Fresh beef burger with cheese, lettuce, tomato, and special sauce', 12.99, 'Main'),
('Grilled Chicken', 'Tender grilled chicken breast with herb butter', 14.99, 'Main'),
('Spaghetti Carbonara', 'Creamy pasta with bacon and parmesan cheese', 11.99, 'Pasta'),
('Caesar Salad', 'Fresh romaine lettuce with parmesan and croutons', 9.99, 'Salad'),
('Fish & Chips', 'Crispy fried fish with golden fries', 13.99, 'Main'),
('Vegetable Stir Fry', 'Mixed vegetables in a savory sauce over rice', 10.99, 'Vegetarian'),
('Margherita Pizza', 'Fresh mozzarella, tomato, and basil on thin crust', 11.49, 'Pizza'),
('Chocolate Cake', 'Rich chocolate cake with ganache frosting', 6.99, 'Dessert'),
('Tiramisu', 'Classic Italian coffee-flavored dessert', 7.49, 'Dessert'),
('Iced Tea', 'Refreshing iced tea with lemon', 2.99, 'Beverage');

-- Create an index for better query performance
CREATE INDEX idx_order_date ON orders(order_date);
CREATE INDEX idx_order_status ON orders(status);
CREATE INDEX idx_order_item ON order_items(order_id);
